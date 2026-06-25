<?php
declare(strict_types=1);namespace grupo_donato_gestao\Models;class Gd_receivables_model extends Gd_Model{public function __construct(){parent::__construct('gd_receivables');}public function get_scoped(int $id,int $unit):?object{return $this->db->table($this->table)->where('id',$id)->where('unit_id',$unit)->where('deleted',0)->get(1)->getRow();}}
