<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Domínio central de Custos: valores, classificação, rateio e consultas. */
final class CostService extends CatalogDataService
{
    private string $expenses_table;
    private string $categories_table;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->expenses_table = $this->db->prefixTable("gd_expenses");
        $this->categories_table = $this->db->prefixTable("gd_expense_categories");
    }

    public function accounts(): array
    {
        $table = $this->db->prefixTable("gd_financial_accounts");
        return $this->db->table($table)->select("id,name,account_type")
            ->where("unit_id", $this->unit_id)->where("status", "active")->where("deleted", 0)
            ->orderBy("name", "ASC")->get()->getResultArray();
    }

    /** @return array<int,object> */
    public function categories(?int $parent_id = null, bool $include_inactive = false): array
    {
        $q = $this->db->table($this->categories_table)
            ->where("deleted", 0)
            ->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd();
        if (!$include_inactive) $q->where("status", "active");
        if ($parent_id === null) $q->where("parent_id IS NULL", null, false);
        else $q->where("parent_id", $parent_id);
        return $q->orderBy("sort_order", "ASC")->orderBy("name", "ASC")->get()->getResult();
    }

    /** @return array<int,object> */
    public function all_categories(bool $include_inactive = false): array
    {
        $q = $this->db->table($this->categories_table)
            ->where("deleted", 0)
            ->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd();
        if (!$include_inactive) $q->where("status", "active");
        return $q->orderBy("parent_id", "ASC")->orderBy("sort_order", "ASC")->orderBy("name", "ASC")->get()->getResult();
    }

    public function saveCategory(array $input, int $id = 0): array
    {
        $old = $id ? $this->db->table($this->categories_table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow() : null;
        if ($id && !$old) throw new \DomainException("gd_record_not_found");
        $name = DataNormalizationService::text($input["name"] ?? "");
        $code = strtolower((string) preg_replace('/[^a-z0-9_\-.]+/i', "_", DataNormalizationService::text($input["code"] ?? $name)));
        $parent = (int) ($input["parent_id"] ?? 0);
        if ($name === "" || $code === "" || ($parent && !$this->category($parent))) throw new \DomainException("gd_invalid_cost_category");
        $duplicate = $this->db->table($this->categories_table)->where("code", $code)->where("deleted", 0)->where("unit_id", $this->unit_id);
        if ($id) $duplicate->where("id !=", $id);
        if ($duplicate->countAllResults() > 0) throw new \DomainException("gd_cost_category_duplicate");
        $data = ["unit_id" => $this->unit_id, "parent_id" => $parent ?: null, "code" => $code, "name" => $name,
            "status" => in_array(($input["status"] ?? "active"), ["active", "inactive"], true) ? $input["status"] : "active",
            "is_system" => 0, "sort_order" => max(0, (int) ($input["sort_order"] ?? 0))];
        $data = $this->stamp($data, !$old);
        if ($old) {
            $changed = $this->db->table($this->categories_table)->where("id", $id)->where("unit_id", $this->unit_id)->update($data);
            if (!$changed) throw new \RuntimeException("gd_save_failed");
        } else {
            $this->db->table($this->categories_table)->insert($data);
            $id = (int) $this->db->insertID();
        }
        $after = $this->db->table($this->categories_table)->where("id", $id)->where("unit_id", $this->unit_id)->get(1)->getRow();
        $this->audit_change($old ? "update" : "create", "expense_category", $id, $old ? (array) $old : null, $after ? (array) $after : null);
        return ["id" => $id, "data" => $after];
    }

    public function save(array $input, int $id = 0): array
    {
        $old = $id ? $this->get_scoped($id) : null;
        if ($id && !$old) throw new \DomainException("gd_record_not_found");
        if ($old && (string) $old->status === "cancelled") throw new \DomainException("gd_cancelled_cost_immutable");

        $expected = array_key_exists("lock_version", $input) && trim((string) $input["lock_version"]) !== "" ? (int) $input["lock_version"] : null;
        if ($old && $expected !== null && $expected !== (int) $old->lock_version) throw new \DomainException("gd_finance_edit_conflict");

        $description = DataNormalizationService::text($input["description"] ?? ($old->description ?? ""));
        $payee = DataNormalizationService::text($input["payee"] ?? ($old->payee ?? ""));
        $issue = $this->valid_date($input["issue_date"] ?? ($input["expense_date"] ?? ($old->issue_date ?? $old->expense_date ?? "")));
        $due = $this->valid_date($input["due_date"] ?? ($old->due_date ?? ""));
        if (!$issue || $description === "") throw new \DomainException("gd_finance_invalid_cost");
        if ($due && $due < $issue) throw new \DomainException("gd_invalid_cost_date_range");
        $reference = trim((string) ($input["reference_month"] ?? ($old->reference_month ?? "")));
        if ($reference === "") $reference = substr($issue, 0, 7);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reference)) throw new \DomainException("gd_finance_invalid_reference");

        $gross = DataNormalizationService::decimal($input["gross_amount"] ?? ($input["amount"] ?? ($old->gross_amount ?? $old->amount ?? "")), 2);
        $discount = DataNormalizationService::decimal($input["discount_amount"] ?? ($old->discount_amount ?? "0"), 2);
        $interest = DataNormalizationService::decimal($input["interest_amount"] ?? ($old->interest_amount ?? "0"), 2);
        $penalty = DataNormalizationService::decimal($input["penalty_amount"] ?? ($old->penalty_amount ?? "0"), 2);
        $final = $this->add($this->sub($gross, $discount), $this->add($interest, $penalty));
        if ($this->compare($gross, "0.00") <= 0 || $this->compare($discount, $gross) > 0 || $this->compare($final, "0.00") <= 0) throw new \DomainException("gd_finance_invalid_cost");

        $paid = $old ? $this->paid_amount($id) : "0.00";
        if ($this->compare($final, $paid) < 0) throw new \DomainException("gd_finance_amount_below_paid");
        if ($old && $this->compare($paid, "0.00") > 0 && $this->critical_fields_changed($old, $input, $final, $gross, $discount, $interest, $penalty)) {
            throw new \DomainException("gd_finance_paid_expense_immutable");
        }

        $nature = (string) ($input["nature"] ?? ($old->nature ?? "operational_cost"));
        $behavior = (string) ($input["cost_behavior"] ?? ($old->cost_behavior ?? "unclassified"));
        if (!in_array($nature, Constants::EXPENSE_NATURES, true) || !in_array($behavior, Constants::EXPENSE_COST_BEHAVIORS, true)) throw new \DomainException("gd_invalid_cost_classification");
        $category = $this->assert_cost_category((int) ($input["category_id"] ?? ($old->category_id ?? 0)));
        $subcategory = $this->assert_subcategory((int) ($input["subcategory_id"] ?? ($old->subcategory_id ?? 0)), $category);
        $area = $this->assert_area((int) ($input["business_area_id"] ?? ($old->business_area_id ?? 0)));
        $center = $this->assert_cost_center((int) ($input["cost_center_id"] ?? ($old->cost_center_id ?? 0)), $area);
        $resource = $this->assert_resource((int) ($input["resource_id"] ?? ($old->resource_id ?? 0)));
        $base_status = (string) ($input["status"] ?? ($old->status ?? "pending"));
        if (!in_array($base_status, ["planned", "pending"], true)) $base_status = "pending";
        $status = $this->status_for($final, $paid, $due, $base_status);
        $occurrence = trim((string) ($input["occurrence_key"] ?? ($old->occurrence_key ?? "")));
        if (!$old && $occurrence !== "") {
            $duplicate = $this->db->table($this->expenses_table)->where("unit_id", $this->unit_id)->where("occurrence_key", $occurrence)->where("deleted", 0)->get(1)->getRow();
            if ($duplicate) return ["id" => (int) $duplicate->id, "duplicate" => true, "data" => $duplicate];
        }

        $data = [
            "unit_id" => $this->unit_id, "description" => $description, "payee" => $payee ?: null,
            "expense_date" => $issue, "issue_date" => $issue, "reference_month" => $reference, "due_date" => $due,
            "amount" => $final, "gross_amount" => $gross, "discount_amount" => $discount, "interest_amount" => $interest,
            "penalty_amount" => $penalty, "final_amount" => $final, "paid_amount" => $paid,
            "balance_amount" => $this->sub($final, $paid), "status" => $status, "business_area_id" => $area,
            "cost_center_id" => $center, "category_id" => $category, "subcategory_id" => $subcategory, "resource_id" => $resource,
            "nature" => $nature, "cost_behavior" => $behavior, "notes" => DataNormalizationService::text($input["notes"] ?? ($old->notes ?? "")) ?: null,
            "installment_group_id" => trim((string) ($input["installment_group_id"] ?? ($old->installment_group_id ?? ""))) ?: null,
            "installment_number" => (int) ($input["installment_number"] ?? ($old->installment_number ?? 0)) ?: null,
            "installment_total" => (int) ($input["installment_total"] ?? ($old->installment_total ?? 0)) ?: null,
            "occurrence_key" => $occurrence ?: null, "recurrence_id" => (int) ($input["recurrence_id"] ?? ($old->recurrence_id ?? 0)) ?: null,
            "source_type" => DataNormalizationService::text($input["source_type"] ?? ($old->source_type ?? "")) ?: null,
            "source_id" => (int) ($input["source_id"] ?? ($old->source_id ?? 0)) ?: null,
            "lock_version" => $old ? ((int) $old->lock_version + 1) : 1,
        ];
        if (!$old) {
            $sequence = new SequenceService();
            $sequence->ensure($this->unit_id, "cost", "CST-", 6, false);
            $data["expense_number"] = $sequence->next($this->unit_id, "cost");
        }
        $data = $this->stamp($data, !$old);
        $this->db->transBegin();
        try {
            if ($old) {
                $q = $this->db->table($this->expenses_table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0);
                if ($expected !== null) $q->where("lock_version", $expected);
                if (!$q->update($data) || $this->db->affectedRows() !== 1) throw new \DomainException("gd_finance_edit_conflict");
            } else {
                $this->db->table($this->expenses_table)->insert($data);
                $id = (int) $this->db->insertID();
                if ($id <= 0) throw new \RuntimeException("gd_save_failed");
            }
            $fresh = $this->get_scoped($id);
            $this->audit_change($old ? "update" : "create", "cost", $id, $old ? (array) $old : null, $fresh ? (array) $fresh : null);
            if ($this->db->transCommit() === false) throw new \RuntimeException("gd_save_failed");
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        return ["id" => $id, "data" => $this->get_scoped($id)];
    }

    public function cancel(int $id, string $reason, ?int $lock_version = null): void
    {
        $expense = $this->get_scoped($id);
        if (!$expense) throw new \DomainException("gd_record_not_found");
        if ((string) $expense->status === "cancelled") throw new \DomainException("gd_cancelled_cost_immutable");
        if ($this->compare($this->paid_amount($id), "0.00") > 0) throw new \DomainException("gd_finance_partial_cost_cancel");
        $reason = DataNormalizationService::text($reason);
        if ($reason === "") throw new \DomainException("gd_reason_required");
        if ($lock_version !== null && $lock_version !== (int) $expense->lock_version) throw new \DomainException("gd_finance_edit_conflict");
        $q = $this->db->table($this->expenses_table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status !=", "cancelled");
        if ($lock_version !== null) $q->where("lock_version", $lock_version);
        if (!$q->update(["status" => "cancelled", "cancel_reason" => $reason, "notes" => trim((string) $expense->notes . "\nCancelamento: " . $reason), "lock_version" => (int) $expense->lock_version + 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]) || $this->db->affectedRows() !== 1) throw new \DomainException("gd_finance_edit_conflict");
        $this->audit_change("cancel", "cost", $id, (array) $expense, (array) $this->get_scoped($id), ["reason" => $reason]);
    }

    /** @return object|null */
    public function get_scoped(int $id): ?object
    {
        return $this->db->table($this->expenses_table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
    }

    public function get(int $id): ?object
    {
        $expense = $this->get_scoped($id);
        if (!$expense) return null;
        $expense->category_name = $this->category_name((int) $expense->category_id);
        $expense->subcategory_name = $this->category_name((int) $expense->subcategory_id);
        $center = $this->db->table($this->db->prefixTable("gd_cost_centers"))->where("id", (int) $expense->cost_center_id)->where("deleted", 0)->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow();
        $area = $this->db->table($this->db->prefixTable("gd_business_areas"))->where("id", (int) $expense->business_area_id)->where("deleted", 0)->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow();
        $resource = $this->db->table($this->db->prefixTable("gd_resources"))->where("id", (int) $expense->resource_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        $expense->cost_center_name = (string) ($center->name ?? "");
        $expense->business_area_name = (string) ($area->name ?? "");
        $expense->resource_name = (string) ($resource->name ?? "");
        $expense->display_status = $this->display_status($expense);
        $expense->payments = $this->db->table($this->db->prefixTable("gd_expense_payments"))->where("unit_id", $this->unit_id)->where("expense_id", $id)->where("deleted", 0)->orderBy("payment_date", "ASC")->orderBy("id", "ASC")->get()->getResult();
        $expense->allocations = $this->db->table($this->db->prefixTable("gd_expense_allocations"))->where("unit_id", $this->unit_id)->where("expense_id", $id)->where("deleted", 0)->orderBy("id", "ASC")->get()->getResult();
        $expense->attachments = $this->db->table($this->db->prefixTable("gd_expense_attachments"))->where("unit_id", $this->unit_id)->where("expense_id", $id)->where("deleted", 0)->orderBy("id", "DESC")->get()->getResult();
        return $expense;
    }

    /** Listagem server-side com recordsTotal/recordsFiltered corretos. */
    public function page(array $options = []): array
    {
        $t = $this->expenses_table;
        $category = $this->categories_table;
        $center = $this->db->prefixTable("gd_cost_centers");
        $area = $this->db->prefixTable("gd_business_areas");
        $base = function () use ($options, $t, $category, $center, $area) {
            $q = $this->db->table($t)
                ->join($category . " c", "c.id={$t}.category_id AND c.deleted=0 AND (c.unit_id={$t}.unit_id OR c.unit_id IS NULL)", "left", false)
                ->join($category . " sc", "sc.id={$t}.subcategory_id AND sc.deleted=0 AND (sc.unit_id={$t}.unit_id OR sc.unit_id IS NULL)", "left", false)
                ->join($center . " cc", "cc.id={$t}.cost_center_id AND cc.deleted=0 AND (cc.unit_id={$t}.unit_id OR cc.unit_id IS NULL)", "left", false)
                ->join($area . " ba", "ba.id={$t}.business_area_id AND ba.deleted=0 AND (ba.unit_id={$t}.unit_id OR ba.unit_id IS NULL)", "left", false)
                ->where("{$t}.unit_id", $this->unit_id)->where("{$t}.deleted", 0);
            $status = (string) ($options["status"] ?? "");
            if ($status === "overdue") $q->groupStart()->whereIn("{$t}.status", ["planned", "pending", "partial"])->where("{$t}.due_date <", gmdate("Y-m-d"))->groupEnd();
            elseif ($status !== "") $q->where("{$t}.status", $status);
            foreach (["nature", "cost_behavior", "category_id", "subcategory_id", "business_area_id", "cost_center_id", "resource_id", "reference_month"] as $field) {
                if (($value = $options[$field] ?? "") !== "") $q->where("{$t}.{$field}", $value);
            }
            if (($value = $options["date_from"] ?? "") !== "") $q->where("{$t}.issue_date >=", $value);
            if (($value = $options["date_to"] ?? "") !== "") $q->where("{$t}.issue_date <=", $value);
            if (($value = trim((string) ($options["search_by"] ?? ""))) !== "") {
                $q->groupStart()->like("{$t}.expense_number", $value)->orLike("{$t}.description", $value)->orLike("{$t}.payee", $value)->orLike("c.name", $value)->orLike("cc.name", $value)->groupEnd();
            }
            return $q;
        };
        $total = $this->db->table($t)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults();
        $filtered_query = $base();
        $filtered = $filtered_query->countAllResults();
        $requested_order = $options["sort"] ?? ($options["order_by"] ?? "");
        $order = in_array($requested_order, ["issue_date", "due_date", "final_amount", "paid_amount", "balance_amount", "expense_number", "description"], true) ? $requested_order : "issue_date";
        $requested_direction = $options["sort_dir"] ?? ($options["order_dir"] ?? "DESC");
        $direction = strtoupper((string) $requested_direction) === "ASC" ? "ASC" : "DESC";
        $limit = max(1, min(200, (int) ($options["limit"] ?? 25)));
        $skip = max(0, (int) ($options["skip"] ?? 0));
        $rows = $base()->select("{$t}.*,c.name category_name,sc.name subcategory_name,cc.name cost_center_name,ba.name business_area_name,CASE WHEN {$t}.status IN ('planned','pending','partial') AND {$t}.due_date < CURDATE() THEN 'overdue' ELSE {$t}.status END display_status", false)->orderBy("{$t}.{$order}", $direction)->orderBy("{$t}.id", "DESC")->limit($limit, $skip)->get()->getResult();
        return ["data" => $rows, "recordsTotal" => $total, "recordsFiltered" => $filtered];
    }

    public function metrics(string $reference_month = ""): array
    {
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $reference_month) ? $reference_month : gmdate("Y-m");
        $sum = function (string $expression, string $extra = "") use ($month): string {
            $sql = "SELECT COALESCE(SUM({$expression}),0) total FROM `{$this->expenses_table}` WHERE unit_id=? AND deleted=0 AND status<>? AND reference_month=? {$extra}";
            return DataNormalizationService::decimal((string) ($this->db->query($sql, [$this->unit_id, "cancelled", $month])->getRow()->total ?? "0"), 2);
        };
        $total = $sum("final_amount");
        $paid = $sum("paid_amount");
        $balance = $sum("balance_amount", "AND balance_amount>0");
        $overdue = $sum("balance_amount", "AND balance_amount>0 AND due_date<CURDATE()");
        $rows = $this->db->query("SELECT reference_month,COALESCE(SUM(final_amount),0) amount FROM `{$this->expenses_table}` WHERE unit_id=? AND deleted=0 AND status<>? AND reference_month >= DATE_FORMAT(DATE_SUB(CURDATE(),INTERVAL 11 MONTH),'%Y-%m') GROUP BY reference_month ORDER BY reference_month", [$this->unit_id, "cancelled"])->getResult();
        $by_category = $this->db->query("SELECT COALESCE(c.name,'Não categorizado') name,COALESCE(SUM(e.final_amount),0) amount FROM `{$this->expenses_table}` e LEFT JOIN `{$this->categories_table}` c ON c.id=e.category_id AND c.deleted=0 AND (c.unit_id=e.unit_id OR c.unit_id IS NULL) WHERE e.unit_id=? AND e.deleted=0 AND e.status<>? AND e.reference_month=? GROUP BY e.category_id,c.name ORDER BY amount DESC LIMIT 12", [$this->unit_id, "cancelled", $month])->getResult();
        $allocations_table = $this->db->prefixTable("gd_expense_allocations");
        $centers_table = $this->db->prefixTable("gd_cost_centers");
        $by_center = $this->db->query("SELECT name,COALESCE(SUM(amount),0) amount FROM (SELECT COALESCE(cc.name,'Sem centro') name,e.final_amount amount FROM `{$this->expenses_table}` e LEFT JOIN `{$centers_table}` cc ON cc.id=e.cost_center_id AND cc.deleted=0 AND (cc.unit_id=e.unit_id OR cc.unit_id IS NULL) WHERE e.unit_id=? AND e.deleted=0 AND e.status<>? AND e.reference_month=? AND e.cost_center_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `{$allocations_table}` a0 WHERE a0.unit_id=e.unit_id AND a0.expense_id=e.id AND a0.deleted=0) UNION ALL SELECT COALESCE(cc.name,'Rateio sem centro') name,a.amount FROM `{$allocations_table}` a JOIN `{$this->expenses_table}` e ON e.id=a.expense_id AND e.unit_id=a.unit_id AND e.deleted=0 AND e.status<>? AND e.reference_month=? LEFT JOIN `{$centers_table}` cc ON cc.id=a.cost_center_id AND cc.deleted=0 AND (cc.unit_id=a.unit_id OR cc.unit_id IS NULL) WHERE a.unit_id=? AND a.deleted=0 AND a.cost_center_id IS NOT NULL) center_values GROUP BY name ORDER BY amount DESC LIMIT 12", [$this->unit_id, "cancelled", $month, "cancelled", $month, $this->unit_id])->getResult();
        $budget = $this->budget_summary($month);
        return ["reference_month" => $month, "total" => $total, "paid" => $paid, "balance" => $balance, "overdue" => $overdue, "months" => $rows, "by_category" => $by_category, "by_center" => $by_center, "budget" => $budget];
    }

    public function save_allocations(int $expense_id, array $lines): array
    {
        $expense = $this->get_scoped($expense_id);
        if (!$expense) throw new \DomainException("gd_record_not_found");
        $valid = [];
        $percentage_total = 0;
        $amount_total = "0.00";
        foreach ($lines as $line) {
            if (!is_array($line)) continue;
            $percentage = DataNormalizationService::decimal($line["percentage"] ?? "", 4);
            $amount = DataNormalizationService::decimal($line["amount"] ?? "", 2);
            if (DataNormalizationService::decimalCompare($percentage, "0.0000") <= 0) throw new \DomainException("gd_invalid_cost_allocation");
            $percentage_total += $this->percentage_units($percentage);
            $amount_total = $this->add($amount_total, $amount);
            $area = $this->assert_area((int) ($line["business_area_id"] ?? 0));
            $center = $this->assert_cost_center((int) ($line["cost_center_id"] ?? 0), $area);
            $resource = $this->assert_resource((int) ($line["resource_id"] ?? 0));
            if (!$area && !$center && !$resource) throw new \DomainException("gd_invalid_cost_allocation");
            $valid[] = ["unit_id" => $this->unit_id, "expense_id" => $expense_id, "business_area_id" => $area, "cost_center_id" => $center, "resource_id" => $resource, "percentage" => $percentage, "amount" => $amount];
        }
        if (!$valid || $percentage_total !== 1000000 || $this->compare($amount_total, (string) $expense->final_amount) !== 0) throw new \DomainException("gd_cost_allocation_total");
        $table = $this->db->prefixTable("gd_expense_allocations");
        $before = $this->db->table($table)->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->where("deleted", 0)->get()->getResult();
        $this->db->transBegin();
        try {
            $now = gmdate("Y-m-d H:i:s");
            $this->db->table($table)->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->where("deleted", 0)->update(["deleted" => 1, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]);
            foreach ($valid as $line) {
                $line["created_at"] = $now; $line["updated_at"] = $now; $line["created_by"] = $this->actor_id ?: null; $line["updated_by"] = $this->actor_id ?: null; $line["deleted"] = 0;
                $this->db->table($table)->insert($line);
            }
            $after = $this->db->table($table)->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->where("deleted", 0)->get()->getResult();
            $this->audit_change("allocation_update", "cost", $expense_id, array_map(static fn($v) => (array) $v, $before), array_map(static fn($v) => (array) $v, $after));
            if ($this->db->transCommit() === false) throw new \RuntimeException("gd_save_failed");
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
        return ["expense_id" => $expense_id, "allocations" => $after];
    }

    public function create_installments(array $input): array
    {
        $total = DataNormalizationService::decimal($input["gross_amount"] ?? ($input["amount"] ?? ""), 2);
        $count = (int) ($input["installment_total"] ?? 0);
        if ($count < 2 || $count > 120 || $this->compare($total, "0.00") <= 0) throw new \DomainException("gd_invalid_installment");
        $start = $this->valid_date($input["issue_date"] ?? gmdate("Y-m-d"));
        if (!$start) throw new \DomainException("gd_invalid_date");
        $group = trim((string) ($input["installment_group_id"] ?? ""));
        if ($group === "") $group = bin2hex(random_bytes(16));
        $total_cents = $this->cents($total); $base = intdiv($total_cents, $count); $remainder = $total_cents % $count; $created = [];
        $date = new \DateTimeImmutable($start);
        $anchor_day = (int) $date->format("d");
        for ($i = 1; $i <= $count; $i++) {
            $occurrence = "installment:{$group}:{$i}";
            $existing = $this->db->table($this->expenses_table)->where("unit_id", $this->unit_id)->where("occurrence_key", $occurrence)->where("deleted", 0)->get(1)->getRow();
            if ($existing) { $created[] = (int) $existing->id; continue; }
            $installment_date = $this->month_with_anchor($date, $i - 1, $anchor_day);
            $due = $installment_date->format("Y-m-d");
            $row = $input; $row["gross_amount"] = $this->money($base + ($i <= $remainder ? 1 : 0)); $row["issue_date"] = $due; $row["due_date"] = $due; $row["installment_group_id"] = $group; $row["installment_number"] = $i; $row["installment_total"] = $count; $row["occurrence_key"] = $occurrence; $row["discount_amount"] = "0.00"; $row["interest_amount"] = "0.00"; $row["penalty_amount"] = "0.00";
            $created[] = (int) ($this->save($row)["id"] ?? 0);
        }
        return ["installment_group_id" => $group, "ids" => $created, "count" => count($created)];
    }

    public function budget_summary(string $month): array
    {
        $budget_table = $this->db->prefixTable("gd_cost_budgets");
        $budget = "0.00";
        if ($this->db->tableExists($budget_table)) $budget = DataNormalizationService::decimal((string) ($this->db->query("SELECT COALESCE(SUM(amount),0) total FROM `{$budget_table}` WHERE unit_id=? AND reference_month=? AND deleted=0 AND status='active'", [$this->unit_id, $month])->getRow()->total ?? "0"), 2);
        $actual = DataNormalizationService::decimal((string) ($this->db->query("SELECT COALESCE(SUM(final_amount),0) total FROM `{$this->expenses_table}` WHERE unit_id=? AND reference_month=? AND deleted=0 AND status<>?", [$this->unit_id, $month, "cancelled"])->getRow()->total ?? "0"), 2);
        return ["budget" => $budget, "actual" => $actual, "variance" => $this->sub($actual, $budget), "over_budget" => $this->compare($actual, $budget) > 0];
    }

    public function paid_amount(int $expense_id): string
    {
        $table = $this->db->prefixTable("gd_expense_payments");
        if (!$this->db->tableExists($table)) return "0.00";
        $row = $this->db->query("SELECT COALESCE(SUM(amount),0) total FROM `{$table}` WHERE unit_id=? AND expense_id=? AND deleted=0 AND status IN ('confirmed','legacy_migrated')", [$this->unit_id, $expense_id])->getRow();
        return DataNormalizationService::decimal((string) ($row->total ?? "0"), 2);
    }

    public function update_payment_totals(int $expense_id): object
    {
        $expense = $this->get_scoped($expense_id);
        if (!$expense) throw new \DomainException("gd_record_not_found");
        $paid = $this->paid_amount($expense_id); $balance = $this->sub((string) $expense->final_amount, $paid);
        if ($this->compare($balance, "0.00") < 0) throw new \DomainException("gd_finance_negative_balance");
        $last = $this->db->table($this->db->prefixTable("gd_expense_payments"))->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->whereIn("status", ["confirmed", "legacy_migrated"])->where("deleted", 0)->orderBy("payment_date", "DESC")->orderBy("id", "DESC")->get(1)->getRow();
        $status = $this->status_for((string) $expense->final_amount, $paid, $expense->due_date, (string) $expense->status);
        if ((string) $expense->status === "cancelled") $status = "cancelled";
        $data = ["paid_amount" => $paid, "balance_amount" => $balance, "amount" => $expense->final_amount, "status" => $status, "paid_date" => $last->payment_date ?? null, "lock_version" => (int) $expense->lock_version + 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null];
        $this->db->table($this->expenses_table)->where("id", $expense_id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $expense->lock_version)->update($data);
        return $this->get_scoped($expense_id);
    }

    private function category(?int $id): ?object
    {
        if (!$id) return null;
        return $this->db->table($this->categories_table)->where("id", $id)->where("deleted", 0)->where("status", "active")->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow();
    }
    private function assert_cost_category(int $id): ?int
    {
        if (!$id) return null;
        if (!$this->category($id)) throw new \DomainException("gd_invalid_cost_category");
        return $id;
    }
    private function assert_subcategory(int $id, ?int $parent): ?int
    {
        if (!$id) return null;
        $row = $this->category($id);
        if (!$row || !$parent || (int) $row->parent_id !== $parent) throw new \DomainException("gd_invalid_cost_subcategory");
        return $id;
    }
    private function category_name(int $id): string
    {
        $row = $this->category($id); return (string) ($row->name ?? "");
    }
    private function assert_resource(int $id): ?int
    {
        if (!$id) return null;
        $table = $this->db->prefixTable("gd_resources");
        if (!$this->db->tableExists($table) || !$this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults()) throw new \DomainException("gd_invalid_resource");
        return $id;
    }
    private function critical_fields_changed(object $old, array $input, string $final, string $gross, string $discount, string $interest, string $penalty): bool
    {
        foreach ([["gross_amount", $gross, $old->gross_amount], ["discount_amount", $discount, $old->discount_amount], ["interest_amount", $interest, $old->interest_amount], ["penalty_amount", $penalty, $old->penalty_amount], ["category_id", $input["category_id"] ?? $old->category_id, $old->category_id], ["subcategory_id", $input["subcategory_id"] ?? $old->subcategory_id, $old->subcategory_id], ["nature", $input["nature"] ?? $old->nature, $old->nature], ["cost_behavior", $input["cost_behavior"] ?? $old->cost_behavior, $old->cost_behavior]] as [$field, $new, $previous]) {
            if ((string) $new !== (string) $previous) return true;
        }
        return (string) $final !== (string) $old->final_amount;
    }
    private function status_for(string $final, string $paid, ?string $due, string $base): string
    {
        if ($this->compare($paid, "0.00") === 0) return $base === "planned" ? "planned" : "pending";
        return $this->compare($paid, $final) >= 0 ? "paid" : "partial";
    }
    private function display_status(object $expense): string
    {
        if ((string) $expense->status === "cancelled") return "cancelled";
        if (in_array((string) $expense->status, ["pending", "partial", "planned"], true) && $expense->due_date && (string) $expense->due_date < gmdate("Y-m-d") && $this->compare((string) $expense->balance_amount, "0.00") > 0) return "overdue";
        return (string) $expense->status;
    }
    private function percentage_units(string $value): int
    {
        [$integer, $fraction] = array_pad(explode(".", $value, 2), 2, "");
        return ((int) $integer * 10000) + (int) str_pad(substr($fraction, 0, 4), 4, "0");
    }
    private function compare(string $a, string $b): int { return DataNormalizationService::decimalCompare(DataNormalizationService::decimal($a, 2), DataNormalizationService::decimal($b, 2)); }
    private function cents(string $value): int
    {
        $value = DataNormalizationService::decimal($value, 2); [$i, $f] = explode(".", $value); return ((int) $i * 100) + (int) $f;
    }
    private function money(int $cents): string { return intdiv($cents, 100) . "." . str_pad((string) ($cents % 100), 2, "0", STR_PAD_LEFT); }
    private function add(string $a, string $b): string { return $this->money($this->cents($a) + $this->cents($b)); }
    private function sub(string $a, string $b): string { return $this->money($this->cents($a) - $this->cents($b)); }
    private function month_with_anchor(\DateTimeImmutable $date, int $offset, int $anchor_day): \DateTimeImmutable
    {
        $target = $date->modify("first day of this month")->modify("+{$offset} month");
        return $target->setDate((int) $target->format("Y"), (int) $target->format("m"), min($anchor_day, (int) $target->format("t")));
    }
}
