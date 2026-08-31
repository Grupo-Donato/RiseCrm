<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V055_create_expense_categories extends SchemaVersion
{
    public function version(): string { return "055"; }
    public function description(): string { return "Cria categorias hierárquicas de custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expense_categories";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NULL,
            `unit_scope_id` BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED,
            `parent_id` BIGINT UNSIGNED NULL,
            `code` VARCHAR(80) NOT NULL,
            `name` VARCHAR(190) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `is_system` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_expense_category_scope` (`unit_scope_id`,`code`,`deleted`),
            KEY `idx_expense_category_parent` (`parent_id`,`status`,`deleted`),
            KEY `idx_expense_category_unit` (`unit_id`,`status`,`deleted`)
        ) ENGINE=InnoDB");
        $this->ensureColumn($db, $table, "unit_scope_id", "BIGINT UNSIGNED AS (IFNULL(`unit_id`, 0)) STORED");
        $this->ensureIndex($db, $table, "uniq_expense_category_scope", "UNIQUE KEY `uniq_expense_category_scope` (`unit_scope_id`,`code`,`deleted`)");
    }
}
