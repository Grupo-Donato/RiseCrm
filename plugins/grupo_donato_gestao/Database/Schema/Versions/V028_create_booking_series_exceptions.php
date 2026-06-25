<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V028_create_booking_series_exceptions extends SchemaVersion
{
    public function version(): string { return "028"; }
    public function description(): string { return "Cria exceções históricas das séries."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_series_exceptions";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `series_id` BIGINT UNSIGNED NOT NULL,
                `booking_id` BIGINT UNSIGNED NULL,
                `replacement_series_id` BIGINT UNSIGNED NULL,
                `occurrence_key` VARCHAR(40) NOT NULL,
                `local_date` DATE NOT NULL,
                `exception_type` VARCHAR(24) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `payload` MEDIUMTEXT NULL,
                `created_at` DATETIME NOT NULL,
                `created_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_series_exception` (`series_id`,`occurrence_key`,`exception_type`),
                KEY `idx_series_exception_unit` (`unit_id`,`series_id`,`local_date`),
                KEY `idx_series_exception_booking` (`unit_id`,`booking_id`)
            ) ENGINE=InnoDB
        ");
    }
}
