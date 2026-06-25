<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class BookingEventService extends CustomerDataService
{
    private $events;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->events=model("grupo_donato_gestao\\Models\\Gd_booking_events_model");}
    public function append(int $booking_id,string $event_type,?string $from_status,?string $to_status,?string $reason=null,array $payload=[]):int
    {
        if(!in_array($event_type,Constants::BOOKING_EVENT_TYPES,true)){throw new \DomainException("gd_invalid_booking_event");}
        $safe=DataPrivacyService::forAudit($payload);$encoded=$safe?json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE):null;
        return $this->events->add(["unit_id"=>$this->unit_id,"booking_id"=>$booking_id,"event_type"=>$event_type,"from_status"=>$from_status,"to_status"=>$to_status,"reason"=>$reason?mb_substr(strip_tags($reason),0,255):null,"payload"=>$encoded,"actor_type"=>$this->actor_id?"staff":"system","actor_id"=>$this->actor_id?:null,"request_id"=>AuditService::request_id(),"created_at"=>function_exists("get_current_utc_time")?get_current_utc_time():gmdate("Y-m-d H:i:s")]);
    }
}
