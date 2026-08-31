<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Ledger transacional de pagamentos de Custos e seus movimentos de caixa. */
final class CostPaymentService extends CustomerDataService
{
    private string $payments_table;
    private string $cash_table;
    private CostService $costs;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->payments_table = $this->db->prefixTable("gd_expense_payments");
        $this->cash_table = $this->db->prefixTable("gd_cash_movements");
        $this->costs = new CostService($unit_id, $actor_id, $login_user);
    }

    public function pay(array $input): array
    {
        $expense_id = (int) ($input["expense_id"] ?? 0);
        $amount = DataNormalizationService::decimal($input["amount"] ?? "", 2);
        $date = $this->valid_date($input["payment_date"] ?? gmdate("Y-m-d"));
        $method = Constants::normalizePaymentMethod((string) ($input["payment_method"] ?? ""));
        $account_id = (int) ($input["financial_account_id"] ?? 0);
        if ($expense_id <= 0 || !$date || !$method || $this->compare($amount, "0.00") <= 0) throw new \DomainException("gd_invalid_cost_payment");
        $this->active_account($account_id);
        $external = DataNormalizationService::text($input["external_reference"] ?? "");
        $idempotency = trim((string) ($input["idempotency_key"] ?? ""));
        if ($idempotency === "") $idempotency = hash("sha256", implode("|", [$this->unit_id, $expense_id, $date, $amount, $account_id, $method, $external]));
        $idempotency = substr(preg_replace('/[^a-zA-Z0-9_.:\-]/', "", $idempotency) ?: hash("sha256", uniqid("cost-payment", true)), 0, 140);

        $this->db->transBegin();
        try {
            $existing = $this->db->table($this->payments_table)->where("unit_id", $this->unit_id)->where("idempotency_key", $idempotency)->where("deleted", 0)->get(1)->getRow();
            if ($existing) {
                if ((string) $existing->status !== "confirmed") throw new \DomainException("gd_payment_idempotency_reused");
                if ($this->db->transCommit() === false) throw new \RuntimeException("gd_save_failed");
                return ["id" => (int) $existing->id, "duplicate" => true, "data" => $existing];
            }
            $expense = $this->db->query("SELECT * FROM `{$this->db->prefixTable("gd_expenses")}` WHERE id=? AND unit_id=? AND deleted=0 FOR UPDATE", [$expense_id, $this->unit_id])->getRow();
            if (!$expense || (string) $expense->status === "cancelled") throw new \DomainException("gd_cost_unavailable_for_payment");
            $paid = $this->costs->paid_amount($expense_id);
            $balance = $this->sub((string) $expense->final_amount, $paid);
            if ($this->compare($amount, $balance) > 0) throw new \DomainException("gd_cost_payment_exceeds_balance");

            $sequence = new SequenceService();
            $sequence->ensure($this->unit_id, "expense_payment", "CPG-", 6, false);
            $payment_number = $sequence->next($this->unit_id, "expense_payment");
            $now = gmdate("Y-m-d H:i:s");
            $this->db->table($this->payments_table)->insert([
                "unit_id" => $this->unit_id, "expense_id" => $expense_id, "payment_number" => $payment_number,
                "financial_account_id" => $account_id, "payment_date" => $date, "amount" => $amount,
                "payment_method" => $method, "external_reference" => $external ?: null, "idempotency_key" => $idempotency,
                "status" => "confirmed", "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null,
                "created_at" => $now, "updated_at" => $now, "created_by" => $this->actor_id ?: null,
                "updated_by" => $this->actor_id ?: null, "deleted" => 0,
            ]);
            $payment_id = (int) $this->db->insertID();
            if ($payment_id <= 0) throw new \RuntimeException("gd_save_failed");
            $movement_id = $this->cash_movement($account_id, $date, "out", "expense_payment", $payment_id, "Pagamento de custo " . $expense->expense_number, $amount, null);
            $this->db->table($this->payments_table)->where("id", $payment_id)->where("unit_id", $this->unit_id)->update(["cash_movement_id" => $movement_id, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]);
            $fresh = $this->costs->update_payment_totals($expense_id);
            $payment = $this->db->table($this->payments_table)->where("id", $payment_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
            $this->audit_change("payment", "cost_payment", $payment_id, null, $payment ? (array) $payment : null, ["expense_id" => $expense_id, "cash_movement_id" => $movement_id]);
            $this->audit_change("payment_update", "cost", $expense_id, (array) $expense, (array) $fresh, ["payment_id" => $payment_id]);
            if ($this->db->transCommit() === false) throw new \RuntimeException("gd_save_failed");
            return ["id" => $payment_id, "payment_number" => $payment_number, "cash_movement_id" => $movement_id, "expense" => $fresh, "data" => $payment];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    public function reverse(int $payment_id, string $reason): array
    {
        $reason = DataNormalizationService::text($reason);
        if ($payment_id <= 0 || $reason === "") throw new \DomainException("gd_reason_required");
        $this->db->transBegin();
        try {
            $payment = $this->db->query("SELECT * FROM `{$this->payments_table}` WHERE id=? AND unit_id=? AND deleted=0 FOR UPDATE", [$payment_id, $this->unit_id])->getRow();
            if (!$payment) throw new \DomainException("gd_record_not_found");
            if (!in_array((string) $payment->status, ["confirmed", "legacy_migrated"], true)) throw new \DomainException("gd_cost_payment_already_reversed");
            $original_id = (int) ($payment->cash_movement_id ?: $payment->legacy_cash_movement_id);
            if (!$original_id && (string) $payment->status === "legacy_migrated") {
                $original = $this->db->table($this->cash_table)->where("unit_id", $this->unit_id)->where("source_type", "expense")->where("source_id", (int) $payment->expense_id)->where("movement_type", "out")->get(1)->getRow();
                $original_id = (int) ($original->id ?? 0);
            }
            if (!$original_id) throw new \DomainException("gd_cost_payment_movement_missing");
            $original = $this->db->table($this->cash_table)->where("id", $original_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
            if (!$original) throw new \DomainException("gd_cost_payment_movement_missing");
            $existing_reversal = $this->db->table($this->cash_table)->where("unit_id", $this->unit_id)->where("source_type", "expense_payment_reversal")->where("source_id", $payment_id)->where("movement_type", "in")->get(1)->getRow();
            if ($existing_reversal) throw new \DomainException("gd_cost_payment_already_reversed");

            $account_id = (int) ($payment->financial_account_id ?: $original->financial_account_id);
            $movement_id = $this->cash_movement($account_id, gmdate("Y-m-d"), "in", "expense_payment_reversal", $payment_id, "Estorno do pagamento " . $payment->payment_number, (string) $payment->amount, $original_id);
            $now = gmdate("Y-m-d H:i:s");
            $this->db->table($this->payments_table)->where("id", $payment_id)->where("unit_id", $this->unit_id)->update(["status" => "reversed", "reversed_at" => $now, "reversed_by" => $this->actor_id ?: null, "reversal_reason" => $reason, "reversal_cash_movement_id" => $movement_id, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]);
            $expense = $this->costs->get_scoped((int) $payment->expense_id);
            $fresh = $this->costs->update_payment_totals((int) $payment->expense_id);
            $this->audit_change("payment_reversal", "cost_payment", $payment_id, (array) $payment, (array) $this->db->table($this->payments_table)->where("id", $payment_id)->where("unit_id", $this->unit_id)->get(1)->getRow(), ["reason" => $reason, "cash_movement_id" => $movement_id]);
            $this->audit_change("payment_reversal_update", "cost", (int) $payment->expense_id, $expense ? (array) $expense : null, (array) $fresh, ["payment_id" => $payment_id]);
            if ($this->db->transCommit() === false) throw new \RuntimeException("gd_save_failed");
            return ["id" => $payment_id, "cash_movement_id" => $movement_id, "expense" => $fresh];
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
    }

    public function for_expense(int $expense_id): array
    {
        return $this->db->table($this->payments_table)->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->where("deleted", 0)->orderBy("payment_date", "ASC")->orderBy("id", "ASC")->get()->getResult();
    }

    private function active_account(int $id): object
    {
        $table = $this->db->prefixTable("gd_financial_accounts");
        $row = $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("status", "active")->where("deleted", 0)->get(1)->getRow();
        if (!$row) throw new \DomainException("gd_finance_account_inactive");
        return $row;
    }
    private function cash_movement(int $account, string $date, string $type, string $source, int $source_id, string $description, string $amount, ?int $reversal_of): int
    {
        $existing = $this->db->table($this->cash_table)->where("unit_id", $this->unit_id)->where("source_type", $source)->where("source_id", $source_id)->where("movement_type", $type)->get(1)->getRow();
        if ($existing) return (int) $existing->id;
        $this->db->table($this->cash_table)->insert(["unit_id" => $this->unit_id, "financial_account_id" => $account, "movement_date" => $date, "movement_type" => $type, "source_type" => $source, "source_id" => $source_id, "description" => DataNormalizationService::text($description), "amount" => $amount, "reversal_of_movement_id" => $reversal_of, "created_at" => gmdate("Y-m-d H:i:s"), "created_by" => $this->actor_id ?: null]);
        $id = (int) $this->db->insertID();
        if ($id <= 0) throw new \RuntimeException("gd_cash_movement_failed");
        return $id;
    }
    private function compare(string $a, string $b): int { return DataNormalizationService::decimalCompare(DataNormalizationService::decimal($a, 2), DataNormalizationService::decimal($b, 2)); }
    private function cents(string $value): int { $value = DataNormalizationService::decimal($value, 2); [$i, $f] = explode(".", $value); return ((int) $i * 100) + (int) $f; }
    private function money(int $cents): string { return intdiv($cents, 100) . "." . str_pad((string) ($cents % 100), 2, "0", STR_PAD_LEFT); }
    private function sub(string $a, string $b): string { return $this->money($this->cents($a) - $this->cents($b)); }
}
