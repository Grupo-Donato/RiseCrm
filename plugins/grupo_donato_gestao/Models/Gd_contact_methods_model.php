<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_contact_methods_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_contact_methods"); }
    public function get_scoped(int $id,int $unit_id):?object{return $this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id)->where("deleted",0)->get(1)->getRow();}
    public function get_details(array $options=[]):array{$b=$this->db->table($this->table)->where("unit_id",(int)get_array_value($options,"unit_id"))->where("person_id",(int)get_array_value($options,"person_id"))->where("deleted",0);$total=(clone $b)->countAllResults();$b->orderBy("is_primary","DESC")->orderBy("contact_type","ASC")->limit(max(1,min(100,(int)(get_array_value($options,"limit")?:25))),max(0,(int)get_array_value($options,"skip")));return ["data"=>$b->get()->getResult(),"recordsTotal"=>$total,"recordsFiltered"=>$total];}
}
