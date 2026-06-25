<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class CustomerAccountService extends CustomerDataService
{
    private $model; private DuplicateDetectionService $duplicates;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->model=model('grupo_donato_gestao\\Models\\Gd_customer_accounts_model');$this->duplicates=new DuplicateDetectionService($unit_id);}
    public function get(int $id):?object{return $this->model->get_scoped($id,$this->unit_id);}
    public function save(array $input,int $id=0,bool $duplicate_override=false):array
    {
        $existing=$id?$this->get($id):null;if($id&&!$existing)throw new \DomainException('gd_record_not_found');
        $type=(string)($input['account_type']??'');$status=(string)($input['status']??'active');$docType=(string)($input['document_type']??'none');
        if(!in_array($type,Constants::ACCOUNT_TYPES,true)||!in_array($status,Constants::ACCOUNT_STATUSES,true)||!in_array($docType,Constants::DOCUMENT_TYPES_CUSTOMER,true))throw new \DomainException('gd_invalid_value');
        $display=DataNormalizationService::text($input['display_name']??'');if($display===''||mb_strlen($display)>190)throw new \DomainException('gd_account_name_required');
        $document=DataNormalizationService::text($input['document_number']??'');if($docType==='none'){$document='';}
        $email=DataNormalizationService::text($input['email']??'');if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \DomainException('gd_invalid_email');
        $data=['unit_id'=>$this->unit_id,'account_type'=>$type,'display_name'=>$display,'normalized_name'=>DataNormalizationService::name($display),'legal_name'=>DataNormalizationService::text($input['legal_name']??'')?:null,'trade_name'=>DataNormalizationService::text($input['trade_name']??'')?:null,'document_type'=>$docType,'document_number'=>$document?:null,'document_number_normalized'=>$document?DataNormalizationService::document($document,$docType):null,'email'=>$email?:null,'email_normalized'=>$email?DataNormalizationService::contact($email,'email'):null,'phone'=>DataNormalizationService::text($input['phone']??'')?:null,'phone_normalized'=>DataNormalizationService::contact($input['phone']??'','phone')?:null,'whatsapp'=>DataNormalizationService::text($input['whatsapp']??'')?:null,'whatsapp_normalized'=>DataNormalizationService::contact($input['whatsapp']??'','whatsapp')?:null,'status'=>$status,'rise_client_id'=>$this->assert_rise_id('clients',(int)($input['rise_client_id']??0)),'notes'=>DataNormalizationService::text($input['notes']??'')?:null];
        $matches=$this->duplicates->accounts($data,$id);$strong=array_values(array_filter($matches,fn($m)=>$m['confidence']==='exact'));
        if($strong&&!$duplicate_override){return ['saved'=>false,'duplicate_confirmation_required'=>true,'duplicates'=>$matches];}
        $data=$this->stamp($data,$id===0);$before=$existing?(array)$existing:null;$save=(int)$this->model->ci_save($data,$id);if(!$save)throw new \RuntimeException('save_failed');$after=(array)$this->get($save);
        $action=$id&&$existing->status!==$after['status']?'status_change':($id?'update':'create');
        $this->audit_change($action,'customer_account',$save,$before,$after);
        $oldRise=$existing?(int)($existing->rise_client_id??0):0;$newRise=(int)($after['rise_client_id']??0);
        if($oldRise!==$newRise){$this->audit_change('rise_link_change','customer_account',$save,null,null,['previous_id'=>$oldRise?:null,'new_id'=>$newRise?:null]);}
        if($matches&&$duplicate_override){$this->audit_change('duplicate_override','customer_account',$save,null,null,['matches'=>array_column($matches,'record_id')]);}
        return ['saved'=>true,'id'=>$save,'duplicates'=>$matches];
    }
    public function delete(int $id,string $reason):void
    { $row=$this->get($id);if(!$row)throw new \DomainException('gd_record_not_found');$reason=DataNormalizationService::text($reason);if($reason==='')throw new \DomainException('gd_delete_reason_required');if($this->model->active_relation_count($id,$this->unit_id)>0)throw new \DomainException('gd_account_has_relations');$this->model->delete($id);$this->audit_change('delete','customer_account',$id,(array)$row,null,['reason'=>$reason]); }
    public function duplicates(array $input,int $exclude_id=0):array
    { $display=DataNormalizationService::text($input['display_name']??'');$type=(string)($input['document_type']??'none');return $this->duplicates->accounts(['normalized_name'=>DataNormalizationService::name($display),'document_number_normalized'=>DataNormalizationService::document($input['document_number']??'',$type),'email_normalized'=>DataNormalizationService::contact($input['email']??'','email'),'phone_normalized'=>DataNormalizationService::contact($input['phone']??'','phone'),'whatsapp_normalized'=>DataNormalizationService::contact($input['whatsapp']??'','whatsapp')],$exclude_id); }
}
