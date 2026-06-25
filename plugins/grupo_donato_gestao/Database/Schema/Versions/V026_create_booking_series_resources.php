<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V026_create_booking_series_resources extends SchemaVersion
{
    public function version(): string { return "026"; }
    public function description(): string { return "Cria recursos padrão das séries."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_series_resources";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `series_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `buffer_before_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `buffer_after_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_series_resource_active` (`series_id`,`resource_id`,`deleted`),
                KEY `idx_series_resources_unit` (`unit_id`,`series_id`,`deleted`),
                KEY `idx_series_resources_resource` (`unit_id`,`resource_id`,`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
