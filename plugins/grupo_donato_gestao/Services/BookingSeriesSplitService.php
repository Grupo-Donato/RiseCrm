<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class BookingSeriesSplitService extends CustomerDataService
{
    private $series;
    private ?object $login_user;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->series = model("grupo_donato_gestao\\Models\\Gd_booking_series_model");
        $this->login_user = $login_user;
    }

    public function split(int $series_id, string $from_local_date, array $changes): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_local_date)) { throw new \DomainException("gd_invalid_date"); }
        $existing = $this->db->table($this->db->prefixTable("gd_booking_series_exceptions"))->select("replacement_series_id")->where("unit_id", $this->unit_id)->where("series_id", $series_id)->where("occurrence_key", $from_local_date)->where("exception_type", "split")->get(1)->getRow();
        if ($existing && (int) $existing->replacement_series_id) { return ["old_series_id" => $series_id, "new_series_id" => (int) $existing->replacement_series_id, "idempotent" => true]; }
        $lock = new BookingSeriesLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $series_id);
            $existing = $this->db->table($this->db->prefixTable("gd_booking_series_exceptions"))->select("replacement_series_id")->where("unit_id", $this->unit_id)->where("series_id", $series_id)->where("occurrence_key", $from_local_date)->where("exception_type", "split")->get(1)->getRow();
            if ($existing && (int) $existing->replacement_series_id) { return ["old_series_id" => $series_id, "new_series_id" => (int) $existing->replacement_series_id, "idempotent" => true]; }
            $old = $this->series->get_scoped($series_id, $this->unit_id);
            if (!$old) { throw new \DomainException("gd_booking_series_not_found"); }
            if (!in_array((string) $old->status, ["active", "paused"], true) || $from_local_date < (string) $old->starts_on) { throw new \DomainException("gd_booking_series_not_editable"); }
            $service = new BookingSeriesService($this->unit_id, $this->actor_id, $this->login_user);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("series split transaction"); }
            $in_tx = true;
            $input = array_replace($service->inputFrom($old), $changes);
            $input["starts_on"] = $from_local_date;
            unset($input["lock_version"]);
            $new = $service->create($input, false);
            $day_before = (new \DateTimeImmutable($from_local_date))->modify("-1 day")->format("Y-m-d");
            $old_data = ["status" => $from_local_date === (string) $old->starts_on ? "completed" : $old->status, "ends_mode" => "until_date", "ends_on" => $day_before, "max_occurrences" => null, "updated_by" => $this->actor_id ?: null];
            if (!$this->series->optimistic_update($series_id, $this->unit_id, (int) $old->lock_version, $old_data)) { throw new \DomainException("gd_booking_series_edit_conflict"); }
            $cancelled = (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->cancelFuture($series_id, $from_local_date, "Série dividida", true);
            (new BookingSeriesExceptionService($this->unit_id, $this->actor_id, $this->login_user))->append($series_id, $from_local_date, $from_local_date, "split", null, null, ["cancelled_booking_ids" => $cancelled], (int) $new["id"]);
            (new BookingSeriesEventService($this->unit_id, $this->actor_id, $this->login_user))->append($series_id, "split", (string) $old->status, (string) $old_data["status"], null, ["from_local_date" => $from_local_date, "replacement_series_id" => (int) $new["id"]]);
            $this->audit_change("booking_series_split", "booking_series", $series_id, (array) $old, $old_data, ["replacement_series_id" => (int) $new["id"], "from_local_date" => $from_local_date]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("series split commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        $generation = null;
        $new_series = $this->series->get_scoped((int) $new["id"], $this->unit_id);
        if ($new_series && (string) $new_series->status === "active") { $generation = (new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id, $this->login_user))->generate((int) $new["id"]); }
        return ["old_series_id" => $series_id, "new_series_id" => (int) $new["id"], "idempotent" => false, "generation" => $generation];
    }
}
