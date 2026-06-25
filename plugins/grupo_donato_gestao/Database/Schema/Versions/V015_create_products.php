<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V015_create_products extends SchemaVersion
{
    public function version(): string { return "015"; }
    public function description(): string { return "Cria os produtos e serviços comercializáveis."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_products";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NULL,
                `business_area_id` BIGINT UNSIGNED NULL,
                `default_cost_center_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `description` TEXT NULL,
                `product_type` VARCHAR(30) NOT NULL DEFAULT 'service',
                `billing_mode` VARCHAR(30) NOT NULL DEFAULT 'one_time',
                `unit_of_measure` VARCHAR(30) NOT NULL DEFAULT 'unit',
                `track_stock` TINYINT(1) NOT NULL DEFAULT 0,
                `allows_variants` TINYINT(1) NOT NULL DEFAULT 0,
                `allows_discount` TINYINT(1) NOT NULL DEFAULT 0,
                `requires_resource` TINYINT(1) NOT NULL DEFAULT 0,
                `status` VARCHAR(30) NOT NULL DEFAULT 'draft',
                `rise_item_id` BIGINT UNSIGNED NULL,
                `metadata` MEDIUMTEXT NULL,
                `active_code_key` VARCHAR(120) AS (IF(`deleted`=0, CONCAT(`unit_id`, ':', `code`), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_code` (`active_code_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_category` (`category_id`),
                KEY `idx_area` (`business_area_id`),
                KEY `idx_cost_center` (`default_cost_center_id`),
                KEY `idx_type` (`product_type`),
                KEY `idx_status` (`status`),
                KEY `idx_rise_item` (`rise_item_id`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
