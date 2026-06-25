<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ProductVariantService extends CatalogDataService
{
    private $model;
    private $products;
    private CatalogDuplicateDetectionService $duplicates;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_product_variants_model");
        $this->products = model("grupo_donato_gestao\\Models\\Gd_products_model");
        $this->duplicates = new CatalogDuplicateDetectionService($unit_id);
    }

    public function get(int $id): ?object { return $this->model->get_scoped($id, $this->unit_id); }

    /** Produto válido para receber variações (unidade, não excluído, allows_variants, não arquivado). */
    private function assert_product(int $product_id): object
    {
        $product = $this->products->get_scoped($product_id, $this->unit_id);
        if (!$product) { throw new \DomainException("gd_invalid_product"); }
        if (!(int) $product->allows_variants) { throw new \DomainException("gd_product_no_variants"); }
        if ((string) $product->status === "archived") { throw new \DomainException("gd_product_archived"); }
        return $product;
    }

    public function save(array $input, int $id = 0, bool $duplicate_override = false): array
    {
        $existing = $id ? $this->get($id) : null;
        if ($id && !$existing) { throw new \DomainException("gd_record_not_found"); }

        $product_id = (int) ($input["product_id"] ?? ($existing->product_id ?? 0));
        $this->assert_product($product_id);

        $code = DataNormalizationService::text($input["code"] ?? "");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($code === "" || mb_strlen($code) > 40) { throw new \DomainException("gd_code_required"); }
        if ($name === "" || mb_strlen($name) > 190) { throw new \DomainException("gd_name_required"); }

        $status = (string) ($input["status"] ?? "active");
        if (!Constants::isVariantStatus($status)) { throw new \DomainException("gd_invalid_value"); }

        $attributes = DataNormalizationService::json($input["attributes"] ?? null);
        $wants_default = !empty($input["is_default"]) && $status === "active";

        if ($this->model->is_duplicate_code($code, $product_id, $id)) {
            throw new \DomainException("gd_duplicate_code");
        }

        $matches = $this->duplicates->variants(["code" => $code, "attributes" => $attributes], $product_id, $id);
        $strong = array_values(array_filter($matches, static fn($m) => $m["confidence"] === "high"));
        if ($strong && !$duplicate_override) {
            return ["saved" => false, "duplicate_confirmation_required" => true, "duplicates" => $matches];
        }

        // is_default é sempre 0 no save; mark_as_default (transacional) ajusta depois,
        // evitando conflito do índice único de padrão na inserção.
        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "product_id" => $product_id,
            "code" => $code,
            "name" => $name,
            "barcode" => DataNormalizationService::text($input["barcode"] ?? "") ?: null,
            "attributes" => $attributes,
            "is_default" => 0,
            "sort_order" => max(0, (int) ($input["sort_order"] ?? 0)),
            "status" => $status,
        ], $id === 0);

        $before = $existing ? (array) $existing : null;
        $save = (int) $this->model->ci_save($data, $id);
        if (!$save) { throw new \RuntimeException("save_failed"); }

        if ($wants_default) {
            $this->model->mark_as_default($save, $product_id);
        }

        $after = (array) $this->get($save);
        $this->audit_change(!$id ? "create" : "update", "product_variant", $save, $before, $after);
        if ((int) ($before["is_default"] ?? 0) !== (int) $after["is_default"]) {
            $this->audit_change("default_change", "product_variant", $save, null, null, ["product_id" => $product_id, "is_default" => (int) $after["is_default"]]);
        }
        if ($strong && $duplicate_override) {
            $this->audit_change("duplicate_override", "product_variant", $save, null, null, ["matches" => array_column($matches, "record_id")]);
        }
        return ["saved" => true, "id" => $save, "duplicates" => $matches];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        if ($this->model->active_price_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_variant_has_prices");
        }
        $this->model->delete($id);
        $this->audit_change("delete", "product_variant", $id, (array) $row, null);
    }
}
