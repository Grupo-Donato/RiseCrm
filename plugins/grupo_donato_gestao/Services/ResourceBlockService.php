<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ResourceBlockService extends ResourceTemporalService
{
    private $model;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->model=model("grupo_donato_gestao\\Models\\Gd_resource_blocks_model");}
    public function get(int $id):?object{return $this->model->get_scoped($id,$this->unit_id);} public function listForResource(int $resource_id):array{$this->assertResource($resource_id);return $this->model->for_resource($resource_id,$this->unit_id);}
    public function listPage(array $options):array{$this->assertResource((int)($options["resource_id"]??0));$options["unit_id"]=$this->unit_id;return $this->model->get_details($options);}
    public function save(array $input,int $id=0,bool $overlap_override=false):array
    {
        $existing=$id?$this->get($id):null;if($id&&!$existing){throw new \DomainException("gd_record_not_found");}$resource_id=(int)($input["resource_id"]??0);$this->assertResource($resource_id);
        $type=(string)($input["block_type"]??"");if(!in_array($type,Constants::RESOURCE_BLOCK_TYPES,true)){throw new \DomainException("gd_invalid_block_type");}[$start,$end]=$this->interval($input);
        $title=DataNormalizationService::text($input["title"]??"");if($title===""||mb_strlen($title)>160){throw new \DomainException("gd_title_required");}$reason=DataNormalizationService::text($input["reason"]??"")?:null;if(in_array($type,Constants::RESOURCE_BLOCK_REASON_REQUIRED,true)&&!$reason){throw new \DomainException("gd_reason_required");}
        $status=(string)($input["status"]??"active");if(!in_array($status,Constants::RESOURCE_BLOCK_STATUSES,true)){throw new \DomainException("gd_invalid_value");}
        $data=$this->stamp(["unit_id"=>$this->unit_id,"resource_id"=>$resource_id,"block_type"=>$type,"starts_at_utc"=>$start,"ends_at_utc"=>$end,"title"=>$title,"reason"=>$reason,"status"=>$status,"metadata"=>DataNormalizationService::json($input["metadata"]??null)],$id===0);
        $lock=$this->acquireLock("block:".$resource_id);try{$this->db->transBegin();if($status==="active"&&$this->exactExists("gd_resource_blocks",$resource_id,"block_type",$type,$start,$end,$id)){throw new \DomainException("gd_duplicate_exact_interval");}$conflicts=$status==="active"?$this->overlaps("gd_resource_blocks",$resource_id,$start,$end,$id):[];if($conflicts&&!$overlap_override){$this->db->transRollback();return ["saved"=>false,"overlap_confirmation_required"=>true,"conflicts"=>$conflicts];}$saved=(int)$this->model->ci_save($data,$id);if(!$saved||!$this->db->transStatus()){throw new \RuntimeException("save_failed");}$this->db->transCommit();}catch(\Throwable $e){$this->db->transRollback();throw $e;}finally{$this->releaseLock($lock);}
        $this->audit_change($id?"update":"create","resource_block",$saved,$existing?(array)$existing:null,(array)$this->get($saved));if($conflicts&&$overlap_override){$this->audit_change("overlap_override","resource_block",$saved,null,null,["conflicts"=>array_column($conflicts,"id")]);}return ["saved"=>true,"id"=>$saved];
    }
    public function delete(int $id):void{$row=$this->get($id);if(!$row){throw new \DomainException("gd_record_not_found");}$this->model->delete($id);$this->audit_change("delete","resource_block",$id,(array)$row,null);}
}
