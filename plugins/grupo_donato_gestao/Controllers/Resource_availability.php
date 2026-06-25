<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ResourceAvailabilityRuleService;
use grupo_donato_gestao\Services\ResourceService;

class Resource_availability extends Gd_Controller
{
    private int $unit_id; private ResourceAvailabilityRuleService $service; private ResourceService $resources;
    public function __construct(){parent::__construct();$this->access->require("gd_calendar_view");$this->unit_id=(int)$this->active_unit_id();if(!$this->unit_id){throw new \RuntimeException("No active unit.");}$this->service=new ResourceAvailabilityRuleService($this->unit_id,$this->user_id(),$this->login_user);$this->resources=new ResourceService($this->unit_id,$this->user_id(),$this->login_user);}
    public function index($resource_id){$resource=$this->resources->get((int)$resource_id);if(!$resource){return show_404();}return $this->gd_render("availability/index",["resource"=>$resource,"can_manage"=>$this->access->can("gd_resource_availability_manage")]);}
    public function list_data(){try{$result=$this->service->listPage(append_server_side_filtering_commmon_params(["resource_id"=>(int)$this->request->getPost("resource_id"),"weekday"=>$this->request->getPost("weekday"),"status"=>$this->request->getPost("status")]));$result["data"]=array_map(fn($r)=>$this->row($r),$result["data"]);echo json_encode($result);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function modal_form(){try{$this->access->require("gd_resource_availability_manage");$id=(int)$this->request->getPost("id");$row=$id?$this->service->get($id):null;if($id&&!$row){return show_404();}$resource_id=$row?(int)$row->resource_id:(int)$this->request->getPost("resource_id");if(!$this->resources->get($resource_id)){return show_404();}return $this->gd_view("availability/modal_form",["model_info"=>$row?:new \stdClass(),"resource_id"=>$resource_id,"weekdays"=>$this->weekdays(),"statuses"=>Constants::AVAILABILITY_RULE_STATUSES]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function save(){try{$this->access->require("gd_resource_availability_manage");$r=$this->service->save(["resource_id"=>$this->request->getPost("resource_id"),"weekday"=>$this->request->getPost("weekday"),"start_time"=>$this->request->getPost("start_time"),"end_time"=>$this->request->getPost("end_time"),"spans_next_day"=>$this->request->getPost("spans_next_day"),"valid_from"=>$this->request->getPost("valid_from"),"valid_until"=>$this->request->getPost("valid_until"),"status"=>$this->request->getPost("status"),"sort_order"=>$this->request->getPost("sort_order"),"notes"=>$this->request->getPost("notes")],(int)$this->request->getPost("id"));$this->json_success(app_lang("record_saved"),["id"=>$r["id"]]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function delete(){try{$this->access->require("gd_resource_availability_manage");$this->service->delete((int)$this->request->getPost("id"));$this->json_success(app_lang("record_deleted"));}catch(\Throwable $e){$this->gd_fail($e);}}
    private function row($r):array{$actions="";if($this->access->can("gd_resource_availability_manage")){$actions=modal_anchor(get_uri("grupo_donato/resources/availability/modal_form"),"<i data-feather='edit' class='icon-16'></i>",["data-post-id"=>$r->id,"title"=>app_lang("edit")]).js_anchor("<i data-feather='x' class='icon-16'></i>",["class"=>"delete","data-id"=>$r->id,"data-action-url"=>get_uri("grupo_donato/resources/availability/delete"),"data-action"=>"delete","title"=>app_lang("delete")]);}return [app_lang("gd_weekday_".$r->weekday),$this->escape(substr($r->start_time,0,5))." – ".$this->escape(substr($r->end_time,0,5)).((int)$r->spans_next_day?" (+1)":""),$this->escape($r->valid_from?:"-")." – ".$this->escape($r->valid_until?:"-"),app_lang("gd_status_".$r->status),$this->escape($r->notes?:"-"),$actions];}
    private function weekdays():array{$out=[];for($i=0;$i<7;$i++){$out[$i]=app_lang("gd_weekday_".$i);}return $out;}
}
