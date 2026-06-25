<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

/** Paginação server-side, escopo e whitelists comuns às tabelas temporais. */
abstract class Gd_resource_temporal_model extends Gd_Model
{
    protected function temporal_details(array $options,array $filter_fields,array $order_map,array $search_fields):array
    {
        $unit_id=(int)get_array_value($options,"unit_id");$resource_id=(int)get_array_value($options,"resource_id");
        $base=function()use($options,$unit_id,$resource_id,$filter_fields,$search_fields){$b=$this->db->table($this->table)->where("unit_id",$unit_id)->where("deleted",0);if($resource_id){$b->where("resource_id",$resource_id);}foreach($filter_fields as $field){$value=get_array_value($options,$field);if($value!==null&&$value!==""){$b->where($field,$value);}}$search=trim((string)get_array_value($options,"search_by"));if($search!==""){$b->groupStart();foreach(array_values($search_fields) as $i=>$field){$i?$b->orLike($field,$search):$b->like($field,$search);}$b->groupEnd();}return $b;};
        $total=$this->db->table($this->table)->where("unit_id",$unit_id)->where("deleted",0);if($resource_id){$total->where("resource_id",$resource_id);}$records_total=$total->countAllResults();$records_filtered=$base()->countAllResults(false);$builder=$base();$order=$order_map[(string)get_array_value($options,"order_by")]??reset($order_map);$dir=get_array_value($options,"order_dir")==="DESC"?"DESC":"ASC";$builder->orderBy($order,$dir)->orderBy("id","DESC");$limit=max(1,min(100,(int)(get_array_value($options,"limit")?:25)));$builder->limit($limit,max(0,(int)get_array_value($options,"skip")));return ["data"=>$builder->get()->getResult(),"recordsTotal"=>$records_total,"recordsFiltered"=>$records_filtered];
    }
}
