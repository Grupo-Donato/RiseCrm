<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V060_create_cost_budgets extends SchemaVersion
{
    public function version(): string { return "060"; }
    public function description(): string { return "Cria orçamento mensal de custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_cost_budgets";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `budget_key` VARCHAR(190) NOT NULL,
            `reference_month` VARCHAR(7) NOT NULL,
            `name` VARCHAR(190) NOT NULL,
            `category_id` BIGINT UNSIGNED NULL,
            `business_area_id` BIGINT UNSIGNED NULL,
            `cost_center_id` BIGINT UNSIGNED NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `notes` TEXT NULL,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_cost_budget_key` (`unit_id`,`budget_key`),
            KEY `idx_cost_budget_month` (`unit_id`,`reference_month`,`status`,`deleted`),
            KEY `idx_cost_budget_scope` (`unit_id`,`category_id`,`cost_center_id`,`deleted`)
        ) ENGINE=InnoDB");
    }
}
