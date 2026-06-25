<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V038_create_attendance_records extends SchemaVersion
{
    public function version(): string { return "038"; }
    public function description(): string { return "Cria marcações de presença por aluno."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_attendance_records";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `attendance_session_id` BIGINT UNSIGNED NOT NULL,
                `class_id` BIGINT UNSIGNED NOT NULL,
                `school_profile_id` BIGINT UNSIGNED NOT NULL,
                `attendance_status` VARCHAR(20) NOT NULL DEFAULT 'unmarked',
                `notes` VARCHAR(500) NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_attendance_student` (`attendance_session_id`,`school_profile_id`),
                KEY `idx_attendance_student_history` (`unit_id`,`school_profile_id`,`attendance_status`,`id`),
                KEY `idx_attendance_class` (`unit_id`,`class_id`,`attendance_session_id`)
            ) ENGINE=InnoDB
        ");
    }
}
