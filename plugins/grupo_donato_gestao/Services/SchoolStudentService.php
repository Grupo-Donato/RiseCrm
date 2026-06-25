<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Student operations over the canonical person/account/contact records. */
class SchoolStudentService extends CustomerDataService
{
    private $profiles; private ?object $login_user;
    public function __construct(int $unit_id,int $actor_id=0,?object $login_user=null){parent::__construct($unit_id,$actor_id,$login_user);$this->profiles=model('grupo_donato_gestao\\Models\\Gd_school_profiles_model');$this->login_user=$login_user;}

    public function save(array $input,int $id=0):array
    {
        $old=$id?$this->profiles->get_scoped($id,$this->unit_id):null;if($id&&!$old)throw new \DomainException('gd_record_not_found');
        $status=(string)($input['status']??'active');if(!in_array($status,Constants::SCHOOL_PROFILE_STATUSES,true))throw new \DomainException('gd_invalid_value');
        $person_id=(int)($input['person_id']??($old->person_id??0));$family_id=(int)($input['family_account_id']??($old->family_account_id??0));$new_person=false;$new_family=false;
        $this->db->transBegin();
        try {
            if($person_id>0){$this->assertScoped('gd_people',$person_id);}else{$result=(new PersonService($this->unit_id,$this->actor_id,$this->login_user))->save(['full_name'=>$input['full_name']??'','birth_date'=>$input['birth_date']??'','status'=>'active','notes'=>$input['person_notes']??''],0,!empty($input['duplicate_override']));if(empty($result['saved'])){$this->db->transRollback();return $result;}$person_id=(int)$result['id'];$new_person=true;}
            if($family_id>0){$family=$this->assertScoped('gd_customer_accounts',$family_id);if((string)$family->account_type!=='family'||(string)$family->status!=='active')throw new \DomainException('gd_school_family_required');}
            elseif(trim((string)($input['new_family_name']??''))!==''){$result=(new CustomerAccountService($this->unit_id,$this->actor_id,$this->login_user))->save(['account_type'=>'family','display_name'=>$input['new_family_name'],'document_type'=>'none','status'=>'active'],0,!empty($input['duplicate_override']));if(empty($result['saved'])){$this->db->transRollback();return $result;}$family_id=(int)$result['id'];$new_family=true;}
            if($family_id<=0)throw new \DomainException('gd_school_family_required');
            $this->ensureRelation($family_id,$person_id,'family_member',false);
            $guardians=array_values(array_unique(array_filter(array_map('intval',(array)($input['guardian_ids']??[])))));$primary=(int)($input['primary_guardian_id']??0);if($primary&&!in_array($primary,$guardians,true))$guardians[]=$primary;
            foreach($guardians as $guardian){$this->assertScoped('gd_people',$guardian);$this->ensureRelation($family_id,$guardian,'guardian',$guardian===$primary);}
            if(trim((string)($input['contact_value']??''))!==''){(new ContactMethodService($this->unit_id,$this->actor_id,$this->login_user))->save(['person_id'=>$person_id,'contact_type'=>$input['contact_type']??'phone','value'=>$input['contact_value'],'is_primary'=>1,'status'=>'active']);}
            $data=$this->stamp(['unit_id'=>$this->unit_id,'person_id'=>$person_id,'family_account_id'=>$family_id,'status'=>$status,'notes'=>DataNormalizationService::text($input['notes']??'')?:null,'metadata'=>null],$id===0);
            $saved=(int)$this->profiles->ci_save($data,$id);if(!$saved)throw new \RuntimeException('save_failed');
            $after=$this->profiles->get_scoped($saved,$this->unit_id);$this->audit_change($id?'update':'create','school_profile',$saved,$old?(array)$old:null,$after?(array)$after:null,['new_person'=>$new_person,'new_family'=>$new_family]);
            if($this->db->transCommit()===false)throw new \RuntimeException('save_failed');return ['saved'=>true,'id'=>$saved,'person_id'=>$person_id,'family_account_id'=>$family_id];
        }catch(\Throwable $e){$this->db->transRollback();throw $e;}
    }

    public function get(int $id):?object
    {
        $p=$this->profiles->get_scoped($id,$this->unit_id);if(!$p)return null;$people=$this->db->prefixTable('gd_people');$accounts=$this->db->prefixTable('gd_customer_accounts');
        $p->person=$this->db->table($people)->where('id',$p->person_id)->where('unit_id',$this->unit_id)->where('deleted',0)->get(1)->getRow();$p->family=$this->db->table($accounts)->where('id',$p->family_account_id)->where('unit_id',$this->unit_id)->where('deleted',0)->get(1)->getRow();
        $p->guardians=$this->db->query("SELECT p.*,ap.is_primary FROM `{$this->db->prefixTable('gd_account_people')}` ap JOIN `$people` p ON p.id=ap.person_id AND p.unit_id=ap.unit_id AND p.deleted=0 WHERE ap.unit_id=? AND ap.account_id=? AND ap.role='guardian' AND ap.status='active' AND ap.deleted=0 ORDER BY ap.is_primary DESC,p.full_name",[$this->unit_id,$p->family_account_id])->getResult();
        $p->enrollments=$this->db->query("SELECT e.*,c.name class_name,c.class_type FROM `{$this->db->prefixTable('gd_enrollments')}` e JOIN `{$this->db->prefixTable('gd_classes')}` c ON c.id=e.class_id AND c.unit_id=e.unit_id AND c.deleted=0 WHERE e.unit_id=? AND e.school_profile_id=? AND e.deleted=0 ORDER BY e.starts_on DESC",[$this->unit_id,$id])->getResult();
        $p->attendance=$this->history($id);return $p;
    }

    public function listPage(array $o):array
    {
        $sp=$this->db->prefixTable('gd_school_profiles');$pp=$this->db->prefixTable('gd_people');$fa=$this->db->prefixTable('gd_customer_accounts');$base=function()use($o,$sp,$pp,$fa){$q=$this->db->table($sp)->join($pp,"$pp.id=$sp.person_id AND $pp.unit_id=$sp.unit_id AND $pp.deleted=0",'inner',false)->join($fa,"$fa.id=$sp.family_account_id AND $fa.unit_id=$sp.unit_id AND $fa.deleted=0",'inner',false)->where("$sp.unit_id",$this->unit_id)->where("$sp.deleted",0);if($v=trim((string)($o['status']??'')))$q->where("$sp.status",$v);if($v=trim((string)($o['search_by']??'')))$q->groupStart()->like("$pp.full_name",$v)->orLike("$fa.display_name",$v)->groupEnd();return $q;};
        $ap=$this->db->prefixTable('gd_account_people');$cm=$this->db->prefixTable('gd_contact_methods');$en=$this->db->prefixTable('gd_enrollments');$cl=$this->db->prefixTable('gd_classes');$ar=$this->db->prefixTable('gd_attendance_records');$total=$this->db->table($sp)->where('unit_id',$this->unit_id)->where('deleted',0)->countAllResults();$filtered=$base()->countAllResults(false);$select="$sp.*,$pp.full_name,$pp.birth_date,$fa.display_name family_name,(SELECT gp.full_name FROM `$ap` gar JOIN `$pp` gp ON gp.id=gar.person_id AND gp.deleted=0 WHERE gar.account_id=$sp.family_account_id AND gar.unit_id=$sp.unit_id AND gar.role='guardian' AND gar.status='active' AND gar.deleted=0 ORDER BY gar.is_primary DESC,gar.id LIMIT 1) guardian_name,(SELECT value FROM `$cm` ctm WHERE ctm.person_id=$sp.person_id AND ctm.unit_id=$sp.unit_id AND ctm.status='active' AND ctm.deleted=0 ORDER BY ctm.is_primary DESC,ctm.id LIMIT 1) primary_contact,(SELECT GROUP_CONCAT(cc.name ORDER BY cc.name SEPARATOR ', ') FROM `$en` ee JOIN `$cl` cc ON cc.id=ee.class_id AND cc.deleted=0 WHERE ee.school_profile_id=$sp.id AND ee.unit_id=$sp.unit_id AND ee.status IN ('active','paused') AND ee.deleted=0) class_names,(SELECT ROUND(100*SUM(rr.attendance_status='present')/NULLIF(SUM(rr.attendance_status<>'unmarked'),0),1) FROM `$ar` rr WHERE rr.school_profile_id=$sp.id AND rr.unit_id=$sp.unit_id) frequency";$rows=$base()->select($select,false)->orderBy("$pp.full_name",'ASC')->limit(max(1,min(100,(int)($o['limit']??25))),max(0,(int)($o['skip']??0)))->get()->getResult();return ['data'=>$rows,'recordsTotal'=>$total,'recordsFiltered'=>$filtered];
    }
    public function options(string $table,string $label,string $extra=''):array{$allowed=['gd_people','gd_customer_accounts'];if(!in_array($table,$allowed,true))return [];$q=$this->db->table($this->db->prefixTable($table))->select("id,$label AS text",false)->where('unit_id',$this->unit_id)->where('deleted',0)->where('status','active');if($extra)$q->where($extra);return $q->orderBy($label)->limit(200)->get()->getResultArray();}
    public function history(int $profile_id):array{$this->assertScoped('gd_school_profiles',$profile_id);$rows=$this->db->query("SELECT s.attendance_date,r.attendance_status,r.notes,c.name class_name FROM `{$this->db->prefixTable('gd_attendance_records')}` r JOIN `{$this->db->prefixTable('gd_attendance_sessions')}` s ON s.id=r.attendance_session_id AND s.unit_id=r.unit_id AND s.deleted=0 JOIN `{$this->db->prefixTable('gd_classes')}` c ON c.id=r.class_id AND c.unit_id=r.unit_id AND c.deleted=0 WHERE r.unit_id=? AND r.school_profile_id=? ORDER BY s.attendance_date DESC",[$this->unit_id,$profile_id])->getResult();$marked=array_filter($rows,fn($r)=>$r->attendance_status!=='unmarked');$present=array_filter($marked,fn($r)=>$r->attendance_status==='present');return ['rows'=>$rows,'marked'=>count($marked),'present'=>count($present),'percentage'=>count($marked)?round(count($present)*100/count($marked),1):null];}
    private function assertScoped(string $table,int $id):object{$row=$this->db->table($this->db->prefixTable($table))->where('id',$id)->where('unit_id',$this->unit_id)->where('deleted',0)->get(1)->getRow();if(!$row)throw new \DomainException('gd_record_not_found');return $row;}
    private function ensureRelation(int $account,int $person,string $role,bool $primary):void{$t=$this->db->prefixTable('gd_account_people');$r=$this->db->table($t)->where('unit_id',$this->unit_id)->where('account_id',$account)->where('person_id',$person)->where('role',$role)->where('status','active')->where('deleted',0)->get(1)->getRow();if($r){if($primary&&!(int)$r->is_primary)(new AccountPersonService($this->unit_id,$this->actor_id,$this->login_user))->save(['account_id'=>$account,'person_id'=>$person,'role'=>$role,'status'=>'active','is_primary'=>1],(int)$r->id);return;}(new AccountPersonService($this->unit_id,$this->actor_id,$this->login_user))->save(['account_id'=>$account,'person_id'=>$person,'role'=>$role,'status'=>'active','is_primary'=>$primary?1:0]);}
}
