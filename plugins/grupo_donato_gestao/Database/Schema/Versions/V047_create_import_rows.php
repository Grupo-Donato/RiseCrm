<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V047_create_import_rows extends SchemaVersion
{
    public function version(): string { return "047"; }
    public function description(): string { return "Cria linhas de importação com valor bruto preservado."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_import_rows";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `batch_id` BIGINT UNSIGNED NOT NULL,
                `row_number` INT UNSIGNED NOT NULL,
                `source_key` CHAR(64) NOT NULL,
                `raw_data` MEDIUMTEXT NULL,
                `normalized_data` MEDIUMTEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
                `note` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_batch_row` (`unit_id`,`batch_id`,`row_number`),
                KEY `idx_row_status` (`unit_id`,`batch_id`,`status`),
                KEY `idx_row_source_key` (`unit_id`,`source_key`)
            ) ENGINE=InnoDB
        ");
    }
}
