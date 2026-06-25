<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V010_create_account_people extends SchemaVersion
{
    public function version(): string { return "010"; }
    public function description(): string { return "Cria relações N:N entre contas e pessoas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_account_people";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `account_id` BIGINT UNSIGNED NOT NULL,
                `person_id` BIGINT UNSIGNED NOT NULL,
                `role` VARCHAR(40) NOT NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `is_financial_responsible` TINYINT(1) NOT NULL DEFAULT 0,
                `receives_notifications` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `start_date` DATE NULL,
                `end_date` DATE NULL,
                `notes` TEXT NULL,
                `active_relation_key` VARCHAR(190) AS (IF(`deleted`=0 AND `status`='active', CONCAT(`account_id`, ':', `person_id`, ':', `role`), NULL)) PERSISTENT,
                `primary_account_id` BIGINT UNSIGNED AS (IF(`deleted`=0 AND `status`='active' AND `is_primary`=1, `account_id`, NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_relation` (`active_relation_key`),
                UNIQUE KEY `uniq_primary_account` (`primary_account_id`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_account` (`account_id`),
                KEY `idx_person` (`person_id`),
                KEY `idx_role` (`role`),
                KEY `idx_status` (`status`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
