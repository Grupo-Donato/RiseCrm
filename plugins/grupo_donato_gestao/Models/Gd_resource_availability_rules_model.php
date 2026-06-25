<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_resource_availability_rules_model extends Gd_resource_temporal_model
{
    protected array $searchable_fields = ["notes"];
    public function __construct() { parent::__construct("gd_resource_availability_rules"); }

    public function get_scoped(int $id, int $unit_id): ?object
    {
        return $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->get(1)->getRow();
    }

    public function for_resource(int $resource_id, int $unit_id, bool $active_only = false): array
    {
        $b = $this->db->table($this->table)->where("unit_id", $unit_id)->where("resource_id", $resource_id)->where("deleted", 0);
        if ($active_only) { $b->where("status", "active"); }
        return $b->orderBy("weekday")->orderBy("start_time")->orderBy("sort_order")->get()->getResult();
    }
    public function get_details(array $options=[]):array{return $this->temporal_details($options,["weekday","status"],["weekday"=>"weekday","start_time"=>"start_time","status"=>"status","updated_at"=>"updated_at"],["notes"]);}
}
