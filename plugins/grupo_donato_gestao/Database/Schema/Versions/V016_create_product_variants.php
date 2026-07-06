<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V016_create_product_variants extends SchemaVersion
{
    public function version(): string { return "016"; }
    public function description(): string { return "Cria as variações de produto."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_product_variants";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(190) NOT NULL,
                `barcode` VARCHAR(80) NULL,
                `attributes` MEDIUMTEXT NULL,
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `sort_order` INT NOT NULL DEFAULT 0,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `active_code_key` VARCHAR(120) AS (IF(`deleted`=0, CONCAT(`product_id`, ':', `code`), NULL)) STORED,
                `default_variant_key` BIGINT UNSIGNED AS (IF(`deleted`=0 AND `status`='active' AND `is_default`=1, `product_id`, NULL)) STORED,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_code` (`active_code_key`),
                UNIQUE KEY `uniq_default_variant` (`default_variant_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_product` (`product_id`),
                KEY `idx_status` (`status`),
                KEY `idx_barcode` (`barcode`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
