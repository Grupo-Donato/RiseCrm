<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V035_create_classes extends SchemaVersion
{
    public function version(): string { return "035"; }
    public function description(): string { return "Cria turmas de escola e personal."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_classes";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `class_number` VARCHAR(40) NOT NULL,
                `name` VARCHAR(180) NOT NULL,
                `modality` VARCHAR(100) NOT NULL,
                `class_type` VARCHAR(20) NOT NULL,
                `instructor_user_id` BIGINT UNSIGNED NULL,
                `instructor_person_id` BIGINT UNSIGNED NULL,
                `resource_id` BIGINT UNSIGNED NULL,
                `booking_series_id` BIGINT UNSIGNED NULL,
                `booking_id` BIGINT UNSIGNED NULL,
                `weekdays` VARCHAR(32) NULL,
                `local_start_time` TIME NULL,
                `local_end_time` TIME NULL,
                `timezone` VARCHAR(64) NOT NULL,
                `capacity` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                `min_age` TINYINT UNSIGNED NULL,
                `max_age` TINYINT UNSIGNED NULL,
                `starts_on` DATE NULL,
                `ends_on` DATE NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_class_number` (`unit_id`,`class_number`),
                UNIQUE KEY `uniq_class_series` (`unit_id`,`booking_series_id`),
                UNIQUE KEY `uniq_class_booking` (`unit_id`,`booking_id`),
                KEY `idx_classes_status` (`unit_id`,`status`,`class_type`,`deleted`),
                KEY `idx_classes_resource` (`unit_id`,`resource_id`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
