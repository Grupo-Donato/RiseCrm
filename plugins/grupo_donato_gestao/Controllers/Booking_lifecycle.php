<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Services\BookingLifecycleService;

class Booking_lifecycle extends Gd_Controller
{
    private BookingLifecycleService $service;
    public function __construct(){parent::__construct();$this->access->require("gd_booking_status_manage");$unit=(int)$this->active_unit_id();if(!$unit){throw new \RuntimeException("No active unit.");}$this->service=new BookingLifecycleService($unit,$this->user_id(),$this->login_user);}
    public function confirm($id){$this->run(fn()=>$this->service->confirm((int)$id));}
    public function start($id){$this->run(fn()=>$this->service->start((int)$id));}
    public function complete($id){$this->run(fn()=>$this->service->complete((int)$id));}
    public function cancel($id){$reason=(string)$this->request->getPost("reason");$this->run(fn()=>$this->service->cancel((int)$id,$reason));}
    public function no_show($id){$reason=(string)$this->request->getPost("reason");$this->run(fn()=>$this->service->noShow((int)$id,$reason));}
    private function run(callable $fn):void{try{$row=$fn();$this->json_success(app_lang("record_saved"),["id"=>(int)$row->id,"status"=>$row->status]);}catch(\Throwable $e){$this->gd_fail($e);}}
}
