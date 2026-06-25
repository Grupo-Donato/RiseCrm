<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class BookingSeriesLifecycleService extends CustomerDataService
{
    private $series;
    private ?object $login_user;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->series = model("grupo_donato_gestao\\Models\\Gd_booking_series_model");
        $this->login_user = $login_user;
    }

    public function pause(int $id, int $lock_version): object { return $this->transition($id, "paused", $lock_version); }
    public function resume(int $id, int $lock_version): object
    {
        $row = $this->transition($id, "active", $lock_version);
        (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->generate($id);
        return $this->series->get_scoped($id, $this->unit_id);
    }
    public function complete(int $id, int $lock_version, string $reason): object { return $this->transition($id, "completed", $lock_version, $reason, true); }
    public function cancel(int $id, int $lock_version, string $reason): object
    {
        $reason = trim(strip_tags($reason));
        if ($reason === "") { throw new \DomainException("gd_cancellation_reason_required"); }
        return $this->transition($id, "cancelled", $lock_version, $reason, true);
    }

    public function cancelFrom(int $id, int $lock_version, string $from_local_date, string $reason): object
    {
        $reason = trim(strip_tags($reason));
        if ($reason === "") { throw new \DomainException("gd_cancellation_reason_required"); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_local_date)) { throw new \DomainException("gd_invalid_date"); }
        $lock = new BookingSeriesLockService();
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $before = $this->series->get_scoped($id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_booking_series_not_found"); }
            if (!in_array((string) $before->status, ["active", "paused"], true) || (int) $before->lock_version !== $lock_version || $from_local_date < (string) $before->starts_on) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            $day_before = (new \DateTimeImmutable($from_local_date))->modify("-1 day")->format("Y-m-d");
            $to_status = $from_local_date === (string) $before->starts_on ? "completed" : (string) $before->status;
            if (!$this->series->optimistic_update($id, $this->unit_id, $lock_version, ["ends_mode" => "until_date", "ends_on" => $day_before, "max_occurrences" => null, "status" => $to_status, "updated_by" => $this->actor_id ?: null])) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            (new BookingSeriesEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, "updated", (string) $before->status, $to_status, $reason, ["scope" => "this_and_future", "from_local_date" => $from_local_date]);
        } finally { $lock->release(); }
        (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->cancelFuture($id, $from_local_date, $reason);
        return $this->series->get_scoped($id, $this->unit_id);
    }

    private function transition(int $id, string $to, int $lock_version, ?string $reason = null, bool $cancel_future = false): object
    {
        $lock = new BookingSeriesLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $before = $this->series->get_scoped($id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_booking_series_not_found"); }
            $allowed = ["active" => ["paused", "completed", "cancelled"], "paused" => ["active", "completed", "cancelled"], "completed" => ["archived"], "cancelled" => ["archived"]];
            if (!in_array($to, $allowed[(string) $before->status] ?? [], true)) { throw new \DomainException("gd_invalid_booking_series_transition"); }
            if ((int) $before->lock_version !== $lock_version) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            if ($this->db->transBegin() === false) { throw new \RuntimeException("series lifecycle transaction"); }
            $in_tx = true;
            if (!$this->series->optimistic_update($id, $this->unit_id, $lock_version, ["status" => $to, "updated_by" => $this->actor_id ?: null])) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            $event = ["paused" => "paused", "active" => "resumed", "completed" => "completed", "cancelled" => "cancelled", "archived" => "archived"][$to];
            (new BookingSeriesEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, $event, (string) $before->status, $to, $reason);
            $this->audit_change("booking_series_" . $event, "booking_series", $id, ["status" => $before->status], ["status" => $to], ["reason" => $reason]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("series lifecycle commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        if ($cancel_future) {
            $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
            (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->cancelFuture($id, $today, $reason ?: "Série encerrada");
        }
        return $this->series->get_scoped($id, $this->unit_id);
    }
}
