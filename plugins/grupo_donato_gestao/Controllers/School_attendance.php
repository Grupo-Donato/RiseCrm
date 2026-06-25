<?php
declare(strict_types=1);
namespace grupo_donato_gestao\Controllers;
use grupo_donato_gestao\Services\SchoolAttendanceService;
class School_attendance extends Gd_Controller
{
    private SchoolAttendanceService $service;private int $unit;
    public function __construct(){parent::__construct();$this->access->require('gd_school_view');$this->unit=(int)$this->active_unit_id();if(!$this->unit)throw new \RuntimeException('No active unit.');$this->service=new SchoolAttendanceService($this->unit,$this->user_id(),$this->login_user);}
    public function index(){$db=db_connect();$classes=$db->table($db->prefixTable('gd_classes'))->select('id,name')->where('unit_id',$this->unit)->where('status','active')->where('deleted',0)->orderBy('name')->get()->getResult();return $this->gd_render('school_attendance/index',['classes'=>$classes,'can_manage'=>$this->access->can('gd_attendance_manage')]);}
    public function roster(){try{return $this->response->setJSON(['success'=>true,'data'=>$this->service->roster((int)$this->request->getGet('class_id'),(string)$this->request->getGet('date'))]);}catch(\Throwable $e){$this->gd_fail($e);}}
    public function save(){try{$this->access->require('gd_attendance_manage');$marks=(array)$this->request->getPost('marks');$r=$this->service->saveBatch((int)$this->request->getPost('class_id'),(string)$this->request->getPost('date'),$marks,(string)($this->request->getPost('session_status')?:'completed'));$this->json_success(app_lang('record_saved'),['data'=>$r]);}catch(\Throwable $e){$this->gd_fail($e);}}
}
