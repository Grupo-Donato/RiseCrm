<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Fonte única da disponibilidade física: recurso, bloqueios, exceções,
 * grade semanal e reservas já materializadas.
 */
class AvailabilityService extends CustomerDataService
{
    private TemporalService $time;

    public function __construct(int $unit_id)
    {
        parent::__construct($unit_id);
        $this->time = new TemporalService($unit_id);
    }

    public function check(int $resource_id, string $starts_at_utc, string $ends_at_utc, int|array $exclude_booking_id = 0): array
    {
        return $this->checkMany([$resource_id], $starts_at_utc, $ends_at_utc, $exclude_booking_id)[$resource_id] ?? [
            "available" => false,
            "resource_id" => $resource_id,
            "reason_code" => "resource_not_found",
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function checkMany(array $resource_ids, string $starts_at_utc, string $ends_at_utc, int|array $exclude_booking_id = 0): array
    {
        [$start, $end] = $this->time->validateRange($starts_at_utc, $ends_at_utc);
        $ids = array_values(array_unique(array_filter(array_map("intval", $resource_ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            return [];
        }

        $prefix = $this->db->getPrefix();
        $resources = $this->db->table($prefix . "gd_resources")
            ->where("unit_id", $this->unit_id)->whereIn("id", $ids)
            ->get()->getResult();
        $resource_map = [];
        foreach ($resources as $row) {
            $resource_map[(int) $row->id] = $row;
        }

        $blocks = $this->db->table($prefix . "gd_resource_blocks")
            ->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)
            ->where("status", "active")->where("deleted", 0)
            ->where("starts_at_utc <", $end)->where("ends_at_utc >", $start)
            ->get()->getResult();
        $exceptions = $this->db->table($prefix . "gd_resource_availability_exceptions")
            ->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)
            ->where("status", "active")->where("deleted", 0)
            ->where("starts_at_utc <", $end)->where("ends_at_utc >", $start)
            ->get()->getResult();
        $rules = $this->db->table($prefix . "gd_resource_availability_rules")
            ->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)
            ->where("status", "active")->where("deleted", 0)
            ->get()->getResult();
        $bookings = $this->bookingOccupancy($ids, $start, $end, $exclude_booking_id);

        $by_block = $this->group($blocks);
        $by_exception = $this->group($exceptions);
        $by_rule = $this->group($rules);
        $by_booking = $this->group($bookings);
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = $this->evaluate(
                $id,
                $resource_map[$id] ?? null,
                $start,
                $end,
                $by_block[$id] ?? [],
                $by_exception[$id] ?? [],
                $by_rule[$id] ?? [],
                $by_booking[$id] ?? []
            );
        }
        return $out;
    }

    /**
     * Lista horários livres em passos de 30 minutos. O intervalo de busca
     * aceita virada de dia: até 00:00 significa meia-noite do dia seguinte.
     * @return array<int,array<string,mixed>>
     */
    public function findAvailableSlots(
        string $local_date,
        string $from_time,
        string $until_time,
        int $duration_minutes,
        array $resource_ids = [],
        int|array $exclude_booking_id = 0,
        int $step_minutes = 30
    ): array {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $local_date)) {
            throw new \DomainException("gd_invalid_local_datetime");
        }
        $from_time = $this->normalizeTime($from_time);
        $until_time = $this->normalizeTime($until_time);
        if ($duration_minutes < 1 || $duration_minutes > Constants::BOOKING_MAX_DURATION_MINUTES) {
            throw new \DomainException("gd_booking_duration_too_large");
        }
        $step_minutes = max(5, min(120, $step_minutes));
        $from_minutes = TemporalService::timeMinutes($from_time);
        $until_minutes = TemporalService::timeMinutes($until_time);
        if ($until_minutes <= $from_minutes) {
            $until_minutes += 1440;
        }
        if ($until_minutes - $from_minutes > 2880) {
            throw new \DomainException("gd_invalid_time_range");
        }

        $ids = array_values(array_unique(array_filter(array_map("intval", $resource_ids), static fn (int $id): bool => $id > 0)));
        if (!$ids) {
            $ids = array_map(static fn ($row): int => (int) $row->id, $this->db->table($this->db->prefixTable("gd_resources"))
                ->select("id")->where("unit_id", $this->unit_id)->where("resource_type", Constants::COURT_RESOURCE_TYPE)
                ->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->get()->getResult());
        }
        if (!$ids) {
            return [];
        }

        $context_start = $this->time->localToUtc($local_date, "00:00");
        $context_end = $this->time->localToUtc((new \DateTimeImmutable($local_date, new \DateTimeZone($this->time->timezoneName())))->modify("+3 days")->format("Y-m-d"), "00:00");
        $context = $this->loadContext($ids, $context_start, $context_end, $exclude_booking_id);
        $slots = [];
        for ($offset = $from_minutes; $offset + $duration_minutes <= $until_minutes; $offset += $step_minutes) {
            $day_offset = intdiv($offset, 1440);
            $minute = $offset % 1440;
            $date = (new \DateTimeImmutable($local_date, new \DateTimeZone($this->time->timezoneName())))->modify("+{$day_offset} day")->format("Y-m-d");
            $start_local = sprintf("%s %02d:%02d", $date, intdiv($minute, 60), $minute % 60);
            $end_total = $offset + $duration_minutes;
            $end_day_offset = intdiv($end_total, 1440);
            $end_minute = $end_total % 1440;
            $end_date = (new \DateTimeImmutable($local_date, new \DateTimeZone($this->time->timezoneName())))->modify("+{$end_day_offset} day")->format("Y-m-d");
            $start_utc = $this->time->localStringToUtc(str_replace(" ", "T", $start_local));
            $end_utc = $this->time->localStringToUtc(sprintf("%sT%02d:%02d", $end_date, intdiv($end_minute, 60), $end_minute % 60));
            [$start_utc, $end_utc] = $this->time->validateRange($start_utc, $end_utc);
            $available = [];
            foreach ($ids as $id) {
                $result = $this->evaluate(
                    $id,
                    $context["resources"][$id] ?? null,
                    $start_utc,
                    $end_utc,
                    $context["blocks"][$id] ?? [],
                    $context["exceptions"][$id] ?? [],
                    $context["rules"][$id] ?? [],
                    $context["bookings"][$id] ?? []
                );
                if (!empty($result["available"])) {
                    $available[] = $id;
                }
            }
            if ($available) {
                foreach ($available as $resource_id) {
                    $resource = $context["resources"][$resource_id] ?? null;
                    $slots[] = [
                        "resource_id" => $resource_id,
                        "resource_code" => (string) ($resource->code ?? ""),
                        "resource_name" => (string) ($resource->name ?? ""),
                        "starts_at_local" => str_replace(" ", "T", $start_local),
                        "ends_at_local" => sprintf("%sT%02d:%02d", $end_date, intdiv($end_minute, 60), $end_minute % 60),
                        "starts_at_utc" => $start_utc,
                        "ends_at_utc" => $end_utc,
                        "duration_minutes" => $duration_minutes,
                    ];
                }
            }
        }
        return $slots;
    }

    private function evaluate(int $id, ?object $resource, string $start, string $end, array $blocks, array $exceptions, array $rules, array $bookings): array
    {
        $blocks = array_values(array_filter($blocks, static fn ($row): bool => (string) $row->starts_at_utc < $end && (string) $row->ends_at_utc > $start));
        $exceptions = array_values(array_filter($exceptions, static fn ($row): bool => (string) $row->starts_at_utc < $end && (string) $row->ends_at_utc > $start));
        $base = [
            "available" => false, "resource_id" => $id, "starts_at_utc" => $start, "ends_at_utc" => $end,
            "timezone" => $this->time->timezoneName(), "source" => "resource", "reason_code" => "resource_not_found",
            "matched_rule_ids" => [], "matched_exception_ids" => [], "matched_block_ids" => [], "matched_booking_ids" => [],
        ];
        if (!$resource || (int) $resource->deleted === 1) { return $base; }
        if (!(int) $resource->is_active) { $base["reason_code"] = "resource_inactive"; return $base; }
        if (!(int) $resource->is_bookable) { $base["reason_code"] = "resource_not_bookable"; return $base; }
        if ($blocks) {
            $base["source"] = "block"; $base["reason_code"] = "active_block";
            $base["matched_block_ids"] = array_map(static fn ($row): int => (int) $row->id, $blocks); return $base;
        }
        $closed = array_values(array_filter($exceptions, static fn ($row): bool => (string) $row->exception_type === "closed"));
        if ($closed) {
            $base["source"] = "closed_exception"; $base["reason_code"] = "closed_exception";
            $base["matched_exception_ids"] = array_map(static fn ($row): int => (int) $row->id, $closed); return $base;
        }
        $conflicting_bookings = array_values(array_filter($bookings, static fn ($row): bool =>
            (string) $row->occupancy_starts_at_utc < (string) $end && (string) $row->occupancy_ends_at_utc > (string) $start
        ));
        if ($conflicting_bookings) {
            $base["source"] = "booking"; $base["reason_code"] = "booking_conflict";
            $base["matched_booking_ids"] = array_values(array_unique(array_map(static fn ($row): int => (int) $row->booking_id, $conflicting_bookings))); return $base;
        }
        $open = array_values(array_filter($exceptions, static fn ($row): bool => (string) $row->exception_type === "open"));
        $open_ranges = []; $matched = [];
        foreach ($open as $row) { $open_ranges[] = [(string) $row->starts_at_utc, (string) $row->ends_at_utc]; $matched[] = (int) $row->id; }
        if ($this->covered($start, $end, $open_ranges)) {
            $base["available"] = true; $base["source"] = "open_exception"; $base["reason_code"] = "available_open_exception";
            $base["matched_exception_ids"] = array_values(array_unique($matched)); return $base;
        }
        if (!$rules && !$open) {
            $base["available"] = true; $base["source"] = "resource_default"; $base["reason_code"] = "available_without_schedule"; return $base;
        }
        [$weekly_ranges, $rule_ids] = $this->weeklyRanges($rules, $start, $end);
        if ($this->covered($start, $end, $weekly_ranges)) {
            $base["available"] = true; $base["source"] = "weekly_rule"; $base["reason_code"] = "available_weekly_rule"; $base["matched_rule_ids"] = $rule_ids; return $base;
        }
        $base["source"] = "none"; $base["reason_code"] = "outside_availability"; return $base;
    }

    private function loadContext(array $ids, string $start, string $end, int|array $exclude_booking_id = 0): array
    {
        $prefix = $this->db->getPrefix();
        $resources = $this->db->table($prefix . "gd_resources")->where("unit_id", $this->unit_id)->whereIn("id", $ids)->get()->getResult();
        $blocks = $this->db->table($prefix . "gd_resource_blocks")->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)->where("status", "active")->where("deleted", 0)->where("starts_at_utc <", $end)->where("ends_at_utc >", $start)->get()->getResult();
        $exceptions = $this->db->table($prefix . "gd_resource_availability_exceptions")->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)->where("status", "active")->where("deleted", 0)->where("starts_at_utc <", $end)->where("ends_at_utc >", $start)->get()->getResult();
        $rules = $this->db->table($prefix . "gd_resource_availability_rules")->where("unit_id", $this->unit_id)->whereIn("resource_id", $ids)->where("status", "active")->where("deleted", 0)->get()->getResult();
        $bookings = $this->bookingOccupancy($ids, $start, $end, $exclude_booking_id);
        return ["resources" => $this->groupById($resources), "blocks" => $this->group($blocks), "exceptions" => $this->group($exceptions), "rules" => $this->group($rules), "bookings" => $this->group($bookings)];
    }

    private function bookingOccupancy(array $ids, string $start, string $end, int|array $exclude_booking_id = 0, bool $fullRange = false): array
    {
        $prefix = $this->db->getPrefix(); $b = $prefix . "gd_bookings"; $br = $prefix . "gd_booking_resources";
        $q = $this->db->table($br)->select("$br.resource_id,$br.booking_id,$br.occupancy_starts_at_utc,$br.occupancy_ends_at_utc")
            ->join($b, "$b.id=$br.booking_id AND $b.unit_id=$br.unit_id", "inner", false)
            ->where("$br.unit_id", $this->unit_id)->whereIn("$br.resource_id", $ids)->where("$br.deleted", 0)->where("$b.deleted", 0)
            ->whereIn("$b.status", Constants::BOOKING_BLOCKING_STATUSES)
            ->groupStart()->where("$b.status !=", "hold")->orGroupStart()->where("$b.status", "hold")->where("$b.hold_expires_at_utc >", gmdate("Y-m-d H:i:s"))->groupEnd()->groupEnd();
        if (!$fullRange) { $q->where("$br.occupancy_starts_at_utc <", $end)->where("$br.occupancy_ends_at_utc >", $start); }
        $excluded = is_array($exclude_booking_id) ? array_values(array_filter(array_map("intval", $exclude_booking_id))) : [(int) $exclude_booking_id];
        $excluded = array_values(array_filter($excluded, static fn (int $id): bool => $id > 0));
        if (count($excluded) === 1) { $q->where("$b.id !=", $excluded[0]); }
        elseif ($excluded) { $q->whereNotIn("$b.id", $excluded); }
        return $q->get()->getResult();
    }

    private function weeklyRanges(array $rules, string $start, string $end): array
    {
        $local_start = $this->time->utcToLocal($start)->modify("-1 day")->setTime(0, 0);
        $local_end = $this->time->utcToLocal($end)->modify("+1 day")->setTime(0, 0);
        $ranges = []; $ids = [];
        for ($day = $local_start; $day <= $local_end; $day = $day->modify("+1 day")) {
            $date = $day->format("Y-m-d"); $weekday = (int) $day->format("w");
            foreach ($rules as $rule) {
                if ((int) $rule->weekday !== $weekday || ($rule->valid_from && $date < $rule->valid_from) || ($rule->valid_until && $date > $rule->valid_until)) { continue; }
                $end_date = (int) $rule->spans_next_day ? $day->modify("+1 day")->format("Y-m-d") : $date;
                try { $rs = $this->time->localToUtc($date, (string) $rule->start_time); $re = $this->time->localToUtc($end_date, (string) $rule->end_time); }
                catch (\DomainException $e) { continue; }
                if (TemporalService::overlaps($rs, $re, $start, $end)) { $ranges[] = [$rs, $re]; $ids[] = (int) $rule->id; }
            }
        }
        return [$ranges, array_values(array_unique($ids))];
    }

    private function covered(string $start, string $end, array $ranges): bool
    {
        if (!$ranges) { return false; }
        usort($ranges, static fn ($a, $b): int => $a[0] <=> $b[0]); $cursor = $start;
        foreach ($ranges as [$s, $e]) { if ($e <= $cursor) { continue; } if ($s > $cursor) { return false; } $cursor = $e > $cursor ? $e : $cursor; if ($cursor >= $end) { return true; } }
        return false;
    }

    private function group(array $rows): array { $out = []; foreach ($rows as $row) { $out[(int) $row->resource_id][] = $row; } return $out; }
    private function groupById(array $rows): array { $out = []; foreach ($rows as $row) { $out[(int) $row->id] = $row; } return $out; }
    private function normalizeTime(string $value): string
    {
        $value = trim($value); if (preg_match('/^\d{2}:\d{2}$/', $value)) { $value .= ":00"; }
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) { throw new \DomainException("gd_invalid_local_datetime"); }
        $minutes = TemporalService::timeMinutes($value); if ($minutes < 0 || $minutes >= 1440) { throw new \DomainException("gd_invalid_local_datetime"); }
        return substr($value, 0, 5);
    }
}
