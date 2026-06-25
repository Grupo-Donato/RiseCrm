<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class BookingConflictService extends CustomerDataService
{
    public function findConflicts(int $unit_id,array $resource_windows,?int $exclude_booking_id=null):array
    {
        if($unit_id!==$this->unit_id){throw new \DomainException("gd_invalid_unit");}$windows=[];
        foreach($resource_windows as $w){$rid=(int)($w["resource_id"]??0);$start=(string)($w["occupancy_starts_at_utc"]??"");$end=(string)($w["occupancy_ends_at_utc"]??"");if($rid<=0||$start===""||$end===""||$end<=$start){throw new \DomainException("gd_invalid_booking_resources");}$windows[]=["resource_id"=>$rid,"start"=>$start,"end"=>$end];}
        if(!$windows){return ["has_conflict"=>false,"conflicts"=>[]];}
        $br=$this->db->prefixTable("gd_booking_resources");$b=$this->db->prefixTable("gd_bookings");$q=$this->db->table($br)->select("$b.id AS booking_id,$b.booking_number,$br.resource_id,$b.status,$br.occupancy_starts_at_utc,$br.occupancy_ends_at_utc")
            ->join($b,"$b.id=$br.booking_id AND $b.unit_id=$br.unit_id","inner",false)->where("$br.unit_id",$unit_id)->where("$br.deleted",0)->where("$b.deleted",0)->whereIn("$b.status",Constants::BOOKING_BLOCKING_STATUSES)
            ->groupStart()->where("$b.status !=","hold")->orGroupStart()->where("$b.status","hold")->where("$b.hold_expires_at_utc >",gmdate("Y-m-d H:i:s"))->groupEnd()->groupEnd();
        if($exclude_booking_id){$q->where("$b.id !=",$exclude_booking_id);}$q->groupStart();foreach($windows as $i=>$w){$method=$i===0?"groupStart":"orGroupStart";$q->$method()->where("$br.resource_id",$w["resource_id"])->where("$br.occupancy_starts_at_utc <",$w["end"])->where("$br.occupancy_ends_at_utc >",$w["start"])->groupEnd();}$q->groupEnd();
        $rows=$q->orderBy("$br.resource_id","ASC")->orderBy("$br.occupancy_starts_at_utc","ASC")->get()->getResultArray();return ["has_conflict"=>!empty($rows),"conflicts"=>$rows];
    }
}
