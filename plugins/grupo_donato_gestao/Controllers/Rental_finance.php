<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Services\BookingService;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\FinanceService;
use grupo_donato_gestao\Services\ReceivableGenerationService;

class Rental_finance extends Gd_Controller
{
    private int $unit_id;
    private $db;
    private FinanceService $finance;
    private BookingService $bookings;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_finance_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) {
            throw new \RuntimeException("No active unit.");
        }

        $this->db = db_connect();
        $this->finance = new FinanceService($this->unit_id, $this->user_id(), $this->login_user);
        $this->bookings = new BookingService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("finance/rental_payments", [
            "can_generate" => $this->access->can("gd_receivables_manage"),
            "can_payments" => $this->access->can("gd_payments_manage"),
            "can_calendar" => $this->access->can("gd_calendar_view"),
            "can_court_rentals" => $this->access->can("gd_court_rentals_view"),
            "can_bookings" => $this->access->can("gd_bookings_view"),
            "can_series" => $this->access->can("gd_booking_series_view"),
            "can_finance" => true,
            "active_unit" => $this->unit_context->get_active_unit(),
            "resources" => $this->bookings->bookableResources(),
        ]);
    }

    public function list_data()
    {
        try {
            $options = append_server_side_filtering_commmon_params($this->filters());
            $result = $this->paymentsPage($options);
            [$year, $month] = array_map("intval", explode("-", $this->referenceMonth($options)));
            $rows = [];
            foreach ($result["data"] as $row) {
                $rows[] = $this->row($row, $month, $year);
            }
            return $this->response->setJSON(["data" => $rows]);
        } catch (\Throwable $e) {
            log_message("critical", "Rental payments data error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            $this->gd_fail($e);
        }
    }

    public function summary()
    {
        try {
            $this->json_success("", ["data" => $this->summaryData($this->filters())]);
        } catch (\Throwable $e) {
            log_message("critical", "Rental payments summary error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
            $this->gd_fail($e);
        }
    }

    public function generate_month()
    {
        try {
            $this->access->require("gd_receivables_manage");
            $reference = $this->referenceMonth($this->filters());
            $generator = new ReceivableGenerationService($this->unit_id, $this->user_id(), $this->login_user);
            $rows = array_values(array_filter($generator->preview($reference), static fn($row) => ($row["source_type"] ?? "") === "court_rental"));
            $result = ["generated" => [], "ignored" => [], "errors" => []];

            foreach ($rows as $row) {
                if (($row["amount"] ?? null) === null || ($row["amount"] ?? "") === "") {
                    $result["errors"][] = ["key" => $row["key"] ?? "", "error" => "gd_finance_amount_required"];
                    continue;
                }

                try {
                    $dueDate = $row["due_date"] ?? gmdate("Y-m-d");
                    $saved = $this->finance->createReceivable($row + [
                        "reference_month" => $reference,
                        "issue_date" => min(gmdate("Y-m-d"), $dueDate),
                        "due_date" => $dueDate,
                        "original_amount" => $row["amount"],
                        "unit_amount" => $row["amount"],
                        "quantity" => "1",
                    ]);

                    if (!empty($saved["created"])) {
                        $result["generated"][] = $saved;
                    } else {
                        $result["ignored"][] = $row["key"] ?? "";
                    }
                } catch (\Throwable $e) {
                    $result["errors"][] = ["key" => $row["key"] ?? "", "error" => $e->getMessage()];
                }
            }

            $message = sprintf(app_lang("gd_rental_payments_generation_result"), count($result["generated"]), count($result["ignored"]), count($result["errors"]));
            $this->json_success($message, ["data" => $result]);
        } catch (\Throwable $e) {
            $this->gd_fail($e);
        }
    }

    public function create_rental_charge()
    {
        try {
            $this->access->require("gd_receivables_manage");

            $rentalId = (int) $this->request->getPost("rental_id");
            $reference = $this->referenceMonth($this->filters());
            if ($rentalId <= 0) {
                $this->json_error("Mensalista inválido.");
                return;
            }

            if ($this->existingReceivable($rentalId, $reference)) {
                $this->json_error("Este mensalista já possui cobrança neste mês.");
                return;
            }

            $lockName = "grupo_donato_mensalista_" . $this->unit_id . "_" . $rentalId . "_" . $reference;
            $lock = $this->db->query("SELECT GET_LOCK(" . $this->db->escape($lockName) . ", 10) AS lock_status")->getRow();
            if ((int) ($lock->lock_status ?? 0) !== 1) {
                $this->json_error("Não foi possível bloquear a criação da cobrança. Tente novamente.");
                return;
            }

            try {
                if ($this->existingReceivable($rentalId, $reference)) {
                    $this->json_error("Este mensalista já possui cobrança neste mês.");
                    return;
                }

                $rental = $this->monthlyRentalForReference($rentalId, $reference);
                if (!$rental) {
                    $this->json_error("Mensalista ativo não encontrado nesta competência.");
                    return;
                }

                if ($rental->negotiated_amount === null || $rental->negotiated_amount === "") {
                    $this->json_error(app_lang("gd_finance_amount_required"));
                    return;
                }

                $dueDate = $this->dueDate($reference, (int) ($rental->preferred_due_day ?: 10));
                $saved = $this->finance->createReceivable([
                    "source_type" => "court_rental",
                    "source_id" => $rentalId,
                    "customer_account_id" => (int) $rental->customer_account_id,
                    "reference_month" => $reference,
                    "description" => "Mensalista — " . $rental->title,
                    "issue_date" => min(gmdate("Y-m-d"), $dueDate),
                    "due_date" => $dueDate,
                    "original_amount" => $rental->negotiated_amount,
                    "unit_amount" => $rental->negotiated_amount,
                    "quantity" => "1",
                    "product_id" => (int) $rental->product_id,
                ]);

                if (empty($saved["created"])) {
                    $this->json_error("Este mensalista já possui cobrança neste mês.");
                    return;
                }

                $this->json_success("Cobrança do mês criada.", ["receivable_id" => (int) $saved["id"]]);
            } finally {
                $this->db->query("SELECT RELEASE_LOCK(" . $this->db->escape($lockName) . ")");
            }
        } catch (\Throwable $e) {
            $this->gd_fail($e);
        }
    }

    private function filters(): array
    {
        return [
            "mes_referencia" => $this->request->getPost("mes_referencia") ?? $this->request->getGet("mes_referencia"),
            "ano_referencia" => $this->request->getPost("ano_referencia") ?? $this->request->getGet("ano_referencia"),
            "status_pagamento" => $this->request->getPost("status_pagamento") ?? $this->request->getGet("status_pagamento"),
            "resource_id" => $this->request->getPost("resource_id") ?? $this->request->getGet("resource_id") ?? $this->request->getPost("turma") ?? $this->request->getGet("turma"),
        ];
    }

    private function paymentsPage(array $options): array
    {
        $totalOptions = $options;
        unset($totalOptions["status_pagamento"], $totalOptions["resource_id"], $totalOptions["search_by"]);

        $total = $this->baseQuery($totalOptions)->countAllResults();
        $filtered = $this->baseQuery($options)->countAllResults();
        $resourceNames = $this->resourceNamesSql();
        $lastPaymentDate = $this->lastPaymentSql("p.payment_date");
        $lastPaymentMethod = $this->lastPaymentSql("p.payment_method");
        $lastPaymentId = $this->lastPaymentSql("p.id");
        $contactValue = $this->contactSql("cm.value");
        $contactNormalized = $this->contactSql("cm.normalized_value");
        $displayStatus = "CASE WHEN r.id IS NULL THEN 'none' WHEN r.status IN ('open','partial') AND r.balance_amount > 0 AND r.due_date < CURDATE() THEN 'overdue' ELSE r.status END";
        $orderMap = [
            "rental_number" => "cr.rental_number",
            "customer" => "a.display_name",
            "reference_month" => "r.reference_month",
            "due_date" => "r.due_date",
            "amount" => "r.original_amount",
            "status" => "display_status",
        ];
        $orderBy = $orderMap[(string) ($options["order_by"] ?? "")] ?? "r.due_date";
        $orderDir = strtoupper((string) ($options["order_dir"] ?? "")) === "ASC" ? "ASC" : "DESC";

        $query = $this->baseQuery($options)
            ->select("r.id receivable_id,r.receivable_number,r.reference_month,r.description,r.due_date,r.original_amount,r.paid_amount,r.balance_amount,r.status receivable_status,r.notes", false)
            ->select("cr.id rental_id,cr.rental_number,cr.title rental_title,cr.contact_person_id,cr.preferred_due_day,cr.negotiated_amount", false)
            ->select("a.display_name customer_name,a.phone account_phone,a.phone_normalized account_phone_normalized,a.whatsapp account_whatsapp,a.whatsapp_normalized account_whatsapp_normalized", false)
            ->select("ppl.full_name contact_name", false)
            ->select("$resourceNames resource_names", false)
            ->select("$lastPaymentDate last_payment_date,$lastPaymentMethod last_payment_method,$lastPaymentId last_payment_id", false)
            ->select("$contactValue contact_phone,$contactNormalized contact_phone_normalized", false)
            ->select("$displayStatus display_status", false)
            ->orderBy($orderBy, $orderDir, false);

        if (!empty($options["server_side"])) {
            $query->limit(max(1, min(100, (int) ($options["limit"] ?? 25))), max(0, (int) ($options["skip"] ?? 0)));
        }

        $rows = $query->get()
            ->getResult();

        return ["data" => $rows, "recordsTotal" => $total, "recordsFiltered" => $filtered];
    }

    private function baseQuery(array $options)
    {
        $receivables = $this->db->prefixTable("gd_receivables");
        $rentals = $this->db->prefixTable("gd_court_rentals");
        $accounts = $this->db->prefixTable("gd_customer_accounts");
        $people = $this->db->prefixTable("gd_people");
        $reference = $this->referenceMonth($options);
        $first = $reference . "-01";
        $last = (new \DateTimeImmutable($first))->format("Y-m-t");
        $referenceSql = $this->db->escape($reference);

        $query = $this->db->table($rentals . " cr")
            ->join($accounts . " a", "a.id=cr.customer_account_id AND a.unit_id=cr.unit_id AND a.deleted=0", "inner", false)
            ->join($people . " ppl", "ppl.id=cr.contact_person_id AND ppl.unit_id=cr.unit_id AND ppl.deleted=0", "left", false)
            ->join($receivables . " r", "r.source_type='court_rental' AND r.source_id=cr.id AND r.unit_id=cr.unit_id AND r.reference_month=$referenceSql AND r.deleted=0", "left", false)
            ->where("cr.unit_id", $this->unit_id)
            ->where("cr.rental_type", "recurring")
            ->where("cr.status", "active")
            ->where("cr.deleted", 0)
            ->groupStart()
                ->where("cr.effective_from IS NULL", null, false)
                ->orWhere("cr.effective_from <=", $last)
            ->groupEnd()
            ->groupStart()
                ->where("cr.effective_until IS NULL", null, false)
                ->orWhere("cr.effective_until >=", $first)
            ->groupEnd();

        $status = (string) ($options["status_pagamento"] ?? "");
        if ($status === "pago") {
            $query->where("r.id IS NOT NULL", null, false);
            $query->where("r.status", "paid");
        } elseif ($status === "aberto") {
            $query->where("r.id IS NOT NULL", null, false)->whereIn("r.status", ["open", "partial"])->where("r.balance_amount >", 0)->where("r.due_date >=", gmdate("Y-m-d"));
        } elseif ($status === "vencido") {
            $query->where("r.id IS NOT NULL", null, false)->whereIn("r.status", ["open", "partial", "overdue"])->where("r.balance_amount >", 0)->where("r.due_date <", gmdate("Y-m-d"));
        }

        $resourceId = (int) ($options["resource_id"] ?? 0);
        if ($resourceId > 0) {
            $query->where($this->resourceFilterSql($resourceId), null, false);
        }

        $search = trim((string) ($options["search_by"] ?? ""));
        if ($search !== "") {
            $query->groupStart()
                ->like("r.receivable_number", $search)
                ->orLike("r.description", $search)
                ->orLike("cr.rental_number", $search)
                ->orLike("cr.title", $search)
                ->orLike("a.display_name", $search)
                ->orLike("ppl.full_name", $search)
            ->groupEnd();
        }

        return $query;
    }

    private function summaryData(array $options): array
    {
        $rows = $this->baseQuery($options)
            ->select("r.id receivable_id,r.status,r.due_date,r.original_amount,r.paid_amount,r.balance_amount", false)
            ->get()
            ->getResult();

        $total = 0;
        $paid = 0;
        $open = 0;
        $overdue = 0;
        $received = "0.00";
        $toReceive = "0.00";
        $planned = "0.00";
        $today = gmdate("Y-m-d");

        foreach ($rows as $row) {
            if ((int) ($row->receivable_id ?? 0) <= 0) {
                continue;
            }

            $status = (string) $row->status;
            if ($status !== "cancelled") {
                $total++;
                $planned = $this->moneyAdd($planned, (string) $row->original_amount);
            }
            $received = $this->moneyAdd($received, (string) $row->paid_amount);
            if ($status === "paid") {
                $paid++;
            } elseif (in_array($status, ["open", "partial", "overdue"], true) && DataNormalizationService::decimalCompare((string) $row->balance_amount, "0.00") > 0) {
                $toReceive = $this->moneyAdd($toReceive, (string) $row->balance_amount);
                if ((string) $row->due_date < $today) {
                    $overdue++;
                } else {
                    $open++;
                }
            }
        }

        return [
            "total_alunos" => (string) $total,
            "total_pagos" => (string) $paid,
            "total_em_aberto" => (string) $open,
            "total_vencidos" => (string) $overdue,
            "total_recebido_formatado" => $this->currencyBr($received),
            "total_a_receber_formatado" => $this->currencyBr($toReceive),
            "valor_previsto_formatado" => $this->currencyBr($planned),
        ];
    }

    private function row(object $data, int $month, int $year): array
    {
        $hasReceivable = (int) ($data->receivable_id ?? 0) > 0;
        $phoneDigits = $this->digits($data->contact_phone_normalized ?: $data->contact_phone ?: $data->account_whatsapp_normalized ?: $data->account_whatsapp ?: $data->account_phone_normalized ?: $data->account_phone);
        $whatsapp = $phoneDigits !== "" ? anchor("https://wa.me/55" . $phoneDigits, $this->escape($this->formatPhone($phoneDigits)), ["target" => "_blank", "title" => "Abrir WhatsApp"]) : "-";
        $canPay = $hasReceivable && $this->access->can("gd_payments_manage") && DataNormalizationService::decimalCompare((string) $data->balance_amount, "0.00") > 0 && !in_array((string) $data->receivable_status, ["paid", "cancelled"], true);
        $options = "";
        if ($canPay) {
            $options = modal_anchor(get_uri("grupo_donato/finance/payment-modal"), "<i data-feather='check-circle' class='icon-16'></i> " . app_lang("gd_rental_payments_settle"), [
                "class" => "btn btn-primary btn-sm",
                "title" => app_lang("gd_rental_payments_settle"),
                "data-post-receivable_id" => (int) $data->receivable_id,
                "data-post-balance" => (string) $data->balance_amount,
                "data-post-reload_target" => "bombeiros-pagamentos-table",
            ]);
        } elseif (!$hasReceivable && $this->access->can("gd_receivables_manage")) {
            $options = js_anchor("<i data-feather='plus-circle' class='icon-16'></i> Criar cobrança", [
                "class" => "btn btn-default btn-sm gd-rental-create-charge",
                "title" => "Criar cobrança deste mês",
                "data-rental-id" => (int) $data->rental_id,
                "data-mes" => $month,
                "data-ano" => $year,
            ]);
        } elseif ($hasReceivable && (int) ($data->last_payment_id ?? 0) > 0) {
            $options = anchor(get_uri("grupo_donato/finance/payments/receipt/" . (int) $data->last_payment_id), "<i data-feather='file-text' class='icon-16'></i>", ["class" => "btn btn-default btn-sm", "title" => app_lang("gd_finance_receipt"), "target" => "_blank"]);
        }

        return [
            anchor(get_uri("grupo_donato/court-rentals/view/" . $data->rental_id), $this->escape($data->rental_number ?: "-")),
            $this->escape($data->customer_name ?: "-"),
            $this->escape($data->contact_name ?: "-"),
            $whatsapp,
            $this->escape($data->resource_names ?: "-"),
            $hasReceivable && $data->reference_month ? $this->escape($this->referenceLabel((string) $data->reference_month)) : sprintf("%02d/%04d", $month, $year),
            $hasReceivable ? $this->escape($data->description ?: "-") : "-",
            $hasReceivable ? $this->dateBr((string) $data->due_date) : "-",
            $hasReceivable ? $this->currencyBr((string) $data->original_amount) : "-",
            $this->statusBadge((string) $data->display_status),
            $hasReceivable && $data->last_payment_date ? $this->dateBr((string) $data->last_payment_date) : "-",
            $hasReceivable && $data->last_payment_method ? $this->escape(app_lang("gd_finance_method_" . $data->last_payment_method)) : "-",
            $hasReceivable ? $this->escape($data->notes ?: "-") : "-",
            $options ?: "-",
        ];
    }

    private function statusBadge(string $status): string
    {
        $classes = ["none" => "bg-secondary", "open" => "bg-warning", "partial" => "bg-warning", "paid" => "bg-success", "overdue" => "bg-danger", "cancelled" => "bg-secondary"];
        $labels = [
            "none" => "Sem cobrança",
            "open" => app_lang("gd_rental_payments_status_open"),
            "partial" => app_lang("gd_finance_receivable_status_partial"),
            "paid" => app_lang("gd_rental_payments_status_paid"),
            "overdue" => app_lang("gd_rental_payments_status_overdue"),
            "cancelled" => app_lang("gd_finance_receivable_status_cancelled"),
        ];
        $class = $classes[$status] ?? "bg-secondary";
        return '<span class="badge ' . $class . '">' . $this->escape($labels[$status] ?? $status) . '</span>';
    }

    private function existingReceivable(int $rentalId, string $reference): ?object
    {
        return $this->db->table($this->db->prefixTable("gd_receivables"))
            ->where("unit_id", $this->unit_id)
            ->where("source_type", "court_rental")
            ->where("source_id", $rentalId)
            ->where("reference_month", $reference)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
    }

    private function monthlyRentalForReference(int $rentalId, string $reference): ?object
    {
        $first = $reference . "-01";
        $last = (new \DateTimeImmutable($first))->format("Y-m-t");
        return $this->db->table($this->db->prefixTable("gd_court_rentals") . " cr")
            ->select("cr.id,cr.customer_account_id,cr.title,cr.preferred_due_day,cr.negotiated_amount,cr.product_id", false)
            ->join($this->db->prefixTable("gd_customer_accounts") . " a", "a.id=cr.customer_account_id AND a.unit_id=cr.unit_id AND a.deleted=0", "inner", false)
            ->where("cr.id", $rentalId)
            ->where("cr.unit_id", $this->unit_id)
            ->where("cr.rental_type", "recurring")
            ->where("cr.status", "active")
            ->where("cr.deleted", 0)
            ->groupStart()
                ->where("cr.effective_from IS NULL", null, false)
                ->orWhere("cr.effective_from <=", $last)
            ->groupEnd()
            ->groupStart()
                ->where("cr.effective_until IS NULL", null, false)
                ->orWhere("cr.effective_until >=", $first)
            ->groupEnd()
            ->get(1)
            ->getRow();
    }

    private function dueDate(string $reference, int $day): string
    {
        $max = (int) (new \DateTimeImmutable($reference . "-01"))->format("t");
        return sprintf("%s-%02d", $reference, min(max($day, 1), $max));
    }

    private function referenceMonth(array $options): string
    {
        $month = (int) ($options["mes_referencia"] ?? date("m"));
        $year = (int) ($options["ano_referencia"] ?? date("Y"));
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            throw new \DomainException("gd_finance_invalid_reference");
        }
        return sprintf("%04d-%02d", $year, $month);
    }

    private function referenceLabel(string $reference): string
    {
        return preg_match('/^\d{4}-\d{2}$/', $reference) ? substr($reference, 5, 2) . "/" . substr($reference, 0, 4) : $reference;
    }

    private function resourceFilterSql(int $resourceId): string
    {
        $links = $this->db->prefixTable("gd_court_rental_schedule_links");
        $bookingResources = $this->db->prefixTable("gd_booking_resources");
        $seriesResources = $this->db->prefixTable("gd_booking_series_resources");
        return "EXISTS (SELECT 1 FROM `$links` lf WHERE lf.rental_id=cr.id AND lf.unit_id=cr.unit_id AND lf.deleted=0 AND lf.link_kind <> 'historical' AND ("
            . "EXISTS (SELECT 1 FROM `$bookingResources` bf WHERE bf.booking_id=lf.booking_id AND bf.unit_id=lf.unit_id AND bf.deleted=0 AND bf.resource_id=$resourceId)"
            . " OR EXISTS (SELECT 1 FROM `$seriesResources` sf WHERE sf.series_id=lf.booking_series_id AND sf.unit_id=lf.unit_id AND sf.deleted=0 AND sf.resource_id=$resourceId)"
            . "))";
    }

    private function resourceNamesSql(): string
    {
        $links = $this->db->prefixTable("gd_court_rental_schedule_links");
        $bookingResources = $this->db->prefixTable("gd_booking_resources");
        $seriesResources = $this->db->prefixTable("gd_booking_series_resources");
        $resources = $this->db->prefixTable("gd_resources");
        $booking = "(SELECT GROUP_CONCAT(DISTINCT CONCAT(rb.code, ' - ', rb.name) ORDER BY rb.sort_order,rb.name SEPARATOR ', ') FROM `$links` lb JOIN `$bookingResources` br ON br.booking_id=lb.booking_id AND br.unit_id=lb.unit_id AND br.deleted=0 JOIN `$resources` rb ON rb.id=br.resource_id AND rb.unit_id=br.unit_id AND rb.deleted=0 WHERE lb.rental_id=cr.id AND lb.unit_id=cr.unit_id AND lb.deleted=0 AND lb.link_kind <> 'historical')";
        $series = "(SELECT GROUP_CONCAT(DISTINCT CONCAT(rs.code, ' - ', rs.name) ORDER BY rs.sort_order,rs.name SEPARATOR ', ') FROM `$links` ls JOIN `$seriesResources` sr ON sr.series_id=ls.booking_series_id AND sr.unit_id=ls.unit_id AND sr.deleted=0 JOIN `$resources` rs ON rs.id=sr.resource_id AND rs.unit_id=sr.unit_id AND rs.deleted=0 WHERE ls.rental_id=cr.id AND ls.unit_id=cr.unit_id AND ls.deleted=0 AND ls.link_kind <> 'historical')";
        return "CONCAT_WS(', ', $booking, $series)";
    }

    private function lastPaymentSql(string $field): string
    {
        $allocations = $this->db->prefixTable("gd_payment_allocations");
        $payments = $this->db->prefixTable("gd_payments");
        return "(SELECT $field FROM `$allocations` pa JOIN `$payments` p ON p.id=pa.payment_id AND p.unit_id=pa.unit_id AND p.deleted=0 AND p.status='confirmed' WHERE pa.unit_id=r.unit_id AND pa.receivable_id=r.id AND pa.status='active' ORDER BY p.payment_date DESC,p.id DESC LIMIT 1)";
    }

    private function contactSql(string $field): string
    {
        $contacts = $this->db->prefixTable("gd_contact_methods");
        return "(SELECT $field FROM `$contacts` cm WHERE cm.unit_id=cr.unit_id AND cm.person_id=cr.contact_person_id AND cm.deleted=0 AND cm.status='active' AND cm.contact_type IN ('whatsapp','phone') ORDER BY (cm.contact_type='whatsapp') DESC,cm.is_primary DESC,cm.id ASC LIMIT 1)";
    }

    private function digits($value): string
    {
        $digits = preg_replace('/\D+/', "", (string) $value) ?? "";
        if (strlen($digits) > 11 && str_starts_with($digits, "55")) {
            $digits = substr($digits, 2);
        }
        return substr($digits, 0, 11);
    }

    private function formatPhone(string $digits): string
    {
        if (strlen($digits) === 11) {
            return "(" . substr($digits, 0, 2) . ") " . substr($digits, 2, 5) . "-" . substr($digits, 7);
        }
        if (strlen($digits) === 10) {
            return "(" . substr($digits, 0, 2) . ") " . substr($digits, 2, 4) . "-" . substr($digits, 6);
        }
        return $digits;
    }

    private function currencyBr(string $value): string
    {
        return "R$ " . number_format((float) $value, 2, ",", ".");
    }

    private function dateBr(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value) ? date("d/m/Y", strtotime($value)) : "-";
    }

    private function moneyAdd(string $a, string $b): string
    {
        return number_format((float) $a + (float) $b, 2, ".", "");
    }
}
