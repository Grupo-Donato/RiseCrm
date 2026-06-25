<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V022_create_bookings extends SchemaVersion
{
    public function version(): string { return "022"; }
    public function description(): string { return "Cria reservas únicas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_bookings";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `booking_number` VARCHAR(40) NOT NULL,
                `customer_account_id` BIGINT UNSIGNED NULL,
                `contact_person_id` BIGINT UNSIGNED NULL,
                `booking_type` VARCHAR(30) NOT NULL,
                `title` VARCHAR(180) NOT NULL,
                `status` VARCHAR(30) NOT NULL,
                `starts_at_utc` DATETIME NOT NULL,
                `ends_at_utc` DATETIME NOT NULL,
                `timezone` VARCHAR(64) NOT NULL,
                `hold_expires_at_utc` DATETIME NULL,
                `source_type` VARCHAR(40) NULL,
                `source_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `confirmed_at` DATETIME NULL,
                `confirmed_by` BIGINT UNSIGNED NULL,
                `started_at` DATETIME NULL,
                `started_by` BIGINT UNSIGNED NULL,
                `completed_at` DATETIME NULL,
                `completed_by` BIGINT UNSIGNED NULL,
                `cancelled_at` DATETIME NULL,
                `cancelled_by` BIGINT UNSIGNED NULL,
                `cancellation_reason` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_booking_number` (`unit_id`,`booking_number`),
                KEY `idx_unit_status_deleted` (`unit_id`,`status`,`deleted`),
                KEY `idx_unit_period` (`unit_id`,`starts_at_utc`,`ends_at_utc`),
                KEY `idx_customer` (`unit_id`,`customer_account_id`,`deleted`),
                KEY `idx_contact` (`unit_id`,`contact_person_id`,`deleted`),
                KEY `idx_hold_expiry` (`status`,`hold_expires_at_utc`,`deleted`),
                KEY `idx_updated` (`unit_id`,`updated_at`)
            ) ENGINE=InnoDB
        ");
    }
}
