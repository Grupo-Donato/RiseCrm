<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Conversões temporais canônicas: instantes UTC e horários civis da unidade. */
class TemporalService extends CustomerDataService
{
    private \DateTimeZone $timezone;

    public function __construct(int $unit_id)
    {
        parent::__construct($unit_id);
        $units = $this->db->prefixTable("gd_units");
        $name = (string) ($this->db->table($units)->select("timezone")->where("id", $unit_id)->get(1)->getRow()->timezone ?? "");
        try { $this->timezone = new \DateTimeZone($name ?: "UTC"); }
        catch (\Throwable $e) { throw new \DomainException("gd_invalid_timezone"); }
    }

    public function timezoneName(): string { return $this->timezone->getName(); }

    public function parseUtc(string $value): \DateTimeImmutable
    {
        $value = trim($value);
        $utc = new \DateTimeZone("UTC");
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $value, $utc);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$d || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])) || $d->format("Y-m-d H:i:s") !== $value) {
            throw new \DomainException("gd_invalid_utc_datetime");
        }
        return $d;
    }

    public function parseIsoInstant(string $value): \DateTimeImmutable
    {
        try { $d = new \DateTimeImmutable(trim($value)); }
        catch (\Throwable $e) { throw new \DomainException("gd_invalid_utc_datetime"); }
        return $d->setTimezone(new \DateTimeZone("UTC"));
    }

    public function localToUtc(string $date, string $time): string
    {
        $date = trim($date); $time = trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $time)) { $time .= ":00"; }
        $wall = $date . " " . $time;
        $d = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $wall, $this->timezone);
        $errors = \DateTimeImmutable::getLastErrors();
        if (!$d || ($errors !== false && ($errors["warning_count"] || $errors["error_count"])) || $d->format("Y-m-d H:i:s") !== $wall) {
            throw new \DomainException("gd_invalid_local_datetime");
        }
        if ($this->isAmbiguousWallTime($wall, $d->getTimestamp())) { throw new \DomainException("gd_ambiguous_local_time"); }
        return $d->setTimezone(new \DateTimeZone("UTC"))->format("Y-m-d H:i:s");
    }

    public function localStringToUtc(string $value): string
    {
        $value = trim(str_replace("T", " ", $value));
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}(?::\d{2})?)$/', $value, $m)) { throw new \DomainException("gd_invalid_local_datetime"); }
        return $this->localToUtc($m[1], $m[2]);
    }

    public function utcToLocal(string $value): \DateTimeImmutable { return $this->parseUtc($value)->setTimezone($this->timezone); }
    public function utcToLocalInput(string $value): string { return $this->utcToLocal($value)->format("Y-m-d\TH:i"); }
    public function utcToIsoLocal(string $value): string { return $this->utcToLocal($value)->format("Y-m-d\TH:i:sP"); }

    /** @return array{0:string,1:string} */
    public function validateRange(string $starts_at_utc, string $ends_at_utc, ?int $max_days = null): array
    {
        $start = $this->parseUtc($starts_at_utc); $end = $this->parseUtc($ends_at_utc);
        if ($end <= $start) { throw new \DomainException("gd_invalid_datetime_range"); }
        $limit = $max_days ?? $this->configuredLimit("temporal_admin_max_days", Constants::TEMPORAL_ADMIN_MAX_DAYS, 1, 3660);
        if (($end->getTimestamp() - $start->getTimestamp()) > $limit * 86400) { throw new \DomainException("gd_datetime_range_too_large"); }
        return [$start->format("Y-m-d H:i:s"), $end->format("Y-m-d H:i:s")];
    }

    public function calendarMaxDays(): int { return $this->configuredLimit("calendar_max_days", Constants::CALENDAR_MAX_DAYS, 1, 366); }

    public static function overlaps(string $a_start, string $a_end, string $b_start, string $b_end): bool { return $a_start < $b_end && $a_end > $b_start; }
    public static function contains(string $outer_start, string $outer_end, string $inner_start, string $inner_end): bool { return $outer_start <= $inner_start && $outer_end >= $inner_end; }

    public static function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) { return $value . ":00"; }
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $value)) { return $value; }
        throw new \DomainException("gd_invalid_time");
    }

    public static function timeMinutes(string $value): int
    {
        $parts = array_map("intval", explode(":", self::normalizeTime($value)));
        return $parts[0] * 60 + $parts[1];
    }

    private function configuredLimit(string $key, int $default, int $min, int $max): int
    {
        $settings = $this->db->prefixTable("gd_settings");
        $row = $this->db->table($settings)->select("value")->where("key", $key)->where("deleted", 0)
            ->groupStart()->where("unit_id", $this->unit_id)->orWhere("unit_id IS NULL", null, false)->groupEnd()
            ->orderBy("unit_id", "DESC")->get(1)->getRow();
        $value = $row && preg_match('/^\d+$/', (string) $row->value) ? (int) $row->value : $default;
        return max($min, min($max, $value));
    }

    private function isAmbiguousWallTime(string $wall, int $near_utc): bool
    {
        $wall_ts = \DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", $wall, new \DateTimeZone("UTC"))->getTimestamp();
        $transitions = $this->timezone->getTransitions($near_utc - 172800, $near_utc + 172800) ?: [];
        $previous = null;
        foreach ($transitions as $transition) {
            $offset = (int) $transition["offset"];
            if ($previous !== null && $offset < $previous) {
                $from = (int) $transition["ts"] + $offset;
                $until = (int) $transition["ts"] + $previous;
                if ($wall_ts >= $from && $wall_ts < $until) { return true; }
            }
            $previous = $offset;
        }
        return false;
    }
}
