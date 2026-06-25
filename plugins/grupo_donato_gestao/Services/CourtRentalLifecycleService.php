<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Ciclo de vida da locação comercial (Fase 3C).
 *
 * Transições: draft→active|cancelled; active→suspended|cancelled|completed;
 * suspended→active|cancelled; completed/cancelled→archived (terminais).
 *
 * Suspender/cancelar NÃO apaga série ou reservas; impede geração futura adicional
 * da série (pausa) e aplica uma política EXPLÍCITA às ocorrências futuras
 * (keep|cancel|pause_series), sempre auditada. Não gera multa nem crédito.
 */
final class CourtRentalLifecycleService extends CustomerDataService
{
    private $rentals;
    private $links;
    private ?object $login_user;

    private const ALLOWED = [
        "draft" => ["active", "cancelled"],
        "active" => ["suspended", "cancelled", "completed"],
        "suspended" => ["active", "cancelled"],
        "completed" => ["archived"],
        "cancelled" => ["archived"],
    ];

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->rentals = model("grupo_donato_gestao\\Models\\Gd_court_rentals_model");
        $this->links = model("grupo_donato_gestao\\Models\\Gd_court_rental_schedule_links_model");
        $this->login_user = $login_user;
    }

    public function activate(int $id, int $lock_version, bool $allow_value_waiver = false, string $justification = ""): object
    {
        return $this->transition($id, "active", $lock_version, null, function (object $rental) use ($allow_value_waiver, $justification): array {
            // Conta ainda válida.
            if ($this->db->table($this->db->prefixTable("gd_customer_accounts"))->where("id", $rental->customer_account_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->countAllResults() !== 1) {
                throw new \DomainException("gd_court_rental_invalid_customer");
            }
            // Ao menos um vínculo operacional ativo.
            $count = $this->db->table($this->db->prefixTable("gd_court_rental_schedule_links"))->where("rental_id", $rental->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("link_kind !=", "historical")->countAllResults();
            if ($count < 1) { throw new \DomainException("gd_court_rental_activation_requires_link"); }
            // Consistência comercial: desconto não supera a base.
            $base = $rental->list_amount ?? $rental->negotiated_amount;
            if ($rental->discount_amount !== null && DataNormalizationService::decimalCompare((string) $rental->discount_amount, "0.00") > 0) {
                if ($base === null || DataNormalizationService::decimalCompare((string) $rental->discount_amount, (string) $base) > 0) { throw new \DomainException("gd_court_rental_discount_exceeds_base"); }
            }
            // Valor OU justificativa formal (com permissão de override).
            $justification = trim(strip_tags($justification));
            $has_value = $rental->negotiated_amount !== null || $rental->list_amount !== null;
            if (!$has_value) {
                $reason = $justification ?: trim((string) ($rental->discount_reason ?? "")) ?: trim((string) ($rental->commercial_notes ?? ""));
                if (!$allow_value_waiver || $reason === "") { throw new \DomainException("gd_court_rental_value_required"); }
                return ["activated_at" => gmdate("Y-m-d H:i:s"), "activated_by" => $this->actor_id ?: null, "_event_reason" => $reason, "_event_payload" => ["value_waived" => true]];
            }
            return ["activated_at" => gmdate("Y-m-d H:i:s"), "activated_by" => $this->actor_id ?: null];
        });
    }

    public function complete(int $id, int $lock_version): object
    {
        return $this->transition($id, "completed", $lock_version, null, fn(object $r): array => ["completed_at" => gmdate("Y-m-d H:i:s"), "completed_by" => $this->actor_id ?: null]);
    }

    public function archive(int $id, int $lock_version): object
    {
        return $this->transition($id, "archived", $lock_version);
    }

    public function suspend(int $id, int $lock_version, string $future_policy, string $reason = ""): object
    {
        if (!Constants::isCourtRentalFuturePolicy($future_policy)) { throw new \DomainException("gd_court_rental_future_policy_required"); }
        $reason = trim(strip_tags($reason));
        $this->transition($id, "suspended", $lock_version, $reason ?: null, fn(object $r): array => ["suspended_at" => gmdate("Y-m-d H:i:s"), "suspended_by" => $this->actor_id ?: null, "_event_payload" => ["future_policy" => $future_policy]]);
        $this->applyFuturePolicy($id, $future_policy, $reason ?: "Locação suspensa");
        return $this->rentals->get_scoped($id, $this->unit_id);
    }

    public function resume(int $id, int $lock_version): object
    {
        $this->transition($id, "active", $lock_version, null, fn(object $r): array => []);
        $this->resumeLinkedSeries($id);
        return $this->rentals->get_scoped($id, $this->unit_id);
    }

    public function cancel(int $id, int $lock_version, string $reason, string $future_policy): object
    {
        $reason = trim(strip_tags($reason));
        if ($reason === "") { throw new \DomainException("gd_cancellation_reason_required"); }
        if (!Constants::isCourtRentalFuturePolicy($future_policy)) { throw new \DomainException("gd_court_rental_future_policy_required"); }
        $this->transition($id, "cancelled", $lock_version, $reason, fn(object $r): array => ["cancelled_at" => gmdate("Y-m-d H:i:s"), "cancelled_by" => $this->actor_id ?: null, "cancellation_reason" => mb_substr($reason, 0, 255), "_event_payload" => ["future_policy" => $future_policy]]);
        $this->applyFuturePolicy($id, $future_policy, $reason);
        return $this->rentals->get_scoped($id, $this->unit_id);
    }

    /**
     * @param callable(object):array $extra recebe a locação atual e devolve colunas
     *        adicionais a gravar; chaves "_event_reason"/"_event_payload" são extraídas.
     */
    private function transition(int $id, string $to, int $lock_version, ?string $reason = null, ?callable $extra = null): object
    {
        $lock = new CourtRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $before = $this->rentals->get_scoped($id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if (!in_array($to, self::ALLOWED[(string) $before->status] ?? [], true)) { throw new \DomainException("gd_invalid_court_rental_transition"); }
            if ((int) $before->lock_version !== $lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            $columns = ["status" => $to, "updated_by" => $this->actor_id ?: null];
            $event_reason = $reason; $event_payload = [];
            if ($extra) {
                $more = $extra($before);
                $event_reason = $more["_event_reason"] ?? $event_reason;
                $event_payload = $more["_event_payload"] ?? [];
                unset($more["_event_reason"], $more["_event_payload"]);
                $columns += $more;
            }
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental transition transaction"); }
            $in_tx = true;
            if (!$this->rentals->optimistic_update($id, $this->unit_id, $lock_version, $columns)) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            $event = ["active" => $before->status === "suspended" ? "resumed" : "activated", "suspended" => "suspended", "completed" => "completed", "cancelled" => "cancelled", "archived" => "updated"][$to];
            (new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, $event, (string) $before->status, $to, $event_reason, $event_payload);
            $this->audit_change("court_rental_" . $event, "court_rental", $id, ["status" => $before->status], ["status" => $to], ["reason" => $event_reason] + $event_payload);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental transition commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        return $this->rentals->get_scoped($id, $this->unit_id);
    }

    /**
     * Trata as ocorrências futuras com a política explícita escolhida.
     *
     * A série vinculada é SEMPRE pausada (impede geração futura adicional, requisito
     * de suspensão/cancelamento). A política decide apenas o destino das ocorrências
     * já materializadas: "keep"/"pause_series" mantêm; "cancel" encerra as futuras.
     */
    private function applyFuturePolicy(int $rental_id, string $policy, string $reason): void
    {
        $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
        foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
            if ((string) $link->link_kind === "historical") { continue; }
            if ($link->booking_series_id) {
                $sid = (int) $link->booking_series_id;
                $series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,status,lock_version")->where("id", $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
                if ($series && (string) $series->status === "active") {
                    try { (new BookingSeriesLifecycleService($this->unit_id, $this->actor_id, $this->login_user))->pause($sid, (int) $series->lock_version); }
                    catch (\Throwable $e) { log_message("error", "GD court rental pause series: " . $e->getMessage()); }
                }
                if ($policy === "cancel") {
                    (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->cancelFuture($sid, $today, $reason);
                }
            } elseif ($link->booking_id && $policy === "cancel") {
                $sid = (int) $link->booking_id;
                $b = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,status,starts_at_utc")->where("id", $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
                if ($b && in_array((string) $b->status, Constants::BOOKING_EDITABLE_STATUSES, true) && (string) $b->starts_at_utc >= gmdate("Y-m-d H:i:s")) {
                    try { (new BookingLifecycleService($this->unit_id, $this->actor_id, $this->login_user))->cancel($sid, $reason); }
                    catch (\Throwable $e) { log_message("error", "GD court rental cancel booking: " . $e->getMessage()); }
                }
            }
        }
    }

    private function resumeLinkedSeries(int $rental_id): void
    {
        foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
            if ((string) $link->link_kind === "historical" || !$link->booking_series_id) { continue; }
            $sid = (int) $link->booking_series_id;
            $series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,status,lock_version")->where("id", $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if ($series && (string) $series->status === "paused") {
                try { (new BookingSeriesLifecycleService($this->unit_id, $this->actor_id, $this->login_user))->resume($sid, (int) $series->lock_version); }
                catch (\Throwable $e) { log_message("error", "GD court rental resume series: " . $e->getMessage()); }
            }
        }
    }
}
