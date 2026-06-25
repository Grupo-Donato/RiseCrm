<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V036_create_enrollments extends SchemaVersion
{
    public function version(): string { return "036"; }
    public function description(): string { return "Cria matrículas escolares."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_enrollments";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `class_id` BIGINT UNSIGNED NOT NULL,
                `school_profile_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NULL,
                `starts_on` DATE NOT NULL,
                `ends_on` DATE NULL,
                `preferred_due_day` TINYINT UNSIGNED NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `open_guard` TINYINT GENERATED ALWAYS AS (CASE WHEN `deleted`=0 AND `status` IN ('active','paused') THEN 1 ELSE NULL END) PERSISTENT,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_open_enrollment` (`class_id`,`school_profile_id`,`open_guard`),
                KEY `idx_enrollment_class` (`unit_id`,`class_id`,`status`,`deleted`),
                KEY `idx_enrollment_student` (`unit_id`,`school_profile_id`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
