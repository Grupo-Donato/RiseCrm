<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ResourceService extends CatalogDataService
{
    private $model;
    private CatalogDuplicateDetectionService $duplicates;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_resources_model");
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

        $type = (string) ($input["resource_type"] ?? "");
        if (!Constants::isResourceType($type)) { throw new \DomainException("gd_invalid_resource_type"); }

        $area_id = $this->assert_area((int) ($input["business_area_id"] ?? 0) ?: null);
        $cc_id = $this->assert_cost_center((int) ($input["cost_center_id"] ?? 0) ?: null, $area_id);

        $capacity = null;
        if (isset($input["capacity"]) && trim((string) $input["capacity"]) !== "") {
            if (!preg_match('/^\d+$/', trim((string) $input["capacity"]))) { throw new \DomainException("gd_invalid_capacity"); }
            $capacity = (int) $input["capacity"];
        }

        $metadata = DataNormalizationService::json($input["metadata"] ?? null);

        if ($this->model->is_duplicate_code($code, $this->unit_id, $id)) {
            throw new \DomainException("gd_duplicate_code");
        }

        $matches = $this->duplicates->resources(["code" => $code, "name" => $name], $id);
        $strong = array_values(array_filter($matches, static fn($m) => $m["confidence"] === "exact"));
        if ($strong && !$duplicate_override) {
            return ["saved" => false, "duplicate_confirmation_required" => true, "duplicates" => $matches];
        }

        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "business_area_id" => $area_id,
            "cost_center_id" => $cc_id,
            "code" => $code,
            "name" => $name,
            "resource_type" => $type,
            "description" => DataNormalizationService::text($input["description"] ?? "") ?: null,
            "capacity" => $capacity,
            "is_bookable" => !empty($input["is_bookable"]) ? 1 : 0,
            "is_active" => array_key_exists("is_active", $input) ? (!empty($input["is_active"]) ? 1 : 0) : 1,
            "sort_order" => max(0, (int) ($input["sort_order"] ?? 0)),
            "metadata" => $metadata,
        ], $id === 0);

        $before = $existing ? (array) $existing : null;
        $save = (int) $this->model->ci_save($data, $id);
        if (!$save) { throw new \RuntimeException("save_failed"); }
        $after = (array) $this->get($save);

        $this->audit_change(!$id ? "create" : "update", "resource", $save, $before, $after);
        if ($id && (int) ($before["is_active"] ?? 1) !== (int) $after["is_active"]) {
            $this->audit_change("status_change", "resource", $save, null, null, ["is_active" => $after["is_active"]]);
        }
        return ["saved" => true, "id" => $save, "duplicates" => $matches];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        if ($this->model->active_specific_price_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_resource_has_prices");
        }
        $this->model->delete($id);
        $this->audit_change("delete", "resource", $id, (array) $row, null);
    }
}
