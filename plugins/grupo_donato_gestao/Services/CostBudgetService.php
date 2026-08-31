<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/** Orçamento mensal por escopo, com chave determinística para retry seguro. */
final class CostBudgetService extends CatalogDataService
{
    private string $table;
    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null) { parent::__construct($unit_id, $actor_id, $login_user); $this->table = $this->db->prefixTable("gd_cost_budgets"); }
    public function page(string $month = ""): array { $q = $this->db->table($this->table)->where("unit_id", $this->unit_id)->where("deleted", 0); if ($month !== "") $q->where("reference_month", $month); return $q->orderBy("reference_month", "DESC")->orderBy("name", "ASC")->get()->getResult(); }
    public function save(array $input, int $id = 0): array
    {
        $old = $id ? $this->get($id) : null; if ($id && !$old) throw new \DomainException("gd_record_not_found");
        $month = trim((string) ($input["reference_month"] ?? ($old->reference_month ?? ""))); $name = DataNormalizationService::text($input["name"] ?? ($old->name ?? "")); $amount = DataNormalizationService::decimal($input["amount"] ?? ($old->amount ?? ""), 2);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) || $name === "" || DataNormalizationService::decimalCompare($amount, "0.00") <= 0) throw new \DomainException("gd_invalid_cost_budget");
        $category = $this->category_id((int) ($input["category_id"] ?? ($old->category_id ?? 0))); $area = $this->assert_area((int) ($input["business_area_id"] ?? ($old->business_area_id ?? 0))); $center = $this->assert_cost_center((int) ($input["cost_center_id"] ?? ($old->cost_center_id ?? 0)), $area);
        $key = implode("|", [$month, $category, $area ?: 0, $center ?: 0]);
        $existing = $this->db->table($this->table)->where("unit_id", $this->unit_id)->where("budget_key", $key)->where("deleted", 0)->get(1)->getRow();
        if ($existing && (!$old || (int) $existing->id !== (int) $old->id)) {
            if (!$old) { $id = (int) $existing->id; $old = $existing; }
            else throw new \DomainException("gd_cost_budget_duplicate");
        }
        $data = $this->stamp(["unit_id" => $this->unit_id, "budget_key" => $key, "reference_month" => $month, "name" => $name, "category_id" => $category ?: null, "business_area_id" => $area, "cost_center_id" => $center, "amount" => $amount, "status" => "active", "notes" => DataNormalizationService::text($input["notes"] ?? ($old->notes ?? "")) ?: null], !$old);
        if ($old) $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->update($data); else { $this->db->table($this->table)->insert($data); $id = (int) $this->db->insertID(); }
        $fresh = $this->get($id); $this->audit_change($old ? "update" : "create", "cost_budget", $id, $old ? (array) $old : null, $fresh ? (array) $fresh : null); return ["id" => $id, "data" => $fresh];
    }
    public function get(int $id): ?object { return $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow(); }
    private function category_id(int $id): ?int
    {
        if (!$id) return null;
        $row = $this->db->table($this->db->prefixTable("gd_expense_categories"))->where("id", $id)->where("deleted", 0)->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow();
        if (!$row) throw new \DomainException("gd_invalid_cost_category");
        return $id;
    }
}
