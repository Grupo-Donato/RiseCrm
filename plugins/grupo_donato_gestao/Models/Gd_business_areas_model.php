<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_business_areas_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct()
    {
        parent::__construct("gd_business_areas");
    }

    /**
     * Lista com nome da unidade (join). Tabela pequena → client-side.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details(array $options = [])
    {
        $areas = $this->table;
        $units = $this->db->prefixTable("gd_units");

        $builder = $this->db->table($areas);
        $builder->select("$areas.*, $units.name AS unit_name");
        $builder->join($units, "$units.id = $areas.unit_id", "left");
        $builder->where("$areas.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$areas.id", $id);
        }

        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id) {
            $builder->where("$areas.unit_id", $unit_id);
        }
        $status = get_array_value($options, "status");
        if ($status) {
            $builder->where("$areas.status", $status);
        }

        $builder->orderBy("$areas.name", "ASC");
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
