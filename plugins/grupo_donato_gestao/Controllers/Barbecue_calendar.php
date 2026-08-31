<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\CalendarService;

class Barbecue_calendar extends Gd_Controller
{
    private CalendarService $service;
    public function __construct(){parent::__construct();$this->access->require("gd_barbecue_rentals_view");$unit_id=(int)$this->active_unit_id();if(!$unit_id){throw new \RuntimeException("No active unit.");}$this->service=new CalendarService($unit_id,$this->access->can("gd_bookings_view"),Constants::BARBECUE_RESOURCE_TYPE);}
    public function index(){return $this->gd_render("calendar/barbecue",[
        "resources"=>$this->service->resources(Constants::BARBECUE_RESOURCE_TYPE),
        "timezone"=>$this->service->timezoneName(),
        "can_availability_manage"=>$this->access->can("gd_resource_availability_manage"),
        "can_blocks_manage"=>$this->access->can("gd_resource_blocks_manage"),
        "can_bookings_view"=>$this->access->can("gd_bookings_view"),
        "can_bookings_manage"=>$this->access->can("gd_bookings_manage"),
        "can_series_view"=>$this->access->can("gd_booking_series_view"),
        "can_series_manage"=>$this->access->can("gd_booking_series_manage"),
        "can_barbecue_rentals_view"=>$this->access->can("gd_barbecue_rentals_view"),
        "can_barbecue_rentals_manage"=>$this->access->can("gd_barbecue_rentals_manage"),
        "can_finance"=>$this->access->can("gd_rental_payments_view"),
        "booking_statuses"=>Constants::BOOKING_STATUSES,
    ]);}
    public function events(){try{$allowed=array_map(static fn($r)=>(int)$r["id"],$this->service->resources(Constants::BARBECUE_RESOURCE_TYPE));$requested=$this->csv((string)$this->request->getGet("resources"));$resources=$requested?array_values(array_intersect($requested,$allowed)):$allowed;$types=array_filter(explode(",",(string)$this->request->getGet("types")));$statuses=array_filter(explode(",",(string)$this->request->getGet("statuses")));$duration=$this->parseDuration((string)$this->request->getGet("duration_minutes")) ?: 90;return $this->response->setJSON($this->service->events((string)$this->request->getGet("start"),(string)$this->request->getGet("end"),$resources,$types,$statuses,$duration));}catch(\Throwable $e){$key=$e->getMessage();return $this->response->setStatusCode(400)->setJSON(["error"=>str_starts_with($key,"gd_")?app_lang($key):app_lang("error_occurred")]);}}
    private function parseDuration(string $value): int
    {
        $raw = mb_strtolower(trim($value));
        if ($raw === "") { return 0; }
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
        $minutes = 0;
        if (preg_match('/^\d+$/', $raw)) {
            $minutes = (int) $raw;
        } elseif (preg_match('/^(\d+)\s*h\s*(?:(\d{1,2})\s*(?:m|min|minutos?)?)?$/u', $raw, $match)) {
            $extra = isset($match[2]) ? (int) $match[2] : 0;
            if ($extra < 60) { $minutes = ((int) $match[1] * 60) + $extra; }
        } elseif (preg_match('/^(\d+)\s*(?:m|min|minutos?)$/u', $raw, $match)) {
            $minutes = (int) $match[1];
        } elseif (preg_match('/^(\d+):(\d{2})$/', $raw, $match)) {
            $extra = (int) $match[2];
            if ($extra < 60) { $minutes = ((int) $match[1] * 60) + $extra; }
        }
        return $minutes > 0 && $minutes <= Constants::BOOKING_MAX_DURATION_MINUTES ? $minutes : 0;
    }
    private function csv(string $value):array{return array_values(array_filter(array_map("intval",explode(",",$value)),static fn($v)=>$v>0));}
}
