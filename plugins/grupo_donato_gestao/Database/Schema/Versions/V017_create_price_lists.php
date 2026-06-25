<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V017_create_price_lists extends SchemaVersion
{
    public function version(): string { return "017"; }
    public function description(): string { return "Cria as tabelas (listas) de preço."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_price_lists";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` TEXT NULL,
                `currency` CHAR(3) NOT NULL DEFAULT 'BRL',
                `priority` INT NOT NULL DEFAULT 0,
                `valid_from` DATE NULL,
                `valid_until` DATE NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `active_code_key` VARCHAR(120) AS (IF(`deleted`=0, CONCAT(`unit_id`, ':', `code`), NULL)) PERSISTENT,
                `default_list_key` BIGINT UNSIGNED AS (IF(`deleted`=0 AND `status`='active' AND `is_default`=1, `unit_id`, NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_code` (`active_code_key`),
                UNIQUE KEY `uniq_default_list` (`default_list_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_status` (`status`),
                KEY `idx_priority` (`priority`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
