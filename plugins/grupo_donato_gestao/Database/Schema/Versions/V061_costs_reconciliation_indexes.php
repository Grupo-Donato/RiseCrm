<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V061_costs_reconciliation_indexes extends SchemaVersion
{
    public function version(): string { return "061"; }
    public function description(): string { return "Consolida índices e colunas de reconciliação de Custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $expenses = $prefix . "gd_expenses";
        $payments = $prefix . "gd_expense_payments";
        $this->ensureIndex($db, $expenses, "idx_expense_paid", "KEY `idx_expense_paid` (`unit_id`,`paid_date`,`paid_amount`,`deleted`)");
        $this->ensureIndex($db, $expenses, "uniq_expense_occurrence", "UNIQUE KEY `uniq_expense_occurrence` (`unit_id`,`occurrence_key`)");
        $this->ensureIndex($db, $payments, "idx_expense_payment_account", "KEY `idx_expense_payment_account` (`unit_id`,`financial_account_id`,`payment_date`,`status`)");
    }
}
