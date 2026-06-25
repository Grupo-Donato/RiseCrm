<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_account_people_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_account_people"); }
    public function get_scoped(int $id, int $unit_id): ?object { return $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->get(1)->getRow(); }
    public function get_details(array $options = []): array
    {
        $t=$this->table; $p=$this->db->prefixTable("gd_people"); $a=$this->db->prefixTable("gd_customer_accounts");
        $b=$this->db->table($t)->select("$t.*, $p.full_name, $a.display_name")->join($p,"$p.id=$t.person_id AND $p.unit_id=$t.unit_id AND $p.deleted=0","inner",false)->join($a,"$a.id=$t.account_id AND $a.unit_id=$t.unit_id AND $a.deleted=0","inner",false)
            ->where("$t.unit_id",(int)get_array_value($options,"unit_id"))->where("$t.deleted",0);
        if($v=(int)get_array_value($options,"account_id")){$b->where("$t.account_id",$v);} if($v=(int)get_array_value($options,"person_id")){$b->where("$t.person_id",$v);}
        $total=(clone $b)->countAllResults(); $b->orderBy("$t.is_primary","DESC")->orderBy("$p.full_name","ASC");
        $b->limit(max(1,min(100,(int)(get_array_value($options,"limit")?:25))),max(0,(int)get_array_value($options,"skip")));
        return ["data"=>$b->get()->getResult(),"recordsTotal"=>$total,"recordsFiltered"=>$total];
    }
}
