<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V020_create_resource_availability_exceptions extends SchemaVersion
{
    public function version(): string { return "020"; }
    public function description(): string { return "Cria exceções pontuais de abertura e fechamento."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_resource_availability_exceptions";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `exception_type` VARCHAR(20) NOT NULL,
                `starts_at_utc` DATETIME NOT NULL,
                `ends_at_utc` DATETIME NOT NULL,
                `title` VARCHAR(160) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `metadata` MEDIUMTEXT NULL,
                `active_exact_key` VARCHAR(190) AS (IF(`deleted`=0 AND `status`='active', CONCAT(`resource_id`,':',`exception_type`,':',`starts_at_utc`,':',`ends_at_utc`), NULL)) STORED,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_exact_exception` (`active_exact_key`),
                KEY `idx_unit_resource` (`unit_id`,`resource_id`),
                KEY `idx_resource_period` (`resource_id`,`starts_at_utc`,`ends_at_utc`),
                KEY `idx_type_status` (`exception_type`,`status`,`deleted`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
