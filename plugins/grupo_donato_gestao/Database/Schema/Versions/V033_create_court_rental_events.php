<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V033_create_court_rental_events extends SchemaVersion
{
    public function version(): string { return "033"; }
    public function description(): string { return "Cria o histórico append-only das locações comerciais."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rental_events";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `event_type` VARCHAR(40) NOT NULL,
                `from_status` VARCHAR(20) NULL,
                `to_status` VARCHAR(20) NULL,
                `reason` VARCHAR(255) NULL,
                `payload` MEDIUMTEXT NULL,
                `actor_type` VARCHAR(20) NOT NULL DEFAULT 'system',
                `actor_id` BIGINT UNSIGNED NULL,
                `request_id` VARCHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_rental_events_rental` (`unit_id`,`rental_id`,`id`),
                KEY `idx_rental_events_type` (`unit_id`,`event_type`,`created_at`)
            ) ENGINE=InnoDB
        ");
    }
}
