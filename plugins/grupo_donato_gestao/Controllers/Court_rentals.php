<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\BookingService;
use grupo_donato_gestao\Services\CourtRentalLifecycleService;
use grupo_donato_gestao\Services\CourtRentalService;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\TemporalService;

class Court_rentals extends Gd_Controller
{
    private int $unit_id;
    private CourtRentalService $service;
    private BookingService $bookings;
    private TemporalService $time;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_court_rentals_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->service = new CourtRentalService($this->unit_id, $this->user_id(), $this->login_user);
        $this->bookings = new BookingService($this->unit_id, $this->user_id(), $this->login_user);
        $this->time = new TemporalService($this->unit_id);
    }

    public function index()
    {
        return $this->gd_render("court_rentals/index", [
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "statuses" => Constants::COURT_RENTAL_STATUSES, "types" => Constants::COURT_RENTAL_TYPES,
            "resources" => $this->bookings->bookableResources(),
        ]);
    }

    public function monthly()
    {
        return $this->gd_render("court_rentals/monthly", [
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "statuses" => Constants::COURT_RENTAL_STATUSES, "resources" => $this->bookings->bookableResources(),
            "timezone" => $this->time->timezoneName(),
        ]);
    }

    public function list_data()
    {
        try {
            $result = $this->service->listPage(append_server_side_filtering_commmon_params($this->filters()));
            $rows = []; foreach ($result["data"] as $row) { $rows[] = $this->row($row); } $result["data"] = $rows;
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function monthly_data()
    {
        try {
            $result = $this->service->monthlyRentersList(append_server_side_filtering_commmon_params($this->filters()));
            $rows = []; foreach ($result["data"] as $row) { $rows[] = $this->monthlyRow($row); } $result["data"] = $rows;
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function view($id)
    {
        $rental = $this->service->get((int) $id);
        if (!$rental) { return show_404(); }
        return $this->gd_render("court_rentals/view", [
            "rental" => $rental, "timezone" => $this->time->timezoneName(),
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "can_status" => $this->access->can("gd_court_rentals_status_manage"),
            "can_override" => $this->access->can("gd_court_rentals_price_override"),
            "future_policies" => Constants::COURT_RENTAL_FUTURE_POLICIES,
            "financial" => $this->access->can("gd_finance_view") ? (new \grupo_donato_gestao\Services\FinanceService($this->unit_id, $this->user_id(), $this->login_user))->summary(["source_type" => "court_rental", "source_id" => (int) $rental->id]) : null,
            "can_generate_receivable" => $this->access->can("gd_receivables_manage"),
        ]);
    }

    public function single_modal() { return $this->wizardModal("court_rentals/single_modal"); }
    public function monthly_modal() { return $this->wizardModal("court_rentals/monthly_modal"); }

    private function wizardModal(string $view)
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            return $this->gd_view($view, [
                "resources" => $this->bookings->bookableResources(),
                "accounts" => $this->bookings->customerOptions("", 50),
                "frequencies" => Constants::BOOKING_SERIES_FREQUENCIES, "ends_modes" => Constants::BOOKING_SERIES_ENDS_MODES,
                "default_statuses" => Constants::BOOKING_SERIES_DEFAULT_STATUSES, "conflict_policies" => Constants::BOOKING_SERIES_CONFLICT_POLICIES,
                "timezone" => $this->time->timezoneName(),
            ]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function link_modal()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $rental = $this->service->get((int) $this->request->getPost("id"));
            if (!$rental) { return show_404(); }
            return $this->gd_view("court_rentals/link_modal", ["rental" => $rental, "link_kinds" => ["primary", "replacement"]]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function customer_options()
    {
        try { $this->access->require("gd_court_rentals_manage"); $rows = $this->bookings->customerOptions((string) $this->request->getPost("q")); return $this->response->setJSON(["results" => array_map(static fn($row) => ["id" => (int) $row["id"], "text" => $row["display_name"] . " (" . app_lang("gd_account_type_" . $row["account_type"]) . ")"], $rows)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function contact_options()
    {
        try { $this->access->require("gd_court_rentals_manage"); $rows = $this->bookings->contactOptions((int) $this->request->getPost("customer_account_id"), (string) $this->request->getPost("q")); return $this->response->setJSON(["results" => array_map(static fn($row) => ["id" => (int) $row["id"], "text" => $row["full_name"]], $rows)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function check_availability()
    {
        try { $this->access->require("gd_court_rentals_manage"); $this->json_success("", ["data" => $this->bookings->checkAvailability($this->bookingInput())]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function preview()
    {
        try { $this->access->require("gd_court_rentals_manage"); $this->json_success("", ["data" => (new \grupo_donato_gestao\Services\BookingSeriesService($this->unit_id, $this->user_id(), $this->login_user))->preview($this->seriesInput())]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function resolve_price()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $this->json_success("", ["data" => $this->service->resolvePrice([
                "product_id" => $this->request->getPost("product_id"), "variant_id" => $this->request->getPost("variant_id"),
                "resource_id" => $this->request->getPost("resource_id"), "price_list_id" => $this->request->getPost("price_list_id"),
                "quantity" => $this->request->getPost("quantity"), "reference_date" => $this->request->getPost("reference_date"),
            ])]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save_draft()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $type = (string) $this->request->getPost("rental_type");
            $result = $this->service->createDraft($this->commercialInput(), Constants::isCourtRentalType($type) ? $type : "single");
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save_single()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $input = $this->commercialInput() + $this->bookingInput();
            $result = $this->service->createWithBooking($input);
            $this->maybeActivate($result, (int) $result["id"], (int) $result["lock_version"]);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save_monthly()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $input = $this->commercialInput() + $this->seriesInput();
            $result = $this->service->createWithSeries($input);
            $this->maybeActivate($result, (int) $result["id"], (int) $result["lock_version"]);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function link_existing()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $result = $this->service->linkExisting((int) $this->request->getPost("id"), [
                "booking_id" => $this->request->getPost("booking_id"), "booking_series_id" => $this->request->getPost("booking_series_id"),
                "link_kind" => $this->request->getPost("link_kind"),
            ]);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function reprice()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $result = $this->service->reprice((int) $this->request->getPost("id"), $this->commercialInput() + ["lock_version" => $this->request->getPost("lock_version")], $this->access->can("gd_court_rentals_price_override"));
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function activate($id)
    {
        $this->writeStatus(fn() => (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->activate((int) $id, (int) $this->request->getPost("lock_version"), $this->access->can("gd_court_rentals_price_override"), (string) $this->request->getPost("justification")));
    }
    public function suspend($id)
    {
        $this->writeStatus(fn() => (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->suspend((int) $id, (int) $this->request->getPost("lock_version"), (string) $this->request->getPost("future_policy"), (string) $this->request->getPost("reason")));
    }
    public function resume($id)
    {
        $this->writeStatus(fn() => (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->resume((int) $id, (int) $this->request->getPost("lock_version")));
    }
    public function cancel($id)
    {
        $this->writeStatus(fn() => (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->cancel((int) $id, (int) $this->request->getPost("lock_version"), (string) $this->request->getPost("reason"), (string) $this->request->getPost("future_policy")));
    }
    public function complete($id)
    {
        $this->writeStatus(fn() => (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->complete((int) $id, (int) $this->request->getPost("lock_version")));
    }

    private function maybeActivate(array &$result, int $id, int $lock_version): void
    {
        if (!$this->request->getPost("activate")) { return; }
        $row = (new CourtRentalLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->activate($id, $lock_version, $this->access->can("gd_court_rentals_price_override"), (string) $this->request->getPost("justification"));
        $result["status"] = (string) $row->status;
        $result["lock_version"] = (int) $row->lock_version;
    }

    private function writeStatus(callable $operation): void
    {
        try { $this->access->require("gd_court_rentals_status_manage"); $row = $operation(); $this->json_success(app_lang("record_saved"), ["id" => (int) $row->id, "status" => (string) $row->status, "lock_version" => (int) $row->lock_version]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    /* ---------------- input whitelists ---------------- */

    private function filters(): array
    {
        return [
            "status" => $this->request->getPost("status"), "rental_type" => $this->request->getPost("rental_type"),
            "customer_account_id" => $this->request->getPost("customer_account_id"), "resource_id" => $this->request->getPost("resource_id"),
            "weekday" => $this->request->getPost("weekday"), "date_from" => $this->request->getPost("date_from"), "date_to" => $this->request->getPost("date_to"),
        ];
    }

    private function commercialInput(): array
    {
        return [
            "title" => $this->request->getPost("title"), "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"),
            "rental_type" => $this->request->getPost("rental_type"), "preferred_due_day" => $this->request->getPost("preferred_due_day"),
            "effective_from" => $this->request->getPost("effective_from"), "effective_until" => $this->request->getPost("effective_until"), "currency" => $this->request->getPost("currency"),
            "list_amount" => $this->request->getPost("list_amount"), "negotiated_amount" => $this->request->getPost("negotiated_amount"),
            "discount_amount" => $this->request->getPost("discount_amount"), "discount_reason" => $this->request->getPost("discount_reason"),
            "product_id" => $this->request->getPost("product_id"), "price_list_id" => $this->request->getPost("price_list_id"), "price_id" => $this->request->getPost("price_id"),
            "commercial_notes" => $this->request->getPost("commercial_notes"), "metadata" => $this->request->getPost("metadata"),
        ];
    }

    private function bookingInput(): array
    {
        return [
            "starts_at_local" => $this->request->getPost("starts_at_local"), "ends_at_local" => $this->request->getPost("ends_at_local"),
            "booking_status" => $this->request->getPost("booking_status"), "booking_type" => "customer_rental",
            "title" => $this->request->getPost("title"), "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"),
            "resources" => $this->normalizedResources($this->request->getPost("resources")),
        ];
    }

    private function seriesInput(): array
    {
        return [
            "frequency" => $this->request->getPost("frequency"), "interval_value" => $this->request->getPost("interval_value"),
            "weekdays" => $this->request->getPost("weekdays"), "monthly_day" => $this->request->getPost("monthly_day"),
            "local_start_time" => $this->request->getPost("local_start_time"), "local_end_time" => $this->request->getPost("local_end_time"),
            "starts_on" => $this->request->getPost("starts_on"), "ends_mode" => $this->request->getPost("ends_mode"), "ends_on" => $this->request->getPost("ends_on"),
            "max_occurrences" => $this->request->getPost("max_occurrences"), "default_booking_status" => $this->request->getPost("default_booking_status"),
            "conflict_policy" => $this->request->getPost("conflict_policy"), "generation_horizon_days" => $this->request->getPost("generation_horizon_days"),
            "booking_type" => "customer_rental", "title" => $this->request->getPost("title"),
            "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"),
            "resources" => $this->normalizedResources($this->request->getPost("resources")),
        ];
    }

    private function normalizedResources($raw): array
    {
        $out = []; if (!is_array($raw)) { return []; }
        foreach ($raw as $rid => $value) { if (!is_array($value) || empty($value["selected"])) { continue; } $out[] = ["resource_id" => (int) $rid, "buffer_before_minutes" => $value["buffer_before_minutes"] ?? 0, "buffer_after_minutes" => $value["buffer_after_minutes"] ?? 0]; }
        return $out;
    }

    /* ---------------- row rendering ---------------- */

    private function row(object $row): array
    {
        $actions = anchor(get_uri("grupo_donato/court-rentals/view/" . $row->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        return [
            $this->escape($row->rental_number), $this->escape($row->title), $this->escape($row->customer_name ?? "-"),
            app_lang("gd_court_rental_type_" . $row->rental_type), $this->escape($row->schedule["resource_names"] ?? ""),
            $this->validity($row), $this->contractedAmount($row), app_lang("gd_court_rental_status_" . $row->status),
            $row->updated_at ? format_to_datetime($row->updated_at) : "", $actions,
        ];
    }

    private function monthlyRow(object $row): array
    {
        $weekdays = array_map(static fn($d) => app_lang("gd_weekday_short_" . $d), $row->schedule["weekdays"] ?? []);
        $next = $row->schedule["next_occurrence_utc"] ?? null;
        return [
            $this->escape($row->customer_name ?? "-"),
            $this->escape($this->monthlyContact($row)),
            $this->escape($row->schedule["resource_names"] ?? ""),
            $this->escape(implode(", ", $weekdays)),
            $this->escape($row->schedule["local_time"] ?? ""),
            $this->contractedAmount($row),
            $row->preferred_due_day ? (int) $row->preferred_due_day : "-",
            app_lang("gd_court_rental_status_" . $row->status),
            $next ? format_to_datetime($this->time->utcToLocal($next)->format("Y-m-d H:i:s")) : "-",
            $this->monthlyFinance((int) $row->id),
            $this->monthlyActions($row),
        ];
    }

    /** Contato primário do responsável da locação (ou "-"). */
    private function monthlyContact(object $row): string
    {
        $pid = (int) ($row->contact_person_id ?? 0);
        if ($pid <= 0) { return "-"; }
        $db = db_connect();
        $r = $db->table($db->prefixTable("gd_contact_methods"))->select("value")->where("person_id", $pid)->where("unit_id", $this->unit_id)->where("status", "active")->where("deleted", 0)->orderBy("is_primary", "DESC")->orderBy("id", "ASC")->get(1)->getRow();
        return $r ? (string) $r->value : "-";
    }

    /** Situação financeira da locação (cobranças desta locação), como badge. */
    private function monthlyFinance(int $rentalId): string
    {
        if (!$this->access->can("gd_finance_view")) { return "-"; }
        $db = db_connect();
        $sql = "SELECT COALESCE(SUM(balance_amount),0) bal, COALESCE(SUM(CASE WHEN due_date < CURDATE() THEN balance_amount ELSE 0 END),0) overdue FROM `" . $db->prefixTable("gd_receivables") . "` WHERE unit_id=? AND source_type='court_rental' AND source_id=? AND deleted=0 AND status IN ('open','partial','overdue')";
        $r = $db->query($sql, [$this->unit_id, $rentalId])->getRow();
        $bal = DataNormalizationService::decimal((string) ($r->bal ?? "0"), 2);
        $overdue = DataNormalizationService::decimal((string) ($r->overdue ?? "0"), 2);
        if (DataNormalizationService::decimalCompare($overdue, "0.00") > 0) {
            return '<span class="badge bg-danger">' . app_lang("gd_finance_overdue") . " R$ " . $this->escape($overdue) . "</span>";
        }
        if (DataNormalizationService::decimalCompare($bal, "0.00") > 0) {
            return '<span class="badge bg-warning">' . app_lang("gd_finance_total_receivable") . " R$ " . $this->escape($bal) . "</span>";
        }
        return '<span class="badge bg-success">' . app_lang("gd_finance_up_to_date") . "</span>";
    }

    /** Ações da linha: abrir, suspender/retomar, gerar cobrança, registrar pagamento. */
    private function monthlyActions(object $row): string
    {
        $id = (int) $row->id; $lock = (int) ($row->lock_version ?? 0); $status = (string) $row->status;
        $html = anchor(get_uri("grupo_donato/court-rentals/view/" . $id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details"), "class" => "me-2"]);
        if ($this->access->can("gd_court_rentals_status_manage")) {
            if ($status === "active") { $html .= '<a href="#" class="gd-cr-act me-2" data-id="' . $id . '" data-lock="' . $lock . '" data-action="suspend" title="' . app_lang("gd_suspend") . '"><i data-feather="pause-circle" class="icon-16"></i></a>'; }
            if ($status === "suspended") { $html .= '<a href="#" class="gd-cr-act me-2" data-id="' . $id . '" data-lock="' . $lock . '" data-action="resume" title="' . app_lang("gd_resume") . '"><i data-feather="play-circle" class="icon-16"></i></a>'; }
        }
        if ($this->access->can("gd_receivables_manage")) { $html .= anchor(get_uri("grupo_donato/finance/generate"), "<i data-feather='file-text' class='icon-16'></i>", ["title" => app_lang("gd_finance_generate"), "class" => "me-2"]); }
        if ($this->access->can("gd_payments_manage")) { $html .= modal_anchor(get_uri("grupo_donato/finance/payment-modal"), "<i data-feather='dollar-sign' class='icon-16'></i>", ["title" => app_lang("gd_finance_register_payment")]); }
        return $html;
    }

    private function validity(object $row): string
    {
        $from = $row->effective_from ? $this->escape($row->effective_from) : "…";
        $until = $row->effective_until ? $this->escape($row->effective_until) : "…";
        return "$from → $until";
    }

    private function contractedAmount(object $row): string
    {
        $amount = $row->negotiated_amount ?? $row->list_amount;
        return $amount !== null ? $this->escape($row->currency . " " . $amount) : "-";
    }
}
