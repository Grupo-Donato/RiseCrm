<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V019_create_resource_availability_rules extends SchemaVersion
{
    public function version(): string { return "019"; }
    public function description(): string { return "Cria regras semanais de disponibilidade de recursos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_resource_availability_rules";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `weekday` TINYINT UNSIGNED NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `spans_next_day` TINYINT(1) NOT NULL DEFAULT 0,
                `valid_from` DATE NULL,
                `valid_until` DATE NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `notes` TEXT NULL,
                `active_exact_key` VARCHAR(190) AS (IF(`deleted`=0 AND `status`='active', CONCAT(`resource_id`,':',`weekday`,':',`start_time`,':',`end_time`,':',`spans_next_day`,':',IFNULL(`valid_from`,'0000-00-00'),':',IFNULL(`valid_until`,'9999-12-31')), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_exact_rule` (`active_exact_key`),
                KEY `idx_unit_resource` (`unit_id`,`resource_id`),
                KEY `idx_resource_weekday` (`resource_id`,`weekday`,`status`,`deleted`),
                KEY `idx_validity` (`valid_from`,`valid_until`),
                KEY `idx_status` (`status`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
