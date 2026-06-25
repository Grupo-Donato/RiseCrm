<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V004_create_cost_centers extends SchemaVersion
{
    public function version(): string
    {
        return "004";
    }

    public function description(): string
    {
        return "Cria a tabela de centros de resultado.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_cost_centers";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NULL,
                `business_area_id` BIGINT UNSIGNED NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `type` VARCHAR(30) NOT NULL DEFAULT 'mixed',
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_code` (`unit_id`, `code`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_area` (`business_area_id`),
                KEY `idx_parent` (`parent_id`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
