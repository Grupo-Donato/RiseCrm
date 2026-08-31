<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\CostAttachmentService;
use grupo_donato_gestao\Services\CostBudgetService;
use grupo_donato_gestao\Services\CostPaymentService;
use grupo_donato_gestao\Services\CostRecurrenceService;
use grupo_donato_gestao\Services\CostService;
use grupo_donato_gestao\Services\DataNormalizationService;

/** HTTP adapter do módulo único de Custos. */
final class Costs extends Gd_Controller
{
    protected bool $emit_http_status = true;
    private CostService $costs;
    private int $unit;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_costs_view");
        $this->unit = (int) $this->active_unit_id();
        if (!$this->unit) throw new \RuntimeException("gd_invalid_unit");
        $this->costs = new CostService($this->unit, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("finance/costs", [
            "metrics" => $this->costs->metrics((string) $this->request->getGet("reference_month")),
            "categories" => $this->costs->all_categories(),
            "areas" => $this->options("gd_business_areas", "name", true),
            "centers" => $this->options("gd_cost_centers", "name", true),
            "resources" => $this->options("gd_resources", "name"),
            "natures" => Constants::EXPENSE_NATURES,
            "behaviors" => Constants::EXPENSE_COST_BEHAVIORS,
            "can_manage" => $this->access->can("gd_costs_manage"),
            "can_pay" => $this->access->can("gd_costs_pay"),
            "can_budget" => $this->access->can("gd_costs_budget_manage"),
            "can_categories" => $this->access->can("gd_costs_categories_manage"),
        ]);
    }

    public function legacy_redirect()
    {
        return redirect()->to(site_url("grupo_donato/finance/costs"));
    }

    public function data()
    {
        try {
            $result = $this->costs->page($this->filters(["status", "nature", "cost_behavior", "category_id", "subcategory_id", "business_area_id", "cost_center_id", "resource_id", "reference_month", "date_from", "date_to", "search_by", "sort", "sort_dir", "limit", "skip"]));
            $result["data"] = array_map(function ($row) {
                $actions = modal_anchor(get_uri("grupo_donato/finance/costs/view"), '<i data-feather="eye" class="icon-16"></i>', ["title" => app_lang("gd_cost_view"), "data-post-id" => (int) $row->id]);
                if ($this->access->can("gd_costs_manage") && (string) $row->status !== "cancelled") $actions .= modal_anchor(get_uri("grupo_donato/finance/costs/modal"), '<i data-feather="edit" class="icon-16"></i>', ["title" => app_lang("gd_cost_edit"), "data-post-id" => (int) $row->id]);
                if ($this->access->can("gd_costs_pay") && in_array((string) $row->display_status, ["planned", "pending", "partial", "overdue"], true) && DataNormalizationService::decimalCompare((string) $row->balance_amount, "0.00") > 0) $actions .= modal_anchor(get_uri("grupo_donato/finance/costs/payment-modal"), '<i data-feather="dollar-sign" class="icon-16"></i>', ["title" => app_lang("gd_cost_register_payment"), "data-post-id" => (int) $row->id]);
                return ["number" => $this->escape($row->expense_number), "description" => $this->escape($row->description), "payee" => $this->escape($row->payee ?: "-"), "competence" => $this->escape($row->reference_month), "due" => $row->due_date ? format_to_date($row->due_date, false) : "-", "category" => $this->escape($row->subcategory_name ?: ($row->category_name ?: "-")), "center" => $this->escape($row->cost_center_name ?: "-"), "amount" => $row->final_amount, "paid" => $row->paid_amount, "balance" => $row->balance_amount, "status" => '<span class="badge bg-' . $this->status_class((string) $row->display_status) . '">' . $this->escape(app_lang("gd_cost_status_" . $row->display_status)) . "</span>", "options" => $actions];
            }, $result["data"]);
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function modal()
    {
        try {
            $this->access->require("gd_costs_manage");
            $id = (int) $this->request->getPost("id");
            return $this->gd_view("finance/cost_modal", ["cost" => $id ? $this->costs->get($id) : null, "categories" => $this->costs->all_categories(), "areas" => $this->options("gd_business_areas", "name", true), "centers" => $this->options("gd_cost_centers", "name", true), "resources" => $this->options("gd_resources", "name"), "natures" => Constants::EXPENSE_NATURES, "behaviors" => Constants::EXPENSE_COST_BEHAVIORS]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save()
    {
        try {
            $this->access->require("gd_costs_manage");
            $input = $this->post_input();
            $id = (int) $this->request->getPost("id");
            $legacy_paid = strtolower(trim((string) ($input["status"] ?? ""))) === "paid";
            $installments = (int) ($input["installment_total"] ?? 0);
            $result = $installments > 1 && !$id ? $this->costs->create_installments($input) : $this->costs->save($input, $id);
            if ($legacy_paid && $installments <= 1) {
                $expense_id = (int) ($result["id"] ?? 0);
                $expense = $this->costs->get_scoped($expense_id);
                if ($expense && DataNormalizationService::decimalCompare((string) $expense->paid_amount, "0.00") === 0) {
                    $account_id = (int) ($input["financial_account_id"] ?? 0);
                    if ($account_id <= 0) $account_id = (int) (($this->costs->accounts()[0]["id"] ?? 0));
                    $result["payment"] = (new CostPaymentService($this->unit, $this->user_id(), $this->login_user))->pay([
                        "expense_id" => $expense_id,
                        "amount" => $expense->final_amount,
                        "payment_date" => $input["paid_date"] ?: $expense->issue_date,
                        "financial_account_id" => $account_id,
                        "payment_method" => $input["payment_method"],
                        "idempotency_key" => "legacy-expense-save:" . $expense_id,
                        "notes" => "Pagamento criado pelo endpoint legado de despesas",
                    ]);
                }
            }
            $allocation = $this->request->getPost("allocations");
            if ($installments <= 1 && $this->request->getPost("activate_allocation") && is_array($allocation)) $this->costs->save_allocations((int) $result["id"], $allocation);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function view()
    {
        try { $cost = $this->costs->get((int) $this->request->getPost("id")); if (!$cost) return show_404(); return $this->gd_view("finance/cost_view", ["cost" => $cost, "can_pay" => $this->access->can("gd_costs_pay"), "can_manage" => $this->access->can("gd_costs_manage")]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function payment_modal()
    {
        try { $this->access->require("gd_costs_pay"); $cost = $this->costs->get((int) $this->request->getPost("id")); if (!$cost) return show_404(); return $this->gd_view("finance/cost_payment_modal", ["cost" => $cost, "accounts" => $this->costs->accounts(), "methods" => Constants::PAYMENT_METHODS]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function pay()
    {
        try { $this->access->require("gd_costs_pay"); $result = (new CostPaymentService($this->unit, $this->user_id(), $this->login_user))->pay(["expense_id" => $this->request->getPost("expense_id"), "amount" => $this->request->getPost("amount"), "payment_date" => $this->request->getPost("payment_date"), "financial_account_id" => $this->request->getPost("financial_account_id"), "payment_method" => $this->request->getPost("payment_method"), "external_reference" => $this->request->getPost("external_reference"), "idempotency_key" => $this->request->getPost("idempotency_key"), "notes" => $this->request->getPost("notes")]); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function reverse_payment()
    {
        try { $this->access->require("gd_costs_pay"); $result = (new CostPaymentService($this->unit, $this->user_id(), $this->login_user))->reverse((int) $this->request->getPost("id"), (string) $this->request->getPost("reason")); $this->json_success(app_lang("gd_cost_payment_reversed"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function cancel()
    {
        try { $this->access->require("gd_costs_manage"); $this->costs->cancel((int) $this->request->getPost("id"), (string) $this->request->getPost("reason"), $this->request->getPost("lock_version") === null ? null : (int) $this->request->getPost("lock_version")); $this->json_success(app_lang("record_saved")); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function allocations()
    {
        try { $this->access->require("gd_costs_manage"); $result = $this->costs->save_allocations((int) $this->request->getPost("expense_id"), (array) $this->request->getPost("allocations")); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function categories_data()
    {
        try { $this->access->require_any(["gd_costs_view", "gd_costs_categories_manage"]); return $this->response->setJSON(["data" => $this->costs->all_categories(true)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function category_modal()
    {
        try { $this->access->require("gd_costs_categories_manage"); return $this->gd_view("finance/cost_category_modal", ["categories" => $this->costs->all_categories(true)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function category_save()
    {
        try { $this->access->require("gd_costs_categories_manage"); $result = $this->costs->saveCategory(["name" => $this->request->getPost("name"), "code" => $this->request->getPost("code"), "parent_id" => $this->request->getPost("parent_id"), "status" => $this->request->getPost("status")], (int) $this->request->getPost("id")); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function recurrences_data()
    {
        try { $this->access->require("gd_costs_view"); return $this->response->setJSON(["data" => (new CostRecurrenceService($this->unit, $this->user_id(), $this->login_user))->page()]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function recurrence_modal()
    {
        try { $this->access->require("gd_costs_manage"); return $this->gd_view("finance/cost_recurrence_modal", ["categories" => $this->costs->all_categories(), "areas" => $this->options("gd_business_areas", "name", true), "centers" => $this->options("gd_cost_centers", "name", true), "resources" => $this->options("gd_resources", "name"), "natures" => Constants::EXPENSE_NATURES, "behaviors" => Constants::EXPENSE_COST_BEHAVIORS, "frequencies" => Constants::EXPENSE_RECURRENCE_FREQUENCIES]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function recurrence_save()
    {
        try { $this->access->require("gd_costs_manage"); $result = (new CostRecurrenceService($this->unit, $this->user_id(), $this->login_user))->save($this->post_input(), (int) $this->request->getPost("id")); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function recurrence_generate()
    {
        try { $this->access->require("gd_costs_manage"); $result = (new CostRecurrenceService($this->unit, $this->user_id(), $this->login_user))->generate((int) $this->request->getPost("id"), (string) $this->request->getPost("until")); $this->json_success(app_lang("gd_cost_recurrence_generated"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function budgets_data()
    {
        try { $this->access->require("gd_costs_view"); $month = (string) ($this->request->getPost("reference_month") ?: $this->request->getGet("reference_month")); return $this->response->setJSON(["data" => (new CostBudgetService($this->unit, $this->user_id(), $this->login_user))->page($month)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function budget_modal()
    {
        try { $this->access->require("gd_costs_budget_manage"); return $this->gd_view("finance/cost_budget_modal", ["categories" => $this->costs->all_categories(), "areas" => $this->options("gd_business_areas", "name", true), "centers" => $this->options("gd_cost_centers", "name", true)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function budget_save()
    {
        try { $this->access->require("gd_costs_budget_manage"); $result = (new CostBudgetService($this->unit, $this->user_id(), $this->login_user))->save(["reference_month" => $this->request->getPost("reference_month"), "name" => $this->request->getPost("name"), "category_id" => $this->request->getPost("category_id"), "business_area_id" => $this->request->getPost("business_area_id"), "cost_center_id" => $this->request->getPost("cost_center_id"), "amount" => $this->request->getPost("amount"), "notes" => $this->request->getPost("notes")], (int) $this->request->getPost("id")); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function attachment_upload()
    {
        try {
            $this->access->require("gd_costs_manage");
            $files = method_exists($this->request, "getFileMultiple") ? $this->request->getFileMultiple("file") : [];
            if (!$files) {
                $single = $this->request->getFile("file");
                if ($single) $files = [$single];
            }
            if (!$files) throw new \DomainException("gd_attachment_invalid");
            $service = new CostAttachmentService($this->unit, $this->user_id(), $this->login_user);
            $result = [];
            foreach ($files as $file) $result[] = $service->upload((int) $this->request->getPost("expense_id"), $file, (string) $this->request->getPost("document_type"));
            $this->json_success(app_lang("gd_attachment_uploaded"), ["files" => $result]);
        }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function attachment_delete()
    {
        try { $this->access->require("gd_costs_manage"); (new CostAttachmentService($this->unit, $this->user_id(), $this->login_user))->remove((int) $this->request->getPost("id")); $this->json_success(app_lang("record_saved")); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }
    public function attachment_download(int $id)
    {
        $this->access->require("gd_costs_view"); $service = new CostAttachmentService($this->unit, $this->user_id(), $this->login_user); $row = $service->get($id); $path = $row ? $service->absolute_path($row) : null; if (!$row || !$path) return show_404(); return $this->response->download($path, null)->setFileName((string) $row->original_name);
    }

    public function export()
    {
        $result = $this->costs->page($this->filters(["status", "nature", "cost_behavior", "category_id", "subcategory_id", "business_area_id", "cost_center_id", "resource_id", "reference_month", "date_from", "date_to", "search_by", "sort", "sort_dir", "skip"]) + ["limit" => 10000]);
        $handle = fopen("php://temp", "r+"); fputcsv($handle, ["Competência", "Número", "Descrição", "Fornecedor", "Natureza", "Tipo", "Categoria", "Subcategoria", "Centro", "Vencimento", "Valor", "Pago", "Saldo", "Status"], ";");
        foreach ($result["data"] as $row) fputcsv($handle, [$row->reference_month, $row->expense_number, $row->description, $row->payee, $row->nature, $row->cost_behavior, $row->category_name, $row->subcategory_name, $row->cost_center_name, $row->due_date, $row->final_amount, $row->paid_amount, $row->balance_amount, app_lang("gd_cost_status_" . $row->display_status)], ";");
        rewind($handle); $csv = stream_get_contents($handle); fclose($handle); return $this->response->download("custos.csv", $csv)->setContentType("text/csv; charset=UTF-8");
    }

    private function post_input(): array
    {
        $keys = ["description", "payee", "issue_date", "expense_date", "due_date", "paid_date", "reference_month", "gross_amount", "amount", "discount_amount", "interest_amount", "penalty_amount", "nature", "cost_behavior", "category_id", "subcategory_id", "business_area_id", "cost_center_id", "resource_id", "financial_account_id", "payment_method", "notes", "status", "lock_version", "installment_group_id", "installment_number", "installment_total", "recurrence_id", "occurrence_key", "start_date", "end_date", "frequency", "interval_value", "due_day"];
        $out = []; foreach ($keys as $key) $out[$key] = $this->request->getPost($key); return $out;
    }
    private function filters(array $keys): array { $out = []; foreach ($keys as $key) { $value = $this->request->getPost($key); if ($value === null || $value === "") $value = $this->request->getGet($key); $out[$key] = $value; } if (function_exists("append_server_side_filtering_commmon_params")) $out = append_server_side_filtering_commmon_params($out); return $out; }
    private function options(string $table, string $label, bool $global = false): array { $db = db_connect(); $q = $db->table($db->prefixTable($table))->select("id,{$label} text", false)->where("deleted", 0); if ($global) $q->groupStart()->where("unit_id", $this->unit)->orWhere("unit_id IS NULL", null, false)->groupEnd(); else $q->where("unit_id", $this->unit); return $q->orderBy($label, "ASC")->get()->getResultArray(); }
    private function status_class(string $status): string { return match ($status) { "paid" => "success", "partial" => "info", "overdue" => "danger", "cancelled" => "secondary", "planned" => "primary", default => "warning" }; }
}
