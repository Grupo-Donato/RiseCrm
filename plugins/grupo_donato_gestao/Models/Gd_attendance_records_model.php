<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_attendance_records_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_attendance_records"); }
    public function for_session(int $session_id,int $unit_id):array{return $this->db->table($this->table)->where("attendance_session_id",$session_id)->where("unit_id",$unit_id)->get()->getResult();}
}
