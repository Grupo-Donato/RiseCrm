<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V027_extend_bookings_for_series extends SchemaVersion
{
    public function version(): string { return "027"; }
    public function description(): string { return "Vincula reservas a séries e ocorrências."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_bookings";
        $this->ensureColumn($db, $table, "series_id", "BIGINT UNSIGNED NULL AFTER `source_id`");
        $this->ensureColumn($db, $table, "series_occurrence_key", "VARCHAR(40) NULL AFTER `series_id`");
        $this->ensureColumn($db, $table, "series_local_date", "DATE NULL AFTER `series_occurrence_key`");
        $this->ensureColumn($db, $table, "is_series_exception", "TINYINT(1) NOT NULL DEFAULT 0 AFTER `series_local_date`");
        $this->ensureColumn($db, $table, "detached_from_series", "TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_series_exception`");
        $this->ensureIndex($db, $table, "uniq_series_occurrence", "UNIQUE KEY `uniq_series_occurrence` (`series_id`,`series_occurrence_key`)");
        $this->ensureIndex($db, $table, "idx_series_local_date", "KEY `idx_series_local_date` (`unit_id`,`series_id`,`series_local_date`)");
    }
}
