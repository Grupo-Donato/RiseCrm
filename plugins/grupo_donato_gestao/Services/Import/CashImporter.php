<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services\Import;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\FinanceService;

/**
 * Importa o livro-caixa (planilha CAIXA): entradas viram movimento manual
 * identificado com o lote; saídas/despesas viram despesa paga. Nunca transforma
 * automaticamente uma entrada em pagamento de cobrança.
 */
final class CashImporter extends AbstractImporter
{
    public function type(): string { return "cash"; }

    public function headerDefs(): array
    {
        return [
            ["field" => "movement_date", "label" => "gd_import_col_date", "aliases" => ["data", "dia", "data movimento"], "required" => true],
            ["field" => "description", "label" => "gd_import_col_description", "aliases" => ["descricao", "descrição", "historico", "histórico", "lancamento", "lançamento"], "required" => true],
            ["field" => "type", "label" => "gd_import_col_type", "aliases" => ["tipo", "natureza"], "required" => false],
            ["field" => "amount", "label" => "gd_import_col_amount", "aliases" => ["valor", "total"], "required" => false],
            ["field" => "inflow", "label" => "gd_import_col_inflow", "aliases" => ["entrada", "credito", "crédito", "receita"], "required" => false],
            ["field" => "outflow", "label" => "gd_import_col_outflow", "aliases" => ["saida", "saída", "debito", "débito", "despesa"], "required" => false],
            ["field" => "payment_method", "label" => "gd_import_col_method", "aliases" => ["forma", "metodo", "método", "forma pagamento"], "required" => false],
        ];
    }

    public function primaryTargetTypes(): array { return ["cash_movement", "expense"]; }

    public function validateRow(array $row): array
    {
        $issues = [];
        $date = $this->files->date($row["movement_date"] ?? "");
        $description = DataNormalizationService::text($row["description"] ?? "");
        $inflow = $this->files->amount($row["inflow"] ?? "");
        $outflow = $this->files->amount($row["outflow"] ?? "");
        $amount = null; $direction = null;
        if ($inflow !== null && $outflow === null) { $direction = "in"; $amount = $inflow; }
        elseif ($outflow !== null && $inflow === null) { $direction = "out"; $amount = $outflow; }
        else {
            $amount = $this->files->amount($row["amount"] ?? "");
            $typeRaw = DataNormalizationService::name($row["type"] ?? "");
            if ($typeRaw !== "" && preg_match('/(entrada|credito|receita|recebiment)/', $typeRaw)) { $direction = "in"; }
            elseif ($typeRaw !== "" && preg_match('/(saida|debito|despesa|pagament|retirada)/', $typeRaw)) { $direction = "out"; }
            elseif ($this->files->isNegative($row["amount"] ?? "")) { $direction = "out"; }
        }
        $methodRaw = (string) ($row["payment_method"] ?? "");
        $method = $methodRaw === "" ? null : Constants::normalizePaymentMethod($methodRaw);

        $status = "valid";
        if ($date === null) { $issues[] = $this->issue("invalid_date", "error", "gd_import_issue_invalid_date", ["value" => $row["movement_date"] ?? ""]); $status = "invalid"; }
        if ($description === "") { $issues[] = $this->issue("missing_required", "error", "gd_import_issue_description_required"); $status = "invalid"; }
        if ($amount === null) { $issues[] = $this->issue("invalid_amount", "error", "gd_import_issue_invalid_amount"); $status = "invalid"; }
        if ($amount !== null && $direction === null) { $issues[] = $this->issue("ambiguous_category", "review", "gd_import_issue_ambiguous_direction"); $status = $status === "invalid" ? "invalid" : "needs_review"; }

        $normalized = [
            "movement_date" => $date, "description" => $description, "direction" => $direction,
            "amount" => $amount, "payment_method" => $method,
        ];
        $source_key = $this->files->sourceKey("cash", [(string) $date, $description, (string) $amount, (string) $direction]);
        return ["status" => $status, "normalized" => $normalized, "issues" => $issues, "source_key" => $source_key];
    }

    public function importRow(array $n, string $source_key, int $row_number, int $row_id): array
    {
        $links = [];
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        $account = $this->defaultAccount();
        if ($n["direction"] === "out") {
            if ($this->find($source_key, "expense") === null) {
                $saved = $finance->saveExpense([
                    "description" => $n["description"], "amount" => $n["amount"], "expense_date" => $n["movement_date"],
                    "paid_date" => $n["movement_date"], "status" => "paid", "financial_account_id" => $account,
                    "payment_method" => $n["payment_method"] ?: "other",
                ]);
                $links[] = $this->link($source_key, "expense", (int) $saved["id"]);
            }
        } else {
            if ($this->find($source_key, "cash_movement") === null) {
                $movement = $finance->createCashMovement([
                    "financial_account_id" => $account, "movement_date" => $n["movement_date"], "movement_type" => "in",
                    "description" => $n["description"], "amount" => $n["amount"], "source_id" => $row_id,
                ]);
                $links[] = $this->link($source_key, "cash_movement", (int) $movement["id"]);
            }
        }
        return ["links" => $links];
    }

    private function defaultAccount(): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_financial_accounts"))->select("id")->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->orderBy("account_type = 'cash'", "DESC", false)->orderBy("id")->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_import_no_financial_account"); }
        return (int) $row->id;
    }
}
