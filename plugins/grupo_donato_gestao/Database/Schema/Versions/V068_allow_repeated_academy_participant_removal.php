<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Keeps duplicate protection for active participants without limiting removal history. */
final class V068_allow_repeated_academy_participant_removal extends SchemaVersion
{
    public function version(): string { return "068"; }

    public function description(): string
    {
        return "Permite convocar e remover novamente o mesmo atleta sem conflito com o histórico lógico.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_academy_event_participants";
        if (!$db->tableExists($table)) return;

        $this->dropIndexIfExists($db, $table, "uniq_academy_participant_student");
        $this->dropIndexIfExists($db, $table, "uniq_academy_participant_external");

        $this->ensureColumn(
            $db,
            $table,
            "active_student_id",
            "BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`deleted` = 0, `student_id`, NULL)) STORED"
        );
        $this->ensureColumn(
            $db,
            $table,
            "active_external_athlete_id",
            "BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`deleted` = 0, `external_athlete_id`, NULL)) STORED"
        );
        $this->ensureIndex(
            $db,
            $table,
            "uniq_academy_active_student",
            "UNIQUE KEY `uniq_academy_active_student` (`unit_id`,`category_id`,`active_student_id`)"
        );
        $this->ensureIndex(
            $db,
            $table,
            "uniq_academy_active_external",
            "UNIQUE KEY `uniq_academy_active_external` (`unit_id`,`category_id`,`active_external_athlete_id`)"
        );
    }

    private function dropIndexIfExists(BaseConnection $db, string $table, string $index): void
    {
        $exists = $db->query(
            "SHOW INDEX FROM `" . $table . "` WHERE Key_name=" . $db->escape($index)
        )->getRow();
        if ($exists) $db->query("ALTER TABLE `" . $table . "` DROP INDEX `" . $index . "`");
    }
}
