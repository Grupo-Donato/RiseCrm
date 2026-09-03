<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Normalização única das durações digitadas nos fluxos de locação e agenda. */
final class DurationService
{
    public static function parseMinutes($value): int
    {
        if ($value !== null && !is_scalar($value)) { return 0; }

        $raw = mb_strtolower(trim((string) $value));
        if ($raw === "") { return 0; }
        $raw = preg_replace('/\s+/', ' ', $raw) ?? $raw;
        $minutes = 0;

        if (preg_match('/^\d+$/', $raw)) {
            // Número sem unidade continua significando minutos.
            $minutes = (int) $raw;
        } elseif (preg_match('/^(\d+)\s*h\s*(?:(\d{1,2})\s*(?:m|min|minutos?)?)?$/u', $raw, $match)) {
            $hours = (int) $match[1];
            $extra = isset($match[2]) ? (int) $match[2] : 0;
            if ($extra < 60) { $minutes = ($hours * 60) + $extra; }
        } elseif (preg_match('/^(\d+)\s*(?:m|min|minutos?)$/u', $raw, $match)) {
            $minutes = (int) $match[1];
        } elseif (preg_match('/^(\d+):(\d{2})$/', $raw, $match)) {
            $extra = (int) $match[2];
            if ($extra < 60) { $minutes = ((int) $match[1] * 60) + $extra; }
        }

        return $minutes > 0 && $minutes <= Constants::BOOKING_MAX_DURATION_MINUTES ? $minutes : 0;
    }
}
