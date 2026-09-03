<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Remove da ocupação reservas que ainda ficaram ligadas a uma locação
 * comercial já cancelada/arquivada. Isso protege agenda, disponibilidade e
 * conflito mesmo quando existem dados antigos que não foram encerrados no
 * momento do cancelamento.
 */
final class BookingOccupancyFilter
{
    public static function excludeCancelledRentals($db, $query, string $booking_table): void
    {
        $booking = "`{$booking_table}`";
        $clauses = [];

        foreach ([
            ["gd_court_rental_schedule_links", "gd_court_rentals"],
            ["gd_barbecue_rental_schedule_links", "gd_barbecue_rentals"],
        ] as [$links_suffix, $rentals_suffix]) {
            $links = $db->prefixTable($links_suffix);
            $rentals = $db->prefixTable($rentals_suffix);
            if (!$db->tableExists($links) || !$db->tableExists($rentals)) {
                continue;
            }

            $clauses[] = "NOT EXISTS (
                SELECT 1
                FROM `{$links}` AS rental_link
                INNER JOIN `{$rentals}` AS rental
                    ON rental.id = rental_link.rental_id
                   AND rental.unit_id = rental_link.unit_id
                WHERE rental_link.unit_id = {$booking}.unit_id
                  AND rental_link.deleted = 0
                  AND rental_link.link_kind <> 'historical'
                  AND rental.deleted = 0
                  AND rental.status IN ('cancelled', 'archived')
                  AND (
                      rental_link.booking_id = {$booking}.id
                      OR (
                          rental_link.booking_series_id IS NOT NULL
                          AND {$booking}.series_id IS NOT NULL
                          AND rental_link.booking_series_id = {$booking}.series_id
                      )
                  )
            )";
        }

        if ($clauses) {
            $query->where("(" . implode(" AND ", $clauses) . ")", null, false);
        }
    }
}
