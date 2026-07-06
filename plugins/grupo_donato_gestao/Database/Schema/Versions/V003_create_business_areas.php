<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V003_create_business_areas extends SchemaVersion
{
    public function version(): string
    {
        return "003";
    }

    public function description(): string
    {
        return "Cria a tabela de áreas de negócio.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_business_areas";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NULL,
                `unit_scope_id` BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_code` (`unit_id`, `code`),
                UNIQUE KEY `uniq_scope_code` (`unit_scope_id`, `code`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
        $this->ensureColumn($db, $table, "unit_scope_id", "BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED");
        $this->ensureIndex($db, $table, "uniq_scope_code", "UNIQUE KEY `uniq_scope_code` (`unit_scope_id`, `code`)");
    }
}
