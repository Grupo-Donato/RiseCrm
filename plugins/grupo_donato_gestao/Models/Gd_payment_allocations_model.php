<?php
declare(strict_types=1);namespace grupo_donato_gestao\Models;class Gd_payment_allocations_model extends Gd_Model{public function __construct(){parent::__construct('gd_payment_allocations');}public function for_payment(int $id,int $unit):array{return $this->db->table($this->table)->where('payment_id',$id)->where('unit_id',$unit)->get()->getResult();}}
