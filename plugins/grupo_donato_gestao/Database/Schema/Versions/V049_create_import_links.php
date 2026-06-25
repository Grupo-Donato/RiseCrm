<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V049_create_import_links extends SchemaVersion
{
    public function version(): string { return "049"; }
    public function description(): string { return "Cria rastreabilidade lote→registro de domínio."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_import_links";
        // A unique por (batch,row,target_type) impede duplicar o vínculo da mesma
        // linha; a chave (source_key,target_type) impede recriar o mesmo alvo
        // lógico ao reprocessar/reimportar.
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `batch_id` BIGINT UNSIGNED NOT NULL,
                `row_id` BIGINT UNSIGNED NULL,
                `row_number` INT UNSIGNED NULL,
                `source_key` CHAR(64) NOT NULL,
                `target_type` VARCHAR(30) NOT NULL,
                `target_id` BIGINT UNSIGNED NOT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_batch_row_target` (`unit_id`,`batch_id`,`row_number`,`target_type`),
                KEY `idx_link_target` (`unit_id`,`target_type`,`target_id`),
                KEY `idx_link_source` (`unit_id`,`source_key`,`target_type`)
            ) ENGINE=InnoDB
        ");
    }
}
