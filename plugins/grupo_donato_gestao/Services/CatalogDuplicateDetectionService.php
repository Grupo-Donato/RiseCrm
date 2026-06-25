<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Detecção assistiva de duplicidade do catálogo (Fase 2B).
 *
 * Mantido separado de DuplicateDetectionService (Fase 2A — contas/pessoas) para
 * preservar coesão. Retorna apenas ALERTAS; o bloqueio de duplicidade exata de
 * código/escopo é feito pelos services/índices únicos. Override é permitido só
 * para alertas não exatos (nome/atributos semelhantes) e é auditado.
 */
class CatalogDuplicateDetectionService
{
    private $db;
    private int $unit_id;

    public function __construct(int $unit_id)
    {
        $this->db = db_connect();
        $this->unit_id = $unit_id;
    }

    /** Mesmo código (exato) ou mesmo nome sob a mesma categoria pai. */
    public function categories(array $data, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_product_categories");
        $name = DataNormalizationService::name($data["name"] ?? "");
        $parent = $data["parent_id"] ?? null;
        $result = [];
        foreach ($this->candidates($table, $exclude_id) as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["code"]) && strcasecmp((string) $data["code"], (string) $row->code) === 0) {
                $matched[] = "code"; $confidence = "exact";
            }
            $sameParent = (int) ($parent ?: 0) === (int) ($row->parent_id ?: 0);
            if ($name !== "" && $sameParent && $name === DataNormalizationService::name($row->name)) {
                $matched[] = "name"; if ($confidence !== "exact") { $confidence = "high"; }
            }
            if ($matched) {
                $result[] = $this->row($row->id, "product_category", $confidence, $matched, $row->name);
            }
        }
        return $result;
    }

    /** Mesmo código (exato) ou nome semelhante na unidade. */
    public function resources(array $data, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_resources");
        $name = DataNormalizationService::name($data["name"] ?? "");
        $result = [];
        foreach ($this->candidates($table, $exclude_id) as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["code"]) && strcasecmp((string) $data["code"], (string) $row->code) === 0) {
                $matched[] = "code"; $confidence = "exact";
            }
            $other = DataNormalizationService::name($row->name);
            if ($name !== "" && $name === $other) {
                $matched[] = "name"; if ($confidence !== "exact") { $confidence = "medium"; }
            } elseif (self::similar($name, $other)) {
                $matched[] = "similar_name";
            }
            if ($matched) {
                $result[] = $this->row($row->id, "resource", $confidence, $matched, $row->name);
            }
        }
        return $result;
    }

    /** Mesmo código (exato), mesmo nome+tipo, ou mesmo vínculo com item do Rise. */
    public function products(array $data, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_products");
        $name = DataNormalizationService::name($data["name"] ?? "");
        $type = (string) ($data["product_type"] ?? "");
        $riseId = (int) ($data["rise_item_id"] ?? 0);
        $result = [];
        foreach ($this->candidates($table, $exclude_id) as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["code"]) && strcasecmp((string) $data["code"], (string) $row->code) === 0) {
                $matched[] = "code"; $confidence = "exact";
            }
            if ($name !== "" && $name === DataNormalizationService::name($row->name)) {
                $matched[] = "name";
                if ($type !== "" && $type === (string) $row->product_type) {
                    $matched[] = "type"; if ($confidence !== "exact") { $confidence = "high"; }
                } elseif ($confidence === "low") {
                    $confidence = "medium";
                }
            }
            if ($riseId > 0 && $riseId === (int) ($row->rise_item_id ?? 0)) {
                $matched[] = "rise_item"; if ($confidence === "low") { $confidence = "medium"; }
            }
            if ($matched) {
                $result[] = $this->row($row->id, "product", $confidence, $matched, $row->name);
            }
        }
        return $result;
    }

    /** Mesmo código dentro do produto ou mesmos atributos normalizados. */
    public function variants(array $data, int $product_id, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_product_variants");
        $attrs = self::normalizeAttributes($data["attributes"] ?? null);
        $builder = $this->db->table($table)->where("product_id", $product_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        $result = [];
        foreach ($builder->limit(200)->get()->getResult() as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["code"]) && strcasecmp((string) $data["code"], (string) $row->code) === 0) {
                $matched[] = "code"; $confidence = "exact";
            }
            if ($attrs !== "" && $attrs === self::normalizeAttributes($row->attributes)) {
                $matched[] = "attributes"; if ($confidence !== "exact") { $confidence = "high"; }
            }
            if ($matched) {
                $result[] = $this->row($row->id, "product_variant", $confidence, $matched, $row->name);
            }
        }
        return $result;
    }

    /** Mesmo código (exato) ou mesmo nome e mesmo período. */
    public function priceLists(array $data, int $exclude_id = 0): array
    {
        $table = $this->db->prefixTable("gd_price_lists");
        $name = DataNormalizationService::name($data["name"] ?? "");
        $result = [];
        foreach ($this->candidates($table, $exclude_id) as $row) {
            $matched = [];
            $confidence = "low";
            if (!empty($data["code"]) && strcasecmp((string) $data["code"], (string) $row->code) === 0) {
                $matched[] = "code"; $confidence = "exact";
            }
            $samePeriod = ((string) ($data["valid_from"] ?? "") === (string) ($row->valid_from ?? ""))
                && ((string) ($data["valid_until"] ?? "") === (string) ($row->valid_until ?? ""));
            if ($name !== "" && $name === DataNormalizationService::name($row->name) && $samePeriod) {
                $matched[] = "name_period"; if ($confidence !== "exact") { $confidence = "high"; }
            }
            if ($matched) {
                $result[] = $this->row($row->id, "price_list", $confidence, $matched, $row->name);
            }
        }
        return $result;
    }

    private function candidates(string $table, int $exclude_id): array
    {
        $builder = $this->db->table($table)->where("unit_id", $this->unit_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->orderBy("id", "DESC")->limit(200)->get()->getResult();
    }

    private function row(int|string $id, string $entity, string $confidence, array $fields, $summary): array
    {
        return [
            "record_id" => (int) $id,
            "entity_type" => $entity,
            "confidence" => $confidence,
            "matched_fields" => array_values(array_unique($fields)),
            "display_summary" => (string) $summary,
        ];
    }

    /** Normaliza JSON de atributos para comparação estável (chaves ordenadas). */
    public static function normalizeAttributes($value): string
    {
        if (is_string($value)) { $value = json_decode($value, true); }
        if (!is_array($value)) { return ""; }
        ksort($value);
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : "";
    }

    private static function similar(string $a, string $b): bool
    {
        if ($a === "" || $b === "") { return false; }
        similar_text($a, $b, $percentage);
        return $percentage >= 85.0 || levenshtein($a, $b) <= 2;
    }
}
