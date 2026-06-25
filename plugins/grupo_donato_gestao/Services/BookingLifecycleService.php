<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class BookingLifecycleService extends CustomerDataService
{
    private $bookings;private $resources;private BookingEventService $events;private BookingService $booking_service;
    private const TRANSITIONS=["hold"=>["confirmed","cancelled","expired"],"pending_confirmation"=>["confirmed","cancelled"],"confirmed"=>["in_progress","cancelled","no_show"],"in_progress"=>["completed","cancelled"]];
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->bookings=model("grupo_donato_gestao\\Models\\Gd_bookings_model");$this->resources=model("grupo_donato_gestao\\Models\\Gd_booking_resources_model");$this->events=new BookingEventService($unit_id,$actor_id,$login_user);$this->booking_service=new BookingService($unit_id,$actor_id,$login_user);}
    public function confirm(int $id):object{return $this->transition($id,"confirmed");}
    public function start(int $id):object{return $this->transition($id,"in_progress");}
    public function complete(int $id):object{return $this->transition($id,"completed");}
    public function cancel(int $id,string $reason):object{$reason=trim(strip_tags($reason));if($reason===""){throw new \DomainException("gd_cancellation_reason_required");}return $this->transition($id,"cancelled",$reason);}
    public function noShow(int $id,string $reason):object{$reason=trim(strip_tags($reason));if($reason===""){throw new \DomainException("gd_no_show_reason_required");}return $this->transition($id,"no_show",$reason);}
    public function expire(int $id):object{return $this->transition($id,"expired",null,true);}
    public function allowed(string $from,string $to):bool{return in_array($to,self::TRANSITIONS[$from]??[],true);}
    private function transition(int $id,string $to,?string $reason=null,bool $system_expiry=false):object
    {
        $row=$this->bookings->get_scoped($id,$this->unit_id);if(!$row){throw new \DomainException("gd_booking_not_found");}$resource_rows=$this->resources->for_booking($id,$this->unit_id);$locks=new BookingResourceLockService();$in_tx=false;
        try{$locks->acquire($this->unit_id,array_map(static fn($r)=>(int)$r->resource_id,$resource_rows));if($this->db->transBegin()===false){throw new \RuntimeException("booking lifecycle transaction");}$in_tx=true;$row=$this->bookings->get_scoped($id,$this->unit_id);if(!$row||!$this->allowed((string)$row->status,$to)){throw new \DomainException("gd_invalid_booking_transition");}$now=gmdate("Y-m-d H:i:s");
            if($to==="confirmed"){if($row->status==="hold"&&((string)$row->hold_expires_at_utc===""||(string)$row->hold_expires_at_utc<=$now)){throw new \DomainException("gd_hold_expired");}$this->booking_service->revalidateStored($row);}
            if($to==="no_show"&&(string)$row->starts_at_utc>$now){throw new \DomainException("gd_no_show_too_early");}if($to==="expired"&&(!$system_expiry||$row->status!=="hold"||(string)$row->hold_expires_at_utc>$now)){throw new \DomainException("gd_invalid_booking_transition");}
            $data=["status"=>$to,"updated_by"=>$this->actor_id?:null];if($to==="confirmed"){$data+=["confirmed_at"=>$now,"confirmed_by"=>$this->actor_id?:null,"hold_expires_at_utc"=>null];}elseif($to==="in_progress"){$data+=["started_at"=>$now,"started_by"=>$this->actor_id?:null];}elseif($to==="completed"){$data+=["completed_at"=>$now,"completed_by"=>$this->actor_id?:null];}elseif($to==="cancelled"){$data+=["cancelled_at"=>$now,"cancelled_by"=>$this->actor_id?:null,"cancellation_reason"=>mb_substr((string)$reason,0,255),"hold_expires_at_utc"=>null];}elseif($to==="no_show"){$data+=["cancellation_reason"=>mb_substr((string)$reason,0,255)];}
            if(!$this->bookings->optimistic_update($id,$this->unit_id,(int)$row->lock_version,$data)){throw new \DomainException("gd_booking_edit_conflict");}$events=["confirmed"=>"confirmed","in_progress"=>"started","completed"=>"completed","cancelled"=>"cancelled","expired"=>"expired","no_show"=>"no_show"];$this->events->append($id,$events[$to],(string)$row->status,$to,$reason,["booking_number"=>$row->booking_number]);$this->audit_change("booking_".$events[$to],"booking",$id,["booking_number"=>$row->booking_number,"status"=>$row->status],["booking_number"=>$row->booking_number,"status"=>$to],["reason"=>$reason,"timezone"=>$row->timezone]);
            if($this->db->transCommit()===false){throw new \RuntimeException("booking lifecycle commit");}$in_tx=false;return $this->bookings->get_scoped($id,$this->unit_id);
        }catch(\Throwable $e){if($in_tx){$this->db->transRollback();}throw $e;}finally{$locks->release();}
    }
}
