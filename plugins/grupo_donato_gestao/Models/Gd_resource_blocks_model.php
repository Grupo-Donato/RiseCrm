<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_resource_blocks_model extends Gd_resource_temporal_model
{
    protected array $searchable_fields = ["title", "reason"];
    public function __construct() { parent::__construct("gd_resource_blocks"); }
    public function get_scoped(int $id, int $unit_id): ?object { return $this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id)->where("deleted",0)->get(1)->getRow(); }
    public function for_resource(int $resource_id, int $unit_id): array { return $this->db->table($this->table)->where("unit_id",$unit_id)->where("resource_id",$resource_id)->where("deleted",0)->orderBy("starts_at_utc","DESC")->get()->getResult(); }
    public function get_details(array $options=[]):array{return $this->temporal_details($options,["block_type","status"],["starts_at_utc"=>"starts_at_utc","title"=>"title","status"=>"status","updated_at"=>"updated_at"],["title","reason"]);}
}
