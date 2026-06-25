<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class ResourceAvailabilityRuleService extends ResourceTemporalService
{
    private $model;
    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null) { parent::__construct($unit_id,$actor_id,$login_user); $this->model=model("grupo_donato_gestao\\Models\\Gd_resource_availability_rules_model"); }
    public function get(int $id): ?object { return $this->model->get_scoped($id,$this->unit_id); }
    public function listForResource(int $resource_id): array { $this->assertResource($resource_id); return $this->model->for_resource($resource_id,$this->unit_id); }
    public function listPage(array $options):array{$this->assertResource((int)($options["resource_id"]??0));$options["unit_id"]=$this->unit_id;return $this->model->get_details($options);}

    public function save(array $input, int $id = 0): array
    {
        $existing=$id?$this->get($id):null; if($id&&!$existing){throw new \DomainException("gd_record_not_found");}
        $resource_id=(int)($input["resource_id"]??0); $this->assertResource($resource_id);
        $weekday=(int)($input["weekday"]??-1); if($weekday<0||$weekday>6){throw new \DomainException("gd_invalid_weekday");}
        $start=TemporalService::normalizeTime((string)($input["start_time"]??"")); $end=TemporalService::normalizeTime((string)($input["end_time"]??""));
        $spans=!empty($input["spans_next_day"])?1:0; $sm=TemporalService::timeMinutes($start); $em=TemporalService::timeMinutes($end);
        if((!$spans&&$em<=$sm)||($spans&&$em>$sm)){throw new \DomainException("gd_invalid_weekly_interval");}
        $from=$this->valid_date($input["valid_from"]??""); $until=$this->valid_date($input["valid_until"]??""); if($from&&$until&&$until<$from){throw new \DomainException("gd_invalid_date_range");}
        $status=(string)($input["status"]??"active"); if(!in_array($status,Constants::AVAILABILITY_RULE_STATUSES,true)){throw new \DomainException("gd_invalid_value");}
        $data=$this->stamp(["unit_id"=>$this->unit_id,"resource_id"=>$resource_id,"weekday"=>$weekday,"start_time"=>$start,"end_time"=>$end,"spans_next_day"=>$spans,"valid_from"=>$from,"valid_until"=>$until,"status"=>$status,"sort_order"=>max(0,(int)($input["sort_order"]??0)),"notes"=>DataNormalizationService::text($input["notes"]??"")?:null],$id===0);
        $lock=$this->acquireLock("rule:".$resource_id);
        try {
            $this->db->transBegin();
            if($status==="active"){$this->assertNoOverlap($data,$id);}
            $saved=(int)$this->model->ci_save($data,$id); if(!$saved){throw new \RuntimeException("save_failed");}
            if(!$this->db->transStatus()){throw new \RuntimeException("save_failed");} $this->db->transCommit();
        } catch(\Throwable $e){$this->db->transRollback(); throw $e;} finally {$this->releaseLock($lock);}
        $after=(array)$this->get($saved); $this->audit_change($id?"update":"create","resource_availability_rule",$saved,$existing?(array)$existing:null,$after);
        return ["saved"=>true,"id"=>$saved];
    }

    public function delete(int $id): void { $row=$this->get($id); if(!$row){throw new \DomainException("gd_record_not_found");} $this->model->delete($id); $this->audit_change("delete","resource_availability_rule",$id,(array)$row,null); }

    private function assertNoOverlap(array $candidate,int $exclude_id): void
    {
        foreach($this->model->for_resource((int)$candidate["resource_id"],$this->unit_id,true) as $row){
            if((int)$row->id===$exclude_id||!$this->validitiesOverlap($candidate["valid_from"],$candidate["valid_until"],$row->valid_from,$row->valid_until)){continue;}
            foreach($this->segments((int)$candidate["weekday"],$candidate["start_time"],$candidate["end_time"],(bool)$candidate["spans_next_day"]) as $a){
                foreach($this->segments((int)$row->weekday,$row->start_time,$row->end_time,(bool)$row->spans_next_day) as $b){if($a[0]<$b[1]&&$a[1]>$b[0]){throw new \DomainException("gd_weekly_rule_overlap");}}
            }
        }
    }
    private function validitiesOverlap(?string $af,?string $au,?string $bf,?string $bu):bool{return ($af??"0000-01-01")<=($bu??"9999-12-31")&&($au??"9999-12-31")>=($bf??"0000-01-01");}
    private function segments(int $weekday,string $start,string $end,bool $spans):array
    {
        $s=$weekday*1440+TemporalService::timeMinutes($start); $e=$weekday*1440+TemporalService::timeMinutes($end)+($spans?1440:0); $week=10080;
        if($e<=$week){return [[$s,$e]];} return [[$s,$week],[0,$e-$week]];
    }
}
