<?php

declare(strict_types=1);

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Config\Permissions;
use grupo_donato_gestao\Services\CostBudgetService;
use grupo_donato_gestao\Services\CostAttachmentService;
use grupo_donato_gestao\Services\CostPaymentService;
use grupo_donato_gestao\Services\CostRecurrenceService;
use grupo_donato_gestao\Services\CostService;
use grupo_donato_gestao\Models\Gd_units_model;
use grupo_donato_gestao\Database\Schema\SchemaRunner;

final class GdCostsSelfTestUploadedFile extends \CodeIgniter\HTTP\Files\UploadedFile
{
    public function isValid(): bool { return true; }
}

/** Testes de domínio do módulo central de Custos, executados contra o MySQL configurado. */
function gd_costs_selftest(): void
{
    $db = db_connect();
    $prefix = $db->getPrefix();
    $units = new Gd_units_model();
    $unit_id = 0;
    $attachment_paths = [];
    $temporary_files = [];
    $tables = [
        "gd_expenses", "gd_expense_payments", "gd_expense_allocations", "gd_expense_recurrences",
        "gd_expense_attachments", "gd_cost_budgets", "gd_cash_movements", "gd_financial_accounts",
        "gd_business_areas", "gd_cost_centers", "gd_audit_logs", "gd_sequences",
    ];

    try {
        echo "# Custos: schema e contrato\n";
        foreach (["gd_expense_categories", "gd_expense_payments", "gd_expense_allocations", "gd_expense_recurrences", "gd_expense_attachments", "gd_cost_budgets"] as $table) {
            gd_assert("tabela {$table} existe", $db->tableExists($prefix . $table));
        }
        $routes = (string) @file_get_contents(__DIR__ . "/../Config/Routes.php");
        gd_assert("rota canônica de Custos", strpos($routes, 'finance/costs') !== false && strpos($routes, 'Costs::index') !== false);
        gd_assert("aliases legados apontam para Custos", strpos($routes, 'finance/expenses') !== false && strpos($routes, 'Costs::legacy_redirect') !== false);
        gd_assert("permissões do módulo declaradas", in_array("gd_costs_view", Permissions::KEYS, true) && in_array("gd_costs_pay", Permissions::KEYS, true));
        gd_assert("status pagos aceitam legado", in_array("legacy_migrated", Constants::PAYMENT_STATUSES, true));

        $category = $db->table($prefix . "gd_expense_categories")->where("unit_id IS NULL", null, false)->where("code", "uncategorized")->where("deleted", 0)->get(1)->getRow();
        gd_assert("catálogo global de categorias carregado", (bool) $category);
        $category_id = (int) ($category->id ?? 0);
        if (!$category_id) return;

        echo "# Custos: isolamento e cálculo\n";
        $test_unit = ["name" => "__costs_selftest__", "status" => "active", "is_default" => 0, "deleted" => 0];
        $unit_id = (int) $units->ci_save($test_unit);
        gd_assert("unidade de teste criada", $unit_id > 0);
        $now = gmdate("Y-m-d H:i:s");
        $db->table($prefix . "gd_financial_accounts")->insert(["unit_id" => $unit_id, "code" => "SELF-CASH", "name" => "Caixa do selftest", "account_type" => "cash", "status" => "active", "created_at" => $now, "updated_at" => $now, "deleted" => 0]);
        $account_id = (int) $db->insertID();
        $db->table($prefix . "gd_business_areas")->insert(["unit_id" => $unit_id, "code" => "SELF-AREA", "name" => "Área do selftest", "status" => "active", "deleted" => 0, "created_at" => $now, "updated_at" => $now]);
        $area_id = (int) $db->insertID();
        $db->table($prefix . "gd_cost_centers")->insert(["unit_id" => $unit_id, "business_area_id" => $area_id, "code" => "SELF-CENTER", "name" => "Centro do selftest", "type" => "mixed", "status" => "active", "deleted" => 0, "created_at" => $now, "updated_at" => $now]);
        $center_id = (int) $db->insertID();
        $costs = new CostService($unit_id);
        $today = gmdate("Y-m-d");
        $cost = $costs->save([
            "description" => "Custo calculado do selftest", "payee" => "Fornecedor teste", "issue_date" => $today,
            "due_date" => $today, "gross_amount" => "123.45", "discount_amount" => "3.45", "interest_amount" => "1.00",
            "penalty_amount" => "0.50", "category_id" => $category_id, "nature" => "operational_cost", "cost_behavior" => "fixed",
        ]);
        $expense_id = (int) $cost["id"];
        $expense = $costs->get($expense_id);
        gd_assert("custo criado", $expense_id > 0);
        gd_assert("valor final é recalculado no servidor", (string) $expense->final_amount === "121.50");
        gd_assert("saldo inicial é igual ao valor final", (string) $expense->balance_amount === "121.50" && (string) $expense->paid_amount === "0.00");
        gd_assert("número CST gerado", str_starts_with((string) $expense->expense_number, "CST-"));
        gd_assert("status inicial pending", (string) $expense->status === "pending");
        gd_assert("listagem é server-side", $costs->page(["search_by" => "Custo calculado", "limit" => 10])["recordsFiltered"] === 1);
        gd_assert("métrica do mês inclui o custo", (string) $costs->metrics(substr($today, 0, 7))["total"] === "121.50");
        gd_assert("unidade não expõe custo de outra unidade", (new CostService((int) $units->get_default()->id))->get_scoped($expense_id) === null);

        echo "# Custos: concorrência, parcelas e rateio\n";
        $edited = $costs->save(["description" => "Custo calculado editado", "lock_version" => (int) $expense->lock_version], $expense_id);
        gd_assert("edição incrementa lock_version", (int) $edited["data"]->lock_version === (int) $expense->lock_version + 1);
        gd_assert("lock_version obsoleto é rejeitado", gd_throws(fn() => $costs->save(["description" => "conflito", "lock_version" => (int) $expense->lock_version], $expense_id), "gd_finance_edit_conflict"));

        $installments = $costs->create_installments(["description" => "Parcelamento selftest", "issue_date" => "2026-01-31", "gross_amount" => "100.00", "category_id" => $category_id, "installment_total" => 3]);
        $installment_rows = $db->table($prefix . "gd_expenses")->whereIn("id", $installments["ids"])->orderBy("installment_number", "ASC")->get()->getResult();
        gd_assert("três parcelas criadas", count($installment_rows) === 3);
        gd_assert("parcelas fecham exatamente o total", array_sum(array_map(static fn($row) => (int) round(((float) $row->final_amount) * 100), $installment_rows)) === 10000);
        gd_assert("parcelas usam calendário com último dia válido", (string) ($installment_rows[1]->issue_date ?? "") === "2026-02-28");
        $installments_retry = $costs->create_installments(["description" => "Parcelamento selftest", "issue_date" => "2026-01-31", "gross_amount" => "100.00", "category_id" => $category_id, "installment_total" => 3, "installment_group_id" => $installments["installment_group_id"]]);
        $installment_count = $db->table($prefix . "gd_expenses")->where("installment_group_id", $installments["installment_group_id"])->countAllResults();
        gd_assert("retry de parcelas é idempotente", $installments_retry["ids"] === $installments["ids"] && $installment_count === 3, json_encode(["first" => $installments["ids"], "retry" => $installments_retry["ids"], "count" => $installment_count], JSON_UNESCAPED_UNICODE));

        $alloc = $costs->save_allocations($expense_id, [
            ["business_area_id" => $area_id, "percentage" => "50.0000", "amount" => "60.75"],
            ["cost_center_id" => $center_id, "percentage" => "50.0000", "amount" => "60.75"],
        ]);
        gd_assert("rateio grava linhas", count($alloc["allocations"]) === 2);
        gd_assert("rateio fecha em 100%", (string) array_sum(array_map(static fn($row) => (float) $row->percentage, $alloc["allocations"])) === "100");
        gd_assert("rateio rejeita total incorreto", gd_throws(fn() => $costs->save_allocations($expense_id, [["business_area_id" => $area_id, "percentage" => "100.0000", "amount" => "1.00"]]), "gd_cost_allocation_total"));

        $overdue_cost = $costs->save(["description" => "Custo vencido selftest", "issue_date" => date("Y-m-d", strtotime("-2 days")), "due_date" => date("Y-m-d", strtotime("-1 day")), "gross_amount" => "9.00", "category_id" => $category_id]);
        gd_assert("filtro de vencidos funciona", $costs->page(["status" => "overdue", "limit" => 50])["recordsFiltered"] >= 1 && (int) $overdue_cost["id"] > 0);
        $cancelled = $costs->save(["description" => "Custo cancelável selftest", "issue_date" => $today, "gross_amount" => "8.00", "category_id" => $category_id]);
        $costs->cancel((int) $cancelled["id"], "Lançamento de teste cancelado");
        gd_assert("cancelamento exige motivo e preserva o registro", (string) $costs->get_scoped((int) $cancelled["id"])->status === "cancelled");

        echo "# Custos: pagamentos, caixa e estorno\n";
        $payments = new CostPaymentService($unit_id);
        $first_payment = $payments->pay(["expense_id" => $expense_id, "amount" => "50.00", "payment_date" => $today, "financial_account_id" => $account_id, "payment_method" => "pix", "idempotency_key" => "selftest-first"]);
        $after_first = $costs->get($expense_id);
        gd_assert("pagamento parcial confirmado", (string) $after_first->status === "partial" && (string) $after_first->paid_amount === "50.00");
        $retry_payment = $payments->pay(["expense_id" => $expense_id, "amount" => "50.00", "payment_date" => $today, "financial_account_id" => $account_id, "payment_method" => "pix", "idempotency_key" => "selftest-first"]);
        gd_assert("retry de pagamento é idempotente", !empty($retry_payment["duplicate"]));
        gd_assert("um movimento de caixa para o pagamento", $db->table($prefix . "gd_cash_movements")->where("source_type", "expense_payment")->where("source_id", (int) $first_payment["id"])->where("movement_type", "out")->countAllResults() === 1);
        gd_assert("overpay é rejeitado", gd_throws(fn() => $payments->pay(["expense_id" => $expense_id, "amount" => "72.00", "payment_date" => $today, "financial_account_id" => $account_id, "payment_method" => "pix", "idempotency_key" => "selftest-overpay"]), "gd_cost_payment_exceeds_balance"));
        gd_assert("cancelamento parcial é bloqueado", gd_throws(fn() => $costs->cancel($expense_id, "tentativa"), "gd_finance_partial_cost_cancel"));
        $second_payment = $payments->pay(["expense_id" => $expense_id, "amount" => "71.50", "payment_date" => $today, "financial_account_id" => $account_id, "payment_method" => "pix", "idempotency_key" => "selftest-second"]);
        gd_assert("pagamento integral altera status para paid", (string) $costs->get($expense_id)->status === "paid");
        $reversal = $payments->reverse((int) $second_payment["id"], "Correção do selftest");
        $after_reversal = $costs->get($expense_id);
        gd_assert("estorno muda o pagamento para reversed", (string) $db->table($prefix . "gd_expense_payments")->where("id", (int) $second_payment["id"])->get(1)->getRow()->status === "reversed");
        gd_assert("estorno recompõe saldo do custo", (string) $after_reversal->status === "partial" && (string) $after_reversal->balance_amount === "71.50");
        gd_assert("estorno não duplica movimento inverso", $db->table($prefix . "gd_cash_movements")->where("source_type", "expense_payment_reversal")->where("source_id", (int) $second_payment["id"])->where("movement_type", "in")->countAllResults() === 1 && (int) $reversal["cash_movement_id"] > 0);
        gd_assert("estorno duplicado é bloqueado", gd_throws(fn() => $payments->reverse((int) $second_payment["id"], "duplicado"), "gd_cost_payment_already_reversed"));

        echo "# Custos: recorrência, orçamento e legado\n";
        $recurrences = new CostRecurrenceService($unit_id);
        $recurrence = $recurrences->save(["name" => "Recorrência selftest", "description" => "Mensal selftest", "start_date" => "2026-01-31", "end_date" => "2026-03-31", "frequency" => "monthly", "gross_amount" => "10.00", "category_id" => $category_id, "due_day" => 31]);
        $generated = $recurrences->generate((int) $recurrence["id"], "2026-03-31");
        $generated_retry = $recurrences->generate((int) $recurrence["id"], "2026-03-31");
        gd_assert("recorrência gera três ocorrências", $generated["generated"] === 3);
        gd_assert("recorrência mantém datas de fim de mês", $db->table($prefix . "gd_expenses")->where("recurrence_id", (int) $recurrence["id"])->where("issue_date", "2026-02-28")->countAllResults() === 1);
        gd_assert("retry de recorrência não duplica", $generated_retry["ids"] === $generated["ids"] && $db->table($prefix . "gd_expenses")->where("recurrence_id", (int) $recurrence["id"])->countAllResults() === 3);
        $budget = new CostBudgetService($unit_id);
        $saved_budget = $budget->save(["reference_month" => substr($today, 0, 7), "name" => "Orçamento selftest", "category_id" => $category_id, "amount" => "100.00"]);
        gd_assert("orçamento mensal salvo", (int) $saved_budget["id"] > 0);
        $budget_retry = $budget->save(["reference_month" => substr($today, 0, 7), "name" => "Orçamento selftest alterado", "category_id" => $category_id, "amount" => "110.00"]);
        gd_assert("retry de orçamento reaproveita chave", (int) $budget_retry["id"] === (int) $saved_budget["id"] && (string) $budget->get((int) $saved_budget["id"])->amount === "110.00");
        gd_assert("orçamento retorna comparação atual x previsto", array_key_exists("variance", $costs->metrics(substr($today, 0, 7))["budget"]));
        $budget_cost = $costs->save(["description" => "Custo para comparação orçamentária", "issue_date" => $today, "gross_amount" => "5869.50", "category_id" => $category_id]);
        $budget->save(["reference_month" => substr($today, 0, 7), "name" => "Orçamento selftest", "category_id" => $category_id, "amount" => "5000.00"]);
        $budget_metrics = $costs->metrics(substr($today, 0, 7));
        gd_assert("orçado x realizado calcula variação de R$ 1.000", (string) $budget_metrics["budget"]["actual"] === "6000.00" && (string) $budget_metrics["budget"]["variance"] === "1000.00" && !empty($budget_cost["id"]));

        $legacy = $db->table($prefix . "gd_expenses")->where("id", $expense_id)->get(1)->getRow();
        gd_assert("ledger mantém vínculo com custo", (int) $legacy->id === $expense_id && $db->table($prefix . "gd_expense_payments")->where("expense_id", $expense_id)->countAllResults() === 2);
        gd_assert("tabelas novas têm chave de ocorrência", $db->query("SHOW INDEX FROM `{$prefix}gd_expenses` WHERE Key_name='uniq_expense_occurrence'")->getNumRows() > 0);

        echo "# Custos: anexos, legado, permissões e instalação\n";
        $valid_file = tempnam(sys_get_temp_dir(), "gd-cost-");
        $temporary_files[] = $valid_file;
        file_put_contents($valid_file, base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="));
        $attachment_service = new CostAttachmentService($unit_id);
        $valid_upload = new GdCostsSelfTestUploadedFile($valid_file, "nota-selftest.png", "image/png", filesize($valid_file), UPLOAD_ERR_OK, null);
        gd_assert("fixture de anexo PNG é reconhecida", $valid_upload->isValid() && $valid_upload->getMimeType() === "image/png", $valid_upload->getMimeType());
        $attachment = $attachment_service->upload($expense_id, $valid_upload);
        $attachment_paths[] = $attachment_service->absolute_path($attachment["data"]);
        $write_root = rtrim(str_replace("\\", "/", WRITEPATH), "/") . "/";
        $public_root = rtrim(str_replace("\\", "/", FCPATH), "/") . "/uploads/";
        $attachment_path = str_replace("\\", "/", (string) $attachment_paths[0]);
        gd_assert("anexo válido é armazenado fora do public", (int) $attachment["id"] > 0 && str_starts_with($attachment_path, $write_root) && !str_starts_with($attachment_path, $public_root), json_encode(["path" => $attachment_path, "write" => $write_root, "public" => $public_root], JSON_UNESCAPED_SLASHES));
        $attachment_retry = $attachment_service->upload($expense_id, new GdCostsSelfTestUploadedFile($valid_file, "nota-selftest.png", "image/png", filesize($valid_file), UPLOAD_ERR_OK, null));
        gd_assert("anexo repetido é idempotente", !empty($attachment_retry["duplicate"]));
        $evil_file = tempnam(sys_get_temp_dir(), "gd-cost-");
        $temporary_files[] = $evil_file;
        file_put_contents($evil_file, "<?php echo 'not an image';");
        gd_assert("arquivo perigoso é bloqueado", gd_throws(fn() => $attachment_service->upload($expense_id, new GdCostsSelfTestUploadedFile($evil_file, "shell.php", "application/x-php", filesize($evil_file), UPLOAD_ERR_OK, null)), "gd_attachment_invalid"));

        $no_payment_user = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => []];
        gd_assert("usuário sem permissão não pode pagar", !(new \grupo_donato_gestao\Services\AccessService($no_payment_user))->can("gd_costs_pay"));
        $legacy_number = "LEGACY-SELF-" . substr(hash("sha256", uniqid("legacy", true)), 0, 10);
        $db->table($prefix . "gd_expenses")->insert(["unit_id" => $unit_id, "expense_number" => $legacy_number, "description" => "Despesa legada selftest", "expense_date" => $today, "paid_date" => $today, "amount" => "17.00", "status" => "paid", "financial_account_id" => $account_id, "payment_method" => "pix", "lock_version" => 1, "deleted" => 0]);
        $legacy_id = (int) $db->insertID();
        $db->table($prefix . "gd_cash_movements")->insert(["unit_id" => $unit_id, "financial_account_id" => $account_id, "movement_date" => $today, "movement_type" => "out", "source_type" => "expense", "source_id" => $legacy_id, "description" => "Saída legada", "amount" => "17.00", "created_at" => $now]);
        $legacy_movement_count = $db->table($prefix . "gd_cash_movements")->where("unit_id", $unit_id)->where("source_type", "expense")->where("source_id", $legacy_id)->countAllResults();
        (new \grupo_donato_gestao\Database\Seeds\CostSeeder(0))->run();
        (new \grupo_donato_gestao\Database\Seeds\CostSeeder(0))->run();
        gd_assert("despesa paga legada vira um pagamento", $db->table($prefix . "gd_expense_payments")->where("unit_id", $unit_id)->where("legacy_expense_id", $legacy_id)->where("status", "legacy_migrated")->countAllResults() === 1);
        gd_assert("migração legada não duplica saída de caixa", $legacy_movement_count === 1 && $db->table($prefix . "gd_cash_movements")->where("unit_id", $unit_id)->where("source_type", "expense")->where("source_id", $legacy_id)->countAllResults() === 1);
        $rerun = (new SchemaRunner())->run();
        gd_assert("migration/install repetido é idempotente", !$rerun["failed"] && count($rerun["ran"]) === 0);
        gd_assert("dashboard principal continua usando o ledger", class_exists("grupo_donato_gestao\\Controllers\\Dashboard") && str_contains((string) @file_get_contents(__DIR__ . "/../Controllers/Dashboard.php"), "gd_expense_payments"));
        gd_assert("rotas dos fluxos centrais continuam disponíveis", str_contains($routes, "finance/costs") && str_contains($routes, "finance/expenses"));
    } catch (\Throwable $e) {
        gd_assert("execução do selftest de Custos", false, $e->getMessage());
    } finally {
        foreach ($attachment_paths as $path) if ($path && is_file($path)) @unlink($path);
        foreach ($temporary_files as $path) if ($path && is_file($path)) @unlink($path);
        if ($unit_id > 0) {
            foreach ($tables as $table) {
                if ($db->tableExists($prefix . $table)) $db->table($prefix . $table)->where("unit_id", $unit_id)->delete();
            }
            $db->table($prefix . "gd_units")->where("id", $unit_id)->delete();
        }
    }
}
