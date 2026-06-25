<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V031_create_court_rental_schedule_links extends SchemaVersion
{
    public function version(): string { return "031"; }
    public function description(): string { return "Cria vínculos entre locação comercial e reservas/séries."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rental_schedule_links";
        // active_booking_guard / active_series_guard são colunas-guarda mantidas
        // pelo Service: recebem booking_id/booking_series_id enquanto o vínculo
        // está ativo (link_kind != historical e deleted = 0) e NULL caso contrário.
        // O UNIQUE sobre cada guarda impede que a mesma reserva/série pertença a
        // duas locações ativas; NULLs múltiplos não colidem no MariaDB.
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `booking_id` BIGINT UNSIGNED NULL,
                `booking_series_id` BIGINT UNSIGNED NULL,
                `link_kind` VARCHAR(20) NOT NULL DEFAULT 'primary',
                `active_booking_guard` BIGINT UNSIGNED NULL,
                `active_series_guard` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_active_booking` (`unit_id`,`active_booking_guard`),
                UNIQUE KEY `uniq_active_series` (`unit_id`,`active_series_guard`),
                KEY `idx_link_rental` (`unit_id`,`rental_id`,`deleted`),
                KEY `idx_link_booking` (`unit_id`,`booking_id`),
                KEY `idx_link_series` (`unit_id`,`booking_series_id`)
            ) ENGINE=InnoDB
        ");
    }
}
