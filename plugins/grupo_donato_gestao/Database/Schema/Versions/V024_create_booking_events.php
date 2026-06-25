<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V024_create_booking_events extends SchemaVersion
{
    public function version(): string { return "024"; }
    public function description(): string { return "Cria histórico append-only das reservas."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_events";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `booking_id` BIGINT UNSIGNED NOT NULL,
                `event_type` VARCHAR(40) NOT NULL,
                `from_status` VARCHAR(30) NULL,
                `to_status` VARCHAR(30) NULL,
                `reason` VARCHAR(255) NULL,
                `payload` MEDIUMTEXT NULL,
                `actor_type` VARCHAR(30) NOT NULL DEFAULT 'system',
                `actor_id` BIGINT UNSIGNED NULL,
                `request_id` VARCHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_booking_history` (`unit_id`,`booking_id`,`created_at`,`id`),
                KEY `idx_event_type` (`unit_id`,`event_type`,`created_at`),
                KEY `idx_request` (`request_id`)
            ) ENGINE=InnoDB
        ");
    }
}
