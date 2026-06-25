<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V021_create_resource_blocks extends SchemaVersion
{
    public function version(): string { return "021"; }
    public function description(): string { return "Cria bloqueios operacionais de recursos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_resource_blocks";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `block_type` VARCHAR(30) NOT NULL,
                `starts_at_utc` DATETIME NOT NULL,
                `ends_at_utc` DATETIME NOT NULL,
                `title` VARCHAR(160) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `metadata` MEDIUMTEXT NULL,
                `active_exact_key` VARCHAR(190) AS (IF(`deleted`=0 AND `status`='active', CONCAT(`resource_id`,':',`block_type`,':',`starts_at_utc`,':',`ends_at_utc`), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_exact_block` (`active_exact_key`),
                KEY `idx_unit_resource` (`unit_id`,`resource_id`),
                KEY `idx_resource_period` (`resource_id`,`starts_at_utc`,`ends_at_utc`),
                KEY `idx_type_status` (`block_type`,`status`,`deleted`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
