<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ProductCategoryService extends CatalogDataService
{
    private $model;
    private CatalogDuplicateDetectionService $duplicates;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_product_categories_model");
        $this->duplicates = new CatalogDuplicateDetectionService($unit_id);
    }

    public function get(int $id): ?object { return $this->model->get_scoped($id, $this->unit_id); }

    public function save(array $input, int $id = 0, bool $duplicate_override = false): array
    {
        $existing = $id ? $this->get($id) : null;
        if ($id && !$existing) { throw new \DomainException("gd_record_not_found"); }

        $code = DataNormalizationService::text($input["code"] ?? "");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($code === "" || mb_strlen($code) > 40) { throw new \DomainException("gd_code_required"); }
        if ($name === "" || mb_strlen($name) > 150) { throw new \DomainException("gd_name_required"); }

        $status = (string) ($input["status"] ?? "active");
        if (!Constants::isCategoryStatus($status)) { throw new \DomainException("gd_invalid_value"); }

        $parent_id = (int) ($input["parent_id"] ?? 0) ?: null;
        if ($parent_id !== null) {
            if ($id && $parent_id === $id) { throw new \DomainException("gd_category_self_parent"); }
            $parent = $this->model->get_scoped($parent_id, $this->unit_id);
            if (!$parent) { throw new \DomainException("gd_invalid_parent_category"); }
            if ($id) { $this->assert_no_cycle($id, $parent_id); }
        }

        $sort = max(0, (int) ($input["sort_order"] ?? 0));

        if ($this->model->is_duplicate_code($code, $this->unit_id, $id)) {
            throw new \DomainException("gd_duplicate_code");
        }

        $candidate = ["code" => $code, "name" => $name, "parent_id" => $parent_id];
        $matches = $this->duplicates->categories($candidate, $id);
        $strong = array_values(array_filter($matches, static fn($m) => $m["confidence"] === "high"));
        if ($strong && !$duplicate_override) {
            return ["saved" => false, "duplicate_confirmation_required" => true, "duplicates" => $matches];
        }

        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "parent_id" => $parent_id,
            "code" => $code,
            "name" => $name,
            "description" => DataNormalizationService::text($input["description"] ?? "") ?: null,
            "sort_order" => $sort,
            "status" => $status,
        ], $id === 0);

        $before = $existing ? (array) $existing : null;
        $save = (int) $this->model->ci_save($data, $id);
        if (!$save) { throw new \RuntimeException("save_failed"); }
        $after = (array) $this->get($save);

        $action = !$id ? "create" : "update";
        $this->audit_change($action, "product_category", $save, $before, $after);
        if ($id && (int) ($before["parent_id"] ?? 0) !== (int) ($after["parent_id"] ?? 0)) {
            $this->audit_change("hierarchy_change", "product_category", $save, ["parent_id" => $before["parent_id"] ?? null], ["parent_id" => $after["parent_id"] ?? null]);
        }
        if ($id && (string) ($before["status"] ?? "") !== (string) $after["status"]) {
            $this->audit_change("status_change", "product_category", $save, null, null, ["from" => $before["status"] ?? null, "to" => $after["status"]]);
        }
        if ($strong && $duplicate_override) {
            $this->audit_change("duplicate_override", "product_category", $save, null, null, ["matches" => array_column($matches, "record_id")]);
        }
        return ["saved" => true, "id" => $save, "duplicates" => $matches];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        if ($this->model->active_subcategory_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_category_has_subcategories");
        }
        if ($this->model->active_product_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_category_has_products");
        }
        $this->model->delete($id);
        $this->audit_change("delete", "product_category", $id, (array) $row, null);
    }

    /** Sobe a cadeia de ancestrais do novo pai; se encontrar $id, há ciclo. */
    private function assert_no_cycle(int $id, int $parent_id): void
    {
        $guard = 0;
        $current = $parent_id;
        while ($current && $guard++ < 50) {
            if ($current === $id) { throw new \DomainException("gd_category_cycle"); }
            $row = $this->model->get_scoped($current, $this->unit_id);
            $current = $row && $row->parent_id ? (int) $row->parent_id : 0;
        }
        if ($current) { throw new \DomainException("gd_category_cycle"); }
    }
}
