<?php
declare(strict_types=1);namespace grupo_donato_gestao\Models;class Gd_receivable_items_model extends Gd_Model{public function __construct(){parent::__construct('gd_receivable_items');}public function for_receivable(int $id,int $unit):array{return $this->db->table($this->table)->where('receivable_id',$id)->where('unit_id',$unit)->where('deleted',0)->get()->getResult();}}
