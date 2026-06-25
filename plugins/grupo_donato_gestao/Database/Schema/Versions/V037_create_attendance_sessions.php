<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V037_create_attendance_sessions extends SchemaVersion
{
    public function version(): string { return "037"; }
    public function description(): string { return "Cria sessões de presença por turma e data."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_attendance_sessions";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `class_id` BIGINT UNSIGNED NOT NULL,
                `attendance_date` DATE NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'open',
                `notes` TEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_attendance_session` (`unit_id`,`class_id`,`attendance_date`,`deleted`),
                KEY `idx_attendance_date` (`unit_id`,`attendance_date`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
