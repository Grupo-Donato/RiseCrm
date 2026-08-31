<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Templates e geração lazy/idempotente de ocorrências de custos. */
final class CostRecurrenceService extends CatalogDataService
{
    private string $table;
    private CostService $costs;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->table = $this->db->prefixTable("gd_expense_recurrences");
        $this->costs = new CostService($unit_id, $actor_id, $login_user);
    }

    public function page(): array
    {
        return $this->db->table($this->table)->where("unit_id", $this->unit_id)->where("deleted", 0)->orderBy("status", "ASC")->orderBy("next_generation", "ASC")->get()->getResult();
    }

    public function save(array $input, int $id = 0): array
    {
        $old = $id ? $this->get($id) : null;
        if ($id && !$old) throw new \DomainException("gd_record_not_found");
        $name = DataNormalizationService::text($input["name"] ?? ($old->name ?? ""));
        $description = DataNormalizationService::text($input["description"] ?? ($old->description ?? $name));
        $frequency = (string) ($input["frequency"] ?? ($old->frequency ?? "monthly"));
        $interval = max(1, (int) ($input["interval_value"] ?? ($old->interval_value ?? 1)));
        $start = $this->valid_date($input["start_date"] ?? ($old->start_date ?? gmdate("Y-m-d")));
        $end = $this->valid_date($input["end_date"] ?? ($old->end_date ?? ""));
        $due_day = (int) ($input["due_day"] ?? ($old->due_day ?? 0));
        $gross = DataNormalizationService::decimal($input["gross_amount"] ?? ($old->gross_amount ?? ""), 2);
        $discount = DataNormalizationService::decimal($input["discount_amount"] ?? ($old->discount_amount ?? "0"), 2);
        $interest = DataNormalizationService::decimal($input["interest_amount"] ?? ($old->interest_amount ?? "0"), 2);
        $penalty = DataNormalizationService::decimal($input["penalty_amount"] ?? ($old->penalty_amount ?? "0"), 2);
        if ($name === "" || $description === "" || !$start || ($end && $end < $start) || !in_array($frequency, Constants::EXPENSE_RECURRENCE_FREQUENCIES, true) || $due_day < 0 || $due_day > 31 || $this->compare($gross, "0.00") <= 0 || $this->compare($discount, $gross) > 0) throw new \DomainException("gd_invalid_recurrence");
        $category = $this->category_id((int) ($input["category_id"] ?? ($old->category_id ?? 0)));
        $subcategory = (int) ($input["subcategory_id"] ?? ($old->subcategory_id ?? 0));
        if ($subcategory) {
            $subcategory_row = $this->db->table($this->db->prefixTable("gd_expense_categories"))
                ->where("id", $subcategory)->where("parent_id", $category)->where("deleted", 0)
                ->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()
                ->get(1)->getRow();
            if (!$subcategory_row) throw new \DomainException("gd_invalid_cost_category");
        }
        $area = $this->assert_area((int) ($input["business_area_id"] ?? ($old->business_area_id ?? 0)));
        $center = $this->assert_cost_center((int) ($input["cost_center_id"] ?? ($old->cost_center_id ?? 0)), $area);
        $resource = (int) ($input["resource_id"] ?? ($old->resource_id ?? 0));
        if ($resource) $this->assert_rise_id("gd_resources", $resource, ["unit_id" => $this->unit_id]);
        $data = ["unit_id" => $this->unit_id, "name" => $name, "description" => $description, "payee" => DataNormalizationService::text($input["payee"] ?? ($old->payee ?? "")) ?: null, "nature" => $input["nature"] ?? ($old->nature ?? "operational_cost"), "cost_behavior" => $input["cost_behavior"] ?? ($old->cost_behavior ?? "fixed"), "category_id" => $category, "subcategory_id" => $subcategory ?: null, "business_area_id" => $area, "cost_center_id" => $center, "resource_id" => $resource ?: null, "gross_amount" => $gross, "discount_amount" => $discount, "interest_amount" => $interest, "penalty_amount" => $penalty, "start_date" => $start, "end_date" => $end, "frequency" => $frequency, "interval_value" => $interval, "due_day" => $due_day ?: null, "next_generation" => $old ? $old->next_generation : $start, "status" => in_array(($input["status"] ?? ($old->status ?? "active")), ["active", "inactive"], true) ? ($input["status"] ?? ($old->status ?? "active")) : "active", "notes" => DataNormalizationService::text($input["notes"] ?? ($old->notes ?? "")) ?: null];
        if (!in_array($data["nature"], Constants::EXPENSE_NATURES, true) || !in_array($data["cost_behavior"], Constants::EXPENSE_COST_BEHAVIORS, true)) throw new \DomainException("gd_invalid_cost_classification");
        $data = $this->stamp($data, !$old);
        if ($old) $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->update($data);
        else { $this->db->table($this->table)->insert($data); $id = (int) $this->db->insertID(); }
        $fresh = $this->get($id);
        $this->audit_change($old ? "update" : "create", "cost_recurrence", $id, $old ? (array) $old : null, $fresh ? (array) $fresh : null);
        return ["id" => $id, "data" => $fresh];
    }

    public function generate(int $id, string $until = ""): array
    {
        $recurrence = $this->get($id);
        if (!$recurrence) throw new \DomainException("gd_record_not_found");
        $end = $this->valid_date($until) ?: gmdate("Y-m-d");
        if ($recurrence->end_date && $end > $recurrence->end_date) $end = $recurrence->end_date;
        $next = new \DateTimeImmutable((string) $recurrence->next_generation);
        $anchor_day = (int) ($recurrence->due_day ?: (new \DateTimeImmutable((string) $recurrence->start_date))->format("d"));
        $generated = [];
        $guard = 0;
        while ($next->format("Y-m-d") <= $end && $guard++ < 120) {
            $date = $next->format("Y-m-d");
            $occurrence = "recurrence:{$id}:{$date}";
            $existing = $this->db->table($this->db->prefixTable("gd_expenses"))->where("unit_id", $this->unit_id)->where("occurrence_key", $occurrence)->where("deleted", 0)->get(1)->getRow();
            if ($existing) $generated[] = (int) $existing->id;
            else {
                $result = $this->costs->save(["description" => $recurrence->description, "payee" => $recurrence->payee, "issue_date" => $date, "due_date" => $this->due_date($next, (int) $recurrence->due_day), "reference_month" => substr($date, 0, 7), "gross_amount" => $recurrence->gross_amount, "discount_amount" => $recurrence->discount_amount, "interest_amount" => $recurrence->interest_amount, "penalty_amount" => $recurrence->penalty_amount, "nature" => $recurrence->nature, "cost_behavior" => $recurrence->cost_behavior, "category_id" => $recurrence->category_id, "subcategory_id" => $recurrence->subcategory_id, "business_area_id" => $recurrence->business_area_id, "cost_center_id" => $recurrence->cost_center_id, "resource_id" => $recurrence->resource_id, "recurrence_id" => $id, "occurrence_key" => $occurrence, "notes" => $recurrence->notes]);
                $generated[] = (int) $result["id"];
                $this->audit_change("recurrence_generated", "cost", (int) $result["id"], null, (array) $this->costs->get_scoped((int) $result["id"]), ["recurrence_id" => $id, "occurrence" => $date]);
            }
            $next = $this->advance($next, (string) $recurrence->frequency, (int) $recurrence->interval_value, $anchor_day);
        }
        if (!$generated) {
            $existing_rows = $this->db->table($this->db->prefixTable("gd_expenses"))
                ->select("id")->where("unit_id", $this->unit_id)->where("recurrence_id", $id)->where("deleted", 0)
                ->where("issue_date >=", $recurrence->start_date)->where("issue_date <=", $end)
                ->orderBy("issue_date", "ASC")->orderBy("id", "ASC")->get()->getResult();
            $generated = array_map(static fn($row) => (int) $row->id, $existing_rows);
        }
        $last = $generated ? end($generated) : 0;
        $last_date = $last ? $this->costs->get_scoped((int) $last)->issue_date : null;
        $status = ($recurrence->end_date && $next->format("Y-m-d") > $recurrence->end_date) ? "inactive" : $recurrence->status;
        $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->update(["next_generation" => $next->format("Y-m-d"), "last_generation" => $last_date, "status" => $status, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
        return ["recurrence_id" => $id, "ids" => $generated, "generated" => count($generated), "next_generation" => $next->format("Y-m-d")];
    }

    public function get(int $id): ?object { return $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow(); }
    private function category_id(int $id): ?int { if (!$id) return null; $row = $this->db->table($this->db->prefixTable("gd_expense_categories"))->where("id", $id)->where("deleted", 0)->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow(); if (!$row) throw new \DomainException("gd_invalid_cost_category"); return $id; }
    private function due_date(\DateTimeImmutable $date, int $day): string { if ($day <= 0) return $date->format("Y-m-d"); $last = (int) $date->format("t"); return $date->setDate((int) $date->format("Y"), (int) $date->format("m"), min($day, $last))->format("Y-m-d"); }
    private function advance(\DateTimeImmutable $date, string $frequency, int $interval, int $anchor_day): \DateTimeImmutable
    {
        $months = match ($frequency) {
            "monthly" => $interval,
            "quarterly" => 3 * $interval,
            "semiannual" => 6 * $interval,
            "annual" => 12 * $interval,
            default => 0,
        };
        if ($months > 0) {
            $day = (int) $date->format("d");
            $target = $date->modify("first day of this month")->modify("+{$months} month");
            return $target->setDate((int) $target->format("Y"), (int) $target->format("m"), min($anchor_day ?: $day, (int) $target->format("t")));
        }
        return match ($frequency) {
            "weekly" => $date->modify("+{$interval} week"),
            "biweekly" => $date->modify("+" . (2 * $interval) . " week"),
            default => $date->modify("+{$interval} day"),
        };
    }
    private function compare(string $a, string $b): int { return DataNormalizationService::decimalCompare(DataNormalizationService::decimal($a, 2), DataNormalizationService::decimal($b, 2)); }
}
