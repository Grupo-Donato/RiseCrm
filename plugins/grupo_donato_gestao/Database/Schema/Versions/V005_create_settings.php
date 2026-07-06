<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V005_create_settings extends SchemaVersion
{
    public function version(): string
    {
        return "005";
    }

    public function description(): string
    {
        return "Cria a tabela de configurações do plugin.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_settings";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NULL,
                `unit_scope_id` BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED,
                `key` VARCHAR(120) NOT NULL,
                `value` MEDIUMTEXT NULL,
                `value_type` VARCHAR(20) NOT NULL DEFAULT 'string',
                `is_secret` TINYINT(1) NOT NULL DEFAULT 0,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_scope_key` (`unit_id`, `key`),
                UNIQUE KEY `uniq_normalized_scope_key` (`unit_scope_id`, `key`),
                KEY `idx_key` (`key`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
        $this->ensureColumn($db, $table, "unit_scope_id", "BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED");
        $this->ensureIndex($db, $table, "uniq_normalized_scope_key", "UNIQUE KEY `uniq_normalized_scope_key` (`unit_scope_id`, `key`)");
    }
}
