<?php
declare(strict_types=1);namespace grupo_donato_gestao\Models;class Gd_cash_movements_model extends Gd_Model{public function __construct(){parent::__construct('gd_cash_movements');}public function delete($id=0,$undo=false){throw new \LogicException('gd_append_only');}public function ci_save(&$data=[],$id=0){if($id)throw new \LogicException('gd_append_only');return parent::ci_save($data,0);}}
