<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Preços e resolução de preço (Fase 2B). NÃO gera cobrança, cupom, desconto,
 * imposto ou parcelamento — apenas armazena valores e resolve o aplicável.
 */
class PricingService extends CatalogDataService
{
    private $model;
    private $lists;
    private $products;
    private $variants;
    private $resources;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_prices_model");
        $this->lists = model("grupo_donato_gestao\\Models\\Gd_price_lists_model");
        $this->products = model("grupo_donato_gestao\\Models\\Gd_products_model");
        $this->variants = model("grupo_donato_gestao\\Models\\Gd_product_variants_model");
        $this->resources = model("grupo_donato_gestao\\Models\\Gd_resources_model");
    }

    public function get(int $id): ?object { return $this->model->get_scoped($id, $this->unit_id); }

    public function save(array $input, int $id = 0): array
    {
        $existing = $id ? $this->get($id) : null;
        if ($id && !$existing) { throw new \DomainException("gd_record_not_found"); }

        $list_id = (int) ($input["price_list_id"] ?? 0);
        $list = $list_id ? $this->lists->get_scoped($list_id, $this->unit_id) : null;
        if (!$list) { throw new \DomainException("gd_invalid_price_list"); }

        $product_id = (int) ($input["product_id"] ?? 0);
        $product = $product_id ? $this->products->get_scoped($product_id, $this->unit_id) : null;
        if (!$product) { throw new \DomainException("gd_invalid_product"); }

        $variant_id = (int) ($input["variant_id"] ?? 0) ?: null;
        if ($variant_id !== null) {
            $variant = $this->variants->get_scoped($variant_id, $this->unit_id);
            if (!$variant || (int) $variant->product_id !== $product_id) { throw new \DomainException("gd_invalid_variant"); }
        }

        $resource_id = (int) ($input["resource_id"] ?? 0) ?: null;
        if ($resource_id !== null) {
            $resource = $this->resources->get_scoped($resource_id, $this->unit_id);
            if (!$resource) { throw new \DomainException("gd_invalid_resource"); }
        }

        $amount = DataNormalizationService::decimal($input["amount"] ?? "", 2);
        $reference_cost = DataNormalizationService::decimal($input["reference_cost"] ?? "", 2, true);
        $minimum_quantity = DataNormalizationService::decimal($input["minimum_quantity"] ?? "1", 3);
        if (DataNormalizationService::decimalCompare($minimum_quantity, "0.000") <= 0) {
            throw new \DomainException("gd_invalid_quantity");
        }

        $valid_from = $this->valid_date($input["valid_from"] ?? "", true);
        $valid_until = $this->valid_date($input["valid_until"] ?? "", true);
        if ($valid_from && $valid_until && $valid_until < $valid_from) {
            throw new \DomainException("gd_invalid_date_range");
        }

        $status = (string) ($input["status"] ?? "active");
        if (!Constants::isPriceStatus($status)) { throw new \DomainException("gd_invalid_value"); }

        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "price_list_id" => $list_id,
            "product_id" => $product_id,
            "variant_id" => $variant_id,
            "resource_id" => $resource_id,
            "amount" => $amount,
            "reference_cost" => $reference_cost,
            "minimum_quantity" => $minimum_quantity,
            "valid_from" => $valid_from,
            "valid_until" => $valid_until,
            "status" => $status,
        ], $id === 0);

        // Lock por escopo: evita corrida na criação de preços sobrepostos.
        $lock_name = "gd_price_" . substr(md5($list_id . ":" . $product_id . ":" . (int) $variant_id . ":" . (int) $resource_id . ":" . $minimum_quantity), 0, 40);
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$lock_name])->getRow();
        if (!$lock || (int) $lock->l !== 1) { throw new \RuntimeException("gd_lock_unavailable"); }

        try {
            if ($status === "active") {
                $overlaps = $this->model->overlapping($list_id, $product_id, $variant_id, $resource_id, $minimum_quantity, $valid_from, $valid_until, $id);
                if ($overlaps) { throw new \DomainException("gd_price_overlap"); }
            }

            $before = $existing ? (array) $existing : null;
            try {
                $save = (int) $this->model->ci_save($data, $id);
            } catch (\Throwable $e) {
                if (stripos($e->getMessage(), "Duplicate") !== false) { throw new \DomainException("gd_price_duplicate"); }
                throw $e;
            }
            if (!$save) { throw new \RuntimeException("save_failed"); }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }

        $after = (array) $this->get($save);
        $meta = [
            "currency" => $list->currency,
            "previous_amount" => $before["amount"] ?? null,
            "new_amount" => $after["amount"],
            "product_id" => $product_id,
            "variant_id" => $variant_id,
            "resource_id" => $resource_id,
            "valid_from" => $valid_from,
            "valid_until" => $valid_until,
        ];
        $action = !$id ? "create" : "update";
        $this->audit_change($action, "price", $save, $before, $after, $meta);
        if ($id && (string) ($before["amount"] ?? "") !== (string) $after["amount"]) {
            $this->audit_change("value_change", "price", $save, ["amount" => $before["amount"] ?? null], ["amount" => $after["amount"]], $meta);
        }
        if ($id && ((string) ($before["valid_from"] ?? "") !== (string) ($after["valid_from"] ?? "") || (string) ($before["valid_until"] ?? "") !== (string) ($after["valid_until"] ?? ""))) {
            $this->audit_change("validity_change", "price", $save, null, null, $meta);
        }
        return ["saved" => true, "id" => $save];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        $this->model->delete($id);
        $this->audit_change("delete", "price", $id, (array) $row, null);
    }

    /**
     * Resolve o preço aplicável. Retorna estrutura com found=true/false.
     * Precedência: variação+recurso > produto+recurso > variação > produto base.
     */
    public function resolve(array $params): array
    {
        $product_id = (int) ($params["product_id"] ?? 0);
        $variant_id = (int) ($params["variant_id"] ?? 0);
        $resource_id = (int) ($params["resource_id"] ?? 0);
        $quantity = DataNormalizationService::decimal((string) ($params["quantity"] ?? "1"), 3);
        if (DataNormalizationService::decimalCompare($quantity, "0.000") <= 0) {
            throw new \DomainException("gd_invalid_quantity");
        }
        $reference_date = $this->valid_date($params["reference_date"] ?? "", true) ?? gmdate("Y-m-d");
        $list_id = (int) ($params["price_list_id"] ?? 0);

        $product = $product_id ? $this->products->get_scoped($product_id, $this->unit_id) : null;
        if (!$product || (string) $product->status !== "active") {
            return $this->no_price("product_not_resolvable");
        }

        // Escolha da lista.
        if ($list_id) {
            $list = $this->lists->get_scoped($list_id, $this->unit_id);
            if (!$list || (string) $list->status !== "active" || !$this->list_valid_on($list, $reference_date)) {
                return $this->no_price("price_list_invalid");
            }
        } else {
            $list = $this->lists->get_default($this->unit_id);
            if (!$list || !$this->list_valid_on($list, $reference_date)) {
                return $this->no_price("no_default_price_list");
            }
        }

        // Um escopo explicitamente solicitado deve ser utilizável. Não há
        // fallback silencioso para o preço-base quando a referência é inválida.
        $reqV = 0;
        if ($variant_id) {
            $variant = $this->variants->get_scoped($variant_id, $this->unit_id);
            if (!$variant || (int) $variant->product_id !== $product_id || (string) $variant->status !== "active") {
                return $this->no_price("variant_not_resolvable");
            }
            $reqV = $variant_id;
        }
        $reqR = 0;
        if ($resource_id) {
            $resource = $this->resources->get_scoped($resource_id, $this->unit_id);
            if (!$resource || (int) $resource->is_active !== 1) {
                return $this->no_price("resource_not_resolvable");
            }
            $reqR = $resource_id;
        }

        $candidates = $this->model->resolution_candidates($this->unit_id, (int) $list->id, $product_id, $reference_date, $quantity);

        $levels = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($candidates as $c) {
            $cv = (int) ($c->variant_id ?? 0);
            $cr = (int) ($c->resource_id ?? 0);
            if ($cv > 0 && $cv !== $reqV) { continue; }
            if ($cr > 0 && $cr !== $reqR) { continue; }
            if ($cv > 0 && $cr > 0)      { $levels[1][] = $c; }
            elseif ($cv === 0 && $cr > 0) { $levels[2][] = $c; }
            elseif ($cv > 0 && $cr === 0) { $levels[3][] = $c; }
            else                          { $levels[4][] = $c; }
        }

        $scopes = [1 => "variant_resource", 2 => "product_resource", 3 => "variant", 4 => "product_base"];
        foreach ([1, 2, 3, 4] as $level) {
            if (!$levels[$level]) { continue; }
            $best = $this->pick_best($levels[$level]);
            return [
                "found" => true,
                "price_id" => (int) $best->id,
                "price_list_id" => (int) $best->price_list_id,
                "product_id" => (int) $best->product_id,
                "variant_id" => $best->variant_id !== null ? (int) $best->variant_id : null,
                "resource_id" => $best->resource_id !== null ? (int) $best->resource_id : null,
                "amount" => $best->amount,
                "reference_cost" => $best->reference_cost,
                "currency" => $list->currency,
                "matched_scope" => $scopes[$level],
                "minimum_quantity" => $best->minimum_quantity,
                "valid_from" => $best->valid_from,
                "valid_until" => $best->valid_until,
            ];
        }
        return $this->no_price("no_matching_price");
    }

    /** Desempate determinístico: maior min_qty, vigência mais recente, maior id. */
    private function pick_best(array $rows): object
    {
        usort($rows, static function ($a, $b) {
            $cmp = DataNormalizationService::decimalCompare((string) $b->minimum_quantity, (string) $a->minimum_quantity);
            if ($cmp !== 0) { return $cmp; }
            $cmp = strcmp((string) ($b->valid_from ?? ""), (string) ($a->valid_from ?? ""));
            if ($cmp !== 0) { return $cmp; }
            return (int) $b->id <=> (int) $a->id;
        });
        return $rows[0];
    }

    private function list_valid_on(object $list, string $date): bool
    {
        if ($list->valid_from && $date < (string) $list->valid_from) { return false; }
        if ($list->valid_until && $date > (string) $list->valid_until) { return false; }
        return true;
    }

    private function no_price(string $reason): array
    {
        return ["found" => false, "reason" => $reason];
    }
}
