<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_cost_centers_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct()
    {
        parent::__construct("gd_cost_centers");
    }

    /**
     * Lista com nome de unidade e área (joins). Tabela pequena → client-side.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details(array $options = [])
    {
        $cc = $this->table;
        $units = $this->db->prefixTable("gd_units");
        $areas = $this->db->prefixTable("gd_business_areas");

        $builder = $this->db->table($cc);
        $builder->select("$cc.*, $units.name AS unit_name, $areas.name AS business_area_name");
        $builder->join($units, "$units.id = $cc.unit_id", "left");
        $builder->join($areas, "$areas.id = $cc.business_area_id", "left");
        $builder->where("$cc.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$cc.id", $id);
        }

        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id) {
            $builder->where("$cc.unit_id", $unit_id);
        }

        $builder->orderBy("$cc.name", "ASC");
        return $builder->get();
    }

    public function is_duplicate_code(string $code, $unit_id, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("code", trim($code));
        if ($unit_id) {
            $builder->where("unit_id", $unit_id);
        } else {
            $builder->where("unit_id IS NULL", null, false);
        }
        if ($exclude_id) {
            $builder->where("id !=", $exclude_id);
        }
        return $builder->countAllResults() > 0;
    }

    public function count_active(): int
    {
        return $this->db->table($this->table)->where("deleted", 0)->countAllResults();
    }
}
