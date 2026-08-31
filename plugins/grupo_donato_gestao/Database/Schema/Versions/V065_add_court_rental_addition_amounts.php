<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Registra o valor comercial dos adicionais operacionais da locacao. */
final class V065_add_court_rental_addition_amounts extends SchemaVersion
{
    public function version(): string { return "065"; }
    public function description(): string { return "Adiciona os valores de colete e bola as locacoes de quadra."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rentals";
        $this->ensureColumn($db, $table, "vest_amount", "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `has_ball`");
        $this->ensureColumn($db, $table, "ball_amount", "DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER `vest_amount`");
    }
}
