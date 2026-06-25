<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V025_create_booking_series extends SchemaVersion
{
    public function version(): string { return "025"; }
    public function description(): string { return "Cria séries de reservas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_series";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `series_number` VARCHAR(40) NOT NULL,
                `customer_account_id` BIGINT UNSIGNED NULL,
                `contact_person_id` BIGINT UNSIGNED NULL,
                `booking_type` VARCHAR(30) NOT NULL,
                `title` VARCHAR(180) NOT NULL,
                `frequency` VARCHAR(20) NOT NULL,
                `interval_value` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `weekdays` VARCHAR(32) NULL,
                `monthly_day` TINYINT UNSIGNED NULL,
                `local_start_time` TIME NOT NULL,
                `local_end_time` TIME NOT NULL,
                `timezone` VARCHAR(64) NOT NULL,
                `starts_on` DATE NOT NULL,
                `ends_mode` VARCHAR(20) NOT NULL,
                `ends_on` DATE NULL,
                `max_occurrences` SMALLINT UNSIGNED NULL,
                `default_booking_status` VARCHAR(30) NOT NULL,
                `conflict_policy` VARCHAR(24) NOT NULL,
                `generation_horizon_days` SMALLINT UNSIGNED NOT NULL DEFAULT 90,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `last_generated_until` DATE NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_series_number` (`unit_id`,`series_number`),
                KEY `idx_series_unit_status` (`unit_id`,`status`,`deleted`),
                KEY `idx_series_customer` (`unit_id`,`customer_account_id`,`deleted`),
                KEY `idx_series_dates` (`unit_id`,`starts_on`,`ends_on`),
                KEY `idx_series_updated` (`unit_id`,`updated_at`)
            ) ENGINE=InnoDB
        ");
    }
}
