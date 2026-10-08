<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Stores event participation fees on the event-wide athlete roster. */
final class V075_add_academy_roster_fee extends SchemaVersion
{
    public function version(): string { return "075"; }

    public function description(): string
    {
        return "Move a taxa individual do evento para a lista-base de atletas.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $roster = $prefix . "gd_academy_event_roster";
        $events = $prefix . "gd_academy_events";
        $participants = $prefix . "gd_academy_event_participants";

        if (!$db->tableExists($roster)) return;
        $this->ensureColumn($db, $roster, "participation_amount", "DECIMAL(15,2) NULL DEFAULT NULL AFTER `responsible_id`");

        if (!$db->tableExists($events) || !$db->tableExists($participants)) return;
        $sql = "UPDATE `$roster` rr
            JOIN `$events` ev ON ev.id=rr.event_id AND ev.unit_id=rr.unit_id
            SET rr.participation_amount=CASE
                WHEN ev.default_participation_amount>0 THEN ev.default_participation_amount
                ELSE COALESCE((SELECT MAX(p.amount) FROM `$participants` p WHERE p.unit_id=rr.unit_id AND p.roster_id=rr.id AND p.deleted=0),0.00)
            END
            WHERE rr.participation_amount IS NULL AND rr.deleted=0";
        $result = $db->query($sql);
        if ($result === false) {
            $error = $db->error();
            throw new \RuntimeException($error["message"] ?? "Unable to migrate event roster fees.");
        }
    }
}
