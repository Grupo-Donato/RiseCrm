<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V014_create_resources extends SchemaVersion
{
    public function version(): string { return "014"; }
    public function description(): string { return "Cria os recursos físicos (quadras, espaços, equipamentos)."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_resources";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `business_area_id` BIGINT UNSIGNED NULL,
                `cost_center_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `resource_type` VARCHAR(30) NOT NULL DEFAULT 'other',
                `description` TEXT NULL,
                `capacity` INT UNSIGNED NULL,
                `is_bookable` TINYINT(1) NOT NULL DEFAULT 1,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `sort_order` INT NOT NULL DEFAULT 0,
                `metadata` MEDIUMTEXT NULL,
                `active_code_key` VARCHAR(120) AS (IF(`deleted`=0, CONCAT(`unit_id`, ':', `code`), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_code` (`active_code_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_area` (`business_area_id`),
                KEY `idx_cost_center` (`cost_center_id`),
                KEY `idx_type` (`resource_type`),
                KEY `idx_active` (`is_active`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
