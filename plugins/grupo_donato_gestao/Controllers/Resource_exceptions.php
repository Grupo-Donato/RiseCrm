<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ResourceAvailabilityExceptionService;
use grupo_donato_gestao\Services\ResourceService;
use grupo_donato_gestao\Services\TemporalService;

class Resource_exceptions extends Gd_Controller
{
    private int $unit_id;private ResourceAvailabilityExceptionService $service;private ResourceService $resources;private TemporalService $time;
    public function __construct(){parent::__construct();$this->access->require("gd_calendar_view");$this->unit_id=(int)$this->active_unit_id();if(!$this->unit_id){throw new \RuntimeException("No active unit.");}$this->service=new ResourceAvailabilityExceptionService($this->unit_id,$this->user_id(),$this->login_user);$this->resources=new ResourceService($this->unit_id,$this->user_id(),$this->login_user);$this->time=new TemporalService($this->unit_id);}
    public function index($resource_id){$resource=$this->resources->get((int)$resource_id);if(!$resource){return show_404();}return $this->gd_render("exceptions/index",["resource"=>$resource,"can_manage"=>$this->access->can("gd_resource_availability_manage"),"timezone"=>$this->time->timezoneName()]);}
    public function list_data(){try{$result=$this->service->listPage(append_server_side_filtering_commmon_params(["resource_id"=>(int)$this->request->getPost("resource_id"),"exception_type"=>$this->request->getPost("exception_type"),"status"=>$this->request->getPost("status")]));$data=[];foreach($result["data"] as $r){$data[]=$this->row($r);}$result["data"]=$data;echo json_encode($result);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function modal_form(){try{$this->access->require("gd_resource_availability_manage");$id=(int)$this->request->getPost("id");$row=$id?$this->service->get($id):null;if($id&&!$row){return show_404();}$resource_id=$row?(int)$row->resource_id:(int)$this->request->getPost("resource_id");if(!$this->resources->get($resource_id)){return show_404();}return $this->gd_view("exceptions/modal_form",["model_info"=>$row?:new \stdClass(),"resource_id"=>$resource_id,"types"=>Constants::AVAILABILITY_EXCEPTION_TYPES,"statuses"=>Constants::AVAILABILITY_EXCEPTION_STATUSES,"starts_local"=>$row?$this->time->utcToLocalInput($row->starts_at_utc):"","ends_local"=>$row?$this->time->utcToLocalInput($row->ends_at_utc):"","timezone"=>$this->time->timezoneName()]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function save(){try{$this->access->require("gd_resource_availability_manage");$r=$this->service->save($this->input(),(int)$this->request->getPost("id"),(bool)$this->request->getPost("overlap_override"));if(empty($r["saved"])){$this->json_error(app_lang("gd_overlap_confirmation_required"),["overlap_confirmation_required"=>true,"conflicts"=>$r["conflicts"]]);return;}$this->json_success(app_lang("record_saved"),["id"=>$r["id"]]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function delete(){try{$this->access->require("gd_resource_availability_manage");$this->service->delete((int)$this->request->getPost("id"));$this->json_success(app_lang("record_deleted"));}catch(\Throwable $e){$this->gd_fail($e);}}
    private function input():array{return ["resource_id"=>$this->request->getPost("resource_id"),"exception_type"=>$this->request->getPost("exception_type"),"starts_at_local"=>$this->request->getPost("starts_at_local"),"ends_at_local"=>$this->request->getPost("ends_at_local"),"title"=>$this->request->getPost("title"),"reason"=>$this->request->getPost("reason"),"status"=>$this->request->getPost("status"),"metadata"=>$this->request->getPost("metadata")];}
    private function row($r):array{$actions="";if($this->access->can("gd_resource_availability_manage")){$actions=modal_anchor(get_uri("grupo_donato/resources/exceptions/modal_form"),"<i data-feather='edit' class='icon-16'></i>",["data-post-id"=>$r->id,"title"=>app_lang("edit")]).js_anchor("<i data-feather='x' class='icon-16'></i>",["class"=>"delete","data-id"=>$r->id,"data-action-url"=>get_uri("grupo_donato/resources/exceptions/delete"),"data-action"=>"delete","title"=>app_lang("delete")]);}return [app_lang("gd_exception_type_".$r->exception_type),$this->escape($r->title),$this->escape($this->time->utcToLocal($r->starts_at_utc)->format("d/m/Y H:i")),$this->escape($this->time->utcToLocal($r->ends_at_utc)->format("d/m/Y H:i")),app_lang("gd_status_".$r->status),$actions];}
}
