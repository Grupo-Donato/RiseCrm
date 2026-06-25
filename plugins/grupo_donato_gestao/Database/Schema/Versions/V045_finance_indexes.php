<?php
declare(strict_types=1);
namespace grupo_donato_gestao\Database\Schema\Versions;
use CodeIgniter\Database\BaseConnection;use grupo_donato_gestao\Database\Schema\SchemaVersion;
class V045_finance_indexes extends SchemaVersion{public function version():string{return '045';}public function description():string{return 'Consolida índices financeiros.';}public function up(BaseConnection $db,string $prefix):void{$this->ensureIndex($db,$prefix.'gd_receivables','idx_receivable_area','KEY `idx_receivable_area` (`unit_id`,`business_area_id`,`cost_center_id`,`deleted`)');$this->ensureIndex($db,$prefix.'gd_cash_movements','idx_cash_source_lookup','KEY `idx_cash_source_lookup` (`unit_id`,`source_type`,`source_id`)');}}
