<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V018_create_prices extends SchemaVersion
{
    public function version(): string { return "018"; }
    public function description(): string { return "Cria os preços de produtos/variações/recursos por tabela."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_prices";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `price_list_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NOT NULL,
                `variant_id` BIGINT UNSIGNED NULL,
                `resource_id` BIGINT UNSIGNED NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `reference_cost` DECIMAL(15,2) NULL,
                `minimum_quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
                `valid_from` DATE NULL,
                `valid_until` DATE NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `active_scope_key` VARCHAR(190) AS (IF(`deleted`=0 AND `status`='active', CONCAT(`price_list_id`, ':', `product_id`, ':', IFNULL(`variant_id`,0), ':', IFNULL(`resource_id`,0), ':', `minimum_quantity`, ':', IFNULL(`valid_from`,'0000-00-00')), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_scope` (`active_scope_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_list` (`price_list_id`),
                KEY `idx_product` (`product_id`),
                KEY `idx_variant` (`variant_id`),
                KEY `idx_resource` (`resource_id`),
                KEY `idx_status` (`status`),
                KEY `idx_valid` (`valid_from`, `valid_until`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
