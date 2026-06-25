<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\BookingSeriesLifecycleService;
use grupo_donato_gestao\Services\BookingSeriesOccurrenceService;
use grupo_donato_gestao\Services\BookingSeriesService;
use grupo_donato_gestao\Services\BookingSeriesSplitService;
use grupo_donato_gestao\Services\BookingService;
use grupo_donato_gestao\Services\TemporalService;

class Booking_series extends Gd_Controller
{
    private int $unit_id;
    private BookingSeriesService $service;
    private BookingService $bookings;
    private TemporalService $time;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_booking_series_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->service = new BookingSeriesService($this->unit_id, $this->user_id(), $this->login_user);
        $this->bookings = new BookingService($this->unit_id, $this->user_id(), $this->login_user);
        $this->time = new TemporalService($this->unit_id);
    }

    public function index()
    {
        return $this->gd_render("booking_series/index", ["can_manage" => $this->access->can("gd_booking_series_manage"), "statuses" => Constants::BOOKING_SERIES_STATUSES, "resources" => $this->bookings->bookableResources()]);
    }

    public function list_data()
    {
        try {
            $result = $this->service->listPage(append_server_side_filtering_commmon_params([
                "status" => $this->request->getPost("status"), "resource_id" => $this->request->getPost("resource_id"), "customer_account_id" => $this->request->getPost("customer_account_id"),
                "date_from" => $this->request->getPost("date_from"), "date_to" => $this->request->getPost("date_to"),
            ]));
            $rows = []; foreach ($result["data"] as $row) { $rows[] = $this->row($row); } $result["data"] = $rows;
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function view($id)
    {
        $series = $this->service->get((int) $id);
        if (!$series) { return show_404(); }
        return $this->gd_render("booking_series/view", ["series" => $series, "timezone" => $this->time->timezoneName(), "can_manage" => $this->access->can("gd_booking_series_manage"), "can_status" => $this->access->can("gd_booking_series_status_manage")]);
    }

    public function modal()
    {
        try {
            $this->access->require("gd_booking_series_manage");
            $id = (int) ($this->request->getGet("id") ?: $this->request->getPost("id"));
            $series = $id ? $this->service->get($id) : null;
            if ($id && !$series) { return show_404(); }
            $accounts = $this->bookings->customerOptions("", 50);
            $contacts = $series && $series->customer_account_id ? $this->bookings->contactOptions((int) $series->customer_account_id) : [];
            $scope = (string) ($this->request->getGet("scope") ?: $this->request->getPost("scope"));
            return $this->gd_view("booking_series/modal_form", [
                "model_info" => $series ?: new \stdClass(), "resources" => $this->bookings->bookableResources(), "accounts" => $accounts, "contacts" => $contacts,
                "types" => Constants::BOOKING_TYPES, "frequencies" => Constants::BOOKING_SERIES_FREQUENCIES, "ends_modes" => Constants::BOOKING_SERIES_ENDS_MODES,
                "default_statuses" => Constants::BOOKING_SERIES_DEFAULT_STATUSES, "conflict_policies" => Constants::BOOKING_SERIES_CONFLICT_POLICIES,
                "timezone" => $this->time->timezoneName(), "scope" => $scope, "from_local_date" => (string) ($this->request->getGet("from_local_date") ?: $this->request->getPost("from_local_date")),
            ]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function preview()
    {
        try { $this->access->require("gd_booking_series_manage"); return $this->response->setJSON(["success" => true, "data" => $this->service->preview($this->input())]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function occurrence_modal()
    {
        try {
            $this->access->require("gd_booking_series_manage");
            $id = (int) $this->request->getPost("id");
            $row = $this->bookings->get($id);
            if (!$row || !(int) ($row->series_id ?? 0) || (int) ($row->detached_from_series ?? 0)) { return show_404(); }
            $accounts = $this->bookings->customerOptions("", 50);
            $contacts = $row->customer_account_id ? $this->bookings->contactOptions((int) $row->customer_account_id) : [];
            return $this->gd_view("bookings/modal_form", ["model_info" => $row, "resources" => $this->bookings->bookableResources(), "accounts" => $accounts, "contacts" => $contacts, "types" => Constants::BOOKING_TYPES, "initial_statuses" => Constants::BOOKING_SERIES_DEFAULT_STATUSES, "timezone" => $this->time->timezoneName(), "starts_local" => $this->time->utcToLocalInput($row->starts_at_utc), "ends_local" => $this->time->utcToLocalInput($row->ends_at_utc), "hold_local" => "", "series_scope" => "single", "customer_options_uri" => "grupo_donato/booking-series/customer-options", "contact_options_uri" => "grupo_donato/booking-series/contact-options", "booking_check_uri" => "grupo_donato/booking-series/check-availability"]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function customer_options()
    {
        try { $this->access->require("gd_booking_series_manage"); $rows = $this->bookings->customerOptions((string) $this->request->getPost("q")); return $this->response->setJSON(["results" => array_map(static fn($row) => ["id" => (int) $row["id"], "text" => $row["display_name"] . " (" . app_lang("gd_account_type_" . $row["account_type"]) . ")"], $rows)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function contact_options()
    {
        try { $this->access->require("gd_booking_series_manage"); $rows = $this->bookings->contactOptions((int) $this->request->getPost("customer_account_id"), (string) $this->request->getPost("q")); return $this->response->setJSON(["results" => array_map(static fn($row) => ["id" => (int) $row["id"], "text" => $row["full_name"]], $rows)]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function check_availability()
    {
        try { $this->access->require("gd_booking_series_manage"); $this->json_success("", ["data" => $this->bookings->checkAvailability($this->bookingInput(), (int) $this->request->getPost("id"))]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save()
    {
        try {
            $this->access->require("gd_booking_series_manage");
            $id = (int) $this->request->getPost("id");
            $result = $id ? $this->service->updateEntire($id, $this->input()) : $this->service->create($this->input());
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function generate($id) { $this->writeManage(fn() => (new BookingSeriesOccurrenceService($this->unit_id, $this->user_id(), $this->login_user))->generate((int) $id)); }
    public function pause($id) { $this->writeStatus(fn() => (new BookingSeriesLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->pause((int) $id, (int) $this->request->getPost("lock_version"))); }
    public function resume($id) { $this->writeStatus(fn() => (new BookingSeriesLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->resume((int) $id, (int) $this->request->getPost("lock_version"))); }
    public function complete($id) { $this->writeStatus(fn() => (new BookingSeriesLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->complete((int) $id, (int) $this->request->getPost("lock_version"), (string) $this->request->getPost("reason"))); }
    public function cancel($id) { $this->writeStatus(fn() => (new BookingSeriesLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->cancel((int) $id, (int) $this->request->getPost("lock_version"), (string) $this->request->getPost("reason"))); }

    public function update_occurrence()
    {
        try { $this->access->require("gd_booking_series_manage"); $result = (new BookingSeriesOccurrenceService($this->unit_id, $this->user_id(), $this->login_user))->updateSingle((int) ($this->request->getPost("booking_id") ?: $this->request->getPost("id")), $this->bookingInput()); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function update_this_and_future()
    {
        try { $this->access->require("gd_booking_series_manage"); $result = (new BookingSeriesSplitService($this->unit_id, $this->user_id(), $this->login_user))->split((int) $this->request->getPost("id"), (string) $this->request->getPost("from_local_date"), $this->input()); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function update_entire()
    {
        try { $this->access->require("gd_booking_series_manage"); $result = $this->service->updateEntire((int) $this->request->getPost("id"), $this->input()); $this->json_success(app_lang("record_saved"), $result); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function cancel_occurrence()
    {
        try { $this->access->require("gd_booking_series_status_manage"); $row = (new BookingSeriesOccurrenceService($this->unit_id, $this->user_id(), $this->login_user))->cancelSingle((int) $this->request->getPost("booking_id"), (string) $this->request->getPost("reason")); $this->json_success(app_lang("record_saved"), ["id" => (int) $row->id]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function cancel_this_and_future()
    {
        try { $this->access->require("gd_booking_series_status_manage"); $row = (new BookingSeriesLifecycleService($this->unit_id, $this->user_id(), $this->login_user))->cancelFrom((int) $this->request->getPost("id"), (int) $this->request->getPost("lock_version"), (string) $this->request->getPost("from_local_date"), (string) $this->request->getPost("reason")); $this->json_success(app_lang("record_saved"), ["id" => (int) $row->id]); }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    private function writeManage(callable $operation): void { try { $this->access->require("gd_booking_series_manage"); $result = $operation(); $this->json_success(app_lang("record_saved"), is_array($result) ? $result : ["id" => (int) $result->id]); } catch (\Throwable $e) { $this->gd_fail($e); } }
    private function writeStatus(callable $operation): void { try { $this->access->require("gd_booking_series_status_manage"); $result = $operation(); $this->json_success(app_lang("record_saved"), ["id" => (int) $result->id, "status" => (string) $result->status, "lock_version" => (int) $result->lock_version]); } catch (\Throwable $e) { $this->gd_fail($e); } }

    private function input(): array
    {
        return [
            "booking_type" => $this->request->getPost("booking_type"), "title" => $this->request->getPost("title"), "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"),
            "frequency" => $this->request->getPost("frequency"), "interval_value" => $this->request->getPost("interval_value"), "weekdays" => $this->request->getPost("weekdays"), "monthly_day" => $this->request->getPost("monthly_day"),
            "local_start_time" => $this->request->getPost("local_start_time"), "local_end_time" => $this->request->getPost("local_end_time"), "starts_on" => $this->request->getPost("starts_on"),
            "ends_mode" => $this->request->getPost("ends_mode"), "ends_on" => $this->request->getPost("ends_on"), "max_occurrences" => $this->request->getPost("max_occurrences"),
            "default_booking_status" => $this->request->getPost("default_booking_status"), "conflict_policy" => $this->request->getPost("conflict_policy"), "generation_horizon_days" => $this->request->getPost("generation_horizon_days"),
            "resources" => $this->normalizedResources($this->request->getPost("resources")), "notes" => $this->request->getPost("notes"), "metadata" => $this->request->getPost("metadata"), "lock_version" => $this->request->getPost("lock_version"),
        ];
    }

    private function bookingInput(): array
    {
        return ["booking_type" => $this->request->getPost("booking_type"), "title" => $this->request->getPost("title"), "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"), "starts_at_local" => $this->request->getPost("starts_at_local"), "ends_at_local" => $this->request->getPost("ends_at_local"), "resources" => $this->normalizedResources($this->request->getPost("resources")), "notes" => $this->request->getPost("notes"), "metadata" => $this->request->getPost("metadata"), "lock_version" => $this->request->getPost("lock_version")];
    }

    private function normalizedResources($raw): array
    {
        $out = []; if (!is_array($raw)) { return []; }
        foreach ($raw as $rid => $value) { if (!is_array($value) || empty($value["selected"])) { continue; } $out[] = ["resource_id" => (int) $rid, "buffer_before_minutes" => $value["buffer_before_minutes"] ?? 0, "buffer_after_minutes" => $value["buffer_after_minutes"] ?? 0]; }
        return $out;
    }

    private function row(object $row): array
    {
        $actions = anchor(get_uri("grupo_donato/booking-series/view/" . $row->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        if ($this->access->can("gd_booking_series_manage") && in_array($row->status, ["active", "paused"], true)) { $actions .= modal_anchor(get_uri("grupo_donato/booking-series/modal"), "<i data-feather='edit' class='icon-16'></i>", ["data-post-id" => $row->id, "title" => app_lang("edit")]); }
        return [$this->escape($row->series_number), $this->escape($row->title), app_lang("gd_booking_series_frequency_" . $row->frequency), $this->escape($row->resource_names ?? ""), $this->escape($row->customer_name ?? "-"), $this->escape($row->starts_on), app_lang("gd_booking_series_status_" . $row->status), $row->updated_at ? format_to_datetime($row->updated_at) : "", $actions];
    }
}
