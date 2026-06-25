<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_bookings_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_bookings"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder=$this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id);
        if(!$include_deleted){$builder->where("deleted",0);} return $builder->get(1)->getRow();
    }

    public function optimistic_update(int $id,int $unit_id,int $lock_version,array $data):bool
    {
        $data["lock_version"]=$lock_version+1;$data["updated_at"]=function_exists("get_current_utc_time")?get_current_utc_time():gmdate("Y-m-d H:i:s");
        $this->db->table($this->table)->where("id",$id)->where("unit_id",$unit_id)->where("deleted",0)->where("lock_version",$lock_version)->update($data);
        return $this->db->affectedRows()===1;
    }

    public function get_details(array $options=[]):array
    {
        $unit=(int)get_array_value($options,"unit_id");$b=$this->table;$a=$this->db->prefixTable("gd_customer_accounts");$br=$this->db->prefixTable("gd_booking_resources");$r=$this->db->prefixTable("gd_resources");
        $base=function()use($options,$unit,$b,$a,$br,$r){$q=$this->db->table($b)->join($a,"$a.id=$b.customer_account_id AND $a.unit_id=$b.unit_id AND $a.deleted=0","left",false)->join($br,"$br.booking_id=$b.id AND $br.unit_id=$b.unit_id AND $br.deleted=0","left",false)->join($r,"$r.id=$br.resource_id AND $r.unit_id=$b.unit_id AND $r.deleted=0","left",false)->where("$b.unit_id",$unit)->where("$b.deleted",0);
            foreach(["booking_type","status"] as $f){$v=trim((string)get_array_value($options,$f));if($v!==""){$q->where("$b.$f",$v);}}
            if($v=(int)get_array_value($options,"resource_id")){$q->where("$br.resource_id",$v);}if($v=(int)get_array_value($options,"customer_account_id")){$q->where("$b.customer_account_id",$v);}
            if($v=trim((string)get_array_value($options,"date_from_utc"))){$q->where("$b.ends_at_utc >",$v);}if($v=trim((string)get_array_value($options,"date_to_utc"))){$q->where("$b.starts_at_utc <",$v);}
            if($v=trim((string)get_array_value($options,"search_by"))){$q->groupStart()->like("$b.booking_number",$v)->orLike("$b.title",$v)->orLike("$a.display_name",$v)->orLike("$r.code",$v)->orLike("$r.name",$v)->groupEnd();}return $q;};
        $total=$this->db->table($b)->where("unit_id",$unit)->where("deleted",0)->countAllResults();$count=$base()->select("COUNT(DISTINCT $b.id) total",false)->get()->getRow();
        $q=$base()->select("$b.*, $a.display_name AS customer_name, GROUP_CONCAT(DISTINCT CONCAT($r.code,' — ',$r.name) ORDER BY $r.code SEPARATOR ', ') AS resource_names",false)->groupBy("$b.id");
        $map=["booking_number"=>"$b.booking_number","title"=>"$b.title","booking_type"=>"$b.booking_type","starts_at_utc"=>"$b.starts_at_utc","ends_at_utc"=>"$b.ends_at_utc","status"=>"$b.status","hold_expires_at_utc"=>"$b.hold_expires_at_utc","updated_at"=>"$b.updated_at"];$order=$map[(string)get_array_value($options,"order_by")]??"$b.starts_at_utc";$dir=get_array_value($options,"order_dir")==="ASC"?"ASC":"DESC";$q->orderBy($order,$dir)->limit(max(1,min(100,(int)(get_array_value($options,"limit")?:25))),max(0,(int)get_array_value($options,"skip")));
        return ["data"=>$q->get()->getResult(),"recordsTotal"=>$total,"recordsFiltered"=>(int)($count->total??0)];
    }
}
