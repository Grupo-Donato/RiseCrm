<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V023_create_booking_resources extends SchemaVersion
{
    public function version(): string { return "023"; }
    public function description(): string { return "Cria recursos e ocupações das reservas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_resources";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `booking_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `buffer_before_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `buffer_after_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `occupancy_starts_at_utc` DATETIME NOT NULL,
                `occupancy_ends_at_utc` DATETIME NOT NULL,
                `active_booking_resource_key` VARCHAR(190) AS (IF(`deleted`=0, CONCAT(`booking_id`,':',`resource_id`), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_booking_resource` (`active_booking_resource_key`),
                KEY `idx_booking` (`unit_id`,`booking_id`,`deleted`),
                KEY `idx_resource_occupancy` (`unit_id`,`resource_id`,`deleted`,`occupancy_starts_at_utc`,`occupancy_ends_at_utc`),
                KEY `idx_resource_end` (`unit_id`,`resource_id`,`deleted`,`occupancy_ends_at_utc`)
            ) ENGINE=InnoDB
        ");
    }
}
