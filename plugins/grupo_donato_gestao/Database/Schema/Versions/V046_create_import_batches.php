<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V046_create_import_batches extends SchemaVersion
{
    public function version(): string { return "046"; }
    public function description(): string { return "Cria lotes de importação assistida."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_import_batches";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `batch_number` VARCHAR(40) NOT NULL,
                `import_type` VARCHAR(30) NOT NULL,
                `original_filename` VARCHAR(255) NOT NULL,
                `stored_path` VARCHAR(255) NULL,
                `file_hash` CHAR(64) NOT NULL,
                `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `status` VARCHAR(24) NOT NULL DEFAULT 'draft',
                `mapping` MEDIUMTEXT NULL,
                `row_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `imported_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `issue_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `metadata` MEDIUMTEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `confirmed_at` DATETIME NULL,
                `confirmed_by` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_batch_number` (`unit_id`,`batch_number`),
                KEY `idx_import_hash` (`unit_id`,`file_hash`),
                KEY `idx_import_type_status` (`unit_id`,`import_type`,`status`,`deleted`),
                KEY `idx_import_updated` (`unit_id`,`updated_at`)
            ) ENGINE=InnoDB
        ");
    }
}
