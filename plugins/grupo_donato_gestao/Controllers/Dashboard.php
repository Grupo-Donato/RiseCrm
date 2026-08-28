<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;

/**
 * Painel executivo do Grupo Donato.
 *
 * O painel é um agregado de leitura: todos os números são calculados na
 * unidade ativa e a mesma agenda/contas financeiras alimentam Academia,
 * Quadras e Churrasqueiras.
 */
class Dashboard extends Gd_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_dashboard_view");
    }

    public function index()
    {
        $schema_versions = $this->gd_model("Gd_schema_versions_model");
        $applied = $schema_versions->get_applied_version();
        $active_unit = $this->unit_context->get_active_unit();
        $unit_id = (int) ($this->active_unit_id() ?? 0);

        $timezone_name = (string) ($active_unit->timezone ?? "America/Sao_Paulo");
        try {
            $timezone = new \DateTimeZone($timezone_name ?: "America/Sao_Paulo");
        } catch (\Throwable $e) {
            $timezone = new \DateTimeZone("America/Sao_Paulo");
            $timezone_name = $timezone->getName();
        }

        $now = new \DateTimeImmutable("now", $timezone);
        $requested_period = trim((string) $this->request->getGet("period"));
        $period_key = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested_period) ? $requested_period : $now->format("Y-m");
        $period_start = \DateTimeImmutable::createFromFormat("!Y-m-d", $period_key . "-01", $timezone);
        if (!$period_start) {
            $period_start = $now->modify("first day of this month")->setTime(0, 0);
            $period_key = $period_start->format("Y-m");
        }
        $period_end = $period_start->modify("+1 month");
        $today = $now->format("Y-m-d");

        $dashboard = $unit_id
            ? $this->dashboard_data($unit_id, $period_start, $period_end, $timezone, $today)
            : $this->empty_dashboard();

        $recent_audit = [];
        if ($this->access->can("gd_audit_view")) {
            $recent_audit = $this->gd_model("Gd_audit_logs_model")->get_details(["limit" => 6])->getResult();
        }

        $view_data = array_merge($dashboard, [
            "period_key" => $period_key,
            "period_label" => $period_start->format("m/Y"),
            "today_label" => $now->format("d/m/Y"),
            "timezone_name" => $timezone_name,
            "active_unit" => $active_unit,
            "can_finance" => $this->access->can("gd_finance_view"),
            "can_students" => $this->access->can("gd_students_manage"),
            "can_classes" => $this->access->can("gd_classes_manage"),
            "can_attendance" => $this->access->can("gd_attendance_manage"),
            "can_bookings" => $this->access->can("gd_bookings_manage"),
            "can_court_rentals" => $this->access->can("gd_court_rentals_manage"),
            "can_barbecue_rentals" => $this->access->can("gd_barbecue_rentals_manage"),
            "can_receivables" => $this->access->can("gd_receivables_manage"),
            "can_payments" => $this->access->can("gd_payments_manage"),
            "can_expenses" => $this->access->can("gd_expenses_manage"),
            "plugin_version" => Constants::PLUGIN_VERSION,
            "schema_applied" => $applied ?: "-",
            "schema_target" => Constants::SCHEMA_TARGET,
            "schema_pending" => $applied !== Constants::SCHEMA_TARGET,
            "schema_failed" => $schema_versions->has_failed(),
            "can_view_audit" => $this->access->can("gd_audit_view"),
            "recent_audit" => $recent_audit,
        ]);

        return $this->gd_render("dashboard/index", $view_data);
    }

    private function dashboard_data(int $unit_id, \DateTimeImmutable $period_start, \DateTimeImmutable $period_end, \DateTimeZone $timezone, string $today): array
    {
        $db = db_connect();
        $prefix = $db->getPrefix();
        $table = static fn (string $name): string => $prefix . $name;
        $exists = static function (string $name) use ($db, $prefix): bool {
            return $db->tableExists($prefix . $name);
        };
        $period_from = $period_start->format("Y-m-d");
        $period_to = $period_end->modify("-1 day")->format("Y-m-d");

        $summary = [
            "received" => 0.0, "expenses" => 0.0, "result" => 0.0,
            "open" => 0.0, "overdue" => 0.0, "billed" => 0.0,
            "charges" => 0, "payments" => 0, "overdue_count" => 0, "open_count" => 0,
        ];

        $receivable_table = $table("gd_receivables");
        if ($exists("gd_receivables")) {
            $row = $db->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN status IN ('open','partial','overdue') AND balance_amount > 0 THEN balance_amount ELSE 0 END), 0) AS open_amount,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 AND due_date < ? THEN balance_amount ELSE 0 END), 0) AS overdue_amount,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND issue_date BETWEEN ? AND ? THEN original_amount ELSE 0 END), 0) AS billed_amount,
                    SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 AND due_date < ? THEN 1 ELSE 0 END) AS overdue_count,
                    SUM(CASE WHEN status IN ('open','partial','overdue') AND balance_amount > 0 THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status <> 'cancelled' AND issue_date BETWEEN ? AND ? THEN 1 ELSE 0 END) AS charges
                 FROM `{$receivable_table}`
                 WHERE unit_id = ? AND deleted = 0",
                [$today, $period_from, $period_to, $today, $period_from, $period_to, $unit_id]
            )->getRow();
            $summary["open"] = (float) ($row->open_amount ?? 0);
            $summary["overdue"] = (float) ($row->overdue_amount ?? 0);
            $summary["billed"] = (float) ($row->billed_amount ?? 0);
            $summary["overdue_count"] = (int) ($row->overdue_count ?? 0);
            $summary["open_count"] = (int) ($row->open_count ?? 0);
            $summary["charges"] = (int) ($row->charges ?? 0);
        }

        if ($exists("gd_payments")) {
            $row = $db->query(
                "SELECT COALESCE(SUM(amount), 0) AS received, COUNT(*) AS payments
                 FROM `{$table("gd_payments")}`
                 WHERE unit_id = ? AND deleted = 0 AND status = 'confirmed'
                   AND payment_date BETWEEN ? AND ?",
                [$unit_id, $period_from, $period_to]
            )->getRow();
            $summary["received"] = (float) ($row->received ?? 0);
            $summary["payments"] = (int) ($row->payments ?? 0);
        }

        if ($exists("gd_expenses")) {
            $row = $db->query(
                "SELECT COALESCE(SUM(amount), 0) AS expenses
                 FROM `{$table("gd_expenses")}`
                 WHERE unit_id = ? AND deleted = 0 AND status = 'paid'
                   AND paid_date BETWEEN ? AND ?",
                [$unit_id, $period_from, $period_to]
            )->getRow();
            $summary["expenses"] = (float) ($row->expenses ?? 0);
        }
        $summary["result"] = $summary["received"] - $summary["expenses"];

        $finance_by_source = [
            "enrollment" => $this->empty_product_finance(),
            "court_rental" => $this->empty_product_finance(),
            "barbecue_rental" => $this->empty_product_finance(),
            "manual" => $this->empty_product_finance(),
            "other" => $this->empty_product_finance(),
        ];
        if ($exists("gd_receivables")) {
            $rows = $db->query(
                "SELECT source_type,
                    COUNT(*) AS charges,
                    SUM(CASE WHEN status = 'paid' OR balance_amount <= 0 THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 AND due_date >= ? THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 AND due_date < ? THEN 1 ELSE 0 END) AS overdue_count,
                    COALESCE(SUM(original_amount), 0) AS billed_amount,
                    COALESCE(SUM(paid_amount), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 THEN balance_amount ELSE 0 END), 0) AS balance_amount,
                    COALESCE(SUM(CASE WHEN status <> 'cancelled' AND balance_amount > 0 AND due_date < ? THEN balance_amount ELSE 0 END), 0) AS overdue_amount
                 FROM `{$receivable_table}`
                 WHERE unit_id = ? AND deleted = 0 AND status <> 'cancelled'
                 GROUP BY source_type",
                [$today, $today, $today, $unit_id]
            )->getResult();
            foreach ($rows as $row) {
                $source = (string) ($row->source_type ?? "other");
                if (!isset($finance_by_source[$source])) {
                    $finance_by_source[$source] = $this->empty_product_finance();
                }
                $finance_by_source[$source] = [
                    "charges" => (int) ($row->charges ?? 0),
                    "paid_count" => (int) ($row->paid_count ?? 0),
                    "open_count" => (int) ($row->open_count ?? 0),
                    "overdue_count" => (int) ($row->overdue_count ?? 0),
                    "billed_amount" => (float) ($row->billed_amount ?? 0),
                    "paid_amount" => (float) ($row->paid_amount ?? 0),
                    "balance_amount" => (float) ($row->balance_amount ?? 0),
                    "overdue_amount" => (float) ($row->overdue_amount ?? 0),
                    "received_period" => 0.0,
                    "payment_count" => 0,
                ];
            }
        }
        if ($exists("gd_receivables") && $exists("gd_payment_allocations") && $exists("gd_payments")) {
            $rows = $db->query(
                "SELECT r.source_type,
                    COALESCE(SUM(a.allocated_amount), 0) AS received_period,
                    COUNT(DISTINCT p.id) AS payment_count
                 FROM `{$table("gd_payment_allocations")}` a
                 INNER JOIN `{$receivable_table}` r ON r.id = a.receivable_id AND r.unit_id = a.unit_id AND r.deleted = 0
                 INNER JOIN `{$table("gd_payments")}` p ON p.id = a.payment_id AND p.unit_id = a.unit_id AND p.deleted = 0 AND p.status = 'confirmed'
                 WHERE a.unit_id = ? AND a.status = 'active' AND p.payment_date BETWEEN ? AND ?
                 GROUP BY r.source_type",
                [$unit_id, $period_from, $period_to]
            )->getResult();
            foreach ($rows as $row) {
                $source = (string) ($row->source_type ?? "other");
                if (!isset($finance_by_source[$source])) {
                    $finance_by_source[$source] = $this->empty_product_finance();
                }
                $finance_by_source[$source]["received_period"] = (float) ($row->received_period ?? 0);
                $finance_by_source[$source]["payment_count"] = (int) ($row->payment_count ?? 0);
            }
        }

        $today_local = new \DateTimeImmutable($today . " 00:00:00", $timezone);
        $tomorrow_local = $today_local->modify("+1 day");
        $week_local = $today_local->modify("+7 days");
        $today_start_utc = $today_local->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
        $tomorrow_start_utc = $tomorrow_local->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
        $week_start_utc = $week_local->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
        $now_utc = (new \DateTimeImmutable("now", new \DateTimeZone("UTC")))->format("Y-m-d H:i:s");

        $booking_counts = ["court" => ["today" => 0, "next_7_days" => 0], "barbecue_area" => ["today" => 0, "next_7_days" => 0]];
        if ($exists("gd_bookings") && $exists("gd_booking_resources") && $exists("gd_resources")) {
            $statuses = Constants::BOOKING_BLOCKING_STATUSES;
            $status_placeholders = implode(",", array_fill(0, count($statuses), "?"));
            $rows = $db->query(
                "SELECT res.resource_type,
                    COUNT(DISTINCT CASE WHEN b.starts_at_utc >= ? AND b.starts_at_utc < ? THEN b.id END) AS today_count,
                    COUNT(DISTINCT CASE WHEN b.starts_at_utc >= ? AND b.starts_at_utc < ? THEN b.id END) AS next_count
                 FROM `{$table("gd_bookings")}` b
                 INNER JOIN `{$table("gd_booking_resources")}` br ON br.booking_id = b.id AND br.unit_id = b.unit_id AND br.deleted = 0
                 INNER JOIN `{$table("gd_resources")}` res ON res.id = br.resource_id AND res.unit_id = br.unit_id AND res.deleted = 0
                 WHERE b.unit_id = ? AND b.deleted = 0 AND b.booking_type = 'customer_rental'
                   AND b.status IN ({$status_placeholders})
                   AND (b.status <> 'hold' OR b.hold_expires_at_utc > ?)
                   AND b.starts_at_utc >= ? AND b.starts_at_utc < ?
                 GROUP BY res.resource_type",
                array_merge([$today_start_utc, $tomorrow_start_utc, $today_start_utc, $week_start_utc, $unit_id], $statuses, [$now_utc, $today_start_utc, $week_start_utc])
            )->getResult();
            foreach ($rows as $row) {
                $type = (string) ($row->resource_type ?? "");
                if (isset($booking_counts[$type])) {
                    $booking_counts[$type] = ["today" => (int) ($row->today_count ?? 0), "next_7_days" => (int) ($row->next_count ?? 0)];
                }
            }
        }

        $academy = [
            "active_students" => $this->count($db, $exists("gd_school_profiles") ? $table("gd_school_profiles") : "", "unit_id = ? AND status = 'active' AND deleted = 0", [$unit_id]),
            "active_classes" => $this->count($db, $exists("gd_classes") ? $table("gd_classes") : "", "unit_id = ? AND status = 'active' AND deleted = 0", [$unit_id]),
            "classes_today" => 0,
            "attendance_today" => 0,
        ];
        if ($exists("gd_classes")) {
            $weekday = (int) $today_local->format("N");
            $academy["classes_today"] = $this->count($db, $table("gd_classes"), "unit_id = ? AND status = 'active' AND deleted = 0 AND weekdays IS NOT NULL AND FIND_IN_SET(?, weekdays)", [$unit_id, $weekday]);
        }
        if ($exists("gd_attendance_sessions")) {
            $academy["attendance_today"] = $this->count($db, $table("gd_attendance_sessions"), "unit_id = ? AND attendance_date = ? AND deleted = 0", [$unit_id, $today]);
        }

        $courts = $this->rental_activity($db, $exists, $table, "gd_court_rentals", "court", $unit_id);
        $barbecues = $this->rental_activity($db, $exists, $table, "gd_barbecue_rentals", "barbecue_area", $unit_id);
        $courts["bookings_today"] = $booking_counts["court"]["today"];
        $courts["next_7_days"] = $booking_counts["court"]["next_7_days"];
        $barbecues["bookings_today"] = $booking_counts["barbecue_area"]["today"];
        $barbecues["next_7_days"] = $booking_counts["barbecue_area"]["next_7_days"];

        $agenda = $this->agenda_today($db, $exists, $table, $unit_id, $today_local, $tomorrow_local, $timezone, $now_utc);
        $upcoming = $this->upcoming_receivables($db, $exists, $table, $unit_id, $today);
        $trend = $this->financial_trend($db, $exists, $table, $unit_id, $period_start, $period_end);

        return [
            "summary" => $summary,
            "finance_by_source" => $finance_by_source,
            "academy" => $academy,
            "courts" => $courts,
            "barbecues" => $barbecues,
            "agenda" => $agenda,
            "upcoming_receivables" => $upcoming,
            "trend" => $trend,
            "catalog_products" => $exists("gd_products") ? $this->count($db, $table("gd_products"), "unit_id = ? AND status = 'active' AND deleted = 0", [$unit_id]) : 0,
            "today_events" => count($agenda),
            "today_classes" => (int) $academy["classes_today"],
            "today_bookings" => (int) $booking_counts["court"]["today"] + (int) $booking_counts["barbecue_area"]["today"],
            "active_contracts" => (int) $courts["recurring"] + (int) $barbecues["recurring"],
            "active_resources" => (int) $courts["resources"] + (int) $barbecues["resources"],
        ];
    }

    private function rental_activity($db, callable $exists, callable $table, string $rental_table, string $resource_type, int $unit_id): array
    {
        $data = ["recurring" => 0, "single" => 0, "resources" => 0, "bookings_today" => 0, "next_7_days" => 0];
        if ($exists($rental_table)) {
            $row = $db->query(
                "SELECT
                    SUM(CASE WHEN rental_type = 'recurring' THEN 1 ELSE 0 END) AS recurring,
                    SUM(CASE WHEN rental_type = 'single' THEN 1 ELSE 0 END) AS single
                 FROM `{$table($rental_table)}`
                 WHERE unit_id = ? AND status = 'active' AND deleted = 0",
                [$unit_id]
            )->getRow();
            $data["recurring"] = (int) ($row->recurring ?? 0);
            $data["single"] = (int) ($row->single ?? 0);
        }
        if ($exists("gd_resources")) {
            $data["resources"] = $this->count($db, $table("gd_resources"), "unit_id = ? AND resource_type = ? AND is_active = 1 AND is_bookable = 1 AND deleted = 0", [$unit_id, $resource_type]);
        }
        return $data;
    }

    private function agenda_today($db, callable $exists, callable $table, int $unit_id, \DateTimeImmutable $today_local, \DateTimeImmutable $tomorrow_local, \DateTimeZone $timezone, string $now_utc): array
    {
        $agenda = [];
        if ($exists("gd_classes")) {
            $weekday = (int) $today_local->format("N");
            $classes = $db->query(
                "SELECT name, modality, local_start_time, local_end_time
                 FROM `{$table("gd_classes")}`
                 WHERE unit_id = ? AND status = 'active' AND deleted = 0
                   AND weekdays IS NOT NULL AND FIND_IN_SET(?, weekdays)
                 ORDER BY local_start_time, name
                 LIMIT 30",
                [$unit_id, $weekday]
            )->getResult();
            foreach ($classes as $class) {
                $start = substr((string) ($class->local_start_time ?? ""), 0, 5);
                $end = substr((string) ($class->local_end_time ?? ""), 0, 5);
                $agenda[] = [
                    "sort" => $start,
                    "time" => $start . ($end ? " — " . $end : ""),
                    "title" => (string) ($class->name ?? "Aula"),
                    "product" => "GD Academy",
                    "kind" => "academy",
                    "status" => "Programada",
                    "meta" => $class->modality ? (string) $class->modality : "Aula",
                ];
            }
        }

        if ($exists("gd_bookings") && $exists("gd_booking_resources") && $exists("gd_resources")) {
            $statuses = Constants::BOOKING_BLOCKING_STATUSES;
            $status_placeholders = implode(",", array_fill(0, count($statuses), "?"));
            $start_utc = $today_local->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
            $end_utc = $tomorrow_local->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
            $rows = $db->query(
                "SELECT b.booking_number, b.title, b.status, b.starts_at_utc, b.ends_at_utc,
                    MAX(res.resource_type) AS resource_type,
                    GROUP_CONCAT(DISTINCT res.code ORDER BY res.code SEPARATOR ', ') AS resource_codes
                 FROM `{$table("gd_bookings")}` b
                 INNER JOIN `{$table("gd_booking_resources")}` br ON br.booking_id = b.id AND br.unit_id = b.unit_id AND br.deleted = 0
                 INNER JOIN `{$table("gd_resources")}` res ON res.id = br.resource_id AND res.unit_id = br.unit_id AND res.deleted = 0
                 WHERE b.unit_id = ? AND b.deleted = 0 AND b.booking_type = 'customer_rental'
                   AND b.status IN ({$status_placeholders})
                   AND (b.status <> 'hold' OR b.hold_expires_at_utc > ?)
                   AND b.starts_at_utc >= ? AND b.starts_at_utc < ?
                 GROUP BY b.id
                 ORDER BY b.starts_at_utc
                 LIMIT 30",
                array_merge([$unit_id], $statuses, [$now_utc, $start_utc, $end_utc])
            )->getResult();
            foreach ($rows as $row) {
                try {
                    $start = (new \DateTimeImmutable((string) $row->starts_at_utc, new \DateTimeZone("UTC")))->setTimezone($timezone);
                    $end = (new \DateTimeImmutable((string) $row->ends_at_utc, new \DateTimeZone("UTC")))->setTimezone($timezone);
                } catch (\Throwable $e) {
                    continue;
                }
                $type = (string) ($row->resource_type ?? "court");
                $is_barbecue = $type === Constants::BARBECUE_RESOURCE_TYPE;
                $agenda[] = [
                    "sort" => $start->format("H:i"),
                    "time" => $start->format("H:i") . " — " . $end->format("H:i"),
                    "title" => (string) ($row->title ?: ($row->booking_number ?? "Reserva")),
                    "product" => $is_barbecue ? "Churrasqueiras" : "Locações de quadras",
                    "kind" => $is_barbecue ? "barbecue" : "court",
                    "status" => ucfirst(str_replace("_", " ", (string) ($row->status ?? "confirmada"))),
                    "meta" => (string) ($row->resource_codes ?: "Agenda"),
                ];
            }
        }
        usort($agenda, static fn (array $a, array $b): int => strcmp((string) $a["sort"], (string) $b["sort"]));
        return array_slice($agenda, 0, 10);
    }

    private function upcoming_receivables($db, callable $exists, callable $table, int $unit_id, string $today): array
    {
        if (!$exists("gd_receivables")) {
            return [];
        }
        $customer_join = $exists("gd_customer_accounts")
            ? "LEFT JOIN `{$table("gd_customer_accounts")}` ca ON ca.id = r.customer_account_id AND ca.unit_id = r.unit_id AND ca.deleted = 0"
            : "";
        $customer_name = $exists("gd_customer_accounts") ? "ca.display_name AS customer_name" : "'' AS customer_name";
        return $db->query(
            "SELECT r.id, r.description, r.source_type, r.due_date, r.balance_amount, {$customer_name}
             FROM `{$table("gd_receivables")}` r {$customer_join}
             WHERE r.unit_id = ? AND r.deleted = 0 AND r.status <> 'cancelled'
               AND r.balance_amount > 0 AND r.due_date >= ?
             ORDER BY r.due_date, r.id
             LIMIT 6",
            [$unit_id, $today]
        )->getResult();
    }

    private function financial_trend($db, callable $exists, callable $table, int $unit_id, \DateTimeImmutable $period_start, \DateTimeImmutable $period_end): array
    {
        $from = $period_start->modify("-5 months")->format("Y-m-01");
        $to = $period_end->format("Y-m-d");
        $by_month = [];
        $cursor = $period_start->modify("-5 months");
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format("Y-m");
            $by_month[$key] = ["key" => $key, "label" => $this->month_label((int) $cursor->format("n")), "received" => 0.0, "expenses" => 0.0];
            $cursor = $cursor->modify("+1 month");
        }
        if ($exists("gd_payments")) {
            $rows = $db->query(
                "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS amount
                 FROM `{$table("gd_payments")}`
                 WHERE unit_id = ? AND deleted = 0 AND status = 'confirmed' AND payment_date >= ? AND payment_date < ?
                 GROUP BY month_key",
                [$unit_id, $from, $to]
            )->getResult();
            foreach ($rows as $row) {
                $key = (string) ($row->month_key ?? "");
                if (isset($by_month[$key])) {
                    $by_month[$key]["received"] = (float) ($row->amount ?? 0);
                }
            }
        }
        if ($exists("gd_expenses")) {
            $rows = $db->query(
                "SELECT DATE_FORMAT(paid_date, '%Y-%m') AS month_key, COALESCE(SUM(amount), 0) AS amount
                 FROM `{$table("gd_expenses")}`
                 WHERE unit_id = ? AND deleted = 0 AND status = 'paid' AND paid_date IS NOT NULL AND paid_date >= ? AND paid_date < ?
                 GROUP BY month_key",
                [$unit_id, $from, $to]
            )->getResult();
            foreach ($rows as $row) {
                $key = (string) ($row->month_key ?? "");
                if (isset($by_month[$key])) {
                    $by_month[$key]["expenses"] = (float) ($row->amount ?? 0);
                }
            }
        }
        foreach ($by_month as &$month) {
            $month["result"] = $month["received"] - $month["expenses"];
        }
        unset($month);
        return array_values($by_month);
    }

    private function month_label(int $month): string
    {
        return [1 => "Jan", 2 => "Fev", 3 => "Mar", 4 => "Abr", 5 => "Mai", 6 => "Jun", 7 => "Jul", 8 => "Ago", 9 => "Set", 10 => "Out", 11 => "Nov", 12 => "Dez"][$month] ?? "—";
    }

    private function empty_product_finance(): array
    {
        return [
            "charges" => 0, "paid_count" => 0, "open_count" => 0, "overdue_count" => 0,
            "billed_amount" => 0.0, "paid_amount" => 0.0, "balance_amount" => 0.0,
            "overdue_amount" => 0.0, "received_period" => 0.0, "payment_count" => 0,
        ];
    }

    private function empty_dashboard(): array
    {
        return [
            "summary" => ["received" => 0.0, "expenses" => 0.0, "result" => 0.0, "open" => 0.0, "overdue" => 0.0, "billed" => 0.0, "charges" => 0, "payments" => 0, "overdue_count" => 0, "open_count" => 0],
            "finance_by_source" => ["enrollment" => $this->empty_product_finance(), "court_rental" => $this->empty_product_finance(), "barbecue_rental" => $this->empty_product_finance(), "manual" => $this->empty_product_finance(), "other" => $this->empty_product_finance()],
            "academy" => ["active_students" => 0, "active_classes" => 0, "classes_today" => 0, "attendance_today" => 0],
            "courts" => ["recurring" => 0, "single" => 0, "resources" => 0, "bookings_today" => 0, "next_7_days" => 0],
            "barbecues" => ["recurring" => 0, "single" => 0, "resources" => 0, "bookings_today" => 0, "next_7_days" => 0],
            "agenda" => [], "upcoming_receivables" => [], "trend" => [], "catalog_products" => 0,
            "today_events" => 0, "today_classes" => 0, "today_bookings" => 0, "active_contracts" => 0, "active_resources" => 0,
        ];
    }

    private function count($db, string $table, string $where, array $binds): int
    {
        if ($table === "") {
            return 0;
        }
        $row = $db->query("SELECT COUNT(*) AS total FROM `{$table}` WHERE {$where}", $binds)->getRow();
        return (int) ($row->total ?? 0);
    }
}
