<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V013_create_product_categories extends SchemaVersion
{
    public function version(): string { return "013"; }
    public function description(): string { return "Cria as categorias do catálogo (produtos e serviços)."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_product_categories";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `parent_id` BIGINT UNSIGNED NULL,
                `code` VARCHAR(40) NOT NULL,
                `name` VARCHAR(150) NOT NULL,
                `description` TEXT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `active_code_key` VARCHAR(120) AS (IF(`deleted`=0, CONCAT(`unit_id`, ':', `code`), NULL)) PERSISTENT,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_code` (`active_code_key`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_parent` (`parent_id`),
                KEY `idx_status` (`status`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
