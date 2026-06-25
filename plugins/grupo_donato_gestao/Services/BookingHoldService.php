<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

class BookingHoldService extends CustomerDataService
{
    private $bookings;private BookingEventService $events;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->bookings=model("grupo_donato_gestao\\Models\\Gd_bookings_model");$this->events=new BookingEventService($unit_id,$actor_id,$login_user);}
    public function isActive(object $booking):bool{return $booking->status==="hold"&&!empty($booking->hold_expires_at_utc)&&(string)$booking->hold_expires_at_utc>gmdate("Y-m-d H:i:s");}
    public function expireBatch(int $limit=100):array
    {
        $limit=max(1,min(500,$limit));$table=$this->db->prefixTable("gd_bookings");$rows=$this->db->table($table)->select("id,booking_number,status,lock_version,hold_expires_at_utc,timezone")->where("unit_id",$this->unit_id)->where("status","hold")->where("deleted",0)->where("hold_expires_at_utc <=",gmdate("Y-m-d H:i:s"))->orderBy("hold_expires_at_utc","ASC")->limit($limit)->get()->getResult();$expired=[];
        foreach($rows as $row){if($this->bookings->optimistic_update((int)$row->id,$this->unit_id,(int)$row->lock_version,["status"=>"expired","updated_by"=>$this->actor_id?:null])){$this->events->append((int)$row->id,"expired","hold","expired",null,["booking_number"=>$row->booking_number,"hold_expires_at_utc"=>$row->hold_expires_at_utc]);$this->audit_change("booking_expired","booking",(int)$row->id,["booking_number"=>$row->booking_number,"status"=>"hold"],["booking_number"=>$row->booking_number,"status"=>"expired"],["timezone"=>$row->timezone]);$expired[]=(int)$row->id;}}
        return ["expired"=>count($expired),"booking_ids"=>$expired,"limit"=>$limit];
    }
}
