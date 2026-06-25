<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V034_create_school_profiles extends SchemaVersion
{
    public function version(): string { return "034"; }
    public function description(): string { return "Cria perfil escolar complementar de pessoas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_school_profiles";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `person_id` BIGINT UNSIGNED NOT NULL,
                `family_account_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `notes` TEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_school_profile_person` (`unit_id`,`person_id`,`deleted`),
                KEY `idx_school_profile_family` (`unit_id`,`family_account_id`,`status`,`deleted`),
                KEY `idx_school_profile_status` (`unit_id`,`status`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
