<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\BookingService;
use grupo_donato_gestao\Services\TemporalService;

class Bookings extends Gd_Controller
{
    private int $unit_id;private BookingService $service;private TemporalService $time;
    public function __construct(){parent::__construct();$this->access->require("gd_bookings_view");$this->unit_id=(int)$this->active_unit_id();if(!$this->unit_id){throw new \RuntimeException("No active unit.");}$this->service=new BookingService($this->unit_id,$this->user_id(),$this->login_user);$this->time=new TemporalService($this->unit_id);}
    public function index(){
        if($this->access->can("gd_court_rentals_view")){return redirect()->to(get_uri("grupo_donato/court-rentals")."?tab=bookings");}
        return $this->gd_render("bookings/index",[
            "can_manage"=>$this->access->can("gd_bookings_manage"),
            "can_calendar"=>$this->access->can("gd_calendar_view"),
            "can_court_rentals"=>false,
            "can_bookings"=>true,
            "can_series"=>$this->access->can("gd_booking_series_view"),
            "can_finance"=>$this->access->can("gd_finance_view"),
            "types"=>Constants::BOOKING_TYPES,
            "statuses"=>Constants::BOOKING_STATUSES,
            "resources"=>$this->service->bookableResources()
        ]);
    }
    public function list_data(){try{$r=$this->service->listPage(append_server_side_filtering_commmon_params(["resource_id"=>$this->request->getPost("resource_id"),"booking_type"=>$this->request->getPost("booking_type"),"status"=>$this->request->getPost("status"),"customer_account_id"=>$this->request->getPost("customer_account_id"),"date_from"=>$this->request->getPost("date_from"),"date_to"=>$this->request->getPost("date_to")]));$rows=[];foreach($r["data"] as $x){$rows[]=$this->row($x);}$r["data"]=$rows;return $this->response->setJSON($r);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function modal(){try{$this->access->require("gd_bookings_manage");$id=(int)($this->request->getGet("id")?:$this->request->getPost("id"));$row=$id?$this->service->get($id):null;if($id&&!$row){return show_404();}$accounts=$this->service->customerOptions("",50);$contacts=$row&&$row->customer_account_id?$this->service->contactOptions((int)$row->customer_account_id):[];return $this->gd_view("bookings/modal_form",["model_info"=>$row?:new \stdClass(),"resources"=>$this->service->bookableResources(),"accounts"=>$accounts,"contacts"=>$contacts,"types"=>Constants::BOOKING_TYPES,"initial_statuses"=>$this->access->can("gd_booking_status_manage")?Constants::BOOKING_INITIAL_STATUSES:["hold","pending_confirmation"],"timezone"=>$this->time->timezoneName(),"starts_local"=>$row?$this->time->utcToLocalInput($row->starts_at_utc):"","ends_local"=>$row?$this->time->utcToLocalInput($row->ends_at_utc):"","hold_local"=>$row&&$row->hold_expires_at_utc?$this->time->utcToLocalInput($row->hold_expires_at_utc):"","series_scope"=>(string)$this->request->getPost("series_scope")]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function save(){try{$this->access->require("gd_bookings_manage");$r=$this->service->save($this->input(),(int)$this->request->getPost("id"),$this->access->can("gd_booking_status_manage"));$this->json_success(app_lang("record_saved"),$r);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function view($id){$row=$this->service->get((int)$id);if(!$row){return show_404();}return $this->gd_render("bookings/view",["booking"=>$row,"timezone"=>$this->time->timezoneName(),"can_manage"=>$this->access->can("gd_bookings_manage"),"can_status"=>$this->access->can("gd_booking_status_manage"),"can_audit"=>$this->access->can("gd_audit_view")]);}
    public function delete(){try{$this->access->require("gd_bookings_manage");$this->service->delete((int)$this->request->getPost("id"));$this->json_success(app_lang("record_deleted"));}catch(\Throwable $e){$this->gd_fail($e);}}
    public function check_availability(){try{$this->access->require("gd_bookings_manage");$this->json_success("",["data"=>$this->service->checkAvailability($this->input(),(int)$this->request->getPost("id"))]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function customer_options(){try{$this->access->require("gd_bookings_manage");$rows=$this->service->customerOptions((string)$this->request->getPost("q"));return $this->response->setJSON(["results"=>array_map(static fn($r)=>["id"=>(int)$r["id"],"text"=>$r["display_name"]." (".app_lang("gd_account_type_".$r["account_type"]).")"],$rows)]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function contact_options(){try{$this->access->require("gd_bookings_manage");$rows=$this->service->contactOptions((int)$this->request->getPost("customer_account_id"),(string)$this->request->getPost("q"));return $this->response->setJSON(["results"=>array_map(static fn($r)=>["id"=>(int)$r["id"],"text"=>$r["full_name"]],$rows)]);}catch(\Throwable $e){$this->gd_fail($e);}}
    private function input():array{return ["booking_type"=>$this->request->getPost("booking_type"),"title"=>$this->request->getPost("title"),"customer_account_id"=>$this->request->getPost("customer_account_id"),"contact_person_id"=>$this->request->getPost("contact_person_id"),"starts_at_local"=>$this->request->getPost("starts_at_local"),"ends_at_local"=>$this->request->getPost("ends_at_local"),"status"=>$this->request->getPost("status"),"hold_expires_at_local"=>$this->request->getPost("hold_expires_at_local"),"resources"=>$this->normalizedResources($this->request->getPost("resources")),"notes"=>$this->request->getPost("notes"),"metadata"=>$this->request->getPost("metadata"),"lock_version"=>$this->request->getPost("lock_version")];}
    private function normalizedResources($raw):array{$out=[];if(!is_array($raw)){return [];}foreach($raw as $rid=>$v){if(!is_array($v)||empty($v["selected"])){continue;}$out[]=["resource_id"=>(int)$rid,"buffer_before_minutes"=>$v["buffer_before_minutes"]??0,"buffer_after_minutes"=>$v["buffer_after_minutes"]??0];}return $out;}
    private function row($x):array{$actions=anchor(get_uri("grupo_donato/bookings/view/".$x->id),"<i data-feather='eye' class='icon-16'></i>",["title"=>app_lang("gd_view_details")]);if($this->access->can("gd_bookings_manage")&&in_array($x->status,Constants::BOOKING_EDITABLE_STATUSES,true)&&$x->starts_at_utc>gmdate("Y-m-d H:i:s")){$actions.=modal_anchor(get_uri("grupo_donato/bookings/modal"),"<i data-feather='edit' class='icon-16'></i>",["data-post-id"=>$x->id,"title"=>app_lang("edit")]);}$hold=$x->hold_expires_at_utc?$this->time->utcToLocal($x->hold_expires_at_utc)->format("d/m/Y H:i"):"-";if($x->status==="hold"&&$x->hold_expires_at_utc<=gmdate("Y-m-d H:i:s")){$hold.=" <span class='badge bg-secondary'>".app_lang("gd_expired")."</span>";}return [$this->escape($x->booking_number),$this->escape($x->title),app_lang("gd_booking_type_".$x->booking_type),$this->escape($x->customer_name??"-"),$this->escape($x->resource_names??""),$this->escape($this->time->utcToLocal($x->starts_at_utc)->format("d/m/Y H:i")),$this->escape($this->time->utcToLocal($x->ends_at_utc)->format("d/m/Y H:i")),app_lang("gd_booking_status_".$x->status),$hold,$x->updated_at?format_to_datetime($x->updated_at):"",$actions];}
}
