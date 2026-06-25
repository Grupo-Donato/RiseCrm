<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V032_create_court_rental_price_items extends SchemaVersion
{
    public function version(): string { return "032"; }
    public function description(): string { return "Cria o snapshot comercial (price items) da locação."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rental_price_items";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NULL,
                `variant_id` BIGINT UNSIGNED NULL,
                `resource_id` BIGINT UNSIGNED NULL,
                `price_id` BIGINT UNSIGNED NULL,
                `description` VARCHAR(255) NULL,
                `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
                `unit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `currency` VARCHAR(3) NOT NULL,
                `snapshot` MEDIUMTEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_price_item_rental` (`unit_id`,`rental_id`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
