<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V054_extend_expenses_for_costs extends SchemaVersion
{
    public function version(): string { return "054"; }
    public function description(): string { return "Evolui despesas para a entidade central de custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expenses";
        $columns = [
            "reference_month" => "VARCHAR(7) NULL",
            "issue_date" => "DATE NULL",
            "gross_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "discount_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "interest_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "penalty_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "final_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "paid_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "balance_amount" => "DECIMAL(15,2) NOT NULL DEFAULT 0.00",
            "nature" => "VARCHAR(40) NOT NULL DEFAULT 'operational_cost'",
            "cost_behavior" => "VARCHAR(30) NOT NULL DEFAULT 'unclassified'",
            "category_id" => "BIGINT UNSIGNED NULL",
            "subcategory_id" => "BIGINT UNSIGNED NULL",
            "resource_id" => "BIGINT UNSIGNED NULL",
            "installment_group_id" => "VARCHAR(64) NULL",
            "installment_number" => "INT UNSIGNED NULL",
            "installment_total" => "INT UNSIGNED NULL",
            "recurrence_id" => "BIGINT UNSIGNED NULL",
            "occurrence_key" => "VARCHAR(140) NULL",
            "cancel_reason" => "TEXT NULL",
            "source_type" => "VARCHAR(40) NULL",
            "source_id" => "BIGINT UNSIGNED NULL",
        ];
        foreach ($columns as $column => $definition) {
            $this->ensureColumn($db, $table, $column, $definition);
        }

        $db->query("UPDATE `{$table}` SET issue_date = expense_date WHERE issue_date IS NULL");
        $db->query("UPDATE `{$table}` SET reference_month = LEFT(expense_date, 7) WHERE reference_month IS NULL OR reference_month = ''");
        $db->query("UPDATE `{$table}` SET gross_amount = amount WHERE gross_amount = 0 AND amount > 0");
        $db->query("UPDATE `{$table}` SET final_amount = amount WHERE final_amount = 0 AND amount > 0");
        $db->query("UPDATE `{$table}` SET paid_amount = amount, balance_amount = 0 WHERE status = 'paid' AND paid_amount = 0");
        $db->query("UPDATE `{$table}` SET balance_amount = GREATEST(final_amount - paid_amount, 0) WHERE balance_amount = 0 AND final_amount > paid_amount AND status <> 'paid'");

        $this->ensureIndex($db, $table, "idx_expense_costs", "KEY `idx_expense_costs` (`unit_id`,`reference_month`,`status`,`deleted`)");
        $this->ensureIndex($db, $table, "idx_expense_due", "KEY `idx_expense_due` (`unit_id`,`due_date`,`status`,`deleted`)");
        $this->ensureIndex($db, $table, "idx_expense_classification", "KEY `idx_expense_classification` (`unit_id`,`nature`,`cost_behavior`,`category_id`,`cost_center_id`,`deleted`)");
        $this->ensureIndex($db, $table, "idx_expense_occurrence", "KEY `idx_expense_occurrence` (`unit_id`,`occurrence_key`)");
        $this->ensureIndex($db, $table, "idx_expense_installment", "KEY `idx_expense_installment` (`unit_id`,`installment_group_id`,`installment_number`)");
    }
}
