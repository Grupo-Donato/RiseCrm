<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V012_create_addresses extends SchemaVersion
{
    public function version(): string { return "012"; }
    public function description(): string { return "Cria endereços das contas de clientes."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_addresses";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `account_id` BIGINT UNSIGNED NOT NULL,
                `address_type` VARCHAR(20) NOT NULL,
                `postal_code` VARCHAR(20) NULL,
                `postal_code_normalized` VARCHAR(20) NULL,
                `street` VARCHAR(190) NULL,
                `number` VARCHAR(30) NULL,
                `complement` VARCHAR(120) NULL,
                `district` VARCHAR(120) NULL,
                `city` VARCHAR(120) NULL,
                `state` VARCHAR(80) NULL,
                `country` VARCHAR(80) NULL,
                `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `primary_account_id` BIGINT UNSIGNED AS (IF(`deleted`=0 AND `status`='active' AND `is_primary`=1, `account_id`, NULL)) STORED,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_primary_address` (`primary_account_id`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_account` (`account_id`),
                KEY `idx_type` (`address_type`),
                KEY `idx_postal` (`postal_code_normalized`),
                KEY `idx_status` (`status`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
