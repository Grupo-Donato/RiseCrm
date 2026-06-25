<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_booking_series_resources_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_booking_series_resources"); }

    public function for_series(int $series_id, int $unit_id, bool $include_deleted = false): array
    {
        $resources = $this->db->prefixTable("gd_resources");
        $builder = $this->db->table($this->table)
            ->select("{$this->table}.*, $resources.code AS resource_code, $resources.name AS resource_name")
            ->join($resources, "$resources.id={$this->table}.resource_id AND $resources.unit_id={$this->table}.unit_id", "inner", false)
            ->where("{$this->table}.series_id", $series_id)->where("{$this->table}.unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("{$this->table}.deleted", 0); }
        return $builder->orderBy("{$this->table}.resource_id", "ASC")->get()->getResult();
    }
}
