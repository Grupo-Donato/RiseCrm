<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Separates the event-wide athlete list from category convocations. */
final class V074_create_academy_event_roster extends SchemaVersion
{
    public function version(): string { return "074"; }

    public function description(): string
    {
        return "Cria a lista-base de atletas por evento e vincula convocacoes existentes.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $roster = $prefix . "gd_academy_event_roster";
        $participants = $prefix . "gd_academy_event_participants";

        $this->ensureTable($db, $roster, "
            CREATE TABLE IF NOT EXISTS `{$roster}` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `event_id` BIGINT UNSIGNED NOT NULL,
                `athlete_type` VARCHAR(20) NOT NULL DEFAULT 'internal',
                `student_id` BIGINT UNSIGNED NULL,
                `external_athlete_id` BIGINT UNSIGNED NULL,
                `responsible_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `active_student_id` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`deleted` = 0, `student_id`, NULL)) STORED,
                `active_external_athlete_id` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`deleted` = 0, `external_athlete_id`, NULL)) STORED,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_academy_roster_student` (`unit_id`,`event_id`,`active_student_id`),
                UNIQUE KEY `uniq_academy_roster_external` (`unit_id`,`event_id`,`active_external_athlete_id`),
                KEY `idx_academy_roster_event` (`unit_id`,`event_id`,`status`,`deleted`),
                KEY `idx_academy_roster_student` (`unit_id`,`student_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $this->ensureColumn($db, $participants, "roster_id", "BIGINT UNSIGNED NULL AFTER `category_id`");
        $this->ensureIndex($db, $participants, "idx_academy_participants_roster", "KEY `idx_academy_participants_roster` (`unit_id`,`roster_id`,`deleted`)");

        // Existing installations already have category convocations. Build one
        // event-wide roster row for each distinct athlete and link every active
        // category assignment to it. This is intentionally idempotent.
        if (!$db->tableExists($participants)) return;
        $rows = $db->table($participants)
            ->where("deleted", 0)
            ->get()->getResult();
        foreach ($rows as $row) {
            $identityField = ((string) ($row->athlete_type ?? "internal")) === "external"
                ? "external_athlete_id"
                : "student_id";
            $identity = (int) ($row->{$identityField} ?? 0);
            if ((int) ($row->event_id ?? 0) <= 0 || $identity <= 0) continue;

            $existing = $db->table($roster)
                ->where("unit_id", (int) $row->unit_id)
                ->where("event_id", (int) $row->event_id)
                ->where($identityField, $identity)
                ->where("deleted", 0)
                ->get(1)->getRow();
            if (!$existing) {
                $now = gmdate("Y-m-d H:i:s");
                $db->table($roster)->insert([
                    "unit_id" => (int) $row->unit_id,
                    "event_id" => (int) $row->event_id,
                    "athlete_type" => (string) ($row->athlete_type ?? "internal"),
                    "student_id" => $row->student_id ?: null,
                    "external_athlete_id" => $row->external_athlete_id ?: null,
                    "responsible_id" => $row->responsible_id ?: null,
                    "notes" => $row->notes ?? null,
                    "status" => "active",
                    "lock_version" => 1,
                    "created_at" => $now,
                    "updated_at" => $now,
                    "deleted" => 0,
                ]);
                $existing = $db->table($roster)->where("id", (int) $db->insertID())->get(1)->getRow();
            }
            if ($existing && empty($row->roster_id)) {
                $db->table($participants)
                    ->where("id", (int) $row->id)
                    ->update(["roster_id" => (int) $existing->id]);
            }
        }
    }
}
