<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_product_categories_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct() { parent::__construct("gd_product_categories"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /**
     * Lista com nome da unidade e da categoria pai. Tabela pequena → client-side.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details(array $options = [])
    {
        $cat = $this->table;
        $units = $this->db->prefixTable("gd_units");
        $parent = $cat . " AS parentcat";

        $builder = $this->db->table($cat);
        $builder->select("$cat.*, $units.name AS unit_name, parentcat.name AS parent_name");
        $builder->join($units, "$units.id = $cat.unit_id AND $units.deleted = 0", "left", false);
        $builder->join($parent, "parentcat.id = $cat.parent_id AND parentcat.unit_id = $cat.unit_id AND parentcat.deleted = 0", "left", false);
        $builder->where("$cat.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) { $builder->where("$cat.id", $id); }

        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id) { $builder->where("$cat.unit_id", $unit_id); }

        $status = get_array_value($options, "status");
        if ($status) { $builder->where("$cat.status", $status); }

        $builder->orderBy("$cat.sort_order", "ASC")->orderBy("$cat.name", "ASC");
        return $builder->get();
    }

    public function is_duplicate_code(string $code, int $unit_id, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("code", trim($code))->where("unit_id", $unit_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->countAllResults() > 0;
    }

    public function active_subcategory_count(int $id, int $unit_id, int $exclude_id = 0): int
    {
        $builder = $this->db->table($this->table)->where("parent_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status", "active");
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->countAllResults();
    }

    public function active_product_count(int $id, int $unit_id): int
    {
        $products = $this->db->prefixTable("gd_products");
        return $this->db->table($products)->where("category_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status !=", "archived")->countAllResults();
    }

    public function count_active(int $unit_id): int
    {
        return $this->db->table($this->table)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
    }
}
