<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Define, uma única vez, a duração histórica das churrasqueiras como 5 horas. */
final class V069_backfill_barbecue_duration extends SchemaVersion
{
    private const MARKER = "barbecue_rentals_existing_duration_5h";

    public function version(): string { return "069"; }

    public function description(): string
    {
        return "Define em 5 horas a duração das locações existentes de churrasqueira.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $settings = $prefix . "gd_settings";
        $marker = $db->table($settings)
            ->select("id")
            ->where("unit_id IS NULL", null, false)
            ->where("key", self::MARKER)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
        if ($marker) { return; }

        $rentals = $prefix . "gd_barbecue_rentals";
        $links = $prefix . "gd_barbecue_rental_schedule_links";
        $bookings = $prefix . "gd_bookings";
        $booking_resources = $prefix . "gd_booking_resources";
        $resources = $prefix . "gd_resources";
        $series = $prefix . "gd_booking_series";
        $court_rentals = $prefix . "gd_court_rentals";
        $court_links = $prefix . "gd_court_rental_schedule_links";
        $barbecue_type = "'" . Constants::BARBECUE_RESOURCE_TYPE . "'";

        // Avulsas: o intervalo do booking é a fonte de verdade para a agenda.
        $db->query("UPDATE `$bookings` b
            INNER JOIN `$links` l ON l.booking_id = b.id AND l.unit_id = b.unit_id
            INNER JOIN `$rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            SET b.ends_at_utc = DATE_ADD(b.starts_at_utc, INTERVAL 300 MINUTE),
                b.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND b.deleted = 0");

        $db->query("UPDATE `$booking_resources` br
            INNER JOIN `$bookings` b ON b.id = br.booking_id AND b.unit_id = br.unit_id
            INNER JOIN `$links` l ON l.booking_id = b.id AND l.unit_id = b.unit_id
            INNER JOIN `$rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            INNER JOIN `$resources` resource ON resource.id = br.resource_id AND resource.unit_id = br.unit_id
            SET br.occupancy_ends_at_utc = DATE_ADD(DATE_ADD(b.starts_at_utc, INTERVAL 300 MINUTE), INTERVAL br.buffer_after_minutes MINUTE),
                br.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND b.deleted = 0 AND br.deleted = 0
              AND resource.deleted = 0 AND resource.resource_type = {$barbecue_type}");

        // Combos antigos: quadra e churrasqueira ainda compartilham o mesmo
        // booking. Mantemos o fim da quadra intacto e corrigimos apenas a
        // ocupação da churrasqueira para cinco horas.
        $db->query("UPDATE `$booking_resources` br
            INNER JOIN `$bookings` b ON b.id = br.booking_id AND b.unit_id = br.unit_id
            INNER JOIN `$court_links` l ON l.booking_id = b.id AND l.unit_id = b.unit_id
            INNER JOIN `$court_rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            INNER JOIN `$resources` resource ON resource.id = br.resource_id AND resource.unit_id = br.unit_id
            SET br.occupancy_ends_at_utc = DATE_ADD(DATE_ADD(b.starts_at_utc, INTERVAL 300 MINUTE), INTERVAL br.buffer_after_minutes MINUTE),
                br.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND b.deleted = 0 AND br.deleted = 0
              AND resource.deleted = 0 AND resource.resource_type = {$barbecue_type}");

        // Mensalistas: ajusta a série e todas as ocorrências já geradas.
        $db->query("UPDATE `$series` s
            INNER JOIN `$links` l ON l.booking_series_id = s.id AND l.unit_id = s.unit_id
            INNER JOIN `$rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            SET s.local_end_time = TIME(DATE_ADD(CONCAT('2000-01-01 ', s.local_start_time), INTERVAL 300 MINUTE)),
                s.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND s.deleted = 0");

        $db->query("UPDATE `$bookings` b
            INNER JOIN `$links` l ON l.booking_series_id = b.series_id AND l.unit_id = b.unit_id
            INNER JOIN `$rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            SET b.ends_at_utc = DATE_ADD(b.starts_at_utc, INTERVAL 300 MINUTE),
                b.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND b.deleted = 0 AND b.series_id IS NOT NULL");

        $db->query("UPDATE `$booking_resources` br
            INNER JOIN `$bookings` b ON b.id = br.booking_id AND b.unit_id = br.unit_id
            INNER JOIN `$links` l ON l.booking_series_id = b.series_id AND l.unit_id = b.unit_id
            INNER JOIN `$rentals` r ON r.id = l.rental_id AND r.unit_id = l.unit_id
            INNER JOIN `$resources` resource ON resource.id = br.resource_id AND resource.unit_id = br.unit_id
            SET br.occupancy_ends_at_utc = DATE_ADD(DATE_ADD(b.starts_at_utc, INTERVAL 300 MINUTE), INTERVAL br.buffer_after_minutes MINUTE),
                br.updated_at = UTC_TIMESTAMP()
            WHERE r.deleted = 0 AND l.deleted = 0 AND l.link_kind <> 'historical'
              AND b.deleted = 0 AND b.series_id IS NOT NULL AND br.deleted = 0
              AND resource.deleted = 0 AND resource.resource_type = {$barbecue_type}");

        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
        $db->table($settings)->insert([
            "unit_id" => null,
            "key" => self::MARKER,
            "value" => "1",
            "value_type" => "string",
            "is_secret" => 0,
            "deleted" => 0,
            "created_at" => $now,
            "updated_at" => $now,
            "created_by" => null,
            "updated_by" => null,
        ]);
    }
}
