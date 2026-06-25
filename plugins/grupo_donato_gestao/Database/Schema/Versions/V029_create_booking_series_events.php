<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V029_create_booking_series_events extends SchemaVersion
{
    public function version(): string { return "029"; }
    public function description(): string { return "Cria histórico append-only das séries."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_series_events";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `series_id` BIGINT UNSIGNED NOT NULL,
                `event_type` VARCHAR(40) NOT NULL,
                `from_status` VARCHAR(20) NULL,
                `to_status` VARCHAR(20) NULL,
                `reason` VARCHAR(255) NULL,
                `payload` MEDIUMTEXT NULL,
                `actor_type` VARCHAR(20) NOT NULL DEFAULT 'system',
                `actor_id` BIGINT UNSIGNED NULL,
                `request_id` VARCHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_series_events_series` (`unit_id`,`series_id`,`id`),
                KEY `idx_series_events_type` (`unit_id`,`event_type`,`created_at`)
            ) ENGINE=InnoDB
        ");

        $runs = $prefix . "gd_booking_series_generation_runs";
        $this->ensureTable($db, $runs, "
            CREATE TABLE IF NOT EXISTS `$runs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `series_id` BIGINT UNSIGNED NOT NULL,
                `conflict_policy` VARCHAR(24) NOT NULL,
                `status` VARCHAR(20) NOT NULL,
                `created_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `idempotent_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `skipped_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `error_code` VARCHAR(80) NULL,
                `request_id` VARCHAR(64) NULL,
                `started_at` DATETIME NOT NULL,
                `completed_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                KEY `idx_series_runs_series` (`unit_id`,`series_id`,`id`),
                KEY `idx_series_runs_status` (`unit_id`,`status`,`started_at`)
            ) ENGINE=InnoDB
        ");
    }
}
