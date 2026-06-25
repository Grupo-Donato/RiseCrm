<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/** Motor de disponibilidade da Fase 3A. Não conhece reservas, clientes ou preços. */
class AvailabilityService extends CustomerDataService
{
    private TemporalService $time;
    public function __construct(int $unit_id) { parent::__construct($unit_id); $this->time=new TemporalService($unit_id); }

    public function check(int $resource_id,string $starts_at_utc,string $ends_at_utc):array
    {
        return $this->checkMany([$resource_id],$starts_at_utc,$ends_at_utc)[$resource_id];
    }

    /** Quatro consultas em lote (recursos, bloqueios, exceções e regras), sem N+1. */
    public function checkMany(array $resource_ids,string $starts_at_utc,string $ends_at_utc):array
    {
        [$start,$end]=$this->time->validateRange($starts_at_utc,$ends_at_utc);
        $ids=array_values(array_unique(array_filter(array_map("intval",$resource_ids),static fn($id)=>$id>0)));
        if(!$ids){return [];}
        $p=$this->db->getPrefix();
        $resources=$this->db->table($p."gd_resources")->where("unit_id",$this->unit_id)->whereIn("id",$ids)->get()->getResult();
        $resource_map=[];foreach($resources as $row){$resource_map[(int)$row->id]=$row;}
        $blocks=$this->db->table($p."gd_resource_blocks")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$end)->where("ends_at_utc >",$start)->get()->getResult();
        $exceptions=$this->db->table($p."gd_resource_availability_exceptions")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$end)->where("ends_at_utc >",$start)->get()->getResult();
        $rules=$this->db->table($p."gd_resource_availability_rules")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->get()->getResult();
        $by_block=$this->group($blocks);$by_exception=$this->group($exceptions);$by_rule=$this->group($rules);$out=[];
        foreach($ids as $id){$out[$id]=$this->evaluate($id,$resource_map[$id]??null,$start,$end,$by_block[$id]??[],$by_exception[$id]??[],$by_rule[$id]??[]);}
        return $out;
    }

    private function evaluate(int $id,?object $resource,string $start,string $end,array $blocks,array $exceptions,array $rules):array
    {
        $base=["available"=>false,"resource_id"=>$id,"starts_at_utc"=>$start,"ends_at_utc"=>$end,"timezone"=>$this->time->timezoneName(),"source"=>"resource","reason_code"=>"resource_not_found","matched_rule_ids"=>[],"matched_exception_ids"=>[],"matched_block_ids"=>[]];
        if(!$resource||(int)$resource->deleted===1){return $base;}
        if(!(int)$resource->is_active){$base["reason_code"]="resource_inactive";return $base;}
        if(!(int)$resource->is_bookable){$base["reason_code"]="resource_not_bookable";return $base;}
        if($blocks){$base["source"]="block";$base["reason_code"]="active_block";$base["matched_block_ids"]=array_map(static fn($r)=>(int)$r->id,$blocks);return $base;}
        $closed=array_values(array_filter($exceptions,static fn($r)=>(string)$r->exception_type==="closed"));
        if($closed){$base["source"]="closed_exception";$base["reason_code"]="closed_exception";$base["matched_exception_ids"]=array_map(static fn($r)=>(int)$r->id,$closed);return $base;}
        $open=array_values(array_filter($exceptions,static fn($r)=>(string)$r->exception_type==="open"));
        $matched=[];$open_ranges=[];foreach($open as $row){$open_ranges[]=[(string)$row->starts_at_utc,(string)$row->ends_at_utc];$matched[]=(int)$row->id;}
        if($this->covered($start,$end,$open_ranges)){$base["available"]=true;$base["source"]="open_exception";$base["reason_code"]="available_open_exception";$base["matched_exception_ids"]=array_values(array_unique($matched));return $base;}
        [$weekly_ranges,$rule_ids]=$this->weeklyRanges($rules,$start,$end);
        if($this->covered($start,$end,$weekly_ranges)){$base["available"]=true;$base["source"]="weekly_rule";$base["reason_code"]="available_weekly_rule";$base["matched_rule_ids"]=$rule_ids;return $base;}
        $base["source"]="none";$base["reason_code"]="outside_availability";return $base;
    }

    /** @return array{0:array,1:array} */
    private function weeklyRanges(array $rules,string $start,string $end):array
    {
        $local_start=$this->time->utcToLocal($start)->modify("-1 day")->setTime(0,0);$local_end=$this->time->utcToLocal($end)->modify("+1 day")->setTime(0,0);$ranges=[];$ids=[];
        for($day=$local_start;$day<=$local_end;$day=$day->modify("+1 day")){
            $date=$day->format("Y-m-d");$weekday=(int)$day->format("w");
            foreach($rules as $rule){if((int)$rule->weekday!==$weekday||($rule->valid_from&&$date<$rule->valid_from)||($rule->valid_until&&$date>$rule->valid_until)){continue;}
                $end_date=(int)$rule->spans_next_day?$day->modify("+1 day")->format("Y-m-d"):$date;
                try{$rs=$this->time->localToUtc($date,(string)$rule->start_time);$re=$this->time->localToUtc($end_date,(string)$rule->end_time);}catch(\DomainException $e){continue;}
                if(TemporalService::overlaps($rs,$re,$start,$end)){$ranges[]=[$rs,$re];$ids[]=(int)$rule->id;}
            }
        }
        return [$ranges,array_values(array_unique($ids))];
    }

    private function covered(string $start,string $end,array $ranges):bool
    {
        if(!$ranges){return false;}usort($ranges,static fn($a,$b)=>$a[0]<=>$b[0]);$cursor=$start;
        foreach($ranges as [$s,$e]){if($e<=$cursor){continue;}if($s>$cursor){return false;}$cursor=$e>$cursor?$e:$cursor;if($cursor>=$end){return true;}}
        return false;
    }
    private function group(array $rows):array{$out=[];foreach($rows as $row){$out[(int)$row->resource_id][]=$row;}return $out;}
}
