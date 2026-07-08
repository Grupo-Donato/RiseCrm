<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\AccountPersonService;
use grupo_donato_gestao\Services\BookingService;
use grupo_donato_gestao\Services\ContactMethodService;
use grupo_donato_gestao\Services\CourtRentalLifecycleService;
use grupo_donato_gestao\Services\CourtRentalService;
use grupo_donato_gestao\Services\CustomerAccountService;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\PersonService;
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
        $can_bookings = $this->access->can("gd_bookings_view");
        $can_series = $this->access->can("gd_booking_series_view");
        $tab = (string) $this->request->getGet("tab");
        if (!in_array($tab, ["rentals", "bookings", "series"], true)) {
            $tab = "rentals";
        }
        if ($tab === "bookings" && !$can_bookings) {
            $tab = "rentals";
        }
        if ($tab === "series" && !$can_series) {
            $tab = "rentals";
        }

        return $this->gd_render("court_rentals/index", [
            "active_tab" => $tab,
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "can_calendar" => $this->access->can("gd_calendar_view"),
            "can_court_rentals" => true,
            "can_bookings" => $can_bookings,
            "can_bookings_manage" => $this->access->can("gd_bookings_manage"),
            "can_series" => $can_series,
            "can_series_manage" => $this->access->can("gd_booking_series_manage"),
            "can_finance" => $this->access->can("gd_finance_view"),
            "statuses" => Constants::COURT_RENTAL_STATUSES,
            "types" => Constants::COURT_RENTAL_TYPES,
            "booking_types" => Constants::BOOKING_TYPES,
            "booking_statuses" => Constants::BOOKING_STATUSES,
            "series_statuses" => Constants::BOOKING_SERIES_STATUSES,
            "resources" => $this->bookings->bookableResources(),
        ]);
    }

    public function monthly()
    {
        return $this->gd_render("court_rentals/monthly", [
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "can_calendar" => $this->access->can("gd_calendar_view"),
            "can_court_rentals" => true,
            "can_bookings" => $this->access->can("gd_bookings_view"),
            "can_series" => $this->access->can("gd_booking_series_view"),
            "can_finance" => $this->access->can("gd_finance_view"),
            "statuses" => Constants::COURT_RENTAL_STATUSES,
            "resources" => $this->bookings->bookableResources(),
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
            // Situação financeira e contatos calculados EM LOTE (sem N+1 por linha).
            $ids = array_map(static fn($row) => (int) $row->id, $result["data"]);
            $balances = $this->access->can("gd_finance_view")
                ? (new \grupo_donato_gestao\Services\FinanceService($this->unit_id, $this->user_id(), $this->login_user))->balancesBySource("court_rental", $ids)
                : [];
            $contacts = $this->batchContacts($result["data"]);
            $rows = []; foreach ($result["data"] as $row) { $rows[] = $this->monthlyRow($row, $balances, $contacts); } $result["data"] = $rows;
            return $this->response->setJSON($result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function view($id)
    {
        $rental = $this->service->get((int) $id);
        if (!$rental) { return show_404(); }
        $rental->schedule_display = $this->scheduleDisplay($rental);
        return $this->gd_render("court_rentals/view", [
            "rental" => $rental, "timezone" => $this->time->timezoneName(),
            "can_manage" => $this->access->can("gd_court_rentals_manage"),
            "can_status" => $this->access->can("gd_court_rentals_status_manage"),
            "can_override" => $this->access->can("gd_court_rentals_price_override"),
            "future_policies" => Constants::COURT_RENTAL_FUTURE_POLICIES,
            "financial" => $this->access->can("gd_finance_view") ? (new \grupo_donato_gestao\Services\FinanceService($this->unit_id, $this->user_id(), $this->login_user))->summary(["source_type" => "court_rental", "source_id" => (int) $rental->id]) : null,
            "can_generate_receivable" => $this->access->can("gd_receivables_manage"),
            "can_calendar" => $this->access->can("gd_calendar_view"),
            "can_court_rentals" => true,
            "can_bookings" => $this->access->can("gd_bookings_view"),
            "can_series" => $this->access->can("gd_booking_series_view"),
            "can_finance" => $this->access->can("gd_finance_view"),
        ]);
    }

    public function single_modal() { return $this->rentalModal("single"); }
    public function monthly_modal() { return $this->rentalModal("recurring"); }

    /**
     * Formulário único de locação. A rota antiga de mensalistas continua válida,
     * apenas abrindo o mesmo fluxo com a modalidade mensal pré-selecionada.
     */
    private function rentalModal(string $initial_mode)
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            if (!in_array($initial_mode, ["single", "recurring", "special"], true)) {
                $initial_mode = "single";
            }
            return $this->gd_view("court_rentals/rental_modal", [
                "resources" => $this->bookings->bookableResources(),
                "timezone" => $this->time->timezoneName(),
                "initial_mode" => $initial_mode,
                "pricing_presets" => Constants::COURT_RENTAL_PRICE_PRESETS,
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

    public function product_options()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            [$rows, $more] = $this->pageOptions((new \grupo_donato_gestao\Services\ProductService($this->unit_id, $this->user_id(), $this->login_user)));
            return $this->response->setJSON(["results" => array_map(static fn($r) => ["id" => (int) $r["id"], "text" => $r["code"] . " — " . $r["name"]], $rows), "pagination" => ["more" => $more]]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function price_list_options()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            [$rows, $more] = $this->pageOptions((new \grupo_donato_gestao\Services\PriceListService($this->unit_id, $this->user_id(), $this->login_user)));
            return $this->response->setJSON(["results" => array_map(static fn($r) => ["id" => (int) $r["id"], "text" => $r["code"] . " — " . $r["name"]], $rows), "pagination" => ["more" => $more]]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    /** Executa options($q,$limit+1,$offset) do service e devolve [linhas, temMais]. */
    private function pageOptions(object $service): array
    {
        $page = max(1, (int) $this->request->getPost("page"));
        $limit = 20; $offset = ($page - 1) * $limit;
        $rows = $service->options((string) $this->request->getPost("q"), $limit + 1, $offset);
        $more = count($rows) > $limit;
        return [array_slice($rows, 0, $limit), $more];
    }

    public function check_availability()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $input = $this->bookingInput();
            if (in_array((string) $this->request->getPost("rental_mode"), ["single", "special"], true)) {
                if (!$this->validLocalDateTime((string) ($input["starts_at_local"] ?? "")) || !$this->validLocalDateTime((string) ($input["ends_at_local"] ?? ""))) {
                    throw new \DomainException("gd_invalid_local_datetime");
                }
            }
            $this->json_success("", ["data" => $this->bookings->checkAvailability($input)]);
        }
        catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function preview()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $input = $this->seriesInput();
            // A prévia do formulário simplificado não deve criar cliente apenas
            // para testar agenda. Usa tipo interno, mantendo a mesma grade.
            if ((string) $this->request->getPost("rental_mode") === "recurring") {
                if (!$this->validHm((string) ($input["local_start_time"] ?? "")) || !$this->validHm((string) ($input["local_end_time"] ?? ""))) {
                    throw new \DomainException("gd_invalid_local_datetime");
                }
                $input["booking_type"] = "internal";
                $input["customer_account_id"] = null;
                $input["contact_person_id"] = null;
                $input["title"] = "Prévia de horário fixo";
            }
            $data = (new \grupo_donato_gestao\Services\BookingSeriesService($this->unit_id, $this->user_id(), $this->login_user))->preview($input);
            $this->json_success("", ["data" => $data]);
        } catch (\Throwable $e) { $this->gd_fail($e); }
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

    /**
     * Endpoint do formulário simplificado. Os quatro preços regulares são
     * aplicados no servidor, evitando divergência entre tela e persistência.
     */
    public function save_rental()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $mode = trim((string) $this->request->getPost("rental_mode"));
            if (!in_array($mode, ["single", "recurring", "special"], true)) {
                throw new \DomainException("gd_rental_mode_required");
            }

            $input = $this->commercialInput() + ($mode === "recurring" ? $this->seriesInput() : $this->bookingInput());
            $input = $this->applyRentalPreset($input, $mode);
            $input = $this->resolveEditableCustomerContact($input);
            $input["title"] = $this->rentalTitle($input, $mode);

            $result = $mode === "recurring"
                ? $this->service->createWithSeries($input)
                : $this->service->createWithBooking($input);

            $this->maybeActivate($result, (int) $result["id"], (int) $result["lock_version"]);
            $this->json_success(app_lang("record_saved"), $result);
        } catch (\Throwable $e) { $this->gd_fail($e); }
    }

    public function save_single()
    {
        try {
            $this->access->require("gd_court_rentals_manage");
            $input = $this->commercialInput() + $this->bookingInput();
            $input = $this->resolveEditableCustomerContact($input);
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
            $input = $this->resolveEditableCustomerContact($input);
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
        // Ações de ciclo de vida são consumidas por $.ajax nas views (view.php /
        // monthly.php), cujos handlers .fail leem responseJSON.message. Por isso
        // aqui emitimos status HTTP coerente — inclusive 409 em conflito de versão.
        $this->emit_http_status = true;
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
            "contact_phone" => $this->request->getPost("contact_phone"),
        ];
    }

    private function bookingInput(): array
    {
        return [
            "starts_at_local" => $this->request->getPost("starts_at_local"), "ends_at_local" => $this->request->getPost("ends_at_local"),
            "booking_status" => $this->request->getPost("booking_status"), "booking_type" => "customer_rental",
            "title" => $this->request->getPost("title"), "customer_account_id" => $this->request->getPost("customer_account_id"), "contact_person_id" => $this->request->getPost("contact_person_id"),
            "resources" => $this->requestResources(),
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
            "resources" => $this->requestResources(),
        ];
    }

    private function requestResources(): array
    {
        $selected = (int) $this->request->getPost("selected_resource_id");
        if ($selected > 0) {
            return [["resource_id" => $selected, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]];
        }
        return $this->normalizedResources($this->request->getPost("resources"));
    }

    private function normalizedResources($raw): array
    {
        $out = []; if (!is_array($raw)) { return []; }
        foreach ($raw as $rid => $value) { if (!is_array($value) || empty($value["selected"])) { continue; } $out[] = ["resource_id" => (int) $rid, "buffer_before_minutes" => $value["buffer_before_minutes"] ?? 0, "buffer_after_minutes" => $value["buffer_after_minutes"] ?? 0]; }
        return $out;
    }

    /** Aplica duração e preço oficial da operação do Grupo Donato. */
    private function applyRentalPreset(array $input, string $mode): array
    {
        $duration = (int) $this->request->getPost("duration_minutes");
        $resource_id = (int) $this->request->getPost("selected_resource_id");
        if ($resource_id <= 0) { throw new \DomainException("gd_select_at_least_one_court"); }

        $input["currency"] = Constants::DEFAULT_CURRENCY;
        $input["discount_amount"] = null;
        $input["discount_reason"] = null;
        $input["product_id"] = null;
        $input["price_list_id"] = null;
        $input["price_id"] = null;
        $input["resources"] = [["resource_id" => $resource_id, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]];

        if ($mode === "special") {
            $starts = trim((string) ($input["starts_at_local"] ?? ""));
            $ends = trim((string) ($input["ends_at_local"] ?? ""));
            if (!$this->validLocalDateTime($starts) || !$this->validLocalDateTime($ends)) {
                throw new \DomainException("gd_invalid_local_datetime");
            }
            $amount = DataNormalizationService::decimal($input["negotiated_amount"] ?? "", 2, true);
            if ($amount === null || DataNormalizationService::decimalCompare($amount, "0.00") <= 0) {
                throw new \DomainException("gd_special_amount_required");
            }
            $input["list_amount"] = $amount;
            $input["negotiated_amount"] = $amount;
            $input["effective_from"] = substr((string) ($input["starts_at_local"] ?? ""), 0, 10);
            $input["metadata"] = json_encode([
                "rental_mode" => "special",
                "package" => "court_and_barbecue",
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return $input;
        }

        if (!in_array($duration, [90, 120], true)) {
            throw new \DomainException("gd_invalid_rental_duration");
        }

        $amounts = Constants::COURT_RENTAL_PRICE_PRESETS[$mode] ?? [];
        $amount = $amounts[$duration] ?? null;
        if ($amount === null) { throw new \DomainException("gd_invalid_rental_duration"); }
        $input["list_amount"] = $amount;
        $input["negotiated_amount"] = $amount;
        $input["metadata"] = json_encode([
            "rental_mode" => $mode,
            "duration_minutes" => $duration,
            "pricing_preset" => $mode . "_" . $duration,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($mode === "recurring") {
            $due_day_raw = trim((string) ($input["preferred_due_day"] ?? ""));
            if (!preg_match('/^\d{1,2}$/', $due_day_raw) || (int) $due_day_raw < 1 || (int) $due_day_raw > 31) {
                throw new \DomainException("gd_due_day_required");
            }
            $input["preferred_due_day"] = (int) $due_day_raw;
            $starts_on = trim((string) ($input["starts_on"] ?? ""));
            $start_time = trim((string) ($input["local_start_time"] ?? ""));
            if (!$this->validYmd($starts_on) || !$this->validHm($start_time)) {
                throw new \DomainException("gd_invalid_local_datetime");
            }
            $start = new \DateTimeImmutable($starts_on . " " . $start_time, new \DateTimeZone($this->time->timezoneName()));
            $input["local_end_time"] = $start->modify("+" . $duration . " minutes")->format("H:i");
            $input["weekdays"] = [(int) $start->format("N")];
            $input["frequency"] = "weekly";
            $input["interval_value"] = 1;
            $input["ends_mode"] = "open_ended";
            $input["ends_on"] = null;
            $input["max_occurrences"] = null;
            $input["default_booking_status"] = "confirmed";
            $input["conflict_policy"] = "reject_series";
            $input["generation_horizon_days"] = 90;
            $input["effective_from"] = $starts_on;
            $input["effective_until"] = null;
            return $input;
        }

        $starts = trim((string) ($input["starts_at_local"] ?? ""));
        if (!$this->validLocalDateTime($starts)) {
            throw new \DomainException("gd_invalid_local_datetime");
        }
        $start = new \DateTimeImmutable(str_replace("T", " ", $starts), new \DateTimeZone($this->time->timezoneName()));
        $end = $start->modify("+" . $duration . " minutes");
        $input["ends_at_local"] = $end->format("Y-m-d\TH:i");
        $input["booking_status"] = "confirmed";
        $input["effective_from"] = $start->format("Y-m-d");
        $input["effective_until"] = $end->format("Y-m-d");
        return $input;
    }

    private function validYmd(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat("!Y-m-d", $value);
        return $date instanceof \DateTimeImmutable && $date->format("Y-m-d") === $value;
    }

    private function validHm(string $value): bool
    {
        if (!preg_match('/^\d{2}:(00|30)$/', $value)) { return false; }
        $date = \DateTimeImmutable::createFromFormat("!H:i", $value);
        return $date instanceof \DateTimeImmutable && $date->format("H:i") === $value;
    }

    private function validLocalDateTime(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) { return false; }
        [$date, $time] = explode("T", $value, 2);
        return $this->validYmd($date) && $this->validHm($time);
    }

    /** Título operacional automático; elimina digitação redundante no formulário. */
    private function rentalTitle(array $input, string $mode): string
    {
        $db = db_connect();
        $customer = $db->table($db->prefixTable("gd_customer_accounts"))
            ->select("display_name")
            ->where("id", (int) ($input["customer_account_id"] ?? 0))
            ->where("unit_id", $this->unit_id)
            ->where("deleted", 0)
            ->get(1)->getRow();
        $resource_id = (int) (($input["resources"][0]["resource_id"] ?? 0));
        $resource = $db->table($db->prefixTable("gd_resources"))
            ->select("code")
            ->where("id", $resource_id)
            ->where("unit_id", $this->unit_id)
            ->where("deleted", 0)
            ->get(1)->getRow();

        $customer_name = trim((string) ($customer->display_name ?? "Cliente"));
        $resource_code = trim((string) ($resource->code ?? "Quadra"));
        if ($mode === "recurring") {
            $starts = (string) ($input["starts_on"] ?? "");
            $time = (string) ($input["local_start_time"] ?? "");
            $day = $this->validYmd($starts) ? (new \DateTimeImmutable($starts))->format("d/m/Y") : $starts;
            return mb_substr($customer_name . " — " . $resource_code . " — mensalista desde " . $day . " às " . $time, 0, 180);
        }
        $starts = (string) ($input["starts_at_local"] ?? "");
        $label = $mode === "special" ? "quadra + churrasqueira" : "avulso";
        if ($this->validLocalDateTime($starts)) {
            $dt = new \DateTimeImmutable(str_replace("T", " ", $starts));
            $starts = $dt->format("d/m/Y H:i");
        }
        return mb_substr($customer_name . " — " . $resource_code . " — " . $label . " — " . $starts, 0, 180);
    }

    private function resolveEditableCustomerContact(array $input): array
    {
        $contact_raw = (string) ($input["contact_person_id"] ?? "");
        $contact_name = $this->freeTextSelectValue($contact_raw);
        $phone = $this->normalizedBrazilianPhone((string) ($input["contact_phone"] ?? ""));
        $customer_id = $this->resolveEditableCustomerId((string) ($input["customer_account_id"] ?? ""), $contact_name, $phone);
        $contact_id = $this->resolveEditableContactId($customer_id, $contact_raw, $phone);
        if ($contact_id <= 0 && $phone !== "") { $this->ensureCustomerPhone($customer_id, $phone); }

        $input["customer_account_id"] = $customer_id;
        $input["contact_person_id"] = $contact_id ?: null;
        return $input;
    }

    private function resolveEditableCustomerId(string $raw, string $contact_name, string $phone): int
    {
        $id = (int) $raw;
        if ($id > 0) { return $id; }

        $name = $this->freeTextSelectValue($raw);
        if ($name === "") { return 0; }

        $existing = $this->findCustomerByName($name);
        if ($existing) { return (int) $existing->id; }

        $account_phone = $contact_name === "" ? $this->formatBrazilianPhone($phone) : "";
        $result = (new CustomerAccountService($this->unit_id, $this->user_id(), $this->login_user))->save([
            "account_type" => "individual",
            "display_name" => $name,
            "document_type" => "none",
            "phone" => $account_phone,
            "status" => "active",
        ], 0, true);

        return (int) ($result["id"] ?? 0);
    }

    private function resolveEditableContactId(int $customer_id, string $raw, string $phone): int
    {
        if ($customer_id <= 0) { return 0; }
        $id = (int) $raw;
        if ($id > 0) {
            if ($phone !== "" && $this->personBelongsToCustomer($customer_id, $id)) { $this->ensurePersonPhone($id, $phone); }
            return $id;
        }

        $name = $this->freeTextSelectValue($raw);
        if ($name === "") { return 0; }

        $existing = $this->findLinkedPerson($customer_id, $name, $phone);
        if ($existing) {
            $person_id = (int) $existing->id;
            if ($phone !== "") { $this->ensurePersonPhone($person_id, $phone); }
            return $person_id;
        }

        $result = (new PersonService($this->unit_id, $this->user_id(), $this->login_user))->save([
            "full_name" => $name,
            "status" => "active",
        ], 0, true);
        $person_id = (int) ($result["id"] ?? 0);
        if ($person_id <= 0) { return 0; }

        $this->ensureAccountPersonRelation($customer_id, $person_id);
        if ($phone !== "") { $this->ensurePersonPhone($person_id, $phone); }
        return $person_id;
    }

    private function freeTextSelectValue($value): string
    {
        $value = DataNormalizationService::text(strip_tags((string) $value));
        if ($value === "" || preg_match('/^\d+$/', $value)) { return ""; }
        if (str_starts_with($value, "new:")) { $value = substr($value, 4); }
        return DataNormalizationService::text($value);
    }

    private function findCustomerByName(string $name): ?object
    {
        $normalized = DataNormalizationService::name($name);
        if ($normalized === "") { return null; }
        $db = db_connect();
        return $db->table($db->prefixTable("gd_customer_accounts"))
            ->select("id,display_name")
            ->where("unit_id", $this->unit_id)
            ->where("normalized_name", $normalized)
            ->where("status", "active")
            ->where("deleted", 0)
            ->orderBy("id", "ASC")
            ->get(1)
            ->getRow();
    }

    private function findLinkedPerson(int $customer_id, string $name, string $phone): ?object
    {
        if ($customer_id <= 0) { return null; }
        $db = db_connect();
        $links = $db->prefixTable("gd_account_people");
        $people = $db->prefixTable("gd_people");
        $normalized_name = DataNormalizationService::name($name);
        if ($phone !== "") {
            $contacts = $db->prefixTable("gd_contact_methods");
            $row = $db->table($links)
                ->select("$people.id,$people.full_name", false)
                ->join($people, "$people.id=$links.person_id AND $people.unit_id=$links.unit_id AND $people.deleted=0", "inner", false)
                ->join($contacts, "$contacts.person_id=$people.id AND $contacts.unit_id=$people.unit_id AND $contacts.deleted=0 AND $contacts.status='active'", "inner", false)
                ->where("$links.unit_id", $this->unit_id)
                ->where("$links.account_id", $customer_id)
                ->where("$links.status", "active")
                ->where("$links.deleted", 0)
                ->where("$contacts.normalized_value", $phone)
                ->get(1)
                ->getRow();
            if ($row) { return $row; }
        }

        if ($normalized_name === "") { return null; }
        return $db->table($links)
            ->select("$people.id,$people.full_name", false)
            ->join($people, "$people.id=$links.person_id AND $people.unit_id=$links.unit_id AND $people.deleted=0", "inner", false)
            ->where("$links.unit_id", $this->unit_id)
            ->where("$links.account_id", $customer_id)
            ->where("$links.status", "active")
            ->where("$links.deleted", 0)
            ->where("$people.normalized_name", $normalized_name)
            ->orderBy("$links.is_primary", "DESC")
            ->orderBy("$people.id", "ASC")
            ->get(1)
            ->getRow();
    }

    private function personBelongsToCustomer(int $customer_id, int $person_id): bool
    {
        if ($customer_id <= 0 || $person_id <= 0) { return false; }
        $db = db_connect();
        return $db->table($db->prefixTable("gd_account_people"))
            ->where("unit_id", $this->unit_id)
            ->where("account_id", $customer_id)
            ->where("person_id", $person_id)
            ->where("status", "active")
            ->where("deleted", 0)
            ->countAllResults() > 0;
    }

    private function ensureAccountPersonRelation(int $customer_id, int $person_id): void
    {
        if ($customer_id <= 0 || $person_id <= 0) { return; }
        $db = db_connect();
        $table = $db->prefixTable("gd_account_people");
        $existing = $db->table($table)
            ->where("unit_id", $this->unit_id)
            ->where("account_id", $customer_id)
            ->where("person_id", $person_id)
            ->where("status", "active")
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
        if ($existing) { return; }

        $has_primary = $db->table($table)
            ->where("unit_id", $this->unit_id)
            ->where("account_id", $customer_id)
            ->where("is_primary", 1)
            ->where("status", "active")
            ->where("deleted", 0)
            ->countAllResults() > 0;

        (new AccountPersonService($this->unit_id, $this->user_id(), $this->login_user))->save([
            "account_id" => $customer_id,
            "person_id" => $person_id,
            "role" => $has_primary ? "secondary_contact" : "primary_contact",
            "is_primary" => $has_primary ? 0 : 1,
            "receives_notifications" => 1,
            "status" => "active",
        ]);
    }

    private function ensureCustomerPhone(int $customer_id, string $phone): void
    {
        if ($customer_id <= 0 || $phone === "") { return; }
        $db = db_connect();
        $row = $db->table($db->prefixTable("gd_customer_accounts"))
            ->where("unit_id", $this->unit_id)
            ->where("id", $customer_id)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
        if (!$row || (string) ($row->phone_normalized ?? "") === $phone || (string) ($row->whatsapp_normalized ?? "") === $phone || !empty($row->phone)) { return; }

        (new CustomerAccountService($this->unit_id, $this->user_id(), $this->login_user))->save([
            "account_type" => $row->account_type,
            "display_name" => $row->display_name,
            "legal_name" => $row->legal_name,
            "trade_name" => $row->trade_name,
            "document_type" => $row->document_type,
            "document_number" => $row->document_number,
            "email" => $row->email,
            "phone" => $this->formatBrazilianPhone($phone),
            "whatsapp" => $row->whatsapp,
            "status" => $row->status,
            "rise_client_id" => $row->rise_client_id,
            "notes" => $row->notes,
        ], $customer_id, true);
    }

    private function ensurePersonPhone(int $person_id, string $phone): void
    {
        if ($person_id <= 0 || $phone === "") { return; }
        $db = db_connect();
        $table = $db->prefixTable("gd_contact_methods");
        $exists = $db->table($table)
            ->where("unit_id", $this->unit_id)
            ->where("person_id", $person_id)
            ->where("normalized_value", $phone)
            ->where("status", "active")
            ->where("deleted", 0)
            ->countAllResults() > 0;
        if ($exists) { return; }

        $has_primary = $db->table($table)
            ->where("unit_id", $this->unit_id)
            ->where("person_id", $person_id)
            ->where("contact_type", "phone")
            ->where("is_primary", 1)
            ->where("status", "active")
            ->where("deleted", 0)
            ->countAllResults() > 0;

        (new ContactMethodService($this->unit_id, $this->user_id(), $this->login_user))->save([
            "person_id" => $person_id,
            "contact_type" => "phone",
            "value" => $this->formatBrazilianPhone($phone),
            "is_primary" => $has_primary ? 0 : 1,
            "receives_notifications" => 1,
            "status" => "active",
        ]);
    }

    private function normalizedBrazilianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? "";
        if (strlen($digits) > 11 && str_starts_with($digits, "55")) {
            $digits = substr($digits, 2);
        }
        return substr($digits, 0, 11);
    }

    private function formatBrazilianPhone(string $phone): string
    {
        $digits = $this->normalizedBrazilianPhone($phone);
        if (strlen($digits) === 11) {
            return "(" . substr($digits, 0, 2) . ") " . substr($digits, 2, 5) . "-" . substr($digits, 7);
        }
        if (strlen($digits) === 10) {
            return "(" . substr($digits, 0, 2) . ") " . substr($digits, 2, 4) . "-" . substr($digits, 6);
        }
        return $digits;
    }

    /* ---------------- row rendering ---------------- */

    private function row(object $row): array
    {
        $view_uri = get_uri("grupo_donato/court-rentals/view/" . $row->id);
        $actions = anchor($view_uri, "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        $number = anchor($view_uri, $this->escape($row->rental_number));
        return [
            $number,
            $this->escape($row->title),
            $this->escape($row->customer_name ?? "-"),
            $this->rentalTypeBadge((string) $row->rental_type),
            $this->escape($row->schedule["resource_names"] ?? ""),
            $this->validity($row),
            $this->contractedAmount($row),
            $this->rentalStatusBadge((string) $row->status),
            $row->updated_at ? format_to_datetime($row->updated_at) : "",
            $actions,
        ];
    }

    private function monthlyRow(object $row, array $balances, array $contacts): array
    {
        $weekdays = array_map(static fn($d) => app_lang("gd_weekday_short_" . $d), $row->schedule["weekdays"] ?? []);
        $next = $row->schedule["next_occurrence_local"] ?? null;
        $contact = $contacts[(int) ($row->contact_person_id ?? 0)] ?? "";
        $customer = '<strong>' . $this->escape($row->customer_name ?? "-") . '</strong>';
        if ($contact !== "") {
            $customer .= '<br><small class="text-muted">' . $this->escape($contact) . '</small>';
        }
        $schedule = $this->escape(implode(", ", $weekdays));
        if (!empty($row->schedule["local_time"])) {
            $schedule .= ($schedule !== "" ? "<br>" : "") . '<small class="text-muted">' . $this->escape($row->schedule["local_time"]) . '</small>';
        }
        return [
            $customer,
            $this->escape($row->schedule["resource_names"] ?? ""),
            $schedule !== "" ? $schedule : "-",
            $this->contractedAmount($row),
            $row->preferred_due_day ? app_lang("gd_day_prefix") . " " . (int) $row->preferred_due_day : "-",
            $this->rentalStatusBadge((string) $row->status),
            $next ? format_to_datetime((string) $next) : "-",
            $this->monthlyFinance($row, $balances),
            $this->monthlyActions($row, $balances),
        ];
    }

    /** Contatos primários da página (uma query) → mapa person_id => valor. */
    private function batchContacts(array $rows): array
    {
        $pids = array_values(array_unique(array_filter(array_map(static fn($r) => (int) ($r->contact_person_id ?? 0), $rows))));
        if (!$pids) { return []; }
        $db = db_connect();
        $res = $db->table($db->prefixTable("gd_contact_methods"))->select("person_id,value")->whereIn("person_id", $pids)->where("unit_id", $this->unit_id)->where("status", "active")->where("deleted", 0)->orderBy("is_primary", "DESC")->orderBy("id", "ASC")->get()->getResult();
        $map = [];
        foreach ($res as $r) { if (!isset($map[(int) $r->person_id])) { $map[(int) $r->person_id] = (string) $r->value; } }
        return $map;
    }

    /**
     * Situação financeira da locação como badge, a partir do mapa agregado
     * (balancesBySource): em dia / em aberto / parcial / vencido.
     */
    private function monthlyFinance(object $row, array $balances): string
    {
        if (!$this->access->can("gd_finance_view")) { return "-"; }
        $info = $balances[(int) $row->id] ?? null;
        $bal = (string) ($info["balance"] ?? "0.00");
        $overdue = (string) ($info["overdue"] ?? "0.00");
        if (DataNormalizationService::decimalCompare($overdue, "0.00") > 0) {
            return '<span class="badge bg-danger">' . app_lang("gd_finance_overdue") . " " . $this->escape(to_currency((float) $overdue)) . "</span>";
        }
        if (DataNormalizationService::decimalCompare($bal, "0.00") > 0) {
            $key = !empty($info["partial"]) ? "gd_finance_partial" : "gd_finance_total_receivable";
            return '<span class="badge bg-warning">' . app_lang($key) . " " . $this->escape(to_currency((float) $bal)) . "</span>";
        }
        return '<span class="badge bg-success">' . app_lang("gd_finance_up_to_date") . "</span>";
    }

    /**
     * Ações da linha: abrir, suspender/retomar, gerar cobrança e registrar
     * pagamento já contextualizado — 1 cobrança aberta abre o modal com ela
     * pré-selecionada; várias abrem as contas a receber filtradas por esta locação.
     */
    private function monthlyActions(object $row, array $balances): string
    {
        $id = (int) $row->id; $lock = (int) ($row->lock_version ?? 0); $status = (string) $row->status;
        $html = anchor(get_uri("grupo_donato/court-rentals/view/" . $id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details"), "class" => "me-2"]);
        if ($this->access->can("gd_court_rentals_status_manage")) {
            if ($status === "active") { $html .= '<a href="#" class="gd-cr-act me-2" data-id="' . $id . '" data-lock="' . $lock . '" data-action="suspend" title="' . app_lang("gd_suspend") . '"><i data-feather="pause-circle" class="icon-16"></i></a>'; }
            if ($status === "suspended") { $html .= '<a href="#" class="gd-cr-act me-2" data-id="' . $id . '" data-lock="' . $lock . '" data-action="resume" title="' . app_lang("gd_resume") . '"><i data-feather="play-circle" class="icon-16"></i></a>'; }
        }
        if ($this->access->can("gd_receivables_manage")) { $html .= anchor(get_uri("grupo_donato/finance/generate"), "<i data-feather='file-text' class='icon-16'></i>", ["title" => app_lang("gd_finance_generate"), "class" => "me-2"]); }
        if ($this->access->can("gd_payments_manage")) {
            $open_ids = $balances[$id]["open_ids"] ?? [];
            if (count($open_ids) === 1) {
                $html .= modal_anchor(get_uri("grupo_donato/finance/payment-modal"), "<i data-feather='dollar-sign' class='icon-16'></i>", ["title" => app_lang("gd_finance_register_payment"), "data-post-receivable_id" => (int) $open_ids[0], "data-post-balance" => (string) ($balances[$id]["balance"] ?? "")]);
            } elseif (count($open_ids) > 1) {
                $html .= anchor(get_uri("grupo_donato/finance/receivables") . "?source_type=court_rental&source_id=" . $id, "<i data-feather='dollar-sign' class='icon-16'></i>", ["title" => app_lang("gd_finance_register_payment")]);
            } else {
                $html .= modal_anchor(get_uri("grupo_donato/finance/payment-modal"), "<i data-feather='dollar-sign' class='icon-16'></i>", ["title" => app_lang("gd_finance_register_payment")]);
            }
        }
        return $html;
    }

    private function scheduleDisplay(object $rental): string
    {
        // O horário local canônico agora vem do service (CourtRentalService::scheduleSummary),
        // já convertido para o fuso da unidade — sem re-deriva-lo aqui.
        return trim((string) ($rental->schedule["display"] ?? ($rental->schedule["local_time"] ?? "")));
    }

    private function rentalTypeBadge(string $type): string
    {
        $class = $type === "recurring" ? "bg-info" : "bg-secondary";
        return '<span class="badge ' . $class . '">' . $this->escape(app_lang("gd_court_rental_type_" . $type)) . '</span>';
    }

    private function rentalStatusBadge(string $status): string
    {
        $classes = [
            "draft" => "bg-secondary",
            "active" => "bg-success",
            "suspended" => "bg-warning",
            "cancelled" => "bg-danger",
            "completed" => "bg-info",
            "archived" => "bg-secondary",
        ];
        $class = $classes[$status] ?? "bg-secondary";
        return '<span class="badge ' . $class . '">' . $this->escape(app_lang("gd_court_rental_status_" . $status)) . '</span>';
    }

    private function validity(object $row): string
    {
        $from = $row->effective_from ? format_to_date((string) $row->effective_from, false) : "…";
        $until = $row->effective_until ? format_to_date((string) $row->effective_until, false) : "…";
        return $this->escape($from . " → " . $until);
    }

    private function contractedAmount(object $row): string
    {
        $amount = $row->negotiated_amount ?? $row->list_amount;
        return $amount !== null ? $this->escape(to_currency((float) $amount)) : "-";
    }
}
