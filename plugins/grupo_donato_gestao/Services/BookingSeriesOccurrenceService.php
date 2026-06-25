<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

final class BookingSeriesOccurrenceService extends CustomerDataService
{
    private $series;
    private $series_resources;
    private $bookings;
    private RecurrenceGeneratorService $generator;
    private BookingService $booking_service;
    private BookingSeriesExceptionService $exceptions;
    private BookingSeriesEventService $events;
    private ?object $login_user;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->series = model("grupo_donato_gestao\\Models\\Gd_booking_series_model");
        $this->series_resources = model("grupo_donato_gestao\\Models\\Gd_booking_series_resources_model");
        $this->bookings = model("grupo_donato_gestao\\Models\\Gd_bookings_model");
        $this->generator = new RecurrenceGeneratorService($unit_id, $actor_id, $login_user);
        $this->booking_service = new BookingService($unit_id, $actor_id, $login_user);
        $this->exceptions = new BookingSeriesExceptionService($unit_id, $actor_id, $login_user);
        $this->events = new BookingSeriesEventService($unit_id, $actor_id, $login_user);
        $this->login_user = $login_user;
    }

    public function preview($definition, array $resources): array
    {
        $out = [];
        foreach ($this->generator->candidates($definition) as $candidate) {
            $candidate["available"] = false;
            $candidate["reason"] = null;
            try {
                $check = $this->booking_service->checkAvailability($this->bookingInput($definition, $candidate, $resources));
                $candidate["available"] = (bool) $check["available"];
                $candidate["conflicts"] = $check["conflicts"];
                if (!$candidate["available"]) { $candidate["reason"] = $check["conflicts"] ? "gd_booking_conflict" : "gd_booking_resource_unavailable"; }
            } catch (\Throwable $e) {
                $candidate["reason"] = str_starts_with($e->getMessage(), "gd_") ? $e->getMessage() : "gd_booking_resource_unavailable";
                $candidate["conflicts"] = [];
            }
            $out[] = $candidate;
        }
        return $out;
    }

    public function generate(int $series_id, ?string $through_date = null): array
    {
        $lock = new BookingSeriesLockService();
        $run_id = 0;
        try {
            $lock->acquire($this->unit_id, (string) $series_id);
            $series = $this->series->get_scoped($series_id, $this->unit_id);
            if (!$series) { throw new \DomainException("gd_booking_series_not_found"); }
            if ((string) $series->status !== "active") { throw new \DomainException("gd_booking_series_not_active"); }
            $resources = $this->resourcePayload($series_id);
            $candidates = $this->generator->candidates($series, $through_date);
            $existing = $this->existingKeys($series_id);
            $blocked = $this->blockedKeys($series_id);
            $pending = [];
            $idempotent = 0;
            foreach ($candidates as $candidate) {
                $key = $candidate["occurrence_key"];
                if (isset($existing[$key]) || isset($blocked[$key])) { $idempotent++; continue; }
                $pending[] = $candidate;
            }
            $result = ["series_id" => $series_id, "created" => 0, "idempotent" => $idempotent, "skipped" => 0, "conflicts" => [], "booking_ids" => []];
            $runs = $this->db->prefixTable("gd_booking_series_generation_runs");
            $this->db->table($runs)->insert(["unit_id" => $this->unit_id, "series_id" => $series_id, "conflict_policy" => (string) $series->conflict_policy, "status" => "running", "request_id" => AuditService::request_id(), "started_at" => gmdate("Y-m-d H:i:s"), "created_by" => $this->actor_id ?: null]);
            $run_id = (int) $this->db->insertID();
            if (!$pending) { $this->finishRun($run_id, "completed", $result); return $result; }

            if ((string) $series->conflict_policy === "reject_series") {
                $result = $this->generateAtomic($series, $resources, $pending, $result);
            } else {
                $result = $this->generateSkipping($series, $resources, $pending, $result);
            }
            $last = end($candidates);
            if ($last) {
                $this->db->table($this->db->prefixTable("gd_booking_series"))->where("id", $series_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->update([
                    "last_generated_until" => $last["local_date"],
                    "updated_at" => gmdate("Y-m-d H:i:s"),
                    "updated_by" => $this->actor_id ?: null,
                    "lock_version" => (int) $series->lock_version + 1,
                ]);
            }
            $this->events->append($series_id, "generated", (string) $series->status, (string) $series->status, null, $result);
            $this->audit_change("booking_series_generated", "booking_series", $series_id, null, ["created" => $result["created"], "skipped" => $result["skipped"]], ["through" => $last["local_date"] ?? null]);
            $this->finishRun($run_id, "completed", $result);
            return $result;
        } catch (\Throwable $e) {
            if ($run_id > 0) { $this->finishRun($run_id, "failed", ["created" => 0, "idempotent" => 0, "skipped" => 0], $e->getMessage()); }
            throw $e;
        } finally {
            $lock->release();
        }
    }

    private function finishRun(int $run_id, string $status, array $result, ?string $error = null): void
    {
        if ($run_id <= 0) { return; }
        $this->db->table($this->db->prefixTable("gd_booking_series_generation_runs"))->where("id", $run_id)->where("unit_id", $this->unit_id)->update([
            "status" => $status,
            "created_count" => (int) ($result["created"] ?? 0),
            "idempotent_count" => (int) ($result["idempotent"] ?? 0),
            "skipped_count" => (int) ($result["skipped"] ?? 0),
            "error_code" => $error && str_starts_with($error, "gd_") ? mb_substr($error, 0, 80) : null,
            "completed_at" => gmdate("Y-m-d H:i:s"),
        ]);
    }

    public function updateSingle(int $booking_id, array $input): array
    {
        $booking = $this->seriesBooking($booking_id);
        if ((string) $booking->starts_at_utc <= gmdate("Y-m-d H:i:s") || !in_array((string) $booking->status, Constants::BOOKING_EDITABLE_STATUSES, true)) { throw new \DomainException("gd_booking_not_editable"); }
        $result = $this->booking_service->save($input, $booking_id, true);
        $this->db->table($this->db->prefixTable("gd_bookings"))->where("id", $booking_id)->where("unit_id", $this->unit_id)->update(["is_series_exception" => 1, "detached_from_series" => 1]);
        $this->exceptions->append((int) $booking->series_id, (string) $booking->series_occurrence_key, (string) $booking->series_local_date, "detach", $booking_id, null, ["operation" => "single_update"]);
        $this->events->append((int) $booking->series_id, "occurrence_updated", null, null, null, ["booking_id" => $booking_id, "occurrence_key" => $booking->series_occurrence_key]);
        $this->audit_change("booking_series_occurrence_detached", "booking", $booking_id, (array) $booking, ["detached_from_series" => 1], ["series_id" => (int) $booking->series_id]);
        return $result;
    }

    public function cancelSingle(int $booking_id, string $reason): object
    {
        $booking = $this->seriesBooking($booking_id);
        $lifecycle = new BookingLifecycleService($this->unit_id, $this->actor_id, $this->login_user);
        $updated = $lifecycle->cancel($booking_id, $reason);
        $this->db->table($this->db->prefixTable("gd_bookings"))->where("id", $booking_id)->where("unit_id", $this->unit_id)->update(["is_series_exception" => 1]);
        $this->exceptions->append((int) $booking->series_id, (string) $booking->series_occurrence_key, (string) $booking->series_local_date, "cancel", $booking_id, $reason);
        $this->events->append((int) $booking->series_id, "occurrence_cancelled", null, null, $reason, ["booking_id" => $booking_id, "occurrence_key" => $booking->series_occurrence_key]);
        return $updated;
    }

    public function cancelFuture(int $series_id, string $from_local_date, string $reason, bool $detach_for_regeneration = false): array
    {
        $series = $this->series->get_scoped($series_id, $this->unit_id);
        if (!$series) { throw new \DomainException("gd_booking_series_not_found"); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_local_date)) { throw new \DomainException("gd_invalid_date"); }
        $rows = $this->db->table($this->db->prefixTable("gd_bookings"))->where("unit_id", $this->unit_id)->where("series_id", $series_id)->where("deleted", 0)->where("detached_from_series", 0)->where("series_local_date >=", $from_local_date)->where("starts_at_utc >", gmdate("Y-m-d H:i:s"))->whereIn("status", Constants::BOOKING_EDITABLE_STATUSES)->orderBy("series_local_date")->get()->getResult();
        $cancelled = [];
        $lifecycle = new BookingLifecycleService($this->unit_id, $this->actor_id, $this->login_user);
        foreach ($rows as $booking) {
            $lifecycle->cancel((int) $booking->id, $reason);
            $data = ["is_series_exception" => 1];
            if ($detach_for_regeneration) { $data += ["series_id" => null, "detached_from_series" => 1]; }
            $this->db->table($this->db->prefixTable("gd_bookings"))->where("id", (int) $booking->id)->where("unit_id", $this->unit_id)->update($data);
            $type = $detach_for_regeneration ? "override" : "cancel";
            $this->exceptions->append($series_id, (string) $booking->series_occurrence_key, (string) $booking->series_local_date, $type, (int) $booking->id, $reason, ["detached_for_regeneration" => $detach_for_regeneration]);
            $cancelled[] = (int) $booking->id;
        }
        return $cancelled;
    }

    private function generateAtomic(object $series, array $resources, array $pending, array $result): array
    {
        $resource_locks = new BookingResourceLockService();
        $in_tx = false;
        try {
            $resource_locks->acquire($this->unit_id, array_column($resources, "resource_id"));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("series generation transaction"); }
            $in_tx = true;
            foreach ($pending as $candidate) {
                $check = $this->booking_service->checkAvailability($this->bookingInput($series, $candidate, $resources));
                if (!$check["available"]) { throw new \DomainException($check["conflicts"] ? "gd_booking_conflict" : "gd_booking_resource_unavailable"); }
            }
            foreach ($pending as $candidate) {
                $saved = $this->booking_service->createSeriesOccurrence($this->bookingInput($series, $candidate, $resources), (int) $series->id, $candidate["occurrence_key"], $candidate["local_date"], true, true);
                if (!empty($saved["idempotent"])) { $result["idempotent"]++; }
                else { $result["created"]++; $result["booking_ids"][] = (int) $saved["id"]; }
            }
            if ($this->db->transCommit() === false) { throw new \RuntimeException("series generation commit"); }
            $in_tx = false;
            return $result;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $resource_locks->release();
        }
    }

    private function generateSkipping(object $series, array $resources, array $pending, array $result): array
    {
        foreach ($pending as $candidate) {
            try {
                $saved = $this->booking_service->createSeriesOccurrence($this->bookingInput($series, $candidate, $resources), (int) $series->id, $candidate["occurrence_key"], $candidate["local_date"]);
                if (!empty($saved["idempotent"])) { $result["idempotent"]++; }
                else { $result["created"]++; $result["booking_ids"][] = (int) $saved["id"]; }
            } catch (\Throwable $e) {
                if (!in_array($e->getMessage(), ["gd_booking_conflict", "gd_booking_duplicate", "gd_booking_resource_unavailable"], true)) { throw $e; }
                $result["skipped"]++;
                $result["conflicts"][] = ["occurrence_key" => $candidate["occurrence_key"], "reason" => $e->getMessage()];
                $this->exceptions->append((int) $series->id, $candidate["occurrence_key"], $candidate["local_date"], "conflict_skipped", null, $e->getMessage());
                $this->events->append((int) $series->id, "conflict_skipped", null, null, $e->getMessage(), ["occurrence_key" => $candidate["occurrence_key"]]);
            }
        }
        return $result;
    }

    private function bookingInput($series, array $candidate, array $resources): array
    {
        $s = is_object($series) ? get_object_vars($series) : $series;
        return [
            "booking_type" => $s["booking_type"], "title" => $s["title"],
            "customer_account_id" => $s["customer_account_id"] ?? null, "contact_person_id" => $s["contact_person_id"] ?? null,
            "starts_at_local" => $candidate["starts_at_local"], "ends_at_local" => $candidate["ends_at_local"],
            "status" => $s["default_booking_status"], "resources" => $resources,
            "notes" => $s["notes"] ?? null, "metadata" => $s["metadata"] ?? null,
        ];
    }

    private function resourcePayload(int $series_id): array
    {
        $rows = $this->series_resources->for_series($series_id, $this->unit_id);
        if (!$rows) { throw new \DomainException("gd_invalid_booking_resources"); }
        return array_map(static fn($row): array => ["resource_id" => (int) $row->resource_id, "buffer_before_minutes" => (int) $row->buffer_before_minutes, "buffer_after_minutes" => (int) $row->buffer_after_minutes], $rows);
    }

    private function existingKeys(int $series_id): array
    {
        $rows = $this->db->table($this->db->prefixTable("gd_bookings"))->select("series_occurrence_key,id")->where("unit_id", $this->unit_id)->where("series_id", $series_id)->where("deleted", 0)->get()->getResult();
        $out = []; foreach ($rows as $row) { $out[(string) $row->series_occurrence_key] = (int) $row->id; } return $out;
    }

    private function blockedKeys(int $series_id): array
    {
        $rows = $this->db->table($this->db->prefixTable("gd_booking_series_exceptions"))->select("occurrence_key")->where("unit_id", $this->unit_id)->where("series_id", $series_id)->whereIn("exception_type", ["skip", "cancel", "detach", "split"])->get()->getResult();
        $out = []; foreach ($rows as $row) { $out[(string) $row->occurrence_key] = true; } return $out;
    }

    private function seriesBooking(int $booking_id): object
    {
        $booking = $this->bookings->get_scoped($booking_id, $this->unit_id);
        if (!$booking || !(int) $booking->series_id || (int) $booking->detached_from_series) { throw new \DomainException("gd_booking_series_occurrence_not_found"); }
        return $booking;
    }
}
