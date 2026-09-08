<?php

declare(strict_types=1);

namespace Chatwoot_plugin\Services;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Calendar rules shared by campaign validation and the worker. Stored dates include an offset. */
final class Campaign_schedule
{
    public static function date(?string $value, string $timezone): ?string
    {
        if (trim((string) $value) === '') return null;
        try {
            $date = new DateTimeImmutable((string) $value, new DateTimeZone($timezone));
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors && ($errors['warning_count'] || $errors['error_count'])) throw new InvalidArgumentException();
            return $date->format(DATE_ATOM);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Data ou fuso horario da campanha invalido.');
        }
    }

    public static function expired(array $schedule, ?int $now = null): bool
    {
        return !empty($schedule['ends_at']) && strtotime($schedule['ends_at']) <= ($now ?? time());
    }

    /** First allowed weekday at/after the lower bound, never replaying missed days. */
    public static function occurrence(array $schedule, int $lowerBound): ?string
    {
        $zone = new DateTimeZone($schedule['timezone'] ?? 'America/Sao_Paulo');
        $start = (new DateTimeImmutable($schedule['at']))->setTimezone($zone);
        $base = (new DateTimeImmutable('@' . max($start->getTimestamp(), $lowerBound)))->setTimezone($zone);
        $days = array_map('intval', $schedule['days_of_week'] ?? []);
        for ($offset = 0; $offset < 8; $offset++) {
            $candidate = $base->modify('+' . $offset . ' days')->setTime((int) $start->format('H'), (int) $start->format('i'), (int) $start->format('s'));
            if ($candidate->getTimestamp() < max($start->getTimestamp(), $lowerBound)) continue;
            if ($days && !in_array((int) $candidate->format('w'), $days, true)) continue;
            if (self::expired($schedule, $candidate->getTimestamp())) return null;
            return $candidate->format(DATE_ATOM);
        }
        return null;
    }

    public static function interval($value): int
    {
        $seconds = filter_var($value, FILTER_VALIDATE_INT);
        if ($seconds === false || $seconds < 0 || $seconds > 3600) {
            throw new InvalidArgumentException('Intervalo entre mensagens deve ser de 0 a 3600 segundos.');
        }
        return $seconds;
    }
}
