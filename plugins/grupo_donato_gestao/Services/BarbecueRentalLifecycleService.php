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
 * Suspender preserva a série e suas reservas conforme a política operacional.
 * Cancelar encerra definitivamente a série e libera as ocorrências futuras,
 * preservando o histórico passado. Não gera multa nem crédito.
 */
final class BarbecueRentalLifecycleService extends CustomerDataService
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
        $this->rentals = model("grupo_donato_gestao\\Models\\Gd_barbecue_rentals_model");
        $this->links = model("grupo_donato_gestao\\Models\\Gd_barbecue_rental_schedule_links_model");
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
            $count = $this->db->table($this->db->prefixTable("gd_barbecue_rental_schedule_links"))->where("rental_id", $rental->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("link_kind !=", "historical")->countAllResults();
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
                if (!$allow_value_waiver || $reason === "") { throw new \DomainException("gd_barbecue_rental_value_required"); }
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

    public function cancel(int $id, int $lock_version, string $reason, string $future_policy = "cancel"): object
    {
        $reason = trim(strip_tags($reason));
        if ($reason === "") { throw new \DomainException("gd_cancellation_reason_required"); }

        // O quarto parâmetro é mantido apenas para compatibilidade com clientes
        // antigos. Cancelamento comercial sempre encerra a série e cancela todas
        // as reservas futuras; a escolha técnica não é mais uma opção de usuário.
        $lock = new BarbecueRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $before = $this->rentals->get_scoped($id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if (!in_array("cancelled", self::ALLOWED[(string) $before->status] ?? [], true)) { throw new \DomainException("gd_invalid_court_rental_transition"); }
            if ((int) $before->lock_version !== $lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }

            $targets = $this->futureTargets($id);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental cancellation transaction"); }
            $in_tx = true;
            $cancelled_count = $this->cancelLinkedFuture($targets, $reason);
            $now = gmdate("Y-m-d H:i:s");
            $payload = [
                "rental_id" => $id,
                "old_state" => (string) $before->status,
                "new_state" => "cancelled",
                "actor_id" => $this->actor_id ?: null,
                "cancelled_at" => $now,
                "future_occurrences_count" => $cancelled_count,
                "series_ids" => array_keys($targets["series"]),
                "booking_ids" => $targets["bookings"],
            ];
            $columns = [
                "status" => "cancelled", "updated_by" => $this->actor_id ?: null,
                "cancelled_at" => $now, "cancelled_by" => $this->actor_id ?: null,
                "cancellation_reason" => mb_substr($reason, 0, 255),
            ];
            if (!$this->rentals->optimistic_update($id, $this->unit_id, $lock_version, $columns)) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, "cancelled", (string) $before->status, "cancelled", $reason, $payload);
            $this->audit_change("barbecue_rental_cancelled", "barbecue_rental", $id, ["status" => $before->status], ["status" => "cancelled"], $payload + ["reason" => $reason]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental cancellation commit"); }
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
     * @param callable(object):array $extra recebe a locação atual e devolve colunas
     *        adicionais a gravar; chaves "_event_reason"/"_event_payload" são extraídas.
     */
    private function transition(int $id, string $to, int $lock_version, ?string $reason = null, ?callable $extra = null): object
    {
        $lock = new BarbecueRentalLockService();
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
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental transition transaction"); }
            $in_tx = true;
            if (!$this->rentals->optimistic_update($id, $this->unit_id, $lock_version, $columns)) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            $event = ["active" => $before->status === "suspended" ? "resumed" : "activated", "suspended" => "suspended", "completed" => "completed", "cancelled" => "cancelled", "archived" => "updated"][$to];
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, $event, (string) $before->status, $to, $event_reason, $event_payload);
            $this->audit_change("barbecue_rental_" . $event, "barbecue_rental", $id, ["status" => $before->status], ["status" => $to], ["reason" => $event_reason] + $event_payload);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental transition commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        return $this->rentals->get_scoped($id, $this->unit_id);
    }

    /** Trata as ocorrências futuras ao suspender, mantendo a compatibilidade legada. */
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
                    catch (\Throwable $e) { log_message("error", "GD barbecue rental pause series: " . $e->getMessage()); }
                }
                if ($policy === "cancel") {
                    (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->cancelFuture($sid, $today, $reason);
                }
            } elseif ($link->booking_id && $policy === "cancel") {
                $sid = (int) $link->booking_id;
                $b = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,status,starts_at_utc")->where("id", $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
                if ($b && in_array((string) $b->status, Constants::BOOKING_EDITABLE_STATUSES, true) && (string) $b->starts_at_utc >= gmdate("Y-m-d H:i:s")) {
                    try { (new BookingLifecycleService($this->unit_id, $this->actor_id, $this->login_user))->cancel($sid, $reason); }
                    catch (\Throwable $e) { log_message("error", "GD barbecue rental cancel booking: " . $e->getMessage()); }
                }
            }
        }
    }

    /** @return array{series:array<int,array<int>>,bookings:array<int>} */
    private function futureTargets(int $rental_id): array
    {
        $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
        $now = gmdate("Y-m-d H:i:s");
        $series = [];
        $bookings = [];
        foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
            if ((string) $link->link_kind === "historical") { continue; }
            if ((int) ($link->booking_series_id ?? 0) > 0) {
                $sid = (int) $link->booking_series_id;
                $rows = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id")
                    ->where("unit_id", $this->unit_id)->where("series_id", $sid)->where("deleted", 0)
                    ->where("series_local_date >=", $today)
                    ->where("starts_at_utc >", $now)->whereIn("status", Constants::BOOKING_BLOCKING_STATUSES)
                    ->get()->getResult();
                $series[$sid] = array_map(static fn($row): int => (int) $row->id, $rows);
            } elseif ((int) ($link->booking_id ?? 0) > 0) {
                $bid = (int) $link->booking_id;
                $row = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,status,starts_at_utc")
                    ->where("id", $bid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
                if ($row && (string) $row->starts_at_utc > $now && in_array((string) $row->status, Constants::BOOKING_BLOCKING_STATUSES, true)) { $bookings[] = $bid; }
            }
        }
        return ["series" => $series, "bookings" => array_values(array_unique($bookings))];
    }

    /** Cancela os alvos já materializados e retorna quantos mudaram de estado. */
    private function cancelLinkedFuture(array $targets, string $reason): int
    {
        $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
        $occurrences = new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user);
        $seriesLifecycle = new BookingSeriesLifecycleService($this->unit_id, $this->actor_id, $this->login_user);
        foreach ($targets["series"] as $sid => $booking_ids) {
            $series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,status,lock_version")
                ->where("id", (int) $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$series) { continue; }
            if (in_array((string) $series->status, ["active", "paused"], true)) {
                $seriesLifecycle->cancel((int) $sid, (int) $series->lock_version, $reason);
                // Uma ocorrência editada individualmente continua relacionada ao
                // contrato, embora não seja mais regenerável pela série.
                $occurrences->cancelFuture((int) $sid, $today, $reason, false, true);
            } else {
                // Corrige também séries legadas já encerradas que ainda tenham
                // uma reserva futura editável vinculada.
                $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
                $occurrences->cancelFuture((int) $sid, $today, $reason, false, true);
            }
        }
        $bookingLifecycle = new BookingLifecycleService($this->unit_id, $this->actor_id, $this->login_user);
        foreach ($targets["bookings"] as $bid) {
            $booking = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,status,starts_at_utc")
                ->where("id", (int) $bid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if ($booking && (string) $booking->starts_at_utc > gmdate("Y-m-d H:i:s") && in_array((string) $booking->status, Constants::BOOKING_BLOCKING_STATUSES, true)) {
                $bookingLifecycle->cancel((int) $bid, $reason);
            }
        }
        $ids = $targets["bookings"];
        foreach ($targets["series"] as $seriesBookingIds) { $ids = array_merge($ids, $seriesBookingIds); }
        if (!$ids) { return 0; }
        $rows = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,status")->where("unit_id", $this->unit_id)->whereIn("id", array_values(array_unique($ids)))->where("status", "cancelled")->get()->getResult();
        return count($rows);
    }

    private function resumeLinkedSeries(int $rental_id): void
    {
        foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
            if ((string) $link->link_kind === "historical" || !$link->booking_series_id) { continue; }
            $sid = (int) $link->booking_series_id;
            $series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,status,lock_version")->where("id", $sid)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if ($series && (string) $series->status === "paused") {
                try { (new BookingSeriesLifecycleService($this->unit_id, $this->actor_id, $this->login_user))->resume($sid, (int) $series->lock_version); }
                catch (\Throwable $e) { log_message("error", "GD barbecue rental resume series: " . $e->getMessage()); }
            }
        }
    }
}
