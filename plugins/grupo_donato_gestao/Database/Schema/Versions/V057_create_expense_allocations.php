<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V057_create_expense_allocations extends SchemaVersion
{
    public function version(): string { return "057"; }
    public function description(): string { return "Cria rateio por centro de resultado."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expense_allocations";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `expense_id` BIGINT UNSIGNED NOT NULL,
            `business_area_id` BIGINT UNSIGNED NULL,
            `cost_center_id` BIGINT UNSIGNED NULL,
            `resource_id` BIGINT UNSIGNED NULL,
            `percentage` DECIMAL(7,4) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_expense_alloc_expense` (`unit_id`,`expense_id`,`deleted`),
            KEY `idx_expense_alloc_center` (`unit_id`,`cost_center_id`,`resource_id`,`deleted`)
        ) ENGINE=InnoDB");
    }
}
