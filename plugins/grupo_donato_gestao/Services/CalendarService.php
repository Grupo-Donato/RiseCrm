<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Projeção read-only de regras, exceções e bloqueios para FullCalendar. */
class CalendarService extends CustomerDataService
{
    private TemporalService $time;private bool $can_view_bookings;private ?string $resource_type;
    public function __construct(int $unit_id,bool $can_view_bookings=false,?string $resource_type=null){parent::__construct($unit_id);if($resource_type!==null&&!Constants::isResourceType($resource_type)){throw new \InvalidArgumentException("Invalid calendar resource type.");}$this->time=new TemporalService($unit_id);$this->can_view_bookings=$can_view_bookings;$this->resource_type=$resource_type;}
    public function timezoneName():string{return $this->time->timezoneName();}

    public function events(string $range_start,string $range_end,array $resource_ids=[],array $types=[],array $booking_statuses=[],int $free_slot_minutes=90):array
    {
        $start=$this->time->parseIsoInstant($range_start);$end=$this->time->parseIsoInstant($range_end);if($end<=$start){throw new \DomainException("gd_invalid_datetime_range");}
        if(($end->getTimestamp()-$start->getTimestamp())>$this->time->calendarMaxDays()*86400){throw new \DomainException("gd_calendar_range_too_large");}
        $start_utc=$start->format("Y-m-d H:i:s");$end_utc=$end->format("Y-m-d H:i:s");$types=array_values(array_intersect($types,["weekly_rule","open_exception","closed_exception","block","booking","free_slot"]));if(!$types){$types=["open_exception","closed_exception","block","booking"];} // sem tipos explícitos NÃO devolvemos disponibilidade padrão (weekly_rule)
        $p=$this->db->getPrefix();$rb=$this->db->table($p."gd_resources")->select("id,code,name,is_active,is_bookable")->where("unit_id",$this->unit_id)->where("deleted",0)->where("is_active",1)->where("is_bookable",1);if($this->resource_type!==null){$rb->where("resource_type",$this->resource_type);}
        $resource_ids=array_values(array_unique(array_filter(array_map("intval",$resource_ids),static fn($id)=>$id>0)));if($resource_ids){$rb->whereIn("id",$resource_ids);}$resources=$rb->orderBy("sort_order")->orderBy("name")->get()->getResult();$ids=array_map(static fn($r)=>(int)$r->id,$resources);if(!$ids){return [];}$names=[];$codes=[];$orders=[];foreach($resources as $order=>$r){$id=(int)$r->id;$codes[$id]=(string)$r->code;$names[$id]=$codes[$id]." — ".(string)$r->name;$orders[$id]=(int)$order;}$events=[];$show_resource_label=count($ids)>1;
        if(in_array("free_slot",$types,true)&&$resource_ids){foreach($ids as $id){$events=array_merge($events,$this->freeSlotEvents($id,$names[$id],$codes[$id],$orders[$id],$show_resource_label,$start,$end,$free_slot_minutes));}}
        if(in_array("weekly_rule",$types,true)){$rules=$this->db->table($p."gd_resource_availability_rules")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->get()->getResult();$local_start=$start->setTimezone(new \DateTimeZone($this->time->timezoneName()))->modify("-1 day")->setTime(0,0);$local_end=$end->setTimezone(new \DateTimeZone($this->time->timezoneName()))->modify("+1 day")->setTime(0,0);
            for($day=$local_start;$day<=$local_end;$day=$day->modify("+1 day")){foreach($rules as $r){$date=$day->format("Y-m-d");if((int)$r->weekday!==(int)$day->format("w")||($r->valid_from&&$date<$r->valid_from)||($r->valid_until&&$date>$r->valid_until)){continue;}$end_date=(int)$r->spans_next_day?$day->modify("+1 day")->format("Y-m-d"):$date;try{$s=$this->time->localToUtc($date,$r->start_time);$e=$this->time->localToUtc($end_date,$r->end_time);}catch(\DomainException $x){continue;}if(!TemporalService::overlaps($s,$e,$start_utc,$end_utc)){continue;}$events[]=$this->event("rule-{$r->id}-$date",$names[(int)$r->resource_id],$s,$e,"weekly_rule",(int)$r->resource_id,"#198754",true);}}
        }
        if(array_intersect(["open_exception","closed_exception"],$types)){$rows=$this->db->table($p."gd_resource_availability_exceptions")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$end_utc)->where("ends_at_utc >",$start_utc)->get()->getResult();foreach($rows as $r){$kind=$r->exception_type==="open"?"open_exception":"closed_exception";if(!in_array($kind,$types,true)){continue;}$events[]=$this->event("exception-{$r->id}",$names[(int)$r->resource_id]." — ".$r->title,$r->starts_at_utc,$r->ends_at_utc,$kind,(int)$r->resource_id,$r->exception_type==="open"?"#20c997":"#dc3545",true);}}
        if(in_array("block",$types,true)){$rows=$this->db->table($p."gd_resource_blocks")->where("unit_id",$this->unit_id)->whereIn("resource_id",$ids)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$end_utc)->where("ends_at_utc >",$start_utc)->get()->getResult();foreach($rows as $r){$events[]=$this->event("block-{$r->id}",$names[(int)$r->resource_id]." — ".$r->title,$r->starts_at_utc,$r->ends_at_utc,"block",(int)$r->resource_id,"#fd7e14",false);}}
        if(in_array("booking",$types,true)&&$this->db->tableExists($p."gd_bookings")){$allowed=Constants::BOOKING_STATUSES;$booking_statuses=array_values(array_intersect($booking_statuses,$allowed));$b=$p."gd_bookings";$br=$p."gd_booking_resources";$q=$this->db->table($br)->select("$b.id,$b.booking_number,$b.title,$b.status,$b.booking_type,$b.starts_at_utc,$b.ends_at_utc,$b.hold_expires_at_utc,$b.series_id,$b.detached_from_series,$br.resource_id,$br.occupancy_starts_at_utc,$br.occupancy_ends_at_utc,$br.buffer_before_minutes,$br.buffer_after_minutes")->join($b,"$b.id=$br.booking_id AND $b.unit_id=$br.unit_id","inner",false)->where("$br.unit_id",$this->unit_id)->whereIn("$br.resource_id",$ids)->where("$br.deleted",0)->where("$b.deleted",0)->where("$b.status !=","cancelled")->where("$b.starts_at_utc <",$end_utc)->where("$b.ends_at_utc >",$start_utc)->groupStart()->where("$b.status !=","hold")->orWhere("$b.hold_expires_at_utc >",gmdate("Y-m-d H:i:s"))->groupEnd();BookingOccupancyFilter::excludeCancelledRentals($this->db,$q,$b);if($booking_statuses){$q->whereIn("$b.status",$booking_statuses);} $colors=["hold"=>"#ffc107","pending_confirmation"=>"#6f42c1","confirmed"=>"#0d6efd","in_progress"=>"#20c997","completed"=>"#198754","cancelled"=>"#6c757d","expired"=>"#adb5bd","no_show"=>"#dc3545"];
            foreach($q->orderBy("$b.starts_at_utc","ASC")->get()->getResult() as $r){$resource_id=(int)$r->resource_id;$is_series=(int)$r->series_id>0&&!(int)$r->detached_from_series;$base_title=$this->can_view_bookings?($is_series?"↻ ":"").$r->booking_number." — ".$r->title:(function_exists("app_lang")?app_lang("gd_calendar_busy"):"Ocupado");$title=($show_resource_label?$codes[$resource_id]." · ":"").$base_title;$events[]=["id"=>"booking-{$r->id}-{$r->resource_id}","title"=>$title,"start"=>$this->time->utcToIsoLocal($r->starts_at_utc),"end"=>$this->time->utcToIsoLocal($r->ends_at_utc),"backgroundColor"=>$colors[$r->status]??"#0d6efd","borderColor"=>$colors[$r->status]??"#0d6efd","classNames"=>$is_series?["gd-series-occurrence"]:[],"extendedProps"=>["event_type"=>"booking","booking_id"=>(int)$r->id,"booking_number"=>$this->can_view_bookings?$r->booking_number:null,"resource_id"=>$resource_id,"resource_ids"=>[$resource_id],"resource_name"=>$names[$resource_id],"resource_code"=>$codes[$resource_id],"resource_order"=>$orders[$resource_id],"booking_type"=>$r->booking_type,"status"=>$r->status,"is_series"=>$is_series,"series_id"=>$this->can_view_bookings&&$is_series?(int)$r->series_id:null,"occupancy_start"=>$this->time->utcToIsoLocal($r->occupancy_starts_at_utc),"occupancy_end"=>$this->time->utcToIsoLocal($r->occupancy_ends_at_utc),"buffer_before_minutes"=>(int)$r->buffer_before_minutes,"buffer_after_minutes"=>(int)$r->buffer_after_minutes]];}}
        // Detached occurrences still belong to a series. Recover that relation
        // for the calendar projection so a point reschedule can be opened from
        // the event itself.
        if ($this->can_view_bookings) {
            $booking_ids = array_values(array_unique(array_filter(array_map(static fn ($event): int => (int) ($event["extendedProps"]["booking_id"] ?? 0), $events))));
            if ($booking_ids) {
                $series_by_booking = [];
                foreach ($this->db->table($p . "gd_bookings")->select("id,series_id")->where("unit_id", $this->unit_id)->whereIn("id", $booking_ids)->where("deleted", 0)->get()->getResult() as $booking) {
                    if ((int) $booking->series_id > 0) { $series_by_booking[(int) $booking->id] = (int) $booking->series_id; }
                }
                if ($series_by_booking) {
                    foreach ($events as &$event) {
                        $booking_id = (int) ($event["extendedProps"]["booking_id"] ?? 0);
                        $series_id = $series_by_booking[$booking_id] ?? 0;
                        if (!$series_id || ($event["extendedProps"]["event_type"] ?? "") !== "booking") { continue; }
                        $event["extendedProps"]["is_series"] = true;
                        $event["extendedProps"]["series_id"] = $series_id;
                        if (!in_array("gd-series-occurrence", $event["classNames"] ?? [], true)) { $event["classNames"][] = "gd-series-occurrence"; }
                        if (!str_starts_with((string) $event["title"], "↻ ") && $this->can_view_bookings) { $event["title"] = "↻ " . $event["title"]; }
                    }
                    unset($event);
                }
            }
        }

        // Vínculo comercial ativo (gd_court_rental_schedule_links) — enriquece o
        // evento com court_rental_id em lote (sem N+1). Somente para quem já vê
        // detalhes de booking, mantendo o mesmo nível de exposição.
        if($this->db->tableExists($p."gd_booking_series_exceptions")){$booking_ids=array_values(array_unique(array_filter(array_map(static fn($event)=>(int)($event["extendedProps"]["booking_id"]??0),$events))));if($booking_ids){$rescheduled=[];foreach($this->db->table($p."gd_booking_series_exceptions")->select("id,booking_id")->where("unit_id",$this->unit_id)->where("exception_type","reschedule")->where("status","active")->whereIn("booking_id",$booking_ids)->get()->getResult() as $exception){$rescheduled[(int)$exception->booking_id]=(int)$exception->id;}if($rescheduled){foreach($events as &$event){$booking_id=(int)($event["extendedProps"]["booking_id"]??0);if(!isset($rescheduled[$booking_id])){continue;}$event["extendedProps"]["is_rescheduled"]=true;$event["extendedProps"]["reschedule_exception_id"]=$rescheduled[$booking_id];$event["classNames"][]="gd-rescheduled-occurrence";if($this->can_view_bookings){$event["title"].=" · ".(function_exists("app_lang")?app_lang("gd_calendar_rescheduled"):"Remanejado");}}unset($event);}}}
        $rental_link_table=$this->resource_type===Constants::BARBECUE_RESOURCE_TYPE?"gd_barbecue_rental_schedule_links":"gd_court_rental_schedule_links";$rental_prop=$this->resource_type===Constants::BARBECUE_RESOURCE_TYPE?"barbecue_rental_id":"court_rental_id";
        if($this->can_view_bookings&&$this->db->tableExists($p.$rental_link_table)){
            $booking_ids=[];$series_ids=[];
            foreach($events as $ev){if(($ev["extendedProps"]["event_type"]??"")!=="booking")continue;$booking_ids[]=(int)($ev["extendedProps"]["booking_id"]??0);$sid=(int)($ev["extendedProps"]["series_id"]??0);if($sid)$series_ids[]=$sid;}
            $booking_ids=array_values(array_unique(array_filter($booking_ids)));$series_ids=array_values(array_unique(array_filter($series_ids)));
            $by_booking=[];$by_series=[];
            if($booking_ids||$series_ids){
                $lq=$this->db->table($p.$rental_link_table)->select("rental_id,booking_id,booking_series_id")->where("unit_id",$this->unit_id)->where("deleted",0)->where("link_kind !=","historical")->groupStart();
                if($booking_ids)$lq->whereIn("booking_id",$booking_ids);
                if($series_ids){if($booking_ids)$lq->orWhereIn("booking_series_id",$series_ids);else $lq->whereIn("booking_series_id",$series_ids);}
                foreach($lq->groupEnd()->get()->getResult() as $lr){if($lr->booking_id)$by_booking[(int)$lr->booking_id]=(int)$lr->rental_id;if($lr->booking_series_id)$by_series[(int)$lr->booking_series_id]=(int)$lr->rental_id;}
            }
            if($by_booking||$by_series){foreach($events as &$event){if(($event["extendedProps"]["event_type"]??"")!=="booking")continue;$bid=(int)($event["extendedProps"]["booking_id"]??0);$sid=(int)($event["extendedProps"]["series_id"]??0);$rid=$by_booking[$bid]??($sid?($by_series[$sid]??0):0);if($rid)$event["extendedProps"][$rental_prop]=$rid;}unset($event);}
        }
        // School/personal uses the canonical booking projection. Enrich it with
        // its source instead of creating duplicate calendar events.
        if($this->db->tableExists($p."gd_classes")){foreach($events as &$event){if(($event["extendedProps"]["event_type"]??"")!=="booking")continue;$booking_id=(int)($event["extendedProps"]["booking_id"]??0);$series_id=(int)($event["extendedProps"]["series_id"]??0);$q=$this->db->table($p."gd_classes")->select("id,name")->where("unit_id",$this->unit_id)->where("deleted",0)->groupStart()->where("booking_id",$booking_id);if($series_id)$q->orWhere("booking_series_id",$series_id);$school=$q->groupEnd()->get(1)->getRow();if($school){$event["extendedProps"]["source_type"]="school_class";$event["extendedProps"]["school_class_id"]=(int)$school->id;if($this->can_view_bookings)$event["title"]=($show_resource_label?(string)($event["extendedProps"]["resource_code"]??"")." · ":"").(string)$school->name;}}unset($event);}
        return $events;
    }

    /** Projeta inícios livres de 30 em 30 minutos para cada quadra selecionada. */
    private function freeSlotEvents(int $resource_id,string $resource_name,string $resource_code,int $resource_order,bool $show_resource_label,\DateTimeImmutable $range_start,\DateTimeImmutable $range_end,int $duration_minutes):array
    {
        $duration_minutes=max(1,min(Constants::BOOKING_MAX_DURATION_MINUTES,(int)$duration_minutes));
        $p=$this->db->getPrefix();$range_start_utc=$range_start->format("Y-m-d H:i:s");$range_end_utc=$range_end->format("Y-m-d H:i:s");$query_end_utc=$range_end->modify("+$duration_minutes minutes")->format("Y-m-d H:i:s");
        $blocks=$this->db->table($p."gd_resource_blocks")->where("unit_id",$this->unit_id)->where("resource_id",$resource_id)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$query_end_utc)->where("ends_at_utc >",$range_start_utc)->get()->getResult();
        $exceptions=$this->db->table($p."gd_resource_availability_exceptions")->where("unit_id",$this->unit_id)->where("resource_id",$resource_id)->where("status","active")->where("deleted",0)->where("starts_at_utc <",$query_end_utc)->where("ends_at_utc >",$range_start_utc)->get()->getResult();
        $rules=$this->db->table($p."gd_resource_availability_rules")->where("unit_id",$this->unit_id)->where("resource_id",$resource_id)->where("status","active")->where("deleted",0)->get()->getResult();
        $weekly_ranges=$this->freeSlotWeeklyRanges($rules,$range_start_utc,$query_end_utc);
        $bookings=[];
        if($this->db->tableExists($p."gd_bookings")){
            $b=$p."gd_bookings";$br=$p."gd_booking_resources";
            $booking_query=$this->db->table($br)->select("$br.occupancy_starts_at_utc,$br.occupancy_ends_at_utc")
                ->join($b,"$b.id=$br.booking_id AND $b.unit_id=$br.unit_id","inner",false)
                ->where("$br.unit_id",$this->unit_id)->where("$br.resource_id",$resource_id)->where("$br.deleted",0)->where("$b.deleted",0)
                ->whereIn("$b.status",Constants::BOOKING_BLOCKING_STATUSES)->where("$br.occupancy_starts_at_utc <",$query_end_utc)->where("$br.occupancy_ends_at_utc >",$range_start_utc)
                ->groupStart()->where("$b.status !=","hold")->orWhere("$b.hold_expires_at_utc >",gmdate("Y-m-d H:i:s"))->groupEnd();
            BookingOccupancyFilter::excludeCancelledRentals($this->db,$booking_query,$b);
            $bookings=$booking_query->get()->getResult();
        }
        $closed=array_values(array_filter($exceptions,static fn($row)=>(string)$row->exception_type==="closed"));
        $open_ranges=[];foreach($exceptions as $row){if((string)$row->exception_type==="open"){$open_ranges[]=[(string)$row->starts_at_utc,(string)$row->ends_at_utc];}}
        $timezone=new \DateTimeZone($this->time->timezoneName());$local_start=$range_start->setTimezone($timezone);$local_end=$range_end->setTimezone($timezone);
        $minute=(int)$local_start->format("i");$cursor=$local_start->setTime((int)$local_start->format("H"),$minute<30?0:30,0);if($cursor<$local_start){$cursor=$cursor->modify("+30 minutes");}
        $events=[];$now_utc=gmdate("Y-m-d H:i:s");$duration_hours=intdiv($duration_minutes,60);$duration_remainder=$duration_minutes%60;$duration_label=$duration_hours>0?$duration_hours."h".($duration_remainder?str_pad((string)$duration_remainder,2,"0",STR_PAD_LEFT):""):$duration_minutes."min";$availability=new AvailabilityService($this->unit_id);$canonical_slots=[];
        for($day=$local_start->setTime(0,0,0);$day<=$local_end;$day=$day->modify("+1 day")){foreach($availability->findAvailableSlots($day->format("Y-m-d"),"00:00","23:59",$duration_minutes,[$resource_id],0,30) as $slot){$slot_start_utc=(string)($slot["starts_at_utc"]??"");if($slot_start_utc>=$range_start_utc&&$slot_start_utc<$range_end_utc&&$slot_start_utc>$now_utc){$canonical_slots[$slot_start_utc]=true;}}}
        for(;$cursor<$local_end;$cursor=$cursor->modify("+30 minutes")){
            try{$slot_start=$this->time->localToUtc($cursor->format("Y-m-d"),$cursor->format("H:i"));}catch(\DomainException $e){continue;}
            if($slot_start<$range_start_utc||$slot_start>=$range_end_utc||$slot_start<=$now_utc){continue;}
            $slot_end=$this->time->parseUtc($slot_start)->modify("+$duration_minutes minutes")->format("Y-m-d H:i:s");
            if(empty($canonical_slots[$slot_start])){continue;}
            if($this->freeSlotOverlaps($blocks,$slot_start,$slot_end)||$this->freeSlotOverlaps($closed,$slot_start,$slot_end)||$this->freeSlotOverlaps($bookings,$slot_start,$slot_end)){continue;}
            $physically_open=$this->freeSlotCovered($slot_start,$slot_end,$open_ranges)||(!$rules)||$this->freeSlotCovered($slot_start,$slot_end,$weekly_ranges);
            if(!$physically_open){continue;}
            $display_end=$this->time->parseUtc($slot_start)->modify("+30 minutes")->format("Y-m-d H:i:s");
            $free_title=(function_exists("app_lang")?app_lang("gd_calendar_free_slot"):"Livre")." · ".$duration_label;$events[]=["id"=>"free-$resource_id-".str_replace([" ",":"],"",$slot_start),"title"=>($show_resource_label?$resource_code." · ":"").$free_title,"start"=>$this->time->utcToIsoLocal($slot_start),"end"=>$this->time->utcToIsoLocal($display_end),"backgroundColor"=>"#198754","borderColor"=>"#198754","classNames"=>["gd-free-slot"],"extendedProps"=>["event_type"=>"free_slot","resource_id"=>$resource_id,"resource_name"=>$resource_name,"resource_code"=>$resource_code,"resource_order"=>$resource_order,"local_date"=>$cursor->format("Y-m-d"),"local_start_time"=>$cursor->format("H:i"),"duration_minutes"=>$duration_minutes,"available_until"=>$this->time->utcToIsoLocal($slot_end)]];
        }
        return $events;
    }

    private function freeSlotWeeklyRanges(array $rules,string $start_utc,string $end_utc):array
    {
        if(!$rules){return [];}$start=$this->time->utcToLocal($start_utc)->modify("-1 day")->setTime(0,0);$end=$this->time->utcToLocal($end_utc)->modify("+1 day")->setTime(0,0);$ranges=[];
        for($day=$start;$day<=$end;$day=$day->modify("+1 day")){$date=$day->format("Y-m-d");foreach($rules as $rule){if((int)$rule->weekday!==(int)$day->format("w")||($rule->valid_from&&$date<$rule->valid_from)||($rule->valid_until&&$date>$rule->valid_until)){continue;}$end_date=(int)$rule->spans_next_day?$day->modify("+1 day")->format("Y-m-d"):$date;try{$ranges[]=[$this->time->localToUtc($date,(string)$rule->start_time),$this->time->localToUtc($end_date,(string)$rule->end_time)];}catch(\DomainException $e){continue;}}}
        return $ranges;
    }

    private function freeSlotOverlaps(array $rows,string $start,string $end):bool
    {
        foreach($rows as $row){$row_start=(string)($row->occupancy_starts_at_utc??$row->starts_at_utc??"");$row_end=(string)($row->occupancy_ends_at_utc??$row->ends_at_utc??"");if($row_start!==""&&TemporalService::overlaps($row_start,$row_end,$start,$end)){return true;}}return false;
    }

    private function freeSlotCovered(string $start,string $end,array $ranges):bool
    {
        if(!$ranges){return false;}usort($ranges,static fn($a,$b)=>$a[0]<=>$b[0]);$cursor=$start;foreach($ranges as [$range_start,$range_end]){if($range_end<=$cursor){continue;}if($range_start>$cursor){return false;}$cursor=$range_end>$cursor?$range_end:$cursor;if($cursor>=$end){return true;}}return false;
    }
    public function resources(?string $resource_type=null):array{$resource_type=$resource_type??$this->resource_type;$t=$this->db->prefixTable("gd_resources");$b=$this->db->table($t)->select("id,code,name,resource_type")->where("unit_id",$this->unit_id)->where("deleted",0)->where("is_active",1)->where("is_bookable",1);if($resource_type!==null&&$resource_type!==""){$b->where("resource_type",$resource_type);}return $b->orderBy("sort_order")->orderBy("name")->get()->getResultArray();}
    private function event(string $id,string $title,string $start,string $end,string $type,int $resource_id,string $color,bool $background):array{return ["id"=>$id,"title"=>$title,"start"=>$this->time->utcToIsoLocal($start),"end"=>$this->time->utcToIsoLocal($end),"display"=>$background?"background":"auto","backgroundColor"=>$color,"borderColor"=>$color,"extendedProps"=>["event_type"=>$type,"resource_id"=>$resource_id]];}
}
