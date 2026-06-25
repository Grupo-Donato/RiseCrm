<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

abstract class CustomerDataService
{
    protected $db; protected int $unit_id; protected int $actor_id; protected AuditService $audit;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null)
    {
        $this->db=db_connect(); $this->unit_id=$unit_id; $this->actor_id=$actor_id; $this->audit=new AuditService($login_user); $this->assert_unit();
    }
    protected function assert_unit():void
    {
        $t=$this->db->prefixTable('gd_units'); if($this->unit_id<=0||$this->db->table($t)->where('id',$this->unit_id)->where('deleted',0)->where('status','active')->countAllResults()!==1){throw new \DomainException('gd_invalid_unit');}
    }
    protected function stamp(array $data,bool $new):array { $data['updated_by']=$this->actor_id?:null; if($new){$data['created_by']=$this->actor_id?:null;} return $data; }
    protected function audit_change(string $action,string $entity,int $id,?array $before,?array $after,?array $metadata=null):void
    { $this->audit->log($action,$entity,$id,DataPrivacyService::forAudit($before),DataPrivacyService::forAudit($after),DataPrivacyService::forAudit($metadata),$this->unit_id); }
    protected function valid_date($value,bool $allow_future=true):?string
    {
        $value=trim((string)$value); if($value===''){return null;} $d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value); $errors=\DateTimeImmutable::getLastErrors();
        if(!$d||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$d->format('Y-m-d')!==$value||(!$allow_future&&$d>new \DateTimeImmutable('today'))){throw new \DomainException('gd_invalid_date');} return $value;
    }
    protected function assert_rise_id(string $table,int $id,array $where=[]):?int
    { if($id<=0){return null;} $b=$this->db->table($this->db->prefixTable($table))->where('id',$id); if($this->db->fieldExists('deleted',$this->db->prefixTable($table))){$b->where('deleted',0);} foreach($where as $k=>$v){$b->where($k,$v);} if($b->countAllResults()!==1){throw new \DomainException('gd_invalid_rise_link');} return $id; }
}
