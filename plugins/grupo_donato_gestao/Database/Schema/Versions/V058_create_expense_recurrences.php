<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V058_create_expense_recurrences extends SchemaVersion
{
    public function version(): string { return "058"; }
    public function description(): string { return "Cria templates idempotentes de custos recorrentes."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expense_recurrences";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `name` VARCHAR(190) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `payee` VARCHAR(190) NULL,
            `nature` VARCHAR(40) NOT NULL DEFAULT 'operational_cost',
            `cost_behavior` VARCHAR(30) NOT NULL DEFAULT 'fixed',
            `category_id` BIGINT UNSIGNED NULL,
            `subcategory_id` BIGINT UNSIGNED NULL,
            `business_area_id` BIGINT UNSIGNED NULL,
            `cost_center_id` BIGINT UNSIGNED NULL,
            `resource_id` BIGINT UNSIGNED NULL,
            `gross_amount` DECIMAL(15,2) NOT NULL,
            `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `interest_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `penalty_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `start_date` DATE NOT NULL,
            `end_date` DATE NULL,
            `frequency` VARCHAR(20) NOT NULL,
            `interval_value` INT UNSIGNED NOT NULL DEFAULT 1,
            `due_day` TINYINT UNSIGNED NULL,
            `next_generation` DATE NOT NULL,
            `last_generation` DATE NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `notes` TEXT NULL,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_expense_recurrence_next` (`unit_id`,`status`,`next_generation`,`deleted`),
            KEY `idx_expense_recurrence_category` (`unit_id`,`category_id`,`deleted`)
        ) ENGINE=InnoDB");
    }
}
