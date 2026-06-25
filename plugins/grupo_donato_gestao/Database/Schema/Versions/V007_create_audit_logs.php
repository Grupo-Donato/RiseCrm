<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V007_create_audit_logs extends SchemaVersion
{
    public function version(): string
    {
        return "007";
    }

    public function description(): string
    {
        return "Cria a tabela de auditoria (append-only).";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_audit_logs";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NULL,
                `actor_type` VARCHAR(20) NULL,
                `actor_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(40) NOT NULL,
                `entity_type` VARCHAR(60) NULL,
                `entity_id` BIGINT UNSIGNED NULL,
                `before_data` MEDIUMTEXT NULL,
                `after_data` MEDIUMTEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `request_id` VARCHAR(40) NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_entity` (`entity_type`, `entity_id`),
                KEY `idx_actor` (`actor_id`),
                KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB
        ");
    }
}
