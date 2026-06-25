<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_attendance_sessions_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_attendance_sessions"); }
    public function for_date(int $class_id,string $date,int $unit_id):?object{return $this->db->table($this->table)->where("class_id",$class_id)->where("attendance_date",$date)->where("unit_id",$unit_id)->where("deleted",0)->get(1)->getRow();}
}
