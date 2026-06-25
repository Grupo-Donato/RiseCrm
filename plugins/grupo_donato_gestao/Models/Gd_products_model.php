<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_products_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct() { parent::__construct("gd_products"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function get_details(array $options = []): array
    {
        $unit_id = (int) get_array_value($options, "unit_id");
        $prod = $this->table;
        $cats = $this->db->prefixTable("gd_product_categories");
        $areas = $this->db->prefixTable("gd_business_areas");
        $variants = $this->db->prefixTable("gd_product_variants");

        $base = function () use ($options, $unit_id, $prod, $cats, $areas) {
            $b = $this->db->table($prod)
                ->join($cats, "$cats.id = $prod.category_id AND $cats.unit_id = $prod.unit_id AND $cats.deleted = 0", "left", false)
                ->join($areas, "$areas.id = $prod.business_area_id AND $areas.deleted = 0 AND ($areas.unit_id IS NULL OR $areas.unit_id = $prod.unit_id)", "left", false)
                ->where("$prod.unit_id", $unit_id)->where("$prod.deleted", 0);
            $id = (int) get_array_value($options, "id");
            if ($id) { $b->where("$prod.id", $id); }
            foreach (["product_type", "status", "category_id", "business_area_id"] as $field) {
                $value = get_array_value($options, $field);
                if ($value) { $b->where("$prod.$field", $value); }
            }
            $search = trim((string) get_array_value($options, "search_by"));
            if ($search !== "") {
                $b->groupStart()->like("$prod.code", $search)->orLike("$prod.name", $search)->groupEnd();
            }
            return $b;
        };

        $records_total = $this->db->table($prod)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
        $records_filtered = $base()->countAllResults(false);
        $builder = $base()->select("$prod.*, $cats.name AS category_name, $areas.name AS business_area_name, (SELECT COUNT(*) FROM $variants v WHERE v.product_id = $prod.id AND v.unit_id = $prod.unit_id AND v.deleted = 0) AS variant_count", false);
        $order_map = ["code" => "$prod.code", "name" => "$prod.name", "product_type" => "$prod.product_type", "status" => "$prod.status", "updated_at" => "$prod.updated_at", "id" => "$prod.id"];
        $order = $order_map[(string) get_array_value($options, "order_by")] ?? "$prod.name";
        $dir = get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC";
        $builder->orderBy($order, $dir);
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

    public function active_variant_count(int $id, int $unit_id): int
    {
        $variants = $this->db->prefixTable("gd_product_variants");
        return $this->db->table($variants)->where("product_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status !=", "archived")->countAllResults();
    }

    public function active_price_count(int $id, int $unit_id): int
    {
        $prices = $this->db->prefixTable("gd_prices");
        return $this->db->table($prices)->where("product_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status", "active")->countAllResults();
    }
}
