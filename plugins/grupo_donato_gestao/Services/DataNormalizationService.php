<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class DataNormalizationService
{
    public static function text($value): string { return trim((string) preg_replace('/\s+/u', ' ', trim((string) $value))); }
    public static function name($value): string
    {
        $value=self::text($value); $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value); $value=$ascii!==false?$ascii:$value;
        return trim((string)preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($value)));
    }
    public static function document($value,string $type): string
    {
        $value=self::text($value); return in_array($type,["cpf","cnpj"],true)?(preg_replace('/\D+/','',$value)??''):mb_strtolower((string)preg_replace('/[^\pL\pN]+/u','',$value));
    }
    public static function contact($value,string $type): string
    {
        $value=self::text($value); if($type==='email'){return mb_strtolower($value);} if(in_array($type,['phone','whatsapp'],true)){return preg_replace('/\D+/','',$value)??'';} return mb_strtolower($value);
    }
    public static function postal($value): string { return preg_replace('/[^\pL\pN]+/u','',mb_strtoupper(self::text($value)))??''; }

    /**
     * Valida e normaliza um objeto JSON (metadata/attributes). Aceita string JSON
     * ou array. Vazio → null. JSON inválido, não-objeto ou acima do limite lança
     * DomainException. Reencoda de forma canônica (sem barras/unicode escapados).
     */
    public static function json($value, int $maxLen = 60000): ?string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $value = is_string($encoded) ? $encoded : '';
        }
        $value = trim((string) $value);
        if ($value === '') { return null; }
        if (strlen($value) > $maxLen) { throw new \DomainException('gd_json_too_large'); }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw new \DomainException('gd_invalid_json');
        }
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : null;
    }

    /**
     * Normaliza um valor monetário/decimal para string canônica, SEM float
     * (evita erro de precisão). Lança DomainException em valor inválido,
     * negativo, fora de faixa ou com casas decimais além da escala.
     */
    public static function decimal($value, int $scale, bool $allowNull = false): ?string
    {
        $value = str_replace(' ', '', trim((string) $value));
        if ($value === '') {
            if ($allowNull) { return null; }
            throw new \DomainException('gd_invalid_amount');
        }
        if (strpos($value, ',') !== false && strpos($value, '.') === false) {
            $value = str_replace(',', '.', $value);
        }
        if ($value !== '' && $value[0] === '-') { throw new \DomainException('gd_negative_amount'); }
        if (!preg_match('/^(\d+)(?:\.(\d+))?$/', $value, $m)) {
            throw new \DomainException('gd_invalid_amount');
        }
        $int = ltrim($m[1], '0');
        if ($int === '') { $int = '0'; }
        $frac = $m[2] ?? '';
        if (strlen($frac) > $scale) { throw new \DomainException('gd_invalid_amount'); }
        $frac = str_pad($frac, $scale, '0');
        if (strlen($int) > (15 - $scale)) { throw new \DomainException('gd_amount_out_of_range'); }
        return $scale > 0 ? "$int.$frac" : $int;
    }

    /**
     * Compara duas strings decimais canônicas não negativas sem convertê-las
     * para float. Retorna -1, 0 ou 1, como strcmp().
     */
    public static function decimalCompare(string $left, string $right): int
    {
        [$leftInt, $leftFrac] = array_pad(explode('.', $left, 2), 2, '');
        [$rightInt, $rightFrac] = array_pad(explode('.', $right, 2), 2, '');
        $leftInt = ltrim($leftInt, '0') ?: '0';
        $rightInt = ltrim($rightInt, '0') ?: '0';

        $lengthComparison = strlen($leftInt) <=> strlen($rightInt);
        if ($lengthComparison !== 0) { return $lengthComparison; }

        $integerComparison = strcmp($leftInt, $rightInt);
        if ($integerComparison !== 0) { return $integerComparison <=> 0; }

        $scale = max(strlen($leftFrac), strlen($rightFrac));
        $fractionComparison = strcmp(str_pad($leftFrac, $scale, '0'), str_pad($rightFrac, $scale, '0'));
        return $fractionComparison <=> 0;
    }

    private function __construct() {}
}
