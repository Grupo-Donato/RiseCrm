<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

final class RecurrenceGeneratorService extends CustomerDataService
{
    private TemporalService $time;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->time = new TemporalService($unit_id);
    }

    public function candidates($series, ?string $through_date = null): array
    {
        $s = is_object($series) ? get_object_vars($series) : $series;
        $start = $this->date((string) ($s["starts_on"] ?? ""));
        $frequency = (string) ($s["frequency"] ?? "");
        $interval = (int) ($s["interval_value"] ?? 0);
        if (!in_array($frequency, Constants::BOOKING_SERIES_FREQUENCIES, true) || $interval < 1 || $interval > 365) { throw new \DomainException("gd_invalid_recurrence"); }

        $weekdays = $this->weekdays($s["weekdays"] ?? null);
        $monthly_day = (int) ($s["monthly_day"] ?? 0);
        if ($frequency === "weekly" && !$weekdays) { throw new \DomainException("gd_booking_series_weekdays_required"); }
        if ($frequency === "monthly" && ($monthly_day < 1 || $monthly_day > 31)) { throw new \DomainException("gd_booking_series_monthly_day_required"); }

        $mode = (string) ($s["ends_mode"] ?? "");
        if (!in_array($mode, Constants::BOOKING_SERIES_ENDS_MODES, true)) { throw new \DomainException("gd_invalid_booking_series_end"); }
        $count_limit = $mode === "count" ? (int) ($s["max_occurrences"] ?? 0) : Constants::BOOKING_SERIES_MAX_OCCURRENCES_PER_OPERATION;
        if ($count_limit < 1 || $count_limit > Constants::BOOKING_SERIES_MAX_OCCURRENCES_PER_OPERATION) { throw new \DomainException("gd_booking_series_occurrence_limit"); }

        $end = null;
        if ($mode === "until_date") {
            $end = $this->date((string) ($s["ends_on"] ?? ""));
            if ($end < $start) { throw new \DomainException("gd_invalid_booking_series_end"); }
        } elseif ($mode === "open_ended") {
            $horizon = max(1, min(Constants::BOOKING_SERIES_MAX_HORIZON_DAYS, (int) ($s["generation_horizon_days"] ?? Constants::BOOKING_SERIES_DEFAULT_HORIZON_DAYS)));
            $today = new \DateTimeImmutable("today", new \DateTimeZone($this->time->timezoneName()));
            $end = $today->modify("+$horizon days");
        }
        if ($through_date !== null && trim($through_date) !== "") {
            $through = $this->date($through_date);
            $end = $end === null || $through < $end ? $through : $end;
        }

        $start_time = TemporalService::normalizeTime((string) ($s["local_start_time"] ?? ""));
        $end_time = TemporalService::normalizeTime((string) ($s["local_end_time"] ?? ""));
        $out = [];
        $cursor = $start;
        $iterations = 0;
        while ($iterations++ < 150000) {
            if ($end !== null && $cursor > $end) { break; }
            if ($this->matches($start, $cursor, $frequency, $interval, $weekdays, $monthly_day)) {
                $local_date = $cursor->format("Y-m-d");
                $end_date = TemporalService::timeMinutes($end_time) <= TemporalService::timeMinutes($start_time) ? $cursor->modify("+1 day")->format("Y-m-d") : $local_date;
                $starts_utc = $this->time->localToUtc($local_date, $start_time);
                $ends_utc = $this->time->localToUtc($end_date, $end_time);
                $this->time->validateRange($starts_utc, $ends_utc);
                $out[] = [
                    "occurrence_key" => $local_date,
                    "local_date" => $local_date,
                    "starts_at_local" => $local_date . "T" . substr($start_time, 0, 5),
                    "ends_at_local" => $end_date . "T" . substr($end_time, 0, 5),
                    "starts_at_utc" => $starts_utc,
                    "ends_at_utc" => $ends_utc,
                ];
                if (count($out) >= $count_limit) { break; }
            }
            $cursor = $cursor->modify("+1 day");
        }
        if ($iterations >= 150000) { throw new \DomainException("gd_booking_series_occurrence_limit"); }
        return $out;
    }

    private function matches(\DateTimeImmutable $start, \DateTimeImmutable $date, string $frequency, int $interval, array $weekdays, int $monthly_day): bool
    {
        $days = (int) $start->diff($date)->format("%a");
        if ($frequency === "daily") { return $days % $interval === 0; }
        if ($frequency === "weekly") { return intdiv($days, 7) % $interval === 0 && in_array((int) $date->format("N"), $weekdays, true); }
        $months = ((int) $date->format("Y") - (int) $start->format("Y")) * 12 + ((int) $date->format("n") - (int) $start->format("n"));
        return $months % $interval === 0 && (int) $date->format("j") === $monthly_day;
    }

    private function weekdays($value): array
    {
        if (is_string($value)) { $value = json_decode($value, true); }
        if (!is_array($value)) { return []; }
        $days = array_values(array_unique(array_map("intval", $value)));
        $days = array_values(array_filter($days, static fn(int $day): bool => $day >= 1 && $day <= 7));
        sort($days, SORT_NUMERIC);
        return $days;
    }

    private function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat("!Y-m-d", trim($value), new \DateTimeZone($this->time->timezoneName()));
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])) || $date->format("Y-m-d") !== trim($value)) { throw new \DomainException("gd_invalid_date"); }
        return $date;
    }
}
