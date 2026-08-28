<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Marca pagamentos de entrada sem criar uma segunda estrutura financeira. */
class V051_add_payment_type extends SchemaVersion
{
    public function version(): string { return "051"; }

    public function description(): string { return "Diferencia sinal de pagamentos regulares."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $this->ensureColumn(
            $db,
            $prefix . "gd_payments",
            "payment_type",
            "VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER `payment_method`"
        );
    }
}
