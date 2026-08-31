<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/**
 * Dá estado e snapshot de origem/destino às exceções de ocorrências.
 *
 * A tabela genérica de exceções já é usada pelo gerador de séries. A extensão
 * mantém esse desenho e permite que um remanejamento seja revertido sem
 * apagar ou recriar a ocorrência original.
 */
final class V063_extend_series_exceptions_for_reschedules extends SchemaVersion
{
    public function version(): string { return "063"; }
    public function description(): string { return "Estende exceções de séries para remanejamentos pontuais reversíveis."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_booking_series_exceptions";
        $this->ensureColumn($db, $table, "revision", "SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `exception_type`");
        $this->ensureColumn($db, $table, "status", "VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `revision`");
        $this->ensureColumn($db, $table, "original_resource_id", "BIGINT UNSIGNED NULL AFTER `status`");
        $this->ensureColumn($db, $table, "original_starts_at_utc", "DATETIME NULL AFTER `original_resource_id`");
        $this->ensureColumn($db, $table, "original_ends_at_utc", "DATETIME NULL AFTER `original_starts_at_utc`");
        $this->ensureColumn($db, $table, "new_resource_id", "BIGINT UNSIGNED NULL AFTER `original_ends_at_utc`");
        $this->ensureColumn($db, $table, "new_starts_at_utc", "DATETIME NULL AFTER `new_resource_id`");
        $this->ensureColumn($db, $table, "new_ends_at_utc", "DATETIME NULL AFTER `new_starts_at_utc`");
        $this->ensureColumn($db, $table, "notes", "TEXT NULL AFTER `reason`");
        $this->ensureColumn($db, $table, "reverted_at", "DATETIME NULL AFTER `created_by`");
        $this->ensureColumn($db, $table, "reverted_by", "BIGINT UNSIGNED NULL AFTER `reverted_at`");
        $this->ensureColumn($db, $table, "reversal_reason", "VARCHAR(255) NULL AFTER `reverted_by`");
        $this->ensureColumn($db, $table, "updated_at", "DATETIME NULL AFTER `reversal_reason`");
        $this->ensureColumn($db, $table, "updated_by", "BIGINT UNSIGNED NULL AFTER `updated_at`");

        // V028 allowed one exception per type. Reschedules need revisions so
        // that a reverted occurrence can be remanejada again without losing
        // the previous record.
        $index = $db->query(
            "SHOW INDEX FROM `" . $table . "` WHERE Key_name=" . $db->escape("uniq_series_exception")
        );
        if ($index && $index->getRow()) {
            $result = $db->query("ALTER TABLE `" . $table . "` DROP INDEX `uniq_series_exception`");
            if ($result === false) {
                $error = $db->error();
                throw new \RuntimeException($error["message"] ?? "Could not drop old series exception index.");
            }
        }
        $this->ensureIndex(
            $db,
            $table,
            "uniq_series_exception_revision",
            "UNIQUE KEY `uniq_series_exception_revision` (`series_id`,`occurrence_key`,`exception_type`,`revision`)"
        );
        $this->ensureIndex(
            $db,
            $table,
            "idx_series_reschedule_status",
            "KEY `idx_series_reschedule_status` (`unit_id`,`series_id`,`local_date`,`exception_type`,`status`)"
        );
        $this->ensureIndex(
            $db,
            $table,
            "idx_booking_reschedule_status",
            "KEY `idx_booking_reschedule_status` (`unit_id`,`booking_id`,`exception_type`,`status`)"
        );
    }
}
