<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

class PriceListService extends CatalogDataService
{
    private $model;
    private CatalogDuplicateDetectionService $duplicates;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->model = model("grupo_donato_gestao\\Models\\Gd_price_lists_model");
        $this->duplicates = new CatalogDuplicateDetectionService($unit_id);
    }

    public function get(int $id): ?object { return $this->model->get_scoped($id, $this->unit_id); }

    /**
     * Busca Select2 de tabelas de preço ativas da unidade ativa. Paginação por
     * offset/limit.
     *
     * @return array<int,array<string,mixed>> linhas [id, code, name, currency]
     */
    public function options(string $q, int $limit = 20, int $offset = 0): array
    {
        $t = $this->db->prefixTable("gd_price_lists");
        $qb = $this->db->table($t)->select("id,code,name,currency")
            ->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active");
        $q = trim($q);
        if ($q !== "") { $qb->groupStart()->like("name", $q)->orLike("code", $q)->groupEnd(); }
        return $qb->orderBy("priority", "DESC")->orderBy("name")->limit(max(1, min(50, $limit)), max(0, $offset))->get()->getResultArray();
    }

    public function save(array $input, int $id = 0, bool $duplicate_override = false): array
    {
        $existing = $id ? $this->get($id) : null;
        if ($id && !$existing) { throw new \DomainException("gd_record_not_found"); }

        $code = DataNormalizationService::text($input["code"] ?? "");
        $name = DataNormalizationService::text($input["name"] ?? "");
        if ($code === "" || mb_strlen($code) > 40) { throw new \DomainException("gd_code_required"); }
        if ($name === "" || mb_strlen($name) > 150) { throw new \DomainException("gd_name_required"); }

        $currency = strtoupper(DataNormalizationService::text($input["currency"] ?? Constants::DEFAULT_CURRENCY)) ?: Constants::DEFAULT_CURRENCY;
        if (!Constants::isCurrency($currency)) { throw new \DomainException("gd_invalid_currency"); }

        $status = (string) ($input["status"] ?? "active");
        if (!Constants::isPriceListStatus($status)) { throw new \DomainException("gd_invalid_value"); }

        $valid_from = $this->valid_date($input["valid_from"] ?? "", true);
        $valid_until = $this->valid_date($input["valid_until"] ?? "", true);
        if ($valid_from && $valid_until && $valid_until < $valid_from) {
            throw new \DomainException("gd_invalid_date_range");
        }

        $priority = (int) ($input["priority"] ?? 0);
        $wants_default = !empty($input["is_default"]) && $status === "active";

        if ($this->model->is_duplicate_code($code, $this->unit_id, $id)) {
            throw new \DomainException("gd_duplicate_code");
        }

        $candidate = ["code" => $code, "name" => $name, "valid_from" => $valid_from, "valid_until" => $valid_until];
        $matches = $this->duplicates->priceLists($candidate, $id);
        $strong = array_values(array_filter($matches, static fn($m) => $m["confidence"] === "high"));
        if ($strong && !$duplicate_override) {
            return ["saved" => false, "duplicate_confirmation_required" => true, "duplicates" => $matches];
        }

        $data = $this->stamp([
            "unit_id" => $this->unit_id,
            "code" => $code,
            "name" => $name,
            "description" => DataNormalizationService::text($input["description"] ?? "") ?: null,
            "currency" => $currency,
            "priority" => $priority,
            "valid_from" => $valid_from,
            "valid_until" => $valid_until,
            "is_default" => 0,
            "status" => $status,
        ], $id === 0);

        $before = $existing ? (array) $existing : null;
        $save = (int) $this->model->ci_save($data, $id);
        if (!$save) { throw new \RuntimeException("save_failed"); }

        if ($wants_default) {
            $this->model->mark_as_default($save, $this->unit_id);
        }

        $after = (array) $this->get($save);
        $this->audit_change(!$id ? "create" : "update", "price_list", $save, $before, $after);
        if ((int) ($before["is_default"] ?? 0) !== (int) $after["is_default"]) {
            $this->audit_change("default_change", "price_list", $save, null, null, ["unit_id" => $this->unit_id, "is_default" => (int) $after["is_default"]]);
        }
        if ($id && (string) ($before["status"] ?? "") !== (string) $after["status"]) {
            $this->audit_change("status_change", "price_list", $save, null, null, ["from" => $before["status"] ?? null, "to" => $after["status"]]);
        }
        if ($strong && $duplicate_override) {
            $this->audit_change("duplicate_override", "price_list", $save, null, null, ["matches" => array_column($matches, "record_id")]);
        }
        return ["saved" => true, "id" => $save, "duplicates" => $matches];
    }

    public function delete(int $id): void
    {
        $row = $this->get($id);
        if (!$row) { throw new \DomainException("gd_record_not_found"); }
        if ($this->model->active_price_count($id, $this->unit_id) > 0) {
            throw new \DomainException("gd_price_list_has_prices");
        }
        $this->model->delete($id);
        $this->audit_change("delete", "price_list", $id, (array) $row, null);
    }
}
