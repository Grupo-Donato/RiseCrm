<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class Money
{
    public static function normalize($value): string
    {
        $value = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $value)) {
            throw new \DomainException('gdc_invalid_amount');
        }
        $negative = str_starts_with($value, '-');
        if ($negative) {
            $value = substr($value, 1);
        }
        [$whole, $decimal] = array_pad(explode('.', $value, 2), 2, '');
        $decimal = str_pad(substr($decimal, 0, 2), 2, '0');
        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;
        return ($negative ? '-' : '') . $whole . '.' . $decimal;
    }

    public static function cents($value): int
    {
        $normalized = self::normalize($value);
        $negative = str_starts_with($normalized, '-');
        if ($negative) {
            $normalized = substr($normalized, 1);
        }
        [$whole, $decimal] = explode('.', $normalized, 2);
        $cents = ((int) $whole * 100) + (int) $decimal;
        return $negative ? -$cents : $cents;
    }

    public static function compare($left, $right): int
    {
        return self::cents($left) <=> self::cents($right);
    }

    private function __construct() {}
}
