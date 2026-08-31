<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Remanejamento de uma ocorrência de mensalista.
 *
 * A série e a cobrança não são alteradas. A ocorrência materializada continua
 * sendo o mesmo booking, mas recebe um snapshot de origem/destino na tabela de
 * exceções e fica destacada da regeneração da série. Assim o horário original
 * é liberado somente naquela data e pode ser revertido com segurança.
 */
final class MonthlyRescheduleService extends CustomerDataService
{
    private $bookings;
    private $booking_resources;
    private $exceptions;
    private ?object $login_user;
    private TemporalService $time;
    private AvailabilityService $availability;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->login_user = $login_user;
        $this->bookings = model("grupo_donato_gestao\\Models\\Gd_bookings_model");
        $this->booking_resources = model("grupo_donato_gestao\\Models\\Gd_booking_resources_model");
        $this->exceptions = model("grupo_donato_gestao\\Models\\Gd_booking_series_exceptions_model");
        $this->time = new TemporalService($unit_id);
        $this->availability = new AvailabilityService($unit_id);
    }

    /** @return array<string,mixed> */
    public function occurrence(int $booking_id): array
    {
        $booking = $this->loadMonthlyOccurrence($booking_id);
        $exception = $this->exceptions->active_reschedule((int) $booking->series_id, (string) $booking->series_occurrence_key, $this->unit_id);
        $current = $this->snapshotBooking($booking);
        $original = $exception ? $this->snapshotException($exception) : $current;
        return [
            "booking_id" => (int) $booking->id,
            "series_id" => (int) $booking->series_id,
            "rental_id" => (int) $booking->rental_id,
            "occurrence_date" => (string) $booking->series_local_date,
            "title" => (string) $booking->title,
            "lock_version" => (int) $booking->lock_version,
            "status" => (string) $booking->status,
            "original" => $original,
            "current" => $current,
            "has_reschedule" => (bool) $exception,
            "exception" => $exception ? (array) $exception : null,
        ];
    }

    /**
     * Ocorrências futuras que podem receber uma alteração pontual.
     *
     * O contrato mensalista continua sendo representado pela série; a tela
     * só precisa conhecer os bookings já materializados para permitir que o
     * operador escolha a data exata sem editar a série inteira.
     *
     * @return array<int,array<string,mixed>>
     */
    public function futureOccurrencesForRental(int $rental_id, int $limit = 120): array
    {
        $series_ids = $this->seriesIdsForRental($rental_id);
        if (!$series_ids) { return []; }

        $today = (new \DateTimeImmutable("now", new \DateTimeZone($this->time->timezoneName())))->format("Y-m-d");
        $rows = $this->db->table($this->db->prefixTable("gd_bookings"))
            ->select("id,series_id,series_occurrence_key,series_local_date,starts_at_utc,ends_at_utc,title,status,lock_version")
            ->where("unit_id", $this->unit_id)
            ->whereIn("series_id", $series_ids)
            ->where("series_local_date >=", $today)
            ->where("starts_at_utc >", gmdate("Y-m-d H:i:s"))
            ->whereIn("status", Constants::BOOKING_EDITABLE_STATUSES)
            ->where("deleted", 0)
            ->orderBy("series_local_date", "ASC")
            ->orderBy("starts_at_utc", "ASC")
            ->limit(max(1, min(240, $limit)))
            ->get()->getResult();

        $out = [];
        foreach ($rows as $booking) {
            $booking->resources = $this->booking_resources->for_booking((int) $booking->id, $this->unit_id);
            if (!$booking->resources) { continue; }
            $snapshot = $this->snapshotBooking($booking);
            $exception = $this->exceptions->active_reschedule((int) $booking->series_id, (string) $booking->series_occurrence_key, $this->unit_id);
            $out[] = [
                "booking_id" => (int) $booking->id,
                "series_id" => (int) $booking->series_id,
                "occurrence_date" => (string) $booking->series_local_date,
                "title" => (string) $booking->title,
                "status" => (string) $booking->status,
                "lock_version" => (int) $booking->lock_version,
                "resource_id" => (int) ($snapshot["resource_id"] ?? 0),
                "resource" => (string) ($snapshot["resource"] ?? "-"),
                "local_start_time" => (string) ($snapshot["local_start_time"] ?? ""),
                "local_end_time" => (string) ($snapshot["local_end_time"] ?? ""),
                "has_reschedule" => (bool) $exception,
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public function occurrenceForRentalDate(int $rental_id, string $date): array
    {
        $date = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \DomainException("gd_invalid_date");
        }
        $series_ids = $this->seriesIdsForRental($rental_id);
        if (!$series_ids) { throw new \DomainException("gd_monthly_rental_occurrence_required"); }
        $booking = $this->db->table($this->db->prefixTable("gd_bookings"))
            ->select("id")
            ->where("unit_id", $this->unit_id)
            ->whereIn("series_id", $series_ids)
            ->where("series_local_date", $date)
            ->where("deleted", 0)
            ->whereIn("status", Constants::BOOKING_EDITABLE_STATUSES)
            ->orderBy("starts_at_utc", "ASC")
            ->get(1)->getRow();
        if (!$booking) { throw new \DomainException("gd_booking_series_occurrence_not_found"); }
        return $this->occurrence((int) $booking->id);
    }

    /** @return array<string,mixed> */
    public function availableSlots(int $booking_id, array $input): array
    {
        $occurrence = $this->occurrence($booking_id);
        $date = trim((string) ($input["occurrence_date"] ?? $occurrence["occurrence_date"]));
        if ($date !== $occurrence["occurrence_date"]) { throw new \DomainException("gd_reschedule_date_mismatch"); }
        $duration = $this->durationMinutes($occurrence["current"]);
        $from = trim((string) ($input["from_time"] ?? ($occurrence["current"]["local_start_time"] ?? "")));
        $until = trim((string) ($input["until_time"] ?? "23:59"));
        $resource_id = (int) ($input["resource_id"] ?? 0);
        if ($resource_id > 0) { $this->assertCourtResource($resource_id); }
        $slots = $this->availability->findAvailableSlots($date, $from, $until, $duration, $resource_id > 0 ? [$resource_id] : [], $booking_id, 30);
        $now_utc = gmdate("Y-m-d H:i:s");
        $slots = array_values(array_filter($slots, static fn (array $slot): bool => substr((string) ($slot["starts_at_local"] ?? ""), 0, 10) === $date && substr((string) ($slot["ends_at_local"] ?? ""), 0, 10) === $date && (string) ($slot["starts_at_utc"] ?? "") > $now_utc));
        return ["occurrence" => $occurrence, "duration_minutes" => $duration, "slots" => $slots];
    }

    /**
     * Quadras para um novo intervalo escolhido pelo operador.
     *
     * O horário é recebido pronto e a duração vem da ocorrência original.
     * A janela consultada já inclui os buffers da reserva, exatamente como a
     * gravação definitiva em BookingService, mas exclui o próprio booking.
     * Assim a quadra atual continua disponível quando apenas o horário muda.
     *
     * @return array<string,mixed>
     */
    public function availableResourceOptions(int $booking_id, array $input): array
    {
        $booking = $this->loadMonthlyOccurrence($booking_id);
        $occurrence = $this->occurrence($booking_id);
        $date = trim((string) ($input["occurrence_date"] ?? $booking->series_local_date));
        if ($date !== (string) $booking->series_local_date) {
            throw new \DomainException("gd_reschedule_date_mismatch");
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new \DomainException("gd_invalid_date");
        }
        $parts = array_map("intval", explode("-", $date));
        if (count($parts) !== 3 || !checkdate($parts[1], $parts[2], $parts[0])) {
            throw new \DomainException("gd_invalid_date");
        }

        $duration = $this->durationMinutesFromBooking($booking);
        $start_time = substr(TemporalService::normalizeTime(trim((string) ($input["new_start_time"] ?? $input["start_time"] ?? ""))), 0, 5);
        $timezone = new \DateTimeZone($this->time->timezoneName());
        $start_local = new \DateTimeImmutable($date . " " . $start_time, $timezone);
        $end_local = $start_local->modify("+{$duration} minutes");
        if ($end_local->format("Y-m-d") !== $date) {
            throw new \DomainException("gd_reschedule_date_mismatch");
        }

        $start_utc = $this->time->localToUtc($date, $start_local->format("H:i:s"));
        $end_utc = $this->time->localToUtc($date, $end_local->format("H:i:s"));
        [$start_utc, $end_utc] = $this->time->validateRange($start_utc, $end_utc);
        if ($start_utc <= gmdate("Y-m-d H:i:s")) {
            throw new \DomainException("gd_booking_not_editable");
        }

        $booking_resource = $booking->resources[0] ?? null;
        $buffer_before = max(0, (int) ($booking_resource->buffer_before_minutes ?? 0));
        $buffer_after = max(0, (int) ($booking_resource->buffer_after_minutes ?? 0));
        $occupancy_start = $this->time->parseUtc($start_utc)->modify("-{$buffer_before} minutes")->format("Y-m-d H:i:s");
        $occupancy_end = $this->time->parseUtc($end_utc)->modify("+{$buffer_after} minutes")->format("Y-m-d H:i:s");

        $rows = $this->db->table($this->db->prefixTable("gd_resources"))
            ->select("id,code,name")
            ->where("unit_id", $this->unit_id)
            ->where("resource_type", Constants::COURT_RESOURCE_TYPE)
            ->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)
            ->orderBy("sort_order", "ASC")->orderBy("name", "ASC")
            ->get()->getResult();
        $resource_ids = array_map(static fn($row): int => (int) $row->id, $rows);
        $checks = $this->availability->checkMany($resource_ids, $occupancy_start, $occupancy_end, $booking_id);
        $current_resource_id = (int) ($booking_resource->resource_id ?? 0);
        $is_current_schedule = (string) $booking->starts_at_utc === $start_utc && (string) $booking->ends_at_utc === $end_utc;
        $resources = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $check = $checks[$id] ?? ["available" => false, "reason_code" => "resource_not_found"];
            $resources[] = [
                "id" => $id,
                "code" => (string) $row->code,
                "name" => (string) $row->name,
                "available" => ($check["available"] ?? false) === true,
                "reason_code" => (string) ($check["reason_code"] ?? "resource_unavailable"),
                "is_current" => $is_current_schedule && $id === $current_resource_id,
            ];
        }

        return [
            "occurrence" => $occurrence,
            "duration_minutes" => $duration,
            "schedule" => [
                "date" => $date,
                "start_time" => $start_local->format("H:i"),
                "end_time" => $end_local->format("H:i"),
                "starts_at_utc" => $start_utc,
                "ends_at_utc" => $end_utc,
            ],
            "resources" => $resources,
        ];
    }

    /** @return array<string,mixed> */
    public function reschedule(int $booking_id, array $input): array
    {
        $initial = $this->loadMonthlyOccurrence($booking_id);
        $series_id = (int) $initial->series_id;
        $new_resource_id = (int) ($input["new_resource_id"] ?? $input["resource_id"] ?? 0);
        if ($new_resource_id <= 0) { throw new \DomainException("gd_invalid_booking_resources"); }
        $this->assertCourtResource($new_resource_id);
        $new_date = trim((string) ($input["occurrence_date"] ?? $initial->series_local_date));
        if ($new_date !== (string) $initial->series_local_date) { throw new \DomainException("gd_reschedule_date_mismatch"); }
        $new_start_time = trim((string) ($input["new_start_time"] ?? $input["start_time"] ?? ""));
        $new_start_time = substr(TemporalService::normalizeTime($new_start_time), 0, 5);
        $duration = $this->durationMinutesFromBooking($initial);
        $new_start = new \DateTimeImmutable($new_date . " " . $new_start_time, new \DateTimeZone($this->time->timezoneName()));
        $new_end = $new_start->modify("+{$duration} minutes");
        if ($new_end->format("Y-m-d") !== $new_date) { throw new \DomainException("gd_reschedule_date_mismatch"); }
        $new_start_utc = $this->time->localToUtc($new_start->format("Y-m-d"), $new_start->format("H:i:s"));
        $new_end_utc = $this->time->localToUtc($new_end->format("Y-m-d"), $new_end->format("H:i:s"));
        [$new_start_utc, $new_end_utc] = $this->time->validateRange($new_start_utc, $new_end_utc);
        if ($new_start_utc <= gmdate("Y-m-d H:i:s")) { throw new \DomainException("gd_booking_not_editable"); }

        $current_resources = $this->booking_resources->for_booking($booking_id, $this->unit_id);
        $old_ids = array_map(static fn($row): int => (int) $row->resource_id, $current_resources);
        $lock = new BookingSeriesLockService();
        $resource_lock = new BookingResourceLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $series_id);
            $resource_lock->acquire($this->unit_id, array_values(array_unique(array_merge($old_ids, [$new_resource_id]))));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("monthly reschedule transaction"); }
            $in_tx = true;
            $booking = $this->loadMonthlyOccurrence($booking_id);
            $active = $this->exceptions->active_reschedule((int) $booking->series_id, (string) $booking->series_occurrence_key, $this->unit_id);
            if ($active) {
                if ((string) $active->new_starts_at_utc === $new_start_utc && (string) $active->new_ends_at_utc === $new_end_utc && (int) $active->new_resource_id === $new_resource_id) {
                    $this->db->transCommit();
                    $in_tx = false;
                    return ["id" => (int) $active->id, "booking_id" => $booking_id, "series_id" => $series_id, "idempotent" => true, "occurrence" => $this->occurrence($booking_id)];
                }
                throw new \DomainException("gd_reschedule_already_exists");
            }
            if ((string) $booking->starts_at_utc <= gmdate("Y-m-d H:i:s") || !in_array((string) $booking->status, Constants::BOOKING_EDITABLE_STATUSES, true)) { throw new \DomainException("gd_booking_not_editable"); }
            $original = $this->snapshotBooking($booking);
            if ((int) ($original["resource_id"] ?? 0) === $new_resource_id && (string) $booking->starts_at_utc === $new_start_utc && (string) $booking->ends_at_utc === $new_end_utc) { throw new \DomainException("gd_reschedule_same_schedule"); }
            $new_buffer_before = (int) ($current_resources[0]->buffer_before_minutes ?? 0);
            $new_buffer_after = (int) ($current_resources[0]->buffer_after_minutes ?? 0);
            $booking_input = [
                "booking_type" => (string) $booking->booking_type,
                "title" => (string) $booking->title,
                "customer_account_id" => $booking->customer_account_id,
                "contact_person_id" => $booking->contact_person_id,
                "starts_at_local" => $new_start->format("Y-m-d\\TH:i"),
                "ends_at_local" => $new_end->format("Y-m-d\\TH:i"),
                "status" => (string) $booking->status,
                "notes" => $booking->notes,
                "metadata" => $booking->metadata,
                "resources" => [["resource_id" => $new_resource_id, "buffer_before_minutes" => $new_buffer_before, "buffer_after_minutes" => $new_buffer_after]],
                "lock_version" => (int) $booking->lock_version,
            ];
            $saved = (new BookingService($this->unit_id, $this->actor_id, $this->login_user))->save($booking_input, $booking_id, true, true, true);
            $updated = $this->db->table($this->db->prefixTable("gd_bookings"))->where("id", $booking_id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $saved["lock_version"])->update(["is_series_exception" => 1, "detached_from_series" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
            if ($updated === false) { throw new \RuntimeException("monthly reschedule flag update"); }
            $reason = $this->cleanReason($input["reason"] ?? null);
            if ($reason === null) { throw new \DomainException("gd_reschedule_reason_required"); }
            $notes = $this->cleanNotes($input["notes"] ?? null);
            $revision = $this->exceptions->next_reschedule_revision($series_id, (string) $booking->series_occurrence_key, $this->unit_id);
            $payload = ["operation" => "reschedule", "original" => $original, "new" => ["resource_id" => $new_resource_id, "starts_at_utc" => $new_start_utc, "ends_at_utc" => $new_end_utc, "local_start_time" => $new_start->format("H:i"), "local_end_time" => $new_end->format("H:i")], "original_buffer_before_minutes" => (int) ($current_resources[0]->buffer_before_minutes ?? 0), "original_buffer_after_minutes" => (int) ($current_resources[0]->buffer_after_minutes ?? 0)];
            $exception_id = $this->exceptions->add([
                "unit_id" => $this->unit_id, "series_id" => $series_id, "booking_id" => $booking_id, "occurrence_key" => (string) $booking->series_occurrence_key, "local_date" => (string) $booking->series_local_date, "exception_type" => "reschedule", "revision" => $revision, "status" => "active",
                "original_resource_id" => (int) ($original["resource_id"] ?? 0), "original_starts_at_utc" => (string) $booking->starts_at_utc, "original_ends_at_utc" => (string) $booking->ends_at_utc, "new_resource_id" => $new_resource_id, "new_starts_at_utc" => $new_start_utc, "new_ends_at_utc" => $new_end_utc,
                "reason" => $reason, "notes" => $notes, "payload" => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "created_at" => gmdate("Y-m-d H:i:s"), "created_by" => $this->actor_id ?: null,
            ]);
            if ($exception_id <= 0) { throw new \RuntimeException("monthly reschedule exception insert"); }
            (new BookingSeriesEventService($this->unit_id, $this->actor_id, $this->login_user))->append($series_id, "occurrence_rescheduled", (string) $booking->status, (string) $booking->status, $reason, ["booking_id" => $booking_id, "occurrence_key" => $booking->series_occurrence_key, "exception_id" => $exception_id, "original" => $original, "new" => $payload["new"], "finance_unchanged" => true]);
            $this->audit_change("booking_series_occurrence_rescheduled", "booking_series_exception", $exception_id, $original, $payload["new"] + ["reason" => $reason, "notes" => $notes], ["booking_id" => $booking_id, "series_id" => $series_id, "rental_id" => (int) $booking->rental_id, "finance_unchanged" => true]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("monthly reschedule commit"); }
            $in_tx = false;
            return ["id" => $exception_id, "booking_id" => $booking_id, "series_id" => $series_id, "rental_id" => (int) $booking->rental_id, "idempotent" => false, "occurrence" => $this->occurrence($booking_id), "finance_unchanged" => true];
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $resource_lock->release();
            $lock->release();
        }
    }

    /** @return array<string,mixed> */
    public function revert(int $booking_id, array $input = []): array
    {
        $initial = $this->loadMonthlyOccurrence($booking_id);
        $active = $this->exceptions->active_reschedule((int) $initial->series_id, (string) $initial->series_occurrence_key, $this->unit_id);
        if (!$active) { throw new \DomainException("gd_reschedule_not_found"); }
        $original_resource_id = (int) $active->original_resource_id;
        $this->assertCourtResource($original_resource_id);
        $current_resources = $this->booking_resources->for_booking($booking_id, $this->unit_id);
        $lock = new BookingSeriesLockService();
        $resource_lock = new BookingResourceLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $initial->series_id);
            $resource_lock->acquire($this->unit_id, array_values(array_unique(array_merge([$original_resource_id], array_map(static fn($row): int => (int) $row->resource_id, $current_resources)))));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("monthly reschedule revert transaction"); }
            $in_tx = true;
            $booking = $this->loadMonthlyOccurrence($booking_id);
            $active = $this->exceptions->active_reschedule((int) $booking->series_id, (string) $booking->series_occurrence_key, $this->unit_id);
            if (!$active) { throw new \DomainException("gd_reschedule_not_found"); }
            $original_start = (string) $active->original_starts_at_utc;
            $original_end = (string) $active->original_ends_at_utc;
            if ($original_start === "" || $original_end === "") { throw new \DomainException("gd_reschedule_history_incomplete"); }
            if ((string) $booking->starts_at_utc <= gmdate("Y-m-d H:i:s") || $original_start <= gmdate("Y-m-d H:i:s") || !in_array((string) $booking->status, Constants::BOOKING_EDITABLE_STATUSES, true)) { throw new \DomainException("gd_booking_not_editable"); }
            $original_start_local = $this->time->utcToLocalInput($original_start);
            $original_end_local = $this->time->utcToLocalInput($original_end);
            $payload = json_decode((string) $active->payload, true);
            $before_buffer = (int) ($payload["original_buffer_before_minutes"] ?? 0);
            $after_buffer = (int) ($payload["original_buffer_after_minutes"] ?? 0);
            $saved = (new BookingService($this->unit_id, $this->actor_id, $this->login_user))->save([
                "booking_type" => (string) $booking->booking_type, "title" => (string) $booking->title, "customer_account_id" => $booking->customer_account_id, "contact_person_id" => $booking->contact_person_id,
                "starts_at_local" => $original_start_local, "ends_at_local" => $original_end_local, "status" => (string) $booking->status, "notes" => $booking->notes, "metadata" => $booking->metadata,
                "resources" => [["resource_id" => $original_resource_id, "buffer_before_minutes" => $before_buffer, "buffer_after_minutes" => $after_buffer]], "lock_version" => (int) $booking->lock_version,
            ], $booking_id, true, true, true);
            $this->db->table($this->db->prefixTable("gd_bookings"))->where("id", $booking_id)->where("unit_id", $this->unit_id)->where("lock_version", (int) $saved["lock_version"])->update(["is_series_exception" => 0, "detached_from_series" => 0, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
            $reason = $this->cleanReason($input["reason"] ?? null);
            if (!$this->exceptions->mark_reverted((int) $active->id, (int) $booking->series_id, $this->unit_id, $this->actor_id, $reason)) { throw new \DomainException("gd_reschedule_revert_conflict"); }
            $current = ["resource_id" => (int) $active->new_resource_id, "starts_at_utc" => (string) $active->new_starts_at_utc, "ends_at_utc" => (string) $active->new_ends_at_utc];
            (new BookingSeriesEventService($this->unit_id, $this->actor_id, $this->login_user))->append((int) $booking->series_id, "occurrence_reschedule_reverted", (string) $booking->status, (string) $booking->status, $reason, ["booking_id" => $booking_id, "exception_id" => (int) $active->id, "original" => ["resource_id" => $original_resource_id, "starts_at_utc" => $original_start, "ends_at_utc" => $original_end], "current_before_revert" => $current, "finance_unchanged" => true]);
            $this->audit_change("booking_series_occurrence_reschedule_reverted", "booking_series_exception", (int) $active->id, $current, ["resource_id" => $original_resource_id, "starts_at_utc" => $original_start, "ends_at_utc" => $original_end, "reason" => $reason], ["booking_id" => $booking_id, "series_id" => (int) $booking->series_id, "finance_unchanged" => true]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("monthly reschedule revert commit"); }
            $in_tx = false;
            return ["id" => (int) $active->id, "booking_id" => $booking_id, "reverted" => true, "occurrence" => $this->occurrence($booking_id), "finance_unchanged" => true];
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $resource_lock->release();
            $lock->release();
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function historyForRental(int $rental_id): array
    {
        $links = $this->db->table($this->db->prefixTable("gd_court_rental_schedule_links"))->select("booking_series_id")
            ->where("unit_id", $this->unit_id)->where("rental_id", $rental_id)->where("booking_series_id IS NOT NULL", null, false)->get()->getResult();
        $series_ids = array_values(array_unique(array_filter(array_map(static fn($row): int => (int) $row->booking_series_id, $links))));
        if (!$series_ids) { return []; }
        $table = $this->db->prefixTable("gd_booking_series_exceptions");
        $query = $this->db->table($table)->where("unit_id", $this->unit_id)->whereIn("series_id", $series_ids)->where("exception_type", "reschedule")->orderBy("local_date", "DESC")->orderBy("revision", "DESC");
        $rows = $query->get()->getResult();
        if (!$rows) { return []; }
        $resource_ids = [];
        foreach ($rows as $row) { $resource_ids[] = (int) $row->original_resource_id; $resource_ids[] = (int) $row->new_resource_id; }
        $resources = [];
        foreach ($this->db->table($this->db->prefixTable("gd_resources"))->select("id,code,name")->where("unit_id", $this->unit_id)->whereIn("id", array_values(array_unique(array_filter($resource_ids))))->get()->getResult() as $resource) { $resources[(int) $resource->id] = (string) $resource->code . " — " . (string) $resource->name; }
        $actor_ids = [];
        foreach ($rows as $row) { $actor_ids[] = (int) ($row->created_by ?? 0); $actor_ids[] = (int) ($row->reverted_by ?? 0); }
        $actors = [];
        $actor_ids = array_values(array_unique(array_filter($actor_ids)));
        if ($actor_ids) {
            foreach ($this->db->table($this->db->prefixTable("users"))->select("id,TRIM(CONCAT(first_name,' ',last_name)) AS display_name", false)->whereIn("id", $actor_ids)->get()->getResult() as $actor) { $actors[(int) $actor->id] = trim((string) $actor->display_name); }
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                "created_by_name" => $actors[(int) ($row->created_by ?? 0)] ?? "", "reverted_by_name" => $actors[(int) ($row->reverted_by ?? 0)] ?? "",
                "id" => (int) $row->id, "booking_id" => (int) $row->booking_id, "series_id" => (int) $row->series_id, "occurrence_date" => (string) $row->local_date, "revision" => (int) $row->revision, "status" => (string) ($row->status ?: "active"),
                "original_resource_id" => (int) $row->original_resource_id, "original_resource" => $resources[(int) $row->original_resource_id] ?? (string) $row->original_resource_id,
                "original_start" => $this->localLabel((string) $row->original_starts_at_utc), "original_end" => $this->localLabel((string) $row->original_ends_at_utc),
                "new_resource_id" => (int) $row->new_resource_id, "new_resource" => $resources[(int) $row->new_resource_id] ?? (string) $row->new_resource_id,
                "new_start" => $this->localLabel((string) $row->new_starts_at_utc), "new_end" => $this->localLabel((string) $row->new_ends_at_utc),
                "reason" => (string) ($row->reason ?? ""), "notes" => (string) ($row->notes ?? ""), "created_by" => (int) ($row->created_by ?? 0), "created_at" => (string) $row->created_at, "reverted_at" => (string) ($row->reverted_at ?? ""), "reverted_by" => (int) ($row->reverted_by ?? 0),
            ];
        }
        return $out;
    }

    private function loadMonthlyOccurrence(int $booking_id): object
    {
        $booking = $this->bookings->get_scoped($booking_id, $this->unit_id);
        if (!$booking || (int) $booking->series_id <= 0 || trim((string) $booking->series_occurrence_key) === "" || trim((string) $booking->series_local_date) === "") { throw new \DomainException("gd_booking_series_occurrence_not_found"); }
        $series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,status,booking_type")->where("id", (int) $booking->series_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        if (!$series) { throw new \DomainException("gd_booking_series_not_found"); }
        $link = $this->db->table($this->db->prefixTable("gd_court_rental_schedule_links"))->select("rental_id")->where("unit_id", $this->unit_id)->where("booking_series_id", (int) $booking->series_id)->where("deleted", 0)->where("link_kind !=", "historical")->get(1)->getRow();
        if (!$link) { throw new \DomainException("gd_monthly_rental_occurrence_required"); }
        $booking->rental_id = (int) $link->rental_id;
        $booking->resources = $this->booking_resources->for_booking($booking_id, $this->unit_id);
        if (!$booking->resources) { throw new \DomainException("gd_invalid_booking_resources"); }
        return $booking;
    }

    /** @return array<int,int> */
    private function seriesIdsForRental(int $rental_id): array
    {
        if ($rental_id <= 0) { return []; }
        $rows = $this->db->table($this->db->prefixTable("gd_court_rental_schedule_links"))
            ->select("booking_series_id")
            ->where("unit_id", $this->unit_id)
            ->where("rental_id", $rental_id)
            ->where("deleted", 0)
            ->where("link_kind !=", "historical")
            ->where("booking_series_id IS NOT NULL", null, false)
            ->get()->getResult();
        return array_values(array_unique(array_filter(array_map(static fn($row): int => (int) $row->booking_series_id, $rows))));
    }

    /** @return array<string,mixed> */
    private function snapshotBooking(object $booking): array
    {
        $row = $booking->resources[0] ?? null;
        $start = $this->time->utcToLocal((string) $booking->starts_at_utc);
        $end = $this->time->utcToLocal((string) $booking->ends_at_utc);
        return ["resource_id" => (int) ($row->resource_id ?? 0), "resource" => $this->resourceLabel((int) ($row->resource_id ?? 0)), "starts_at_utc" => (string) $booking->starts_at_utc, "ends_at_utc" => (string) $booking->ends_at_utc, "local_date" => $start->format("Y-m-d"), "local_start_time" => $start->format("H:i"), "local_end_date" => $end->format("Y-m-d"), "local_end_time" => $end->format("H:i")];
    }

    /** @return array<string,mixed> */
    private function snapshotException(object $exception): array
    {
        $payload = json_decode((string) $exception->payload, true);
        $start = (string) ($exception->original_starts_at_utc ?? ($payload["original"]["starts_at_utc"] ?? ""));
        $end = (string) ($exception->original_ends_at_utc ?? ($payload["original"]["ends_at_utc"] ?? ""));
        $resource_id = (int) ($exception->original_resource_id ?? ($payload["original"]["resource_id"] ?? 0));
        if ($start === "" || $end === "") { return ["resource_id" => $resource_id, "resource" => $this->resourceLabel($resource_id), "starts_at_utc" => $start, "ends_at_utc" => $end]; }
        $s = $this->time->utcToLocal($start); $e = $this->time->utcToLocal($end);
        return ["resource_id" => $resource_id, "resource" => $this->resourceLabel($resource_id), "starts_at_utc" => $start, "ends_at_utc" => $end, "local_date" => $s->format("Y-m-d"), "local_start_time" => $s->format("H:i"), "local_end_date" => $e->format("Y-m-d"), "local_end_time" => $e->format("H:i")];
    }

    private function durationMinutes(array $snapshot): int
    {
        $start = (string) ($snapshot["starts_at_utc"] ?? ""); $end = (string) ($snapshot["ends_at_utc"] ?? "");
        if ($start === "" || $end === "") { throw new \DomainException("gd_reschedule_history_incomplete"); }
        $minutes = (int) (($this->time->parseUtc($end)->getTimestamp() - $this->time->parseUtc($start)->getTimestamp()) / 60);
        if ($minutes < 1 || $minutes > Constants::BOOKING_MAX_DURATION_MINUTES) { throw new \DomainException("gd_booking_duration_too_large"); }
        return $minutes;
    }

    private function durationMinutesFromBooking(object $booking): int { return $this->durationMinutes(["starts_at_utc" => $booking->starts_at_utc, "ends_at_utc" => $booking->ends_at_utc]); }
    private function assertCourtResource(int $resource_id): void
    {
        $row = $this->db->table($this->db->prefixTable("gd_resources"))->where("id", $resource_id)->where("unit_id", $this->unit_id)->where("resource_type", Constants::COURT_RESOURCE_TYPE)->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_invalid_booking_resource"); }
    }
    private function resourceLabel(int $resource_id): string
    {
        if ($resource_id <= 0) { return "-"; }
        $row = $this->db->table($this->db->prefixTable("gd_resources"))->select("code,name")->where("id", $resource_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
        return $row ? (string) $row->code . " — " . (string) $row->name : (string) $resource_id;
    }
    private function localLabel(string $utc): string
    {
        if ($utc === "") { return ""; }
        try { return $this->time->utcToLocal($utc)->format("d/m/Y H:i"); } catch (\Throwable $e) { return $utc; }
    }
    private function cleanReason($value): ?string { $value = trim(strip_tags((string) $value)); return $value === "" ? null : mb_substr($value, 0, 255); }
    private function cleanNotes($value): ?string { $value = trim(strip_tags((string) $value)); return $value === "" ? null : mb_substr($value, 0, 5000); }
}
