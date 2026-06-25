<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class BookingResourceLockService
{
    private $db;private array $held=[];
    public function __construct(){ $this->db=db_connect(); }
    public function acquire(int $unit_id,array $resource_ids,int $timeout=10):void
    {
        $ids=array_values(array_unique(array_filter(array_map("intval",$resource_ids),static fn($id)=>$id>0)));sort($ids,SORT_NUMERIC);
        try{foreach($ids as $id){$raw="gd:booking:$unit_id:$id";$name=strlen($raw)<=64?$raw:"gd:booking:".hash("sha256",$raw);$row=$this->db->query("SELECT GET_LOCK(?, ?) AS l",[$name,$timeout])->getRow();if(!$row||(int)$row->l!==1){throw new \DomainException("gd_booking_lock_unavailable");}$this->held[]=$name;}}catch(\Throwable $e){$this->release();throw $e;}
    }
    public function release():void{foreach(array_reverse($this->held) as $name){try{$this->db->query("SELECT RELEASE_LOCK(?)",[$name]);}catch(\Throwable $e){log_message("error","GD booking lock release: ".$e->getMessage());}}$this->held=[];}
}
