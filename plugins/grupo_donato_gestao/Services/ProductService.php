<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ProductService extends CatalogDataService
{
    private $model;
    private CatalogDuplicateDetectionService $duplicates;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_products_model");
        $this->duplicates = new CatalogDuplicateDetectionService($unit_id);
    }

    public function get(int $id): ?object { return $this->model->get_scoped($id, $this->unit_id); }

    /**
     * Busca Select2 de produtos compatíveis com locação: ativos, tipos
     * `rental`/`service`/`fee`, da unidade ativa. Nunca retorna produto de outra
     * unidade. Paginação por offset/limit.
     *
     * @return array<int,array<string,mixed>> linhas [id, code, name, product_type]
     */
    public function options(string $q, int $limit = 20, int $offset = 0): array
    {
        $t = $this->db->prefixTable("gd_products");
        $qb = $this->db->table($t)->select("id,code,name,product_type")
            ->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")
            ->whereIn("product_type", Constants::COURT_RENTAL_PRODUCT_TYPES);
        $q = trim($q);
        if ($q !== "") { $qb->groupStart()->like("name", $q)->orLike("code", $q)->groupEnd(); }
        return $qb->orderBy("name")->limit(max(1, min(50, $limit)), max(0, $offset))->get()->getResultArray();
    }

    /** Valida que um produto é utilizável em locação (ativo, tipo compatível, da unidade/global). */
    public function assertRentalCompatible(int $id): object
    {
        $row = $this->get($id);
        if (!$row || (string) $row->status !== "active" || !in_array((string) $row->product_type, Constants::COURT_RENTAL_PRODUCT_TYPES, true)) {
            throw new \DomainException("gd_invalid_product");
        }
        return $row;
    }

    public function save(array $input, int $id = 0, bool $duplicate_override = false): array
    {
        $existing = $id ? $this->get($id) : null;
        if ($id && !$existing) { throw new \DomainException("gd_record_not_found"); }

        $code = DataNormalizationService::text($input["code"] ?? "");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($code === "" || mb_strlen($code) > 40) { throw new \DomainException("gd_code_required"); }
        if ($name === "" || mb_strlen($name) > 190) { throw new \DomainException("gd_name_required"); }

        $type = (string) ($input["product_type"] ?? "");
        // credit/discount são reservados: rejeitados no backend para evitar ambiguidade.
        if (!in_array($type, Constants::PRODUCT_TYPES_SELECTABLE, true)) { throw new \DomainException("gd_invalid_product_type"); }

        $billing = (string) ($input["billing_mode"] ?? "");
        if (!Constants::isBillingMode($billing)) { throw new \DomainException("gd_invalid_billing_mode"); }

        $uom = (string) ($input["unit_of_measure"] ?? "");
        if (!Constants::isUnitOfMeasure($uom)) { throw new \DomainException("gd_invalid_unit_of_measure"); }

        $status = (string) ($input["status"] ?? "draft");
        if (!Constants::isProductStatus($status)) { throw new \DomainException("gd_invalid_value"); }

        $category_id = $this->assert_category((int) ($input["category_id"] ?? 0) ?: null);
        $area_id = $this->assert_area((int) ($input["business_area_id"] ?? 0) ?: null);
        $cc_id = $this->assert_cost_center((int) ($input["default_cost_center_id"] ?? 0) ?: null, $area_id);

        // Normalização de flags: somente produto físico controla estoque.
        $track_stock = ($type === "physical" && !empty($input["track_stock"])) ? 1 : 0;
        $allows_variants = !empty($input["allows_variants"]) ? 1 : 0;
        $allows_discount = !empty($input["allows_discount"]) ? 1 : 0;
        $requires_resource = !empty($input["requires_resource"]) ? 1 : 0;

        // Desabilitar variações exige que não haja variação ativa.
        if ($id && !$allows_variants && $this->model->active_variant_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_product_has_variants");
        }

        $rise_item_id = $this->assert_rise_id("items", (int) ($input["rise_item_id"] ?? 0));
        $metadata = DataNormalizationService::json($input["metadata"] ?? null);

        if ($this->model->is_duplicate_code($code, $this->unit_id, $id)) {
            throw new \DomainException("gd_duplicate_code");
        }

        $candidate = ["code" => $code, "name" => $name, "product_type" => $type, "rise_item_id" => $rise_item_id];
        $matches = $this->duplicates->products($candidate, $id);
        $strong = $this->strong_matches($matches);
        if ($strong && !$duplicate_override) {
            return ["saved" => false, "duplicate_confirmation_required" => true, "duplicates" => $matches];
        }

        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "category_id" => $category_id,
            "business_area_id" => $area_id,
            "default_cost_center_id" => $cc_id,
            "code" => $code,
            "name" => $name,
            "description" => DataNormalizationService::text($input["description"] ?? "") ?: null,
            "product_type" => $type,
            "billing_mode" => $billing,
            "unit_of_measure" => $uom,
            "track_stock" => $track_stock,
            "allows_variants" => $allows_variants,
            "allows_discount" => $allows_discount,
            "requires_resource" => $requires_resource,
            "status" => $status,
            "rise_item_id" => $rise_item_id,
            "metadata" => $metadata,
        ], $id === 0);

        $before = $existing ? (array) $existing : null;
        $save = (int) $this->model->ci_save($data, $id);
        if (!$save) { throw new \RuntimeException("save_failed"); }
        $after = (array) $this->get($save);

        $this->audit_change(!$id ? "create" : "update", "product", $save, $before, $after);
        if ($id && (string) ($before["product_type"] ?? "") !== (string) $after["product_type"]) {
            $this->audit_change("type_change", "product", $save, null, null, ["from" => $before["product_type"] ?? null, "to" => $after["product_type"]]);
        }
        if ($id) {
            $oldFlags = [(int) ($before["track_stock"] ?? 0), (int) ($before["allows_variants"] ?? 0), (int) ($before["allows_discount"] ?? 0), (int) ($before["requires_resource"] ?? 0)];
            $newFlags = [(int) $after["track_stock"], (int) $after["allows_variants"], (int) $after["allows_discount"], (int) $after["requires_resource"]];
            if ($oldFlags !== $newFlags) {
                $this->audit_change("flags_change", "product", $save, ["flags" => $oldFlags], ["flags" => $newFlags]);
            }
            if ((string) ($before["status"] ?? "") !== (string) $after["status"]) {
                $this->audit_change("status_change", "product", $save, null, null, ["from" => $before["status"] ?? null, "to" => $after["status"]]);
            }
        }
        if ($strong && $duplicate_override) {
            $this->audit_change("duplicate_override", "product", $save, null, null, ["matches" => array_column($matches, "record_id")]);
        }
        return ["saved" => true, "id" => $save, "duplicates" => $matches];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        if ($this->model->active_variant_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_product_has_variants");
        }
        if ($this->model->active_price_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_product_has_prices");
        }
        $this->model->delete($id);
        $this->audit_change("delete", "product", $id, (array) $row, null);
    }
}
