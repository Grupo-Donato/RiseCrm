<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class PersonService extends CustomerDataService
{
    private $model; private DuplicateDetectionService $duplicates;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->model=model('grupo_donato_gestao\\Models\\Gd_people_model');$this->duplicates=new DuplicateDetectionService($unit_id);}
    public function get(int $id):?object{return $this->model->get_scoped($id,$this->unit_id);}
    public function save(array $input,int $id=0,bool $duplicate_override=false):array
    {
        $existing=$id?$this->get($id):null;if($id&&!$existing)throw new \DomainException('gd_record_not_found');$name=DataNormalizationService::text($input['full_name']??'');if($name===''||mb_strlen($name)>190)throw new \DomainException('gd_person_name_required');$status=(string)($input['status']??'active');if(!in_array($status,Constants::PERSON_STATUSES,true))throw new \DomainException('gd_invalid_value');$birth=$this->valid_date($input['birth_date']??'',false);
        $data=['unit_id'=>$this->unit_id,'first_name'=>DataNormalizationService::text($input['first_name']??'')?:null,'last_name'=>DataNormalizationService::text($input['last_name']??'')?:null,'full_name'=>$name,'normalized_name'=>DataNormalizationService::name($name),'preferred_name'=>DataNormalizationService::text($input['preferred_name']??'')?:null,'birth_date'=>$birth,'status'=>$status,'rise_user_id'=>$this->assert_rise_id('users',(int)($input['rise_user_id']??0)),'rise_contact_id'=>$this->assert_rise_id('users',(int)($input['rise_contact_id']??0),['user_type'=>'client']),'notes'=>DataNormalizationService::text($input['notes']??'')?:null];
        $matches=$this->duplicates->people($data,$id);$strong=array_values(array_filter($matches,fn($m)=>in_array($m['confidence'],['exact','high'],true)));if($strong&&!$duplicate_override){return ['saved'=>false,'duplicate_confirmation_required'=>true,'duplicates'=>$matches];}$data=$this->stamp($data,$id===0);$before=$existing?(array)$existing:null;$save=(int)$this->model->ci_save($data,$id);if(!$save)throw new \RuntimeException('save_failed');$after=(array)$this->get($save);$action=$id&&$existing->status!==$after['status']?'status_change':($id?'update':'create');$this->audit_change($action,'person',$save,$before,$after);$oldLinks=$existing?[(int)($existing->rise_user_id??0),(int)($existing->rise_contact_id??0)]:[0,0];$newLinks=[(int)($after['rise_user_id']??0),(int)($after['rise_contact_id']??0)];if($oldLinks!==$newLinks){$this->audit_change('rise_link_change','person',$save,null,null,['previous_ids'=>$oldLinks,'new_ids'=>$newLinks]);}if($matches&&$duplicate_override){$this->audit_change('duplicate_override','person',$save,null,null,['matches'=>array_column($matches,'record_id')]);}return ['saved'=>true,'id'=>$save,'duplicates'=>$matches];
    }
    public function delete(int $id,string $reason):void
    { $row=$this->get($id);if(!$row)throw new \DomainException('gd_record_not_found');$reason=DataNormalizationService::text($reason);if($reason==='')throw new \DomainException('gd_delete_reason_required');$links=$this->db->prefixTable('gd_account_people');$contacts=$this->db->prefixTable('gd_contact_methods');$now=function_exists('get_current_utc_time')?get_current_utc_time():gmdate('Y-m-d H:i:s');$this->db->transStart();$this->db->table($links)->where('person_id',$id)->where('unit_id',$this->unit_id)->where('deleted',0)->where('status','active')->update(['status'=>'ended','end_date'=>gmdate('Y-m-d'),'is_primary'=>0,'updated_at'=>$now,'updated_by'=>$this->actor_id?:null]);$this->db->table($contacts)->where('person_id',$id)->where('unit_id',$this->unit_id)->where('deleted',0)->update(['status'=>'inactive','deleted'=>1,'updated_at'=>$now,'updated_by'=>$this->actor_id?:null]);$this->model->delete($id);$this->db->transComplete();if($this->db->transStatus()===false)throw new \RuntimeException('delete_failed');$this->audit_change('delete','person',$id,(array)$row,null,['reason'=>$reason]); }
    public function duplicates(array $input,int $exclude_id=0):array{return $this->duplicates->people(['normalized_name'=>DataNormalizationService::name($input['full_name']??''),'birth_date'=>$input['birth_date']??null,'contact_values'=>(array)($input['contact_values']??[])],$exclude_id);}
}
