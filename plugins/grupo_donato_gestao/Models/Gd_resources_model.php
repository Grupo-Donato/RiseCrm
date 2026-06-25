<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_resources_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct() { parent::__construct("gd_resources"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function get_details(array $options = []): array
    {
        $unit_id = (int) get_array_value($options, "unit_id");
        $res = $this->table;
        $units = $this->db->prefixTable("gd_units");
        $areas = $this->db->prefixTable("gd_business_areas");
        $centers = $this->db->prefixTable("gd_cost_centers");

        $base = function () use ($options, $unit_id, $res, $units, $areas, $centers) {
            $b = $this->db->table($res)
                ->join($units, "$units.id = $res.unit_id AND $units.deleted = 0", "left", false)
                ->join($areas, "$areas.id = $res.business_area_id AND $areas.deleted = 0 AND ($areas.unit_id IS NULL OR $areas.unit_id = $res.unit_id)", "left", false)
                ->join($centers, "$centers.id = $res.cost_center_id AND $centers.deleted = 0 AND ($centers.unit_id IS NULL OR $centers.unit_id = $res.unit_id)", "left", false)
                ->where("$res.unit_id", $unit_id)->where("$res.deleted", 0);
            $id = (int) get_array_value($options, "id");
            if ($id) { $b->where("$res.id", $id); }
            foreach (["resource_type"] as $field) {
                $value = get_array_value($options, $field);
                if ($value) { $b->where("$res.$field", $value); }
            }
            $is_active = get_array_value($options, "is_active");
            if ($is_active !== null && $is_active !== "") { $b->where("$res.is_active", (int) $is_active); }
            $search = trim((string) get_array_value($options, "search_by"));
            if ($search !== "") {
                $b->groupStart()->like("$res.code", $search)->orLike("$res.name", $search)->groupEnd();
            }
            return $b;
        };

        $records_total = $this->db->table($res)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
        $records_filtered = $base()->countAllResults(false);
        $builder = $base()->select("$res.*, $units.name AS unit_name, $areas.name AS business_area_name, $centers.name AS cost_center_name");
        $order_map = ["code" => "$res.code", "name" => "$res.name", "resource_type" => "$res.resource_type", "is_active" => "$res.is_active", "updated_at" => "$res.updated_at", "id" => "$res.id"];
        $order = $order_map[(string) get_array_value($options, "order_by")] ?? "$res.sort_order";
        $dir = get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC";
        $builder->orderBy($order, $dir)->orderBy("$res.name", "ASC");
        $limit = max(1, min(100, (int) (get_array_value($options, "limit") ?: 25)));
        $builder->limit($limit, max(0, (int) get_array_value($options, "skip")));
        return ["data" => $builder->get()->getResult(), "recordsTotal" => $records_total, "recordsFiltered" => $records_filtered];
    }

    public function is_duplicate_code(string $code, int $unit_id, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("code", trim($code))->where("unit_id", $unit_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->countAllResults() > 0;
    }

    public function active_specific_price_count(int $id, int $unit_id): int
    {
        $prices = $this->db->prefixTable("gd_prices");
        return $this->db->table($prices)->where("resource_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status", "active")->countAllResults();
    }
}
