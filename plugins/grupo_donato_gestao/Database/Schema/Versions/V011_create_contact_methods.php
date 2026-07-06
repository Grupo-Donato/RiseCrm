<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V011_create_contact_methods extends SchemaVersion
{
    public function version(): string { return "011"; }
    public function description(): string { return "Cria métodos de contato das pessoas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_contact_methods";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `person_id` BIGINT UNSIGNED NOT NULL,
                `contact_type` VARCHAR(20) NOT NULL,
                `label` VARCHAR(80) NULL,
                `value` VARCHAR(190) NOT NULL,
                `normalized_value` VARCHAR(190) NOT NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `receives_notifications` TINYINT(1) NOT NULL DEFAULT 0,
                `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `primary_contact_key` VARCHAR(100) AS (IF(`deleted`=0 AND `status`='active' AND `is_primary`=1, CONCAT(`person_id`, ':', `contact_type`), NULL)) STORED,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_primary_contact` (`primary_contact_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_person` (`person_id`),
                KEY `idx_type` (`contact_type`),
                KEY `idx_normalized` (`normalized_value`),
                KEY `idx_status` (`status`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
