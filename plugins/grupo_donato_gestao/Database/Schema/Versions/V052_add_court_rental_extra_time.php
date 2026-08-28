<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Guarda o acréscimo de permanência sem criar uma nova reserva. */
class V052_add_court_rental_extra_time extends SchemaVersion
{
    public function version(): string { return "052"; }

    public function description(): string { return "Adiciona acréscimo de permanência às locações."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rentals";
        if (!$db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($db, $table, "extra_time_minutes", "INT UNSIGNED NOT NULL DEFAULT 0 AFTER `commercial_notes`");
        $this->ensureColumn($db, $table, "extra_time_amount", "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `extra_time_minutes`");
        $this->ensureColumn($db, $table, "extra_time_notes", "TEXT NULL AFTER `extra_time_amount`");
    }
}
