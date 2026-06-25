<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Services\CalendarService;

class Calendar extends Gd_Controller
{
    private CalendarService $service;
    public function __construct(){parent::__construct();$this->access->require("gd_calendar_view");$unit_id=(int)$this->active_unit_id();if(!$unit_id){throw new \RuntimeException("No active unit.");}$this->service=new CalendarService($unit_id,$this->access->can("gd_bookings_view"));}
    public function index(){return $this->gd_render("calendar/index",["resources"=>$this->service->resources(),"timezone"=>$this->service->timezoneName(),"can_availability_manage"=>$this->access->can("gd_resource_availability_manage"),"can_blocks_manage"=>$this->access->can("gd_resource_blocks_manage"),"can_bookings_view"=>$this->access->can("gd_bookings_view"),"can_bookings_manage"=>$this->access->can("gd_bookings_manage"),"can_series_view"=>$this->access->can("gd_booking_series_view"),"can_series_manage"=>$this->access->can("gd_booking_series_manage")]);}
    public function events(){try{$resources=$this->csv((string)$this->request->getGet("resources"));$types=array_filter(explode(",",(string)$this->request->getGet("types")));$statuses=array_filter(explode(",",(string)$this->request->getGet("statuses")));return $this->response->setJSON($this->service->events((string)$this->request->getGet("start"),(string)$this->request->getGet("end"),$resources,$types,$statuses));}catch(\Throwable $e){$key=$e->getMessage();return $this->response->setStatusCode(400)->setJSON(["error"=>str_starts_with($key,"gd_")?app_lang($key):app_lang("error_occurred")]);}}
    private function csv(string $value):array{return array_values(array_filter(array_map("intval",explode(",",$value)),static fn($v)=>$v>0));}
}
