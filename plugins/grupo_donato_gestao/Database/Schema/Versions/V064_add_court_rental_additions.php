<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/**
 * Registra adicionais operacionais da locação de quadra.
 *
 * Os campos pertencem à locação (inclusive ao contrato mensal) e não geram
 * qualquer item financeiro ou controle de estoque.
 */
final class V064_add_court_rental_additions extends SchemaVersion
{
    public function version(): string { return "064"; }
    public function description(): string { return "Adiciona indicadores operacionais de colete e bola às locações de quadra."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rentals";
        $this->ensureColumn($db, $table, "has_vest", "TINYINT(1) NOT NULL DEFAULT 0 AFTER `commercial_notes`");
        $this->ensureColumn($db, $table, "has_ball", "TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_vest`");
    }
}
