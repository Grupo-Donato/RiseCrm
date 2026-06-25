<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V048_create_import_issues extends SchemaVersion
{
    public function version(): string { return "048"; }
    public function description(): string { return "Cria inconsistências/pendências de importação."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_import_issues";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `batch_id` BIGINT UNSIGNED NOT NULL,
                `row_id` BIGINT UNSIGNED NULL,
                `row_number` INT UNSIGNED NULL,
                `issue_type` VARCHAR(40) NOT NULL,
                `severity` VARCHAR(12) NOT NULL DEFAULT 'error',
                `message` VARCHAR(255) NULL,
                `context` MEDIUMTEXT NULL,
                `created_at` DATETIME NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_issue_batch` (`unit_id`,`batch_id`,`deleted`),
                KEY `idx_issue_severity` (`unit_id`,`batch_id`,`severity`)
            ) ENGINE=InnoDB
        ");
    }
}
