<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Models\Gd_academy_events_model;

/**
 * Transactional application service for the GD Academy event workspace.
 *
 * Student and responsible ids intentionally refer to the operational tables
 * used by the existing gd_tab=alunos/responsaveis screens. The modern
 * customer account is only a financial bridge, never a second Academy
 * person/student registry.
 */
final class AcademyEventService extends CustomerDataService
{
    private Gd_academy_events_model $events;
    private int $legacy_unit_id;
    private ?object $login_user;

    private const EVENT_TYPES = ["championship", "cup", "tournament", "friendly", "festival", "single_game", "official", "unofficial", "other"];
    private const EVENT_STATUSES = ["draft", "registrations_open", "confirmed", "in_progress", "completed", "cancelled"];
    private const CATEGORY_STATUSES = ["active", "inactive"];
    private const MATCH_STATUSES = ["scheduled", "in_progress", "completed", "cancelled"];
    private const CONFIRMATION_STATUSES = ["waiting", "confirmed", "refused", "no_response"];
    private const LINEUP_STATUSES = ["called", "starter", "substitute", "absent", "cut"];
    private const CHARGE_STRATEGIES = ["immediate", "open", "next_closing"];
    private const CLASSIFICATIONS = ["highlight", "good", "expected", "follow_up"];
    private const POSITION_LABELS = [
        "goalkeeper" => "Goleiro",
        "defender" => "Zagueiro",
        "fullback" => "Lateral",
        "defensive_midfielder" => "Volante",
        "midfielder" => "Meia",
        "winger" => "Ponta",
        "forward" => "Atacante",
    ];

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null, ?int $legacy_unit_id = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->events = new Gd_academy_events_model();
        $this->legacy_unit_id = $legacy_unit_id ?: $unit_id;
        $this->login_user = $login_user;
    }

    public static function positionOptions(): array
    {
        $options = [];
        foreach (self::POSITION_LABELS as $key => $label) $options[$label] = $label;
        return $options;
    }

    public static function positionKey($position): ?string
    {
        $value = mb_strtolower(trim((string) $position));
        if ($value === "") return null;
        $aliases = [
            "goalkeeper" => "goalkeeper", "goleiro" => "goalkeeper",
            "defender" => "defender", "zagueiro" => "defender", "zagueira" => "defender",
            "fullback" => "fullback", "lateral" => "fullback", "ala" => "fullback",
            "defensive_midfielder" => "defensive_midfielder", "volante" => "defensive_midfielder",
            "midfielder" => "midfielder", "meia" => "midfielder", "meio-campista" => "midfielder", "meio campista" => "midfielder",
            "winger" => "winger", "ponta" => "winger",
            "forward" => "forward", "atacante" => "forward",
        ];
        return $aliases[$value] ?? null;
    }

    public static function positionLabel($position): string
    {
        $key = self::positionKey($position);
        return $key ? self::POSITION_LABELS[$key] : trim((string) $position);
    }

    public function listEvents(array $filters = []): array
    {
        $page = $this->events->list_page($this->unit_id, $filters);
        $ids = array_values(array_filter(array_map(static fn($row): int => (int) $row->id, $page["data"] ?? [])));
        $metrics = $this->eventMetrics($ids);
        $summary = $this->eventSummary($ids);
        foreach ($page["data"] as $row) {
            $row->metrics = array_merge($this->emptyMetrics(), $metrics[(int) $row->id] ?? [], $summary[(int) $row->id] ?? []);
        }
        return $page;
    }

    public function dashboard(array $filters = []): array
    {
        $today = gmdate("Y-m-d");
        $month = gmdate("Y-m");
        $eventTable = $this->table("gd_academy_events");
        $participantTable = $this->table("gd_academy_event_participants");
        $evaluationTable = $this->table("gd_academy_athlete_evaluations");
        $receivableTable = $this->table("gd_receivables");
        $base = $this->db->table($eventTable)->where("unit_id", $this->unit_id)->where("deleted", 0);
        if (!empty($filters["search"])) {
            $search = trim((string) $filters["search"]);
            $base->groupStart()->like("name", $search)->orLike("location", $search)->orLike("organizer", $search)->groupEnd();
        }
        if (!empty($filters["status"])) { $base->where("status", (string) $filters["status"]); }
        if (!empty($filters["event_type"])) { $base->where("event_type", (string) $filters["event_type"]); }
        if (!empty($filters["date_from"])) { $base->where("starts_on >=", (string) $filters["date_from"]); }
        if (!empty($filters["date_to"])) { $base->where("starts_on <=", (string) $filters["date_to"]); }
        if (!empty($filters["category_id"])) {
            $categoryEvents = $this->db->table($this->table("gd_academy_event_categories"))->select("event_id")->where("id", (int) $filters["category_id"])->where("unit_id", $this->unit_id)->where("deleted", 0)->get()->getResultArray();
            $base->whereIn("id", $categoryEvents ? array_map(static fn(array $row): int => (int) $row["event_id"], $categoryEvents) : [0]);
        }

        $events = $base->orderBy("starts_on", "ASC")->orderBy("id", "ASC")->limit(50)->get()->getResult();
        $eventIds = array_map(static fn($row): int => (int) $row->id, $events);
        $metrics = $this->eventMetrics($eventIds);
        $summary = $this->eventSummary($eventIds);
        foreach ($events as $row) { $row->metrics = array_merge($this->emptyMetrics(), $metrics[(int) $row->id] ?? [], $summary[(int) $row->id] ?? []); }

        $count = static function ($builder): int { return (int) ($builder->select("COUNT(*) total", false)->get()->getRow()->total ?? 0); };
        $upcoming = $count($this->db->table($eventTable)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("starts_on >=", $today)->whereNotIn("status", ["cancelled", "completed"]));
        $inProgress = $count($this->db->table($eventTable)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "in_progress"));
        $completedMonth = $count($this->db->table($eventTable)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "completed")->where("starts_on >=", $month . "-01")->where("starts_on <=", gmdate("Y-m-t")));
        $athletes = (int) ($this->db->table($participantTable)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("lineup_status !=", "cut")->countAllResults());
        $pendingConfirmations = (int) ($this->db->table($participantTable)->where("unit_id", $this->unit_id)->where("deleted", 0)->whereIn("confirmation_status", ["pending", "waiting", "no_response"])->countAllResults());
        $pendingEvaluations = (int) ($this->db->query("SELECT COUNT(*) total FROM `$participantTable` p LEFT JOIN `$evaluationTable` e ON e.participant_id=p.id AND e.unit_id=p.unit_id AND e.deleted=0 WHERE p.unit_id=? AND p.deleted=0 AND p.lineup_status NOT IN ('cut','absent') AND e.id IS NULL", [$this->unit_id])->getRow()->total ?? 0);
        $finance = $this->db->query("SELECT COALESCE(SUM(r.original_amount),0) expected_amount, COALESCE(SUM(r.paid_amount),0) received_amount, COALESCE(SUM(r.balance_amount),0) open_amount, SUM(r.balance_amount>0) pending_count FROM `$receivableTable` r WHERE r.unit_id=? AND r.source_type='academy_event_participation' AND r.deleted=0 AND r.status<>'cancelled'", [$this->unit_id])->getRow();

        $pending = [];
        foreach ($events as $event) {
            $m = $event->metrics ?? $this->emptyMetrics();
            if ((int) $m["pending_confirmations"] > 0) $pending[] = ["event_id" => (int) $event->id, "message" => $event->name . " - " . (int) $m["pending_confirmations"] . " confirmacoes pendentes", "kind" => "confirmation"];
            if ((int) $m["pending_evaluations"] > 0) $pending[] = ["event_id" => (int) $event->id, "message" => $event->name . " - " . (int) $m["pending_evaluations"] . " atletas sem avaliacao", "kind" => "evaluation"];
            if ((float) $m["open_amount"] > 0) $pending[] = ["event_id" => (int) $event->id, "message" => $event->name . " - R$ " . number_format((float) $m["open_amount"], 2, ",", ".") . " em aberto", "kind" => "finance"];
        }
        return [
            "indicators" => [
                "upcoming_events" => $upcoming,
                "in_progress_events" => $inProgress,
                "completed_month" => $completedMonth,
                "called_athletes" => $athletes,
                "pending_confirmations" => $pendingConfirmations,
                "pending_evaluations" => $pendingEvaluations,
                "pending_payments" => (int) ($finance->pending_count ?? 0),
                "expected_amount" => (string) ($finance->expected_amount ?? "0.00"),
                "received_amount" => (string) ($finance->received_amount ?? "0.00"),
                "open_amount" => (string) ($finance->open_amount ?? "0.00"),
            ],
            "events" => $events,
            "pending" => array_slice($pending, 0, 20),
        ];
    }

    public function categoryOptions(): array
    {
        return $this->db->table($this->table("gd_academy_event_categories") . " c")
            ->select("c.id,c.name,c.event_id,e.name event_name")
            ->join($this->table("gd_academy_events") . " e", "e.id=c.event_id AND e.unit_id=c.unit_id AND e.deleted=0", "inner", false)
            ->where("c.unit_id", $this->unit_id)->where("c.deleted", 0)
            ->orderBy("e.starts_on", "DESC")->orderBy("c.name", "ASC")->limit(200)->get()->getResult();
    }

    /**
     * Read models for the hierarchical Academy screens. These methods keep
     * the existing write API and getEvent() compatibility intact, while each
     * page requests only the context it needs.
     */
    public function eventOverview(int $eventId): array
    {
        $event = $this->assertEvent($eventId);
        $staff = $this->db->table($this->table("gd_academy_event_staff"))
            ->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("deleted", 0)
            ->orderBy("id", "ASC")->get()->getResult();
        return [
            "event" => $event,
            "metrics" => array_merge($this->emptyMetrics(), $this->eventMetrics([$eventId])[$eventId] ?? [], $this->eventSummary([$eventId])[$eventId] ?? []),
            "staff" => $staff,
        ];
    }

    public function eventCategories(int $eventId): array
    {
        $this->assertEvent($eventId);
        $categories = $this->table("gd_academy_event_categories");
        $participants = $this->table("gd_academy_event_participants");
        $matches = $this->table("gd_academy_event_matches");
        $evaluations = $this->table("gd_academy_athlete_evaluations");
        $sql = "SELECT c.*,
            (SELECT COUNT(*) FROM `$participants` p WHERE p.unit_id=c.unit_id AND p.category_id=c.id AND p.deleted=0 AND p.lineup_status<>'cut') athlete_count,
            (SELECT COUNT(*) FROM `$participants` p WHERE p.unit_id=c.unit_id AND p.category_id=c.id AND p.deleted=0 AND p.confirmation_status='confirmed') confirmed_count,
            (SELECT COUNT(*) FROM `$matches` m WHERE m.unit_id=c.unit_id AND m.category_id=c.id AND m.deleted=0) match_count,
            (SELECT COUNT(*) FROM `$evaluations` e WHERE e.unit_id=c.unit_id AND e.category_id=c.id AND e.deleted=0) evaluation_count
            FROM `$categories` c WHERE c.unit_id=? AND c.event_id=? AND c.deleted=0 ORDER BY c.name ASC";
        return $this->db->query($sql, [$this->unit_id, $eventId])->getResult();
    }

    public function eventFinance(int $eventId): array
    {
        $event = $this->assertEvent($eventId);
        $p = $this->table("gd_academy_event_participants");
        $s = $this->table("grupo_donato_alunos");
        $x = $this->table("gd_academy_external_athletes");
        $r = $this->table("grupo_donato_responsaveis");
        $c = $this->table("gd_academy_event_categories");
        $receivables = $this->table("gd_receivables");
        $sql = "SELECT p.id,p.event_id,p.category_id,p.athlete_type,p.student_id,p.external_athlete_id,p.responsible_id,p.financial_status,p.amount,p.due_date,p.receivable_id,
            COALESCE(s.nome_aluno,x.name) athlete_name, c.name category_name, COALESCE(r.nome,x.responsible_name) responsible_name,
            CASE
                WHEN rc.id IS NULL AND p.financial_status IN ('exempt','courtesy','not_applicable') THEN p.financial_status
                WHEN rc.id IS NULL THEN 'pending_generation'
                WHEN rc.status='cancelled' THEN 'cancelled'
                WHEN rc.balance_amount<=0 THEN 'paid'
                WHEN rc.due_date < CURDATE() THEN 'overdue'
                WHEN rc.paid_amount>0 THEN 'partial'
                ELSE 'open'
            END financial_display_status,
            rc.receivable_number,rc.description receivable_description,rc.notes receivable_notes,
            rc.original_amount,rc.paid_amount,rc.balance_amount,rc.due_date receivable_due_date,
            (SELECT pay.id FROM `{$this->table("gd_payment_allocations")}` pa JOIN `{$this->table("gd_payments")}` pay ON pay.id=pa.payment_id AND pay.unit_id=pa.unit_id AND pay.deleted=0 AND pay.status='confirmed' WHERE pa.unit_id=p.unit_id AND pa.receivable_id=rc.id AND pa.status='active' ORDER BY pay.payment_date DESC,pay.id DESC LIMIT 1) last_payment_id,
            (SELECT pay.payment_date FROM `{$this->table("gd_payment_allocations")}` pa JOIN `{$this->table("gd_payments")}` pay ON pay.id=pa.payment_id AND pay.unit_id=pa.unit_id AND pay.deleted=0 AND pay.status='confirmed' WHERE pa.unit_id=p.unit_id AND pa.receivable_id=rc.id AND pa.status='active' ORDER BY pay.payment_date DESC,pay.id DESC LIMIT 1) last_payment_date,
            (SELECT pay.payment_method FROM `{$this->table("gd_payment_allocations")}` pa JOIN `{$this->table("gd_payments")}` pay ON pay.id=pa.payment_id AND pay.unit_id=pa.unit_id AND pay.deleted=0 AND pay.status='confirmed' WHERE pa.unit_id=p.unit_id AND pa.receivable_id=rc.id AND pa.status='active' ORDER BY pay.payment_date DESC,pay.id DESC LIMIT 1) last_payment_method
            FROM `$p` p
            LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0
            LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0
            LEFT JOIN `$r` r ON r.id=p.responsible_id AND r.deleted=0
            JOIN `$c` c ON c.id=p.category_id AND c.unit_id=p.unit_id AND c.deleted=0
            LEFT JOIN `$receivables` rc ON rc.id=p.receivable_id AND rc.unit_id=p.unit_id AND rc.deleted=0
            WHERE p.unit_id=? AND p.event_id=? AND p.deleted=0 ORDER BY c.name ASC, athlete_name ASC";
        $rows = $this->db->query($sql, [$this->legacy_unit_id, $this->unit_id, $eventId])->getResult();
        $metrics = $this->eventMetrics([$eventId])[$eventId] ?? $this->emptyMetrics();
        return ["event" => $event, "metrics" => $metrics, "participants" => $rows];
    }

    public function eventFinancePage(int $eventId, array $options = []): array
    {
        $finance = $this->eventFinance($eventId);
        $rows = $finance["participants"];
        $status = strtolower(trim((string) ($options["status_pagamento"] ?? "")));
        $status = ["pago" => "paid", "aberto" => "open", "vencido" => "overdue", "parcial" => "partial"][$status] ?? $status;
        $categoryId = (int) ($options["category_id"] ?? 0);
        $search = mb_strtolower(trim((string) ($options["search_by"] ?? "")));
        $allowedStatuses = ["pending_generation", "open", "partial", "paid", "overdue", "exempt", "courtesy", "not_applicable", "cancelled"];
        if ($status !== "" && !in_array($status, $allowedStatuses, true)) $status = "";

        $filtered = array_values(array_filter($rows, static function ($row) use ($status, $categoryId, $search): bool {
            $displayStatus = (string) ($row->financial_display_status ?? "pending_generation");
            if ($status !== "" && $displayStatus !== $status) return false;
            if ($categoryId > 0 && (int) ($row->category_id ?? 0) !== $categoryId) return false;
            if ($search !== "") {
                $haystack = mb_strtolower(implode(" ", [
                    (string) ($row->athlete_name ?? ""),
                    (string) ($row->responsible_name ?? ""),
                    (string) ($row->category_name ?? ""),
                    (string) ($row->receivable_number ?? ""),
                    (string) ($row->receivable_description ?? ""),
                ]));
                if (!str_contains($haystack, $search)) return false;
            }
            return true;
        }));

        $total = count($rows);
        $filteredTotal = count($filtered);
        if (!empty($options["server_side"])) {
            $limit = max(1, min(100, (int) ($options["limit"] ?? 100)));
            $skip = max(0, (int) ($options["skip"] ?? 0));
            $filtered = array_slice($filtered, $skip, $limit);
        }
        return ["data" => $filtered, "recordsTotal" => $total, "recordsFiltered" => $filteredTotal, "metrics" => $finance["metrics"]];
    }

    public function eventFinanceParticipant(int $participantId): array
    {
        $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId);
        if (!$participant) throw new \DomainException("gd_record_not_found");
        $finance = $this->eventFinance((int) $participant->event_id);
        foreach ($finance["participants"] as $row) {
            if ((int) ($row->id ?? 0) !== $participantId) continue;
            $receivable = null;
            if ((int) ($row->receivable_id ?? 0) > 0) {
                $receivable = (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->getReceivable((int) $row->receivable_id);
            }
            return ["event" => $finance["event"], "participant" => $row, "receivable" => $receivable, "payment_history" => $receivable ? ($receivable->allocations ?? []) : []];
        }
        throw new \DomainException("gd_record_not_found");
    }

    public function eventPaymentReceipt(int $paymentId): ?object
    {
        if ($paymentId <= 0) return null;
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        $payment = $finance->getPayment($paymentId);
        if (!$payment) return null;
        $allocations = $this->table("gd_payment_allocations");
        $receivables = $this->table("gd_receivables");
        $participants = $this->table("gd_academy_event_participants");
        $rows = $this->db->query("SELECT DISTINCT a.receivable_id FROM `$allocations` a JOIN `$receivables` r ON r.id=a.receivable_id AND r.unit_id=a.unit_id AND r.source_type='academy_event_participation' AND r.deleted=0 JOIN `$participants` p ON p.id=r.source_id AND p.unit_id=r.unit_id AND p.deleted=0 WHERE a.unit_id=? AND a.payment_id=? AND a.status='active'", [$this->unit_id, $paymentId])->getResult();
        if (!$rows) return null;
        $allowed = [];
        foreach ($rows as $row) $allowed[(int) $row->receivable_id] = true;
        $payment->allocations = array_values(array_filter((array) ($payment->allocations ?? []), static fn($allocation): bool => isset($allowed[(int) ($allocation->receivable_id ?? 0)])));
        return $payment;
    }

    public function reverseEventPayment(int $participantId, string $reason): array
    {
        $context = $this->eventFinanceParticipant($participantId);
        $receivable = $context["receivable"];
        if (!$receivable) throw new \DomainException("gd_finance_receivable_not_found");
        $paymentId = 0;
        foreach ((array) ($receivable->allocations ?? []) as $allocation) {
            if ((string) ($allocation->status ?? "") === "active" && (string) ($allocation->payment_status ?? "") === "confirmed") {
                $paymentId = max($paymentId, (int) ($allocation->payment_id ?? 0));
            }
        }
        if ($paymentId <= 0) throw new \DomainException("gd_finance_payment_reversed");
        (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->reversePayment($paymentId, $reason);
        $fresh = (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->getReceivable((int) $receivable->id);
        $status = $fresh && DataNormalizationService::decimalCompare((string) ($fresh->balance_amount ?? "0.00"), "0.00") <= 0
            ? "paid"
            : (($fresh && DataNormalizationService::decimalCompare((string) ($fresh->paid_amount ?? "0.00"), "0.00") > 0) ? "partial" : "generated");
        $this->db->table($this->table("gd_academy_event_participants"))->where("id", $participantId)->where("unit_id", $this->unit_id)->update(["financial_status" => $status, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
        $this->audit_change("payment_reverse", "academy_event_participant", $participantId, null, ["receivable_id" => (int) $receivable->id, "payment_id" => $paymentId, "financial_status" => $status], ["reason" => DataNormalizationService::text($reason)]);
        return ["saved" => true, "payment_id" => $paymentId, "status" => $status];
    }

    public function eventChecklist(int $eventId): array
    {
        $event = $this->assertEvent($eventId);
        $rows = $this->db->table($this->table("gd_academy_event_checklist"))
            ->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("deleted", 0)
            ->orderBy("sort_order", "ASC")->orderBy("id", "ASC")->get()->getResult();
        return ["event" => $event, "checklist" => $rows];
    }

    public function eventSettings(int $eventId): array
    {
        $data = $this->eventOverview($eventId);
        $data["history"] = $this->eventHistory($eventId);
        return $data;
    }

    public function categoryOverview(int $eventId, int $categoryId): array
    {
        $event = $this->assertEvent($eventId);
        $category = $this->assertCategory($eventId, $categoryId);
        $participants = $this->table("gd_academy_event_participants");
        $matches = $this->table("gd_academy_event_matches");
        $evaluations = $this->table("gd_academy_athlete_evaluations");
        $row = $this->db->query("SELECT
            (SELECT COUNT(*) FROM `$participants` WHERE unit_id=? AND category_id=? AND deleted=0 AND lineup_status<>'cut') athlete_count,
            (SELECT COUNT(*) FROM `$participants` WHERE unit_id=? AND category_id=? AND deleted=0 AND confirmation_status='confirmed') confirmed_count,
            (SELECT COUNT(*) FROM `$matches` WHERE unit_id=? AND category_id=? AND deleted=0) match_count,
            (SELECT COUNT(*) FROM `$evaluations` WHERE unit_id=? AND category_id=? AND deleted=0) evaluation_count,
            (SELECT COUNT(*) FROM `$participants` pp LEFT JOIN `$evaluations` ee ON ee.participant_id=pp.id AND ee.unit_id=pp.unit_id AND ee.deleted=0 WHERE pp.unit_id=? AND pp.category_id=? AND pp.deleted=0 AND pp.lineup_status NOT IN ('cut','absent') AND ee.id IS NULL) pending_evaluation_count", [
            $this->unit_id, $categoryId, $this->unit_id, $categoryId, $this->unit_id, $categoryId, $this->unit_id, $categoryId, $this->unit_id, $categoryId,
        ])->getRow();
        return ["event" => $event, "category" => $category, "metrics" => (array) $row];
    }

    public function categoryParticipants(int $eventId, int $categoryId): array
    {
        $this->assertCategory($eventId, $categoryId);
        $p = $this->table("gd_academy_event_participants");
        $s = $this->table("grupo_donato_alunos");
        $x = $this->table("gd_academy_external_athletes");
        $r = $this->table("grupo_donato_responsaveis");
        $sql = "SELECT p.*,COALESCE(s.nome_aluno,x.name) athlete_name,COALESCE(s.nascimento_aluno,x.birth_date) birth_date,s.turma,s.photo_path,x.origin_club,
            COALESCE(r.nome,x.responsible_name) responsible_name
            FROM `$p` p LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0
            LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0
            LEFT JOIN `$r` r ON r.id=p.responsible_id AND r.deleted=0
            WHERE p.unit_id=? AND p.event_id=? AND p.category_id=? AND p.deleted=0 ORDER BY athlete_name ASC";
        $rows = $this->db->query($sql, [$this->legacy_unit_id, $this->unit_id, $eventId, $categoryId])->getResult();
        foreach ($rows as $row) $row->age = $this->age($row->birth_date);
        return $rows;
    }

    public function categoryMatches(int $eventId, int $categoryId): array
    {
        $this->assertCategory($eventId, $categoryId);
        return $this->db->table($this->table("gd_academy_event_matches"))
            ->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("category_id", $categoryId)->where("deleted", 0)
            ->orderBy("match_date", "ASC")->orderBy("match_time", "ASC")->orderBy("id", "ASC")->get()->getResult();
    }

    public function categoryEvaluations(int $eventId, int $categoryId): array
    {
        $this->assertCategory($eventId, $categoryId);
        $p = $this->table("gd_academy_event_participants");
        $s = $this->table("grupo_donato_alunos");
        $x = $this->table("gd_academy_external_athletes");
        $e = $this->table("gd_academy_athlete_evaluations");
        $scores = $this->table("gd_academy_evaluation_scores");
        $sql = "SELECT p.id participant_id,p.athlete_type,p.student_id,p.external_athlete_id,COALESCE(s.nome_aluno,x.name) athlete_name,
            e.id evaluation_id,e.status evaluation_status,e.evaluated_at,e.performance_classification,AVG(es.score) average_score,COUNT(es.id) score_count
            FROM `$p` p LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0
            LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0
            LEFT JOIN `$e` e ON e.participant_id=p.id AND e.unit_id=p.unit_id AND e.deleted=0
            LEFT JOIN `$scores` es ON es.evaluation_id=e.id AND es.unit_id=e.unit_id AND es.deleted=0
            WHERE p.unit_id=? AND p.event_id=? AND p.category_id=? AND p.deleted=0 AND p.lineup_status NOT IN ('cut','absent')
            GROUP BY p.id,p.athlete_type,p.student_id,p.external_athlete_id,s.nome_aluno,x.name,e.id,e.status,e.evaluated_at,e.performance_classification
            ORDER BY athlete_name ASC";
        return $this->db->query($sql, [$this->legacy_unit_id, $this->unit_id, $eventId, $categoryId])->getResult();
    }

    public function categoryStats(int $eventId, int $categoryId): array
    {
        $this->assertCategory($eventId, $categoryId);
        $p = $this->table("gd_academy_event_participants");
        $s = $this->table("grupo_donato_alunos");
        $x = $this->table("gd_academy_external_athletes");
        $stats = $this->table("gd_academy_match_player_stats");
        $sql = "SELECT p.id participant_id,COALESCE(s.nome_aluno,x.name) athlete_name,COUNT(st.id) matches_played,
            COALESCE(SUM(st.goals),0) goals,COALESCE(SUM(st.assists),0) assists,COALESCE(SUM(st.saves),0) saves,COALESCE(SUM(st.minutes_played),0) minutes_played
            FROM `$p` p LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0
            LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0
            LEFT JOIN `$stats` st ON st.participant_id=p.id AND st.unit_id=p.unit_id AND st.deleted=0
            WHERE p.unit_id=? AND p.event_id=? AND p.category_id=? AND p.deleted=0 AND p.lineup_status NOT IN ('cut','absent')
            GROUP BY p.id,s.nome_aluno,x.name ORDER BY athlete_name ASC";
        return $this->db->query($sql, [$this->legacy_unit_id, $this->unit_id, $eventId, $categoryId])->getResult();
    }

    public function matchOverview(int $eventId, int $categoryId, int $matchId): array
    {
        $category = $this->assertCategory($eventId, $categoryId);
        $match = $this->db->table($this->table("gd_academy_event_matches"))
            ->where("id", $matchId)->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("category_id", $categoryId)->where("deleted", 0)->get(1)->getRow();
        if (!$match) throw new \DomainException("gd_record_not_found");
        return ["event" => $this->assertEvent($eventId), "category" => $category, "match" => $match];
    }

    public function matchParticipants(int $eventId, int $categoryId, int $matchId): array
    {
        $this->matchOverview($eventId, $categoryId, $matchId);
        $p = $this->table("gd_academy_event_participants");
        $s = $this->table("grupo_donato_alunos");
        $x = $this->table("gd_academy_external_athletes");
        $stats = $this->table("gd_academy_match_player_stats");
        $sql = "SELECT p.id participant_id,p.athlete_type,COALESCE(s.nome_aluno,x.name) athlete_name,p.position,p.lineup_status,
            st.id stat_id,st.goals,st.assists,st.penalties_scored,st.penalties_missed,st.yellow_cards,st.red_cards,st.saves,st.minutes_played,st.notes stat_notes
            FROM `$p` p LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0
            LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0
            LEFT JOIN `$stats` st ON st.participant_id=p.id AND st.match_id=? AND st.unit_id=p.unit_id AND st.deleted=0
            WHERE p.unit_id=? AND p.event_id=? AND p.category_id=? AND p.deleted=0 AND p.lineup_status NOT IN ('cut','absent') ORDER BY p.lineup_status ASC,athlete_name ASC";
        return $this->db->query($sql, [$this->legacy_unit_id, $matchId, $this->unit_id, $eventId, $categoryId])->getResult();
    }

    public function evaluationDetail(int $eventId, int $categoryId, int $participantId): array
    {
        $this->assertCategory($eventId, $categoryId);
        $participant = $this->db->table($this->table("gd_academy_event_participants"))
            ->where("id", $participantId)->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("category_id", $categoryId)->where("deleted", 0)->get(1)->getRow();
        if (!$participant) throw new \DomainException("gd_record_not_found");
        $participantRows = $this->categoryParticipants($eventId, $categoryId);
        foreach ($participantRows as $row) if ((int) $row->id === $participantId) { $participant = $row; break; }
        $evaluation = $this->db->table($this->table("gd_academy_athlete_evaluations"))
            ->where("unit_id", $this->unit_id)->where("participant_id", $participantId)->where("deleted", 0)->get(1)->getRow();
        $scores = $evaluation ? $this->db->table($this->table("gd_academy_evaluation_scores"))
            ->where("unit_id", $this->unit_id)->where("evaluation_id", (int) $evaluation->id)->where("deleted", 0)->get()->getResult() : [];
        $scoreMap = [];
        foreach ($scores as $score) $scoreMap[(int) $score->criterion_id] = $score;
        $criteria = $this->criteria((string) ($participant->position ?? ""));
        foreach ($criteria as $criterion) $criterion->saved_score = $scoreMap[(int) $criterion->id] ?? null;
        return ["event" => $this->assertEvent($eventId), "category" => $this->assertCategory($eventId, $categoryId), "participant" => $participant, "evaluation" => $evaluation, "criteria" => $criteria];
    }

    public function getEvent(int $id): ?array
    {
        $event = $this->events->get_scoped($id, $this->unit_id);
        if (!$event) return null;
        $categoryTable = $this->table("gd_academy_event_categories");
        $categories = $this->db->table($categoryTable)->where("unit_id", $this->unit_id)->where("event_id", $id)->where("deleted", 0)->orderBy("name", "ASC")->get()->getResult();
        $matches = $this->db->table($this->table("gd_academy_event_matches"))->where("unit_id", $this->unit_id)->where("event_id", $id)->where("deleted", 0)->orderBy("match_date", "ASC")->orderBy("id", "ASC")->get()->getResult();
        $participants = $this->participantRows($id);
        $checklist = $this->db->table($this->table("gd_academy_event_checklist"))->where("unit_id", $this->unit_id)->where("event_id", $id)->where("deleted", 0)->orderBy("sort_order", "ASC")->orderBy("id", "ASC")->get()->getResult();
        $staff = $this->db->table($this->table("gd_academy_event_staff"))->where("unit_id", $this->unit_id)->where("event_id", $id)->where("deleted", 0)->get()->getResult();
        $criteria = $this->criteria();
        $metrics = array_merge($this->emptyMetrics(), $this->eventMetrics([$id])[$id] ?? [], $this->eventSummary([$id])[$id] ?? []);
        $history = $this->eventHistory($id);
        foreach ($categories as $category) {
            $category->matches = array_values(array_filter($matches, static fn($match): bool => (int) $match->category_id === (int) $category->id));
            $category->participants = array_values(array_filter($participants, static fn($participant): bool => (int) $participant->category_id === (int) $category->id));
        }
        return ["event" => $event, "categories" => $categories, "matches" => $matches, "participants" => $participants, "checklist" => $checklist, "staff" => $staff, "criteria" => $criteria, "metrics" => $metrics, "history" => $history];
    }

    public function saveEvent(array $input, int $id = 0): array
    {
        $old = $id ? $this->events->get_scoped($id, $this->unit_id) : null;
        if ($id && !$old) throw new \DomainException("gd_record_not_found");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($name === "") throw new \DomainException("gd_event_name_required");
        $type = (string) ($input["event_type"] ?? "other");
        if (!in_array($type, self::EVENT_TYPES, true)) throw new \DomainException("gd_invalid_value");
        $status = (string) ($input["status"] ?? ($old->status ?? "draft"));
        if (!in_array($status, self::EVENT_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        if ($status === "completed" && (!$old || (string) $old->status !== "completed")) throw new \DomainException("gd_event_finalize_required");
        if ($status === "cancelled" && (!$old || (string) $old->status !== "cancelled")) throw new \DomainException("gd_event_cancel_required");
        if ($old && in_array((string) $old->status, ["completed", "cancelled"], true) && $status !== (string) $old->status) throw new \DomainException("gd_invalid_event_transition");
        $starts = $this->date($input["starts_on"] ?? "", true);
        if (!$starts) throw new \DomainException("gd_event_date_required");
        $ends = $this->date($input["ends_on"] ?? "", true);
        if ($ends && $ends < $starts) throw new \DomainException("gd_invalid_date_range");
        $amount = DataNormalizationService::decimal($input["default_participation_amount"] ?? "0", 2);
        $data = $this->stamp([
            "unit_id" => $this->unit_id, "name" => $name, "event_type" => $type,
            "description" => DataNormalizationService::text($input["description"] ?? "") ?: null,
            "organizer" => DataNormalizationService::text($input["organizer"] ?? "") ?: null,
            "starts_on" => $starts, "ends_on" => $ends,
            "event_time" => $this->time($input["event_time"] ?? ""), "presentation_time" => $this->time($input["presentation_time"] ?? ""),
            "location" => DataNormalizationService::text($input["location"] ?? "") ?: null,
            "address" => DataNormalizationService::text($input["address"] ?? "") ?: null,
            "regulation_path" => DataNormalizationService::text($input["regulation_path"] ?? "") ?: null,
            "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null,
            "default_participation_amount" => $amount, "currency" => "BRL",
            "business_area_id" => $this->optionalCatalogId("gd_business_areas", (int) ($input["business_area_id"] ?? 0)),
            "cost_center_id" => $this->optionalCatalogId("gd_cost_centers", (int) ($input["cost_center_id"] ?? 0)),
            "status" => $status, "lock_version" => $id ? (int) $old->lock_version + 1 : 1,
        ], $id === 0);
        if ($id && isset($input["lock_version"]) && (int) $input["lock_version"] !== (int) $old->lock_version) throw new \DomainException("gd_edit_conflict");
        $saved = (int) $this->events->ci_save($data, $id);
        if (!$saved) throw new \RuntimeException("save_failed");
        $after = $this->events->get_scoped($saved, $this->unit_id);
        $this->audit_change($id ? "update" : "create", "academy_event", $saved, $old ? (array) $old : null, $after ? (array) $after : null);
        return ["saved" => true, "id" => $saved];
    }

    public function saveCategory(int $eventId, array $input, int $id = 0): array
    {
        $this->assertEvent($eventId);
        $table = $this->table("gd_academy_event_categories");
        $old = $id ? $this->scopedRow($table, $id) : null;
        if ($id && (!$old || (int) $old->event_id !== $eventId)) throw new \DomainException("gd_record_not_found");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($name === "") throw new \DomainException("gd_category_name_required");
        $min = $this->optionalInt($input["min_age"] ?? null, 0, 120);
        $max = $this->optionalInt($input["max_age"] ?? null, 0, 120);
        if ($min !== null && $max !== null && $max < $min) throw new \DomainException("gd_invalid_age_range");
        $maxAthletes = $this->optionalInt($input["max_athletes"] ?? null, 1, 1000);
        $amount = DataNormalizationService::decimal($input["participation_amount"] ?? "", 2, true);
        $status = (string) ($input["status"] ?? ($old->status ?? "active"));
        if (!in_array($status, self::CATEGORY_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $now = gmdate("Y-m-d H:i:s");
        $data = $this->stamp(["unit_id" => $this->unit_id, "event_id" => $eventId, "name" => $name, "min_age" => $min, "max_age" => $max, "gender" => DataNormalizationService::text($input["gender"] ?? "") ?: null, "instructor_user_id" => $this->optionalInt($input["instructor_user_id"] ?? null, 1, PHP_INT_MAX), "assistant" => DataNormalizationService::text($input["assistant"] ?? "") ?: null, "max_athletes" => $maxAthletes, "participation_amount" => $amount, "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "status" => $status, "lock_version" => $id ? (int) $old->lock_version + 1 : 1, "updated_at" => $now], $id === 0);
        if (!$id) $data["created_at"] = $now;
        if ($id) { $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("lock_version", (int) $old->lock_version)->update($data); if ($this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict"); $saved = $id; }
        else { $this->db->table($table)->insert($data); $saved = (int) $this->db->insertID(); }
        if (!$saved) throw new \RuntimeException("save_failed");
        $this->audit_change($id ? "update" : "create", "academy_event_category", $saved, $old ? (array) $old : null, (array) $this->scopedRow($table, $saved), ["event_id" => $eventId]);
        return ["saved" => true, "id" => $saved];
    }

    public function saveMatch(int $categoryId, array $input, int $id = 0): array
    {
        $category = $this->scopedRow($this->table("gd_academy_event_categories"), $categoryId);
        if (!$category) throw new \DomainException("gd_record_not_found");
        $table = $this->table("gd_academy_event_matches");
        $old = $id ? $this->scopedRow($table, $id) : null;
        if ($id && (!$old || (int) $old->category_id !== $categoryId)) throw new \DomainException("gd_record_not_found");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($name === "") throw new \DomainException("gd_match_name_required");
        $date = $this->date($input["match_date"] ?? "", true);
        $status = (string) ($input["status"] ?? ($old->status ?? "scheduled"));
        if (!in_array($status, self::MATCH_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $now = gmdate("Y-m-d H:i:s");
        $data = $this->stamp(["unit_id" => $this->unit_id, "event_id" => (int) $category->event_id, "category_id" => $categoryId, "name" => $name, "opponent" => DataNormalizationService::text($input["opponent"] ?? "") ?: null, "phase" => DataNormalizationService::text($input["phase"] ?? "") ?: null, "round" => DataNormalizationService::text($input["round"] ?? "") ?: null, "match_date" => $date, "match_time" => $this->time($input["match_time"] ?? ""), "field_name" => DataNormalizationService::text($input["field_name"] ?? "") ?: null, "location" => DataNormalizationService::text($input["location"] ?? "") ?: null, "gd_score" => $this->optionalInt($input["gd_score"] ?? null, 0, 999), "opponent_score" => $this->optionalInt($input["opponent_score"] ?? null, 0, 999), "status" => $status, "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "lock_version" => $id ? (int) $old->lock_version + 1 : 1, "updated_at" => $now], $id === 0);
        if (!$id) $data["created_at"] = $now;
        if ($id) { $changed = $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $old->lock_version)->update($data); if (!$changed || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict"); $saved = $id; }
        else { $this->db->table($table)->insert($data); $saved = (int) $this->db->insertID(); }
        if (!$saved) throw new \RuntimeException("save_failed");
        $this->audit_change($id ? "update" : "create", "academy_event_match", $saved, $old ? (array) $old : null, (array) $this->scopedRow($table, $saved), ["event_id" => (int) $category->event_id]);
        return ["saved" => true, "id" => $saved];
    }

    public function searchStudents(string $query, int $categoryId = 0): array
    {
        $students = $this->table("grupo_donato_alunos");
        $query = DataNormalizationService::text($query);
        $builder = $this->db->table($students)->select("id,nome_aluno,nascimento_aluno,turma,photo_path,responsavel_id,status")->where("unidade_id", $this->legacy_unit_id)->where("deleted", 0)->where("status", "Ativo");
        if ($query !== "") $builder->groupStart()->like("nome_aluno", $query)->orLike("matricula", $query)->groupEnd();
        $rows = $builder->orderBy("nome_aluno", "ASC")->limit(40)->get()->getResult();
        $existing = [];
        if ($categoryId) { foreach ($this->db->table($this->table("gd_academy_event_participants"))->select("student_id")->where("unit_id", $this->unit_id)->where("category_id", $categoryId)->where("deleted", 0)->where("student_id IS NOT NULL", null, false)->get()->getResult() as $row) $existing[(int) $row->student_id] = true; }
        $category = $categoryId ? $this->scopedRow($this->table("gd_academy_event_categories"), $categoryId) : null;
        foreach ($rows as $row) {
            $row->already_added = isset($existing[(int) $row->id]);
            $row->age = $this->age($row->nascimento_aluno);
            $row->age_compatible = !$category || (($category->min_age === null || ($row->age !== null && $row->age >= (int) $category->min_age)) && ($category->max_age === null || ($row->age !== null && $row->age <= (int) $category->max_age)));
        }
        if ($category) {
            usort($rows, static fn($left, $right): int => ((int) $right->age_compatible <=> (int) $left->age_compatible) ?: strcasecmp((string) $left->nome_aluno, (string) $right->nome_aluno));
        }
        return $rows;
    }

    public function saveMatchScore(int $matchId, array $input): array
    {
        $table = $this->table("gd_academy_event_matches");
        $match = $this->scopedRow($table, $matchId);
        if (!$match) throw new \DomainException("gd_record_not_found");
        $now = gmdate("Y-m-d H:i:s");
        $data = $this->stamp([
            "gd_score" => $this->optionalInt($input["gd_score"] ?? null, 0, 999),
            "opponent_score" => $this->optionalInt($input["opponent_score"] ?? null, 0, 999),
            "status" => (string) ($input["status"] ?? "completed"),
            "updated_at" => $now,
            "lock_version" => (int) $match->lock_version + 1,
        ], false);
        if (!in_array($data["status"], self::MATCH_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $changed = $this->db->table($table)->where("id", $matchId)->where("unit_id", $this->unit_id)->where("lock_version", (int) $match->lock_version)->update($data);
        if (!$changed || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict");
        $this->audit_change("update", "academy_event_match", $matchId, (array) $match, (array) $this->scopedRow($table, $matchId), ["result_update" => true]);
        return ["saved" => true, "id" => $matchId];
    }

    public function saveStaff(int $eventId, array $input): array
    {
        $this->assertEvent($eventId);
        $roles = ["organizer", "coordinator", "coach", "assistant", "support", "responsible"];
        $role = (string) ($input["role"] ?? "support");
        if (!in_array($role, $roles, true)) throw new \DomainException("gd_invalid_value");
        $userId = (int) ($input["user_id"] ?? $this->actor_id);
        $personId = (int) ($input["person_id"] ?? 0);
        if ($userId > 0) $this->assert_rise_id("users", $userId);
        if ($personId > 0) $this->assert_rise_id("gd_people", $personId, ["unit_id" => $this->unit_id]);
        if ($userId <= 0 && $personId <= 0) throw new \DomainException("gd_event_staff_required");
        $table = $this->table("gd_academy_event_staff");
        $data = ["unit_id" => $this->unit_id, "event_id" => $eventId, "user_id" => $userId ?: null, "person_id" => $personId ?: null, "role" => $role, "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "created_at" => gmdate("Y-m-d H:i:s"), "created_by" => $this->actor_id ?: null, "deleted" => 0];
        $duplicate = $this->db->table($table)->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("role", $role)->where("deleted", 0);
        if ($userId > 0) $duplicate->where("user_id", $userId); else $duplicate->where("person_id", $personId);
        if ($duplicate->countAllResults() > 0) throw new \DomainException("gd_duplicate_event_staff");
        $this->db->table($table)->insert($data);
        $id = (int) $this->db->insertID();
        if (!$id) throw new \RuntimeException("save_failed");
        $this->audit_change("create", "academy_event_staff", $id, null, $data, ["event_id" => $eventId]);
        return ["saved" => true, "id" => $id];
    }

    public function addParticipant(int $categoryId, array $input): array
    {
        $category = $this->scopedRow($this->table("gd_academy_event_categories"), $categoryId);
        if (!$category) throw new \DomainException("gd_record_not_found");
        $event = $this->assertEvent((int) $category->event_id);
        $type = (string) ($input["athlete_type"] ?? "internal");
        if (!in_array($type, ["internal", "external"], true)) throw new \DomainException("gd_invalid_value");
        $positionInput = trim((string) ($input["position"] ?? ""));
        $positionKey = self::positionKey($positionInput);
        if ($positionInput !== "" && !$positionKey) throw new \DomainException("gd_invalid_position");
        $position = $positionKey ? self::POSITION_LABELS[$positionKey] : null;
        $studentId = null; $externalId = null; $responsibleId = null; $athleteName = ""; $birthDate = null;
        if ($type === "internal") {
            $studentId = (int) ($input["student_id"] ?? 0);
            $student = $this->db->table($this->table("grupo_donato_alunos"))->where("id", $studentId)->where("unidade_id", $this->legacy_unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$student) throw new \DomainException("gd_student_not_found");
            $athleteName = (string) $student->nome_aluno; $birthDate = $student->nascimento_aluno ?? null; $responsibleId = (int) ($student->responsavel_id ?? 0) ?: null;
        } else {
            $externalId = (int) ($input["external_athlete_id"] ?? 0);
            $requestedResponsible = $this->optionalInt($input["responsible_id"] ?? null, 1, PHP_INT_MAX);
            if ($requestedResponsible && !$this->legacyResponsible($requestedResponsible)) throw new \DomainException("gd_responsible_not_found");
            if ($externalId) { $external = $this->scopedRow($this->table("gd_academy_external_athletes"), $externalId); }
            else { $external = null; }
            if ($externalId && !$external) throw new \DomainException("gd_external_athlete_not_found");
            if (!$external) {
                $externalName = DataNormalizationService::text($input["external_name"] ?? "");
                if ($externalName === "") throw new \DomainException("gd_external_name_required");
                $now = gmdate("Y-m-d H:i:s");
                $externalData = $this->stamp(["unit_id" => $this->unit_id, "name" => $externalName, "birth_date" => $this->date($input["birth_date"] ?? "", false), "responsible_id" => $this->optionalInt($input["responsible_id"] ?? null, 1, PHP_INT_MAX), "responsible_name" => DataNormalizationService::text($input["responsible_name"] ?? "") ?: null, "phone" => DataNormalizationService::text($input["phone"] ?? "") ?: null, "origin_club" => DataNormalizationService::text($input["origin_club"] ?? "") ?: null, "notes" => DataNormalizationService::text($input["external_notes"] ?? "") ?: null, "status" => "active", "deleted" => 0, "created_at" => $now, "updated_at" => $now], true);
                $this->db->table($this->table("gd_academy_external_athletes"))->insert($externalData); $externalId = (int) $this->db->insertID(); $external = $this->scopedRow($this->table("gd_academy_external_athletes"), $externalId);
            }
            $athleteName = (string) $external->name; $birthDate = $external->birth_date ?? null; $responsibleId = (int) ($external->responsible_id ?? 0) ?: null;
        }
        if (!empty($input["responsible_id"])) $responsibleId = (int) $input["responsible_id"];
        if ($responsibleId && !$this->legacyResponsible($responsibleId)) throw new \DomainException("gd_responsible_not_found");
        $age = $this->age($birthDate);
        $ageCompatible = ($category->min_age === null || ($age !== null && $age >= (int) $category->min_age)) && ($category->max_age === null || ($age !== null && $age <= (int) $category->max_age));
        $amount = DataNormalizationService::decimal($input["amount"] ?? ($category->participation_amount !== null ? $category->participation_amount : $event->default_participation_amount), 2);
        $table = $this->table("gd_academy_event_participants");
        if ((int) ($category->max_athletes ?? 0) > 0 && (int) $this->db->table($table)->where("unit_id", $this->unit_id)->where("category_id", $categoryId)->where("deleted", 0)->countAllResults() >= (int) $category->max_athletes) throw new \DomainException("gd_category_capacity_reached");
        $duplicate = $this->db->table($table)->where("unit_id", $this->unit_id)->where("category_id", $categoryId)->where("deleted", 0)->groupStart()->where($studentId ? "student_id" : "external_athlete_id", $studentId ?: $externalId)->groupEnd()->get(1)->getRow();
        if ($duplicate) throw new \DomainException("gd_duplicate_participant");
        $now = gmdate("Y-m-d H:i:s");
        $data = $this->stamp(["unit_id" => $this->unit_id, "event_id" => (int) $category->event_id, "category_id" => $categoryId, "athlete_type" => $type, "student_id" => $studentId, "external_athlete_id" => $externalId, "responsible_id" => $responsibleId, "position" => $position, "confirmation_status" => "waiting", "lineup_status" => "called", "financial_status" => DataNormalizationService::decimalCompare($amount, "0.00") > 0 ? "pending" : "not_applicable", "charge_strategy" => null, "amount" => $amount, "due_date" => null, "financial_reference_month" => "", "receivable_id" => null, "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "lock_version" => 1, "created_at" => $now, "updated_at" => $now], true);
        try { $this->db->table($table)->insert($data); } catch (\Throwable $e) { if (str_contains($e->getMessage(), "uniq_academy_participant")) throw new \DomainException("gd_duplicate_participant"); throw $e; }
        $id = (int) $this->db->insertID();
        $this->audit_change("create", "academy_event_participant", $id, null, $data, ["event_id" => (int) $category->event_id, "athlete_name" => $athleteName]);
        return ["saved" => true, "id" => $id, "name" => $athleteName, "age_compatible" => $ageCompatible];
    }

    public function updateParticipant(int $id, array $input): array
    {
        $table = $this->table("gd_academy_event_participants"); $row = $this->scopedRow($table, $id); if (!$row) throw new \DomainException("gd_record_not_found");
        $lineup = (string) ($input["lineup_status"] ?? $row->lineup_status); if (!in_array($lineup, self::LINEUP_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $confirmation = (string) ($input["confirmation_status"] ?? $row->confirmation_status); if ($confirmation === "pending") $confirmation = "waiting"; if (!in_array($confirmation, self::CONFIRMATION_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $amount = DataNormalizationService::decimal($input["amount"] ?? $row->amount, 2);
        if ((int) $row->receivable_id && DataNormalizationService::decimalCompare($amount, (string) $row->amount) !== 0) throw new \DomainException("gd_event_charged_amount_immutable");
        $positionInput = array_key_exists("position", $input) ? trim((string) $input["position"]) : trim((string) ($row->position ?? ""));
        $positionKey = self::positionKey($positionInput);
        if (array_key_exists("position", $input) && $positionInput !== "" && !$positionKey) throw new \DomainException("gd_invalid_position");
        $position = $positionKey ? self::POSITION_LABELS[$positionKey] : ($positionInput !== "" ? $positionInput : null);
        $data = $this->stamp(["position" => $position, "lineup_status" => $lineup, "confirmation_status" => $confirmation, "amount" => $amount, "notes" => DataNormalizationService::text($input["notes"] ?? $row->notes) ?: null, "lock_version" => (int) $row->lock_version + 1, "updated_at" => gmdate("Y-m-d H:i:s")], false);
        $changed = $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $row->lock_version)->update($data); if (!$changed || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict");
        if ($confirmation !== (string) $row->confirmation_status) $this->saveConfirmation($id, ["status" => $confirmation, "responsible_id" => $row->responsible_id, "origin" => "admin"]);
        $this->audit_change("update", "academy_event_participant", $id, (array) $row, (array) $this->scopedRow($table, $id)); return ["saved" => true, "id" => $id];
    }

    public function saveConfirmation(int $participantId, array $input): array
    {
        $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId); if (!$participant) throw new \DomainException("gd_record_not_found");
        $status = (string) ($input["status"] ?? "waiting"); if (!in_array($status, self::CONFIRMATION_STATUSES, true)) throw new \DomainException("gd_invalid_value");
        $responsible = (int) ($input["responsible_id"] ?? ($participant->responsible_id ?? 0)); if ($responsible && !$this->legacyResponsible($responsible)) throw new \DomainException("gd_responsible_not_found");
        $table = $this->table("gd_academy_event_confirmations"); $now = gmdate("Y-m-d H:i:s"); $existing = $this->db->table($table)->where("unit_id", $this->unit_id)->where("participant_id", $participantId)->get(1)->getRow();
        $data = $this->stamp(["unit_id" => $this->unit_id, "participant_id" => $participantId, "responsible_id" => $responsible ?: null, "status" => $status, "confirmed_at" => in_array($status, ["confirmed", "refused"], true) ? $now : null, "origin" => DataNormalizationService::text($input["origin"] ?? "admin") ?: "admin", "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "updated_at" => $now], !$existing);
        if (!$existing) $data["created_at"] = $now;
        if ($existing) { $this->db->table($table)->where("id", (int) $existing->id)->update($data); $id = (int) $existing->id; } else { $this->db->table($table)->insert($data); $id = (int) $this->db->insertID(); }
        $this->db->table($this->table("gd_academy_event_participants"))->where("id", $participantId)->where("unit_id", $this->unit_id)->update(["confirmation_status" => $status, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]);
        $this->audit_change("confirmation", "academy_event_participant", $participantId, (array) $participant, ["status" => $status, "responsible_id" => $responsible], ["confirmation_id" => $id, "origin" => $data["origin"]]); return ["saved" => true, "id" => $id];
    }

    public function chargeParticipant(int $participantId, array $input = []): array
    {
        $table = $this->table("gd_academy_event_participants"); $participant = $this->scopedRow($table, $participantId); if (!$participant) throw new \DomainException("gd_record_not_found");
        if ((int) $participant->receivable_id) return ["created" => false, "id" => (int) $participant->receivable_id, "duplicate" => true];
        if (in_array((string) $participant->financial_status, ["exempt", "courtesy", "cancelled"], true)) throw new \DomainException("gd_event_financial_unavailable");
        $amount = DataNormalizationService::decimal($input["amount"] ?? $participant->amount, 2); if (DataNormalizationService::decimalCompare($amount, "0.00") <= 0) throw new \DomainException("gd_event_amount_required");
        $responsible = (int) ($participant->responsible_id ?? 0); if (!$responsible) throw new \DomainException("gd_event_responsible_required");
        $account = $this->ensureFamilyAccount($responsible);
        $strategy = (string) ($input["charge_strategy"] ?? "open"); if (!in_array($strategy, self::CHARGE_STRATEGIES, true)) throw new \DomainException("gd_invalid_value");
        $today = gmdate("Y-m-d");
        $defaultDue = $today;
        if ($strategy === "next_closing") {
            $defaultDue = gmdate("Y-m-10");
            if ($defaultDue < $today) $defaultDue = gmdate("Y-m-10", strtotime("+1 month"));
        }
        $due = $this->date($input["due_date"] ?? "", true) ?: $defaultDue;
        if ($due < $today && $strategy !== "immediate") throw new \DomainException("gd_event_due_date_invalid");
        $event = $this->assertEvent((int) $participant->event_id);
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        $saved = $finance->createReceivable(["source_type" => "academy_event_participation", "source_id" => $participantId, "customer_account_id" => $account, "description" => "Evento " . $event->name . " - " . $this->participantName($participant), "issue_date" => min(gmdate("Y-m-d"), $due), "due_date" => $due, "original_amount" => $amount, "unit_amount" => $amount, "quantity" => "1", "reference_month" => "", "business_area_id" => (int) ($event->business_area_id ?? 0), "cost_center_id" => (int) ($event->cost_center_id ?? 0), "notes" => DataNormalizationService::text($input["notes"] ?? "")]);
        $receivableId = (int) ($saved["id"] ?? 0); if (!$receivableId) throw new \RuntimeException("save_failed");
        $updated = $this->db->table($table)->where("id", $participantId)->where("unit_id", $this->unit_id)->where("receivable_id IS NULL", null, false)->update(["receivable_id" => $receivableId, "amount" => $amount, "due_date" => $due, "charge_strategy" => $strategy, "financial_reference_month" => "", "financial_status" => "generated", "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
        if (!$updated && !(int) $participant->receivable_id) throw new \DomainException("gd_event_charge_conflict");
        $payment = null;
        if (DataNormalizationService::decimalCompare(DataNormalizationService::decimal($input["payment_amount"] ?? "0", 2), "0.00") > 0) $payment = $this->registerPayment($participantId, $input + ["receivable_id" => $receivableId]);
        $this->audit_change("charge", "academy_event_participant", $participantId, (array) $participant, ["receivable_id" => $receivableId, "amount" => $amount, "strategy" => $strategy], ["finance_created" => !empty($saved["created"])]);
        return ["created" => !empty($saved["created"]), "id" => $receivableId, "payment" => $payment];
    }

    public function registerPayment(int $participantId, array $input): array
    {
        $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId); if (!$participant) throw new \DomainException("gd_record_not_found");
        $receivableId = (int) $participant->receivable_id; if (!$receivableId) { $chargeInput = $input; unset($chargeInput["payment_amount"]); $this->chargeParticipant($participantId, $chargeInput); $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId); $receivableId = (int) $participant->receivable_id; }
        $amount = DataNormalizationService::decimal($input["payment_amount"] ?? ($input["amount"] ?? ""), 2); if (DataNormalizationService::decimalCompare($amount, "0.00") <= 0) throw new \DomainException("gd_finance_payment_amount_required");
        $method = Constants::normalizePaymentMethod((string) ($input["payment_method"] ?? "")); if (!$method) throw new \DomainException("gd_finance_payment_method_required");
        $account = (int) ($input["financial_account_id"] ?? 0); if (!$account) $account = $this->defaultFinancialAccount();
        $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
        $result = $finance->registerPayment(["allocations" => [$receivableId => $amount], "amount" => $amount, "payment_date" => $input["payment_date"] ?? gmdate("Y-m-d"), "payment_method" => $method, "financial_account_id" => $account, "notes" => $input["payment_notes"] ?? "Pagamento de evento", "external_reference" => $input["external_reference"] ?? ""]);
        $fresh = $this->db->table($this->table("gd_receivables"))->where("id", $receivableId)->where("unit_id", $this->unit_id)->get(1)->getRow();
        $status = $fresh && (string) $fresh->status === "paid" ? "paid" : "partial";
        $this->db->table($this->table("gd_academy_event_participants"))->where("id", $participantId)->where("unit_id", $this->unit_id)->update(["financial_status" => $status, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
        $this->audit_change("payment", "academy_event_participant", $participantId, null, ["receivable_id" => $receivableId, "amount" => $amount, "status" => $status], ["payment_id" => (int) ($result["id"] ?? 0)]); return $result + ["receivable_id" => $receivableId, "status" => $status];
    }

    public function setParticipantFinancialStatus(int $participantId, string $status, string $note = ""): array
    {
        $table = $this->table("gd_academy_event_participants");
        $row = $this->scopedRow($table, $participantId);
        if (!$row) throw new \DomainException("gd_record_not_found");
        if (!in_array($status, ["pending", "not_applicable", "exempt", "courtesy"], true)) throw new \DomainException("gd_invalid_value");
        if ((int) $row->receivable_id) throw new \DomainException("gd_event_charged_amount_immutable");
        if ($status === "not_applicable" && DataNormalizationService::decimalCompare((string) $row->amount, "0.00") > 0) throw new \DomainException("gd_event_amount_required");
        $data = $this->stamp(["financial_status" => $status, "updated_at" => gmdate("Y-m-d H:i:s"), "lock_version" => (int) $row->lock_version + 1], false);
        $ok = $this->db->table($table)->where("id", $participantId)->where("unit_id", $this->unit_id)->where("lock_version", (int) $row->lock_version)->update($data);
        if (!$ok || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict");
        $this->audit_change("finance_status", "academy_event_participant", $participantId, (array) $row, (array) $this->scopedRow($table, $participantId), ["status" => $status, "note" => DataNormalizationService::text($note)]);
        return ["saved" => true, "id" => $participantId, "financial_status" => $status];
    }

    public function saveChecklist(int $eventId, array $input, int $id = 0): array
    {
        $this->assertEvent($eventId); $table = $this->table("gd_academy_event_checklist"); $old = $id ? $this->scopedRow($table, $id) : null; if ($id && (!$old || (int) $old->event_id !== $eventId)) throw new \DomainException("gd_record_not_found");
        $title = DataNormalizationService::text($input["title"] ?? ""); if ($title === "") throw new \DomainException("gd_checklist_title_required"); $now = gmdate("Y-m-d H:i:s"); $data = $this->stamp(["unit_id" => $this->unit_id, "event_id" => $eventId, "title" => $title, "sort_order" => (int) ($input["sort_order"] ?? 0), "responsible_user_id" => $this->optionalInt($input["responsible_user_id"] ?? null, 1, PHP_INT_MAX), "due_date" => $this->date($input["due_date"] ?? "", true), "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "deleted" => 0, "updated_at" => $now], !$id);
        if (!$id) $data["created_at"] = $now;
        if ($id) { $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->update($data); $saved = $id; } else { $this->db->table($table)->insert($data); $saved = (int) $this->db->insertID(); }
        $this->audit_change($id ? "update" : "create", "academy_event_checklist", $saved, $old ? (array) $old : null, (array) $this->scopedRow($table, $saved)); return ["saved" => true, "id" => $saved];
    }

    public function toggleChecklist(int $id, bool $complete): array
    {
        $table = $this->table("gd_academy_event_checklist"); $row = $this->scopedRow($table, $id); if (!$row) throw new \DomainException("gd_record_not_found"); $now = gmdate("Y-m-d H:i:s"); $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->update(["completed_at" => $complete ? $now : null, "completed_by" => $complete ? ($this->actor_id ?: null) : null, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]); $this->audit_change($complete ? "complete" : "reopen", "academy_event_checklist", $id, (array) $row, (array) $this->scopedRow($table, $id)); return ["saved" => true, "id" => $id, "completed" => $complete];
    }

    public function saveStats(int $participantId, array $input): array
    {
        $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId); if (!$participant) throw new \DomainException("gd_record_not_found"); $matchId = (int) ($input["match_id"] ?? 0); $match = $this->scopedRow($this->table("gd_academy_event_matches"), $matchId); if (!$match || (int) $match->category_id !== (int) $participant->category_id) throw new \DomainException("gd_match_participant_mismatch");
        $table = $this->table("gd_academy_match_player_stats"); $existing = $this->db->table($table)->where("unit_id", $this->unit_id)->where("match_id", $matchId)->where("participant_id", $participantId)->get(1)->getRow(); $now = gmdate("Y-m-d H:i:s"); $data = $this->stamp(["unit_id" => $this->unit_id, "match_id" => $matchId, "participant_id" => $participantId, "position" => DataNormalizationService::text($input["position"] ?? "") ?: null, "lineup_status" => DataNormalizationService::text($input["lineup_status"] ?? "") ?: null, "goals" => $this->nonNegativeInt($input["goals"] ?? 0), "assists" => $this->nonNegativeInt($input["assists"] ?? 0), "penalties_scored" => $this->nonNegativeInt($input["penalties_scored"] ?? 0), "penalties_missed" => $this->nonNegativeInt($input["penalties_missed"] ?? 0), "yellow_cards" => $this->nonNegativeInt($input["yellow_cards"] ?? 0), "red_cards" => $this->nonNegativeInt($input["red_cards"] ?? 0), "saves" => $this->nonNegativeInt($input["saves"] ?? 0), "minutes_played" => $this->optionalInt($input["minutes_played"] ?? null, 0, 1000), "notes" => DataNormalizationService::text($input["notes"] ?? "") ?: null, "updated_at" => $now], !$existing);
        if (!$existing) $data["created_at"] = $now;
        if ($existing) { $this->db->table($table)->where("id", (int) $existing->id)->update($data); $id = (int) $existing->id; } else { $this->db->table($table)->insert($data); $id = (int) $this->db->insertID(); } $this->audit_change($existing ? "update" : "create", "academy_match_player_stats", $id, $existing ? (array) $existing : null, (array) $this->scopedRow($table, $id)); return ["saved" => true, "id" => $id];
    }

    public function criteria(?string $position = null): array
    {
        $table = $this->table("gd_academy_evaluation_criteria");
        $builder = $this->db->table($table)->where("deleted", 0)->where("active", 1)
            ->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd();
        if ($position !== null) {
            $positionKey = self::positionKey($position);
            $builder->groupStart()->where("scope", "general");
            if ($positionKey) $builder->orGroupStart()->where("scope", "position")->where("position_key", $positionKey)->groupEnd();
            $builder->groupEnd();
        }
        return $builder->orderBy("scope", "ASC")->orderBy("sort_order", "ASC")->get()->getResult();
    }

    public function saveEvaluation(int $participantId, array $input): array
    {
        $participant = $this->scopedRow($this->table("gd_academy_event_participants"), $participantId); if (!$participant) throw new \DomainException("gd_record_not_found"); $event = $this->assertEvent((int) $participant->event_id); $table = $this->table("gd_academy_athlete_evaluations"); $scoresTable = $this->table("gd_academy_evaluation_scores"); $existing = $this->db->table($table)->where("unit_id", $this->unit_id)->where("participant_id", $participantId)->where("deleted", 0)->get(1)->getRow();
        $matchId = $this->optionalInt($input["match_id"] ?? null, 1, PHP_INT_MAX);
        if ($matchId) {
            $match = $this->scopedRow($this->table("gd_academy_event_matches"), $matchId);
            if (!$match || (int) $match->category_id !== (int) $participant->category_id || (int) $match->event_id !== (int) $event->id) throw new \DomainException("gd_match_participant_mismatch");
        }
        $classification = DataNormalizationService::text($input["performance_classification"] ?? "") ?: null; if ($classification && !in_array($classification, self::CLASSIFICATIONS, true)) throw new \DomainException("gd_invalid_value");
        $now = gmdate("Y-m-d H:i:s");
        $data = $this->stamp(["unit_id" => $this->unit_id, "event_id" => (int) $event->id, "category_id" => (int) $participant->category_id, "match_id" => $matchId, "participant_id" => $participantId, "athlete_type" => $participant->athlete_type, "student_id" => $participant->student_id, "external_athlete_id" => $participant->external_athlete_id, "evaluated_by" => $this->actor_id ?: null, "evaluated_at" => $now, "performance_classification" => $classification, "strengths" => DataNormalizationService::text($input["strengths"] ?? "") ?: null, "development_areas" => DataNormalizationService::text($input["development_areas"] ?? "") ?: null, "next_training_recommendation" => DataNormalizationService::text($input["next_training_recommendation"] ?? "") ?: null, "general_comment" => DataNormalizationService::text($input["general_comment"] ?? "") ?: null, "internal_note" => DataNormalizationService::text($input["internal_note"] ?? "") ?: null, "responsible_feedback" => DataNormalizationService::text($input["responsible_feedback"] ?? "") ?: null, "source_type" => "event", "status" => "completed", "lock_version" => $existing ? (int) $existing->lock_version + 1 : 1, "updated_at" => $now], !$existing);
        if (!$existing) $data["created_at"] = $now;
        $this->db->transBegin(); try { if ($existing) { $ok = $this->db->table($table)->where("id", (int) $existing->id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $existing->lock_version)->update($data); if (!$ok || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict"); $evaluationId = (int) $existing->id; } else { $this->db->table($table)->insert($data); $evaluationId = (int) $this->db->insertID(); }
            $validCriteria = []; foreach ($this->criteria((string) ($participant->position ?? "")) as $criterion) $validCriteria[(int) $criterion->id] = true; foreach ((array) ($input["scores"] ?? []) as $criterionId => $score) { $criterionId = (int) $criterionId; if (!isset($validCriteria[$criterionId])) throw new \DomainException("gd_invalid_criterion"); if ($score === "" || $score === null) continue; $score = (float) str_replace(",", ".", (string) $score); if ($score < 1 || $score > 5) throw new \DomainException("gd_score_out_of_range"); $scoreValue = number_format($score, 1, ".", ""); $oldScore = $this->db->table($scoresTable)->where("unit_id", $this->unit_id)->where("evaluation_id", $evaluationId)->where("criterion_id", $criterionId)->where("deleted", 0)->get(1)->getRow(); $scoreData = $this->stamp(["unit_id" => $this->unit_id, "evaluation_id" => $evaluationId, "criterion_id" => $criterionId, "score" => $scoreValue, "notes" => null, "deleted" => 0], !$oldScore); if ($oldScore) $this->db->table($scoresTable)->where("id", (int) $oldScore->id)->update($scoreData); else $this->db->table($scoresTable)->insert($scoreData); }
            if ($this->db->transCommit() === false) throw new \RuntimeException("save_failed");
        } catch (\Throwable $e) { $this->db->transRollback(); throw $e; }
        $this->audit_change($existing ? "update" : "create", "academy_athlete_evaluation", $evaluationId, $existing ? (array) $existing : null, (array) $this->db->table($table)->where("id", $evaluationId)->get(1)->getRow(), ["event_id" => (int) $event->id]); return ["saved" => true, "id" => $evaluationId];
    }

    public function studentHistory(int $studentId): array
    {
        $student = $this->db->table($this->table("grupo_donato_alunos"))->where("id", $studentId)->where("unidade_id", $this->legacy_unit_id)->where("deleted", 0)->get(1)->getRow(); if (!$student) throw new \DomainException("gd_student_not_found");
        $p = $this->table("gd_academy_event_participants"); $e = $this->table("gd_academy_events"); $c = $this->table("gd_academy_event_categories"); $ev = $this->table("gd_academy_athlete_evaluations"); $s = $this->table("gd_academy_evaluation_scores"); $cr = $this->table("gd_academy_evaluation_criteria"); $m = $this->table("gd_academy_event_matches"); $stats = $this->table("gd_academy_match_player_stats");
        $events = $this->db->query("SELECT p.id participant_id,p.confirmation_status,p.lineup_status,p.financial_status,p.amount,e.id event_id,e.name event_name,e.starts_on,e.status event_status,c.id category_id,c.name category_name FROM `$p` p JOIN `$e` e ON e.id=p.event_id AND e.unit_id=p.unit_id AND e.deleted=0 JOIN `$c` c ON c.id=p.category_id AND c.unit_id=p.unit_id AND c.deleted=0 WHERE p.unit_id=? AND p.student_id=? AND p.deleted=0 ORDER BY e.starts_on DESC", [$this->unit_id, $studentId])->getResult();
        foreach ($events as $row) { $row->evaluation = $this->db->table($ev)->where("unit_id", $this->unit_id)->where("participant_id", (int) $row->participant_id)->where("deleted", 0)->get(1)->getRow(); $row->scores = $this->db->query("SELECT s.score,cr.name FROM `$s` s JOIN `$cr` cr ON cr.id=s.criterion_id AND cr.deleted=0 WHERE s.unit_id=? AND s.evaluation_id=? AND s.deleted=0 ORDER BY cr.sort_order", [$this->unit_id, (int) ($row->evaluation->id ?? 0)])->getResult(); $row->matches = $this->db->query("SELECT m.name,m.opponent,m.match_date,st.* FROM `$stats` st JOIN `$m` m ON m.id=st.match_id AND m.unit_id=st.unit_id AND m.deleted=0 WHERE st.unit_id=? AND st.participant_id=?", [$this->unit_id, (int) $row->participant_id])->getResult(); }
        return ["student" => $student, "events" => $events];
    }

    public function studentProgressReport(int $studentId, string $dateFrom = "", string $dateTo = ""): array
    {
        $history = $this->studentHistory($studentId);
        $today = gmdate("Y-m-d");
        $from = $this->valid_date($dateFrom ?: gmdate("Y-m-d", strtotime("-3 months")), true) ?: $today;
        $to = $this->valid_date($dateTo ?: $today, true) ?: $today;
        if ($to < $from) throw new \DomainException("gd_invalid_date_range");
        $events = array_values(array_filter($history["events"], static fn($event): bool => (string) $event->starts_on >= $from && (string) $event->starts_on <= $to));
        $averages = [];
        $strengths = [];
        $development = [];
        $comments = [];
        foreach ($events as $event) {
            foreach ($event->scores as $score) {
                $key = (string) $score->name;
                $averages[$key] = ($averages[$key] ?? ["sum" => 0.0, "count" => 0]);
                $averages[$key]["sum"] += (float) $score->score;
                $averages[$key]["count"]++;
            }
            if (!empty($event->evaluation->strengths)) $strengths[] = (string) $event->evaluation->strengths;
            if (!empty($event->evaluation->development_areas)) $development[] = (string) $event->evaluation->development_areas;
            if (!empty($event->evaluation->general_comment)) $comments[] = (string) $event->evaluation->general_comment;
        }
        foreach ($averages as $name => $value) $averages[$name] = number_format($value["sum"] / max(1, $value["count"]), 1, ".", "");
        return [
            "student" => $history["student"], "date_from" => $from, "date_to" => $to, "events" => $events,
            "event_count" => count($events), "averages" => $averages, "strengths" => array_values(array_unique($strengths)),
            "development_areas" => array_values(array_unique($development)), "comments" => array_values(array_unique($comments)),
        ];
    }

    public function familyAccount(int $responsibleId): array
    {
        $responsible = $this->legacyResponsible($responsibleId); if (!$responsible) throw new \DomainException("gd_responsible_not_found"); $accountId = $this->ensureFamilyAccount($responsibleId); $r = $this->table("gd_receivables"); $p = $this->table("gd_academy_event_participants"); $a = $this->table("gd_academy_events"); $c = $this->table("gd_academy_event_categories"); $students = $this->table("grupo_donato_alunos"); $rows = $this->db->query("SELECT r.*,p.student_id,p.category_id,c.name category_name,a.name event_name,s.nome_aluno child_name FROM `$r` r LEFT JOIN `$p` p ON p.receivable_id=r.id AND p.unit_id=r.unit_id AND p.deleted=0 LEFT JOIN `$a` a ON a.id=p.event_id AND a.unit_id=p.unit_id AND a.deleted=0 LEFT JOIN `$c` c ON c.id=p.category_id AND c.unit_id=p.unit_id AND c.deleted=0 LEFT JOIN `$students` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0 WHERE r.unit_id=? AND r.customer_account_id=? AND r.deleted=0 ORDER BY r.due_date ASC,r.id ASC", [$this->legacy_unit_id, $this->unit_id, $accountId])->getResult();
        // Legacy monthly charges remain visible in the same family view while
        // their historical storage is migrated separately by the Academy.
        $legacy = []; $legacyTable = $this->table("grupo_donato_cobrancas"); if ($this->db->tableExists($legacyTable)) { $legacyQuery = $this->db->table($legacyTable)->where("responsavel_id", $responsibleId); if ($this->db->fieldExists("deleted", $legacyTable)) $legacyQuery->where("deleted", 0); $legacy = $legacyQuery->orderBy("vencimento", "ASC")->get()->getResult(); }
        return ["responsible" => $responsible, "account_id" => $accountId, "receivables" => $rows, "legacy_monthly" => $legacy];
    }

    public function finalizeEvent(int $eventId, bool $allowPending = false, string $note = ""): array
    {
        $event = $this->assertEvent($eventId); if (in_array((string) $event->status, ["completed", "cancelled"], true)) throw new \DomainException("gd_invalid_event_transition"); $pending = $this->pendingForEvent($eventId); if ($pending && (!$allowPending || DataNormalizationService::text($note) === "")) throw new \DomainException("gd_event_pending"); $table = $this->table("gd_academy_events"); $data = $this->stamp(["status" => "completed", "finalized_at" => gmdate("Y-m-d H:i:s"), "finalized_by" => $this->actor_id ?: null, "finalization_note" => DataNormalizationService::text($note) ?: null, "lock_version" => (int) $event->lock_version + 1], false); $ok = $this->db->table($table)->where("id", $eventId)->where("unit_id", $this->unit_id)->where("lock_version", (int) $event->lock_version)->update($data); if (!$ok || $this->db->affectedRows() !== 1) throw new \DomainException("gd_edit_conflict"); $this->audit_change("finalize", "academy_event", $eventId, (array) $event, (array) $this->events->get_scoped($eventId, $this->unit_id), ["pending" => $pending]); return ["saved" => true, "pending" => $pending];
    }

    public function cancelEvent(int $eventId, string $reason): array
    {
        $event = $this->assertEvent($eventId); if ((string) $event->status === "completed") throw new \DomainException("gd_invalid_event_transition"); if ((string) $event->status === "cancelled") return ["saved" => false, "already_cancelled" => true, "warnings" => []]; $reason = DataNormalizationService::text($reason); if ($reason === "") throw new \DomainException("gd_reason_required"); $warnings = []; $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user); foreach ($this->db->table($this->table("gd_academy_event_participants"))->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("deleted", 0)->get()->getResult() as $participant) { if ((int) $participant->receivable_id) { try { $finance->cancelReceivable((int) $participant->receivable_id, "Evento cancelado: " . $reason); $this->db->table($this->table("gd_academy_event_participants"))->where("id", (int) $participant->id)->update(["financial_status" => "cancelled"]); } catch (\Throwable $e) { $warnings[] = $this->participantName($participant) . ": " . $e->getMessage(); } } }
        $table = $this->table("gd_academy_events"); $this->db->table($table)->where("id", $eventId)->where("unit_id", $this->unit_id)->update(["status" => "cancelled", "cancelled_at" => gmdate("Y-m-d H:i:s"), "cancelled_by" => $this->actor_id ?: null, "notes" => trim((string) $event->notes . "\nCancelamento: " . $reason), "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]); $this->audit_change("cancel", "academy_event", $eventId, (array) $event, (array) $this->events->get_scoped($eventId, $this->unit_id), ["reason" => $reason, "warnings" => $warnings]); return ["saved" => true, "warnings" => $warnings];
    }

    private function participantRows(int $eventId): array
    {
        $p = $this->table("gd_academy_event_participants"); $s = $this->table("grupo_donato_alunos"); $x = $this->table("gd_academy_external_athletes"); $r = $this->table("grupo_donato_responsaveis"); $receivable = $this->table("gd_receivables"); $scores = $this->table("gd_academy_evaluation_scores"); $criteria = $this->table("gd_academy_evaluation_criteria"); $stats = $this->table("gd_academy_match_player_stats"); $rows = $this->db->query("SELECT p.*,COALESCE(s.nome_aluno,x.name) athlete_name,COALESCE(s.nascimento_aluno,x.birth_date) birth_date,s.turma,s.photo_path,x.origin_club,COALESCE(r.nome,x.responsible_name) responsible_name,rc.status receivable_status,rc.paid_amount,rc.balance_amount FROM `$p` p LEFT JOIN `$s` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0 LEFT JOIN `$x` x ON x.id=p.external_athlete_id AND x.unit_id=p.unit_id AND x.deleted=0 LEFT JOIN `$r` r ON r.id=p.responsible_id AND r.deleted=0 LEFT JOIN `$receivable` rc ON rc.id=p.receivable_id AND rc.unit_id=p.unit_id AND rc.deleted=0 WHERE p.unit_id=? AND p.event_id=? AND p.deleted=0 ORDER BY athlete_name ASC", [$this->legacy_unit_id, $this->unit_id, $eventId])->getResult(); foreach ($rows as $row) { $row->age = $this->age($row->birth_date); $row->evaluation = $this->db->table($this->table("gd_academy_athlete_evaluations"))->where("unit_id", $this->unit_id)->where("participant_id", (int) $row->id)->where("deleted", 0)->get(1)->getRow(); $row->scores = $row->evaluation ? $this->db->query("SELECT es.criterion_id,es.score,ec.code,ec.name FROM `$scores` es JOIN `$criteria` ec ON ec.id=es.criterion_id AND ec.deleted=0 WHERE es.unit_id=? AND es.evaluation_id=? AND es.deleted=0 ORDER BY ec.sort_order", [$this->unit_id, (int) $row->evaluation->id])->getResult() : []; $row->match_stats = []; foreach ($this->db->table($stats)->where("unit_id", $this->unit_id)->where("participant_id", (int) $row->id)->where("deleted", 0)->get()->getResult() as $stat) $row->match_stats[(int) $stat->match_id] = $stat; if ($row->receivable_status) $row->financial_status = (string) $row->receivable_status; } return $rows;
    }

    private function eventMetrics(array $ids): array
    {
        if (!$ids) return []; $placeholders = implode(",", array_fill(0, count($ids), "?")); $params = array_merge([$this->unit_id], $ids); $p = $this->table("gd_academy_event_participants"); $ev = $this->table("gd_academy_athlete_evaluations"); $r = $this->table("gd_receivables"); $rows = $this->db->query("SELECT p.event_id,COUNT(*) called_count,SUM(p.confirmation_status='confirmed') confirmed_count,SUM(p.confirmation_status IN ('waiting','pending','no_response')) pending_confirmations,SUM(e.id IS NULL AND p.lineup_status NOT IN ('cut','absent')) pending_evaluations,COALESCE(SUM(CASE WHEN p.financial_status NOT IN ('exempt','courtesy','cancelled','not_applicable') THEN p.amount ELSE 0 END),0) expected_amount,COALESCE(SUM(r.paid_amount),0) received_amount,COALESCE(SUM(r.balance_amount),0) open_amount,COALESCE(SUM(CASE WHEN r.status='overdue' THEN r.balance_amount ELSE 0 END),0) overdue_amount,SUM(r.status IN ('open','partial','overdue')) pending_payments FROM `$p` p LEFT JOIN `$ev` e ON e.participant_id=p.id AND e.unit_id=p.unit_id AND e.deleted=0 LEFT JOIN `$r` r ON r.id=p.receivable_id AND r.unit_id=p.unit_id AND r.deleted=0 WHERE p.unit_id=? AND p.event_id IN ($placeholders) AND p.deleted=0 GROUP BY p.event_id", $params)->getResult(); $out = []; foreach ($rows as $row) $out[(int) $row->event_id] = ["called" => (int) $row->called_count, "confirmed" => (int) $row->confirmed_count, "pending_confirmations" => (int) $row->pending_confirmations, "pending_evaluations" => (int) $row->pending_evaluations, "expected_amount" => (string) $row->expected_amount, "received_amount" => (string) $row->received_amount, "open_amount" => (string) $row->open_amount, "overdue_amount" => (string) $row->overdue_amount, "pending_payments" => (int) $row->pending_payments]; return $out;
    }

    private function eventSummary(array $ids): array
    {
        if (!$ids) return [];
        $placeholders = implode(",", array_fill(0, count($ids), "?"));
        $categories = $this->table("gd_academy_event_categories");
        $participants = $this->table("gd_academy_event_participants");
        $matches = $this->table("gd_academy_event_matches");
        $rows = $this->db->query("SELECT c.event_id,COUNT(DISTINCT c.id) categories,COUNT(DISTINCT m.id) matches,COUNT(DISTINCT CASE WHEN p.financial_status='paid' THEN p.id END) paid,GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') category_names FROM `$categories` c LEFT JOIN `$participants` p ON p.category_id=c.id AND p.unit_id=c.unit_id AND p.deleted=0 LEFT JOIN `$matches` m ON m.category_id=c.id AND m.unit_id=c.unit_id AND m.deleted=0 WHERE c.unit_id=? AND c.event_id IN ($placeholders) AND c.deleted=0 GROUP BY c.event_id", array_merge([$this->unit_id], $ids))->getResult();
        $out = [];
        foreach ($rows as $row) $out[(int) $row->event_id] = ["categories" => (int) $row->categories, "matches" => (int) $row->matches, "paid" => (int) $row->paid, "category_names" => (string) ($row->category_names ?? "")];
        return $out;
    }

    private function eventHistory(int $eventId): array
    {
        $table = $this->table("gd_audit_logs");
        if (!$this->db->tableExists($table)) return [];
        $types = ["academy_event", "academy_event_category", "academy_event_match", "academy_event_participant", "academy_event_staff", "academy_athlete_evaluation", "academy_match_player_stats"];
        $typePlaceholders = implode(",", array_fill(0, count($types), "?"));
        $sql = "SELECT id,action,entity_type,entity_id,actor_id,created_at FROM `$table` WHERE unit_id=? AND ((entity_type='academy_event' AND entity_id=?) OR (entity_type IN ($typePlaceholders) AND metadata LIKE ?)) ORDER BY id DESC LIMIT 100";
        return $this->db->query($sql, array_merge([$this->unit_id, $eventId], $types, ['%"event_id":' . $eventId . '%']))->getResult();
    }

    private function emptyMetrics(): array { return ["categories" => 0, "matches" => 0, "called" => 0, "confirmed" => 0, "paid" => 0, "pending_confirmations" => 0, "pending_evaluations" => 0, "expected_amount" => "0.00", "received_amount" => "0.00", "open_amount" => "0.00", "overdue_amount" => "0.00", "pending_payments" => 0]; }
    private function assertEvent(int $id): object { $event = $this->events->get_scoped($id, $this->unit_id); if (!$event) throw new \DomainException("gd_record_not_found"); return $event; }
    private function assertCategory(int $eventId, int $categoryId): object
    {
        $category = $this->db->table($this->table("gd_academy_event_categories"))
            ->where("id", $categoryId)->where("unit_id", $this->unit_id)->where("event_id", $eventId)->where("deleted", 0)->get(1)->getRow();
        if (!$category) throw new \DomainException("gd_record_not_found");
        return $category;
    }
    private function scopedRow(string $table, int $id): ?object { return $this->db->table($table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow(); }
    private function table(string $name): string { return $this->db->prefixTable($name); }
    private function date($value, bool $allowFuture): ?string { return $this->valid_date($value, $allowFuture); }
    private function time($value): ?string { $value = trim((string) $value); if ($value === "") return null; if (!preg_match("/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/", $value)) throw new \DomainException("gd_invalid_time"); return strlen($value) === 5 ? $value . ":00" : $value; }
    private function age($birth): ?int { if (!$birth) return null; try { return (int) (new \DateTimeImmutable((string) $birth))->diff(new \DateTimeImmutable("today"))->y; } catch (\Throwable $e) { return null; } }
    private function optionalInt($value, int $min, int $max): ?int { if ($value === null || trim((string) $value) === "") return null; if (!is_numeric($value) || (int) $value < $min || (int) $value > $max) throw new \DomainException("gd_invalid_value"); return (int) $value; }
    private function nonNegativeInt($value): int { return $this->optionalInt($value, 0, 100000) ?? 0; }
    private function legacyResponsible(int $id): ?object { if ($id <= 0) return null; return $this->db->table($this->table("grupo_donato_responsaveis"))->where("id", $id)->where("deleted", 0)->get(1)->getRow(); }
    private function participantName(object $participant): string { if (($participant->athlete_type ?? "") === "external") { if (!empty($participant->external_name) || !empty($participant->name)) return (string) ($participant->external_name ?? $participant->name); $row = $this->scopedRow($this->table("gd_academy_external_athletes"), (int) ($participant->external_athlete_id ?? 0)); return (string) ($row->name ?? "Atleta externo"); } $row = $this->db->table($this->table("grupo_donato_alunos"))->where("id", (int) ($participant->student_id ?? 0))->where("unidade_id", $this->legacy_unit_id)->get(1)->getRow(); return (string) ($row->nome_aluno ?? "Aluno"); }
    private function optionalCatalogId(string $name, int $id): ?int { if ($id <= 0) return null; $table = $this->table($name); $row = $this->db->table($table)->where("id", $id)->where("deleted", 0)->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()->get(1)->getRow(); if (!$row) throw new \DomainException("gd_invalid_catalog_link"); return $id; }
    private function ensureFamilyAccount(int $responsibleId): int
    {
        $responsible = $this->legacyResponsible($responsibleId); if (!$responsible) throw new \DomainException("gd_event_responsible_required"); $accounts = $this->table("gd_customer_accounts"); $existing = $this->db->table($accounts)->where("unit_id", $this->unit_id)->where("legacy_responsible_id", $responsibleId)->where("deleted", 0)->get(1)->getRow(); if ($existing) return (int) $existing->id;
        $normalized = DataNormalizationService::name($responsible->nome); $same = $this->db->table($accounts)->where("unit_id", $this->unit_id)->where("normalized_name", $normalized)->where("account_type", "family")->where("deleted", 0)->get(1)->getRow(); $accountId = (int) ($same->id ?? 0); $now = gmdate("Y-m-d H:i:s"); if (!$accountId) { $this->db->table($accounts)->insert(["unit_id" => $this->unit_id, "account_type" => "family", "display_name" => $responsible->nome, "normalized_name" => $normalized, "document_type" => "none", "phone" => $responsible->celular ?: ($responsible->whats ?? null), "phone_normalized" => DataNormalizationService::contact($responsible->celular ?: ($responsible->whats ?? ""), "phone") ?: null, "whatsapp" => $responsible->whats ?? null, "whatsapp_normalized" => DataNormalizationService::contact($responsible->whats ?? "", "whatsapp") ?: null, "email" => $responsible->email ?? null, "email_normalized" => $responsible->email ? mb_strtolower($responsible->email) : null, "legacy_responsible_id" => $responsibleId, "status" => "active", "created_at" => $now, "updated_at" => $now, "created_by" => $this->actor_id ?: null, "updated_by" => $this->actor_id ?: null, "deleted" => 0]); $accountId = (int) $this->db->insertID(); } else { $this->db->table($accounts)->where("id", $accountId)->update(["legacy_responsible_id" => $responsibleId, "updated_at" => $now, "updated_by" => $this->actor_id ?: null]); }
        if (!$accountId) throw new \RuntimeException("gd_finance_account_required"); return $accountId;
    }
    private function defaultFinancialAccount(): int { $row = $this->db->table($this->table("gd_financial_accounts"))->where("unit_id", $this->unit_id)->where("status", "active")->where("deleted", 0)->orderBy("id", "ASC")->get(1)->getRow(); if (!$row) throw new \DomainException("gd_finance_account_required"); return (int) $row->id; }
    private function pendingForEvent(int $eventId): bool { $p = $this->table("gd_academy_event_participants"); $e = $this->table("gd_academy_athlete_evaluations"); $m = $this->table("gd_academy_event_matches"); $c = $this->table("gd_academy_event_checklist"); $row = $this->db->query("SELECT (SELECT COUNT(*) FROM `$p` WHERE unit_id=? AND event_id=? AND deleted=0 AND confirmation_status IN ('waiting','no_response','pending')) confirmations,(SELECT COUNT(*) FROM `$p` pp LEFT JOIN `$e` ee ON ee.participant_id=pp.id AND ee.unit_id=pp.unit_id AND ee.deleted=0 WHERE pp.unit_id=? AND pp.event_id=? AND pp.deleted=0 AND pp.lineup_status NOT IN ('cut','absent') AND ee.id IS NULL) evaluations,(SELECT COUNT(*) FROM `$m` WHERE unit_id=? AND event_id=? AND deleted=0 AND status NOT IN ('cancelled') AND (gd_score IS NULL OR opponent_score IS NULL)) results,(SELECT COUNT(*) FROM `$c` WHERE unit_id=? AND event_id=? AND deleted=0 AND completed_at IS NULL) checklist", [$this->unit_id,$eventId,$this->unit_id,$eventId,$this->unit_id,$eventId,$this->unit_id,$eventId])->getRow(); return (int) ($row->confirmations ?? 0) > 0 || (int) ($row->evaluations ?? 0) > 0 || (int) ($row->results ?? 0) > 0 || (int) ($row->checklist ?? 0) > 0; }
}
