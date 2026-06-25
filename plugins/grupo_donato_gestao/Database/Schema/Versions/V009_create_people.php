<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V009_create_people extends SchemaVersion
{
    public function version(): string { return "009"; }
    public function description(): string { return "Cria o cadastro universal de pessoas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_people";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `first_name` VARCHAR(100) NULL,
                `last_name` VARCHAR(150) NULL,
                `full_name` VARCHAR(190) NOT NULL,
                `normalized_name` VARCHAR(190) NOT NULL,
                `preferred_name` VARCHAR(150) NULL,
                `birth_date` DATE NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `rise_user_id` BIGINT UNSIGNED NULL,
                `rise_contact_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_name` (`normalized_name`),
                KEY `idx_birth_date` (`birth_date`),
                KEY `idx_status` (`status`),
                KEY `idx_rise_user` (`rise_user_id`),
                KEY `idx_rise_contact` (`rise_contact_id`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
