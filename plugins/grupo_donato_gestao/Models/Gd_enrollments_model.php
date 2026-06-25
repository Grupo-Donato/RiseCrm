<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_enrollments_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_enrollments"); }
    public function get_scoped(int $id, int $unit_id): ?object { return $this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id)->where("deleted",0)->get(1)->getRow(); }
    public function optimistic_update(int $id,int $unit_id,int $version,array $data):bool{$data["lock_version"]=$version+1;$data["updated_at"]=gmdate("Y-m-d H:i:s");$this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id)->where("deleted",0)->where("lock_version",$version)->update($data);return $this->db->affectedRows()===1;}
}
