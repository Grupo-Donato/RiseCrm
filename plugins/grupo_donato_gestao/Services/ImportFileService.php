<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Leitura e normalização de arquivos de importação (Fase 6).
 *
 * Reutiliza o PhpSpreadsheet JÁ EMBARCADO no Rise (não copia a biblioteca nem
 * adiciona dependência): lê XLSX e CSV. Calcula hash do arquivo, armazena uma
 * cópia controlada e normaliza datas, valores e métodos preservando o valor
 * bruto da célula. Nenhuma escrita de domínio acontece aqui.
 */
final class ImportFileService
{
    /** Lê o arquivo (XLSX/CSV) e devolve ['header'=>[...], 'rows'=>[[...],...]]. */
    public function read(string $path): array
    {
        if (!is_file($path)) { throw new \DomainException("gd_import_file_unreadable"); }
        $autoload = rtrim(APPPATH, "/\\") . "/ThirdParty/PHPOffice-PhpSpreadsheet/vendor/autoload.php";
        if (!is_file($autoload)) { throw new \DomainException("gd_import_reader_unavailable"); }
        require_once $autoload;
        try {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext === "csv") {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
                $reader->setDelimiter($this->sniffDelimiter($path));
                $reader->setInputEncoding("UTF-8");
                $spreadsheet = $reader->load($path);
            } else {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            }
            $grid = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw new \DomainException("gd_import_file_unreadable");
        }
        $grid = array_values(array_filter($grid, static fn($r) => is_array($r) && array_filter($r, static fn($c) => trim((string) $c) !== "")));
        if (!$grid) { throw new \DomainException("gd_import_file_empty"); }
        $header = array_map(static fn($c) => trim((string) $c), array_shift($grid));
        return ["header" => $header, "rows" => array_values($grid)];
    }

    private function sniffDelimiter(string $path): string
    {
        $handle = @fopen($path, "r");
        if (!$handle) { return ","; }
        $line = (string) fgets($handle);
        fclose($handle);
        return substr_count($line, ";") > substr_count($line, ",") ? ";" : ",";
    }

    public function hashFile(string $path): string { return hash_file("sha256", $path) ?: ""; }

    /** Move/copia o upload para um local controlado do plugin e devolve metadados. */
    public function store(string $tmp_path, string $original_name, int $unit_id, string $hash): array
    {
        $dir = rtrim(WRITEPATH, "/\\") . "/uploads/gd_imports/" . $unit_id;
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION)) ?: "dat";
        $ext = preg_match('/^[a-z0-9]{1,5}$/', $ext) ? $ext : "dat";
        $stored = $dir . "/" . $hash . "." . $ext;
        if (!is_file($stored)) {
            if (is_uploaded_file($tmp_path)) { move_uploaded_file($tmp_path, $stored); }
            else { copy($tmp_path, $stored); }
        }
        return ["stored_path" => $stored, "size" => (int) @filesize($stored)];
    }

    /* ---------------- normalização ---------------- */

    /** Mapeia o cabeçalho do arquivo para os campos canônicos do importador. */
    public function autoMap(array $header, array $defs): array
    {
        $map = [];
        $normalized = [];
        foreach ($header as $index => $label) { $normalized[$index] = DataNormalizationService::name((string) $label); }
        foreach ($defs as $def) {
            foreach ((array) $def["aliases"] as $alias) {
                $alias = DataNormalizationService::name((string) $alias);
                $found = array_search($alias, $normalized, true);
                if ($found !== false) { $map[$def["field"]] = (int) $found; break; }
            }
        }
        return $map;
    }

    /** Constrói a linha associativa {campo: valor bruto} a partir do mapeamento. */
    public function applyMapping(array $row, array $map): array
    {
        $out = [];
        foreach ($map as $field => $index) { $out[$field] = isset($row[$index]) ? trim((string) $row[$index]) : ""; }
        return $out;
    }

    public function date($value): ?string
    {
        $value = trim((string) $value);
        if ($value === "") { return null; }
        if (is_numeric($value) && (float) $value > 59 && (float) $value < 80000) {
            try {
                require_once rtrim(APPPATH, "/\\") . "/ThirdParty/PHPOffice-PhpSpreadsheet/vendor/autoload.php";
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format("Y-m-d");
            } catch (\Throwable $e) { return null; }
        }
        $value = str_replace([".", "\\"], ["/", "/"], $value);
        foreach (["Y-m-d", "d/m/Y", "d/m/y", "m/d/Y", "Y/m/d", "d-m-Y"] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat("!" . $fmt, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d && (!$errors || (!$errors["warning_count"] && !$errors["error_count"]))) { return $d->format("Y-m-d"); }
        }
        return null;
    }

    /** Valor monetário canônico (absoluto, 2 casas) ou null se inválido. */
    public function amount($value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === "") { return null; }
        $raw = preg_replace('/[^\d.,-]/u', "", $raw);
        if ($raw === "" || $raw === "-") { return null; }
        $raw = ltrim($raw, "-");
        $lastComma = strrpos($raw, ",");
        $lastDot = strrpos($raw, ".");
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) { $raw = str_replace(".", "", $raw); $raw = str_replace(",", ".", $raw); }
            else { $raw = str_replace(",", "", $raw); }
        } elseif ($lastComma !== false) {
            $raw = str_replace(",", ".", $raw);
        }
        try { return DataNormalizationService::decimal($raw, 2, true); }
        catch (\Throwable $e) { return null; }
    }

    public function isNegative($value): bool { return str_starts_with(ltrim((string) $value), "-"); }

    /** Mês de referência canônico (Y-m) a partir de Y-m, m/Y, ou data completa; null se inválido. */
    public function referenceMonth($value): ?string
    {
        $value = trim((string) $value);
        if ($value === "") { return null; }
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $value)) { return $value; }
        if (preg_match('#^(0?[1-9]|1[0-2])[/-](\d{4})$#', $value, $m)) { return $m[2] . "-" . str_pad($m[1], 2, "0", STR_PAD_LEFT); }
        $date = $this->date($value);
        return $date ? substr($date, 0, 7) : null;
    }

    /** Chave estável da linha (para dedupe e rastreabilidade). */
    public function sourceKey(string $import_type, array $parts): string
    {
        return hash("sha256", $import_type . "|" . implode("|", array_map(static fn($p) => DataNormalizationService::name((string) $p), $parts)));
    }
}
