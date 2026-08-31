<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V062_unique_cost_occurrences extends SchemaVersion
{
    public function version(): string { return "062"; }
    public function description(): string { return "Garante uma ocorrência única por unidade para Custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expenses";
        $this->ensureIndex($db, $table, "uniq_expense_occurrence", "UNIQUE KEY `uniq_expense_occurrence` (`unit_id`,`occurrence_key`)");
    }
}
