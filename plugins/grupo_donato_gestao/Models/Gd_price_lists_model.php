<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_price_lists_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name"];

    public function __construct() { parent::__construct("gd_price_lists"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /**
     * Listas de preço com contagem de preços. Tabela pequena → client-side.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details(array $options = [])
    {
        $pl = $this->table;
        $prices = $this->db->prefixTable("gd_prices");
        $builder = $this->db->table($pl);
        $builder->select("$pl.*, (SELECT COUNT(*) FROM $prices p WHERE p.price_list_id = $pl.id AND p.unit_id = $pl.unit_id AND p.deleted = 0) AS price_count", false);
        $builder->where("$pl.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) { $builder->where("$pl.id", $id); }

        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id) { $builder->where("$pl.unit_id", $unit_id); }

        $status = get_array_value($options, "status");
        if ($status) { $builder->where("$pl.status", $status); }

        $builder->orderBy("$pl.priority", "DESC")->orderBy("$pl.name", "ASC");
        return $builder->get();
    }

    public function is_duplicate_code(string $code, int $unit_id, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("code", trim($code))->where("unit_id", $unit_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->countAllResults() > 0;
    }

    public function get_default(int $unit_id): ?object
    {
        return $this->db->table($this->table)
            ->where("unit_id", $unit_id)->where("deleted", 0)->where("status", "active")->where("is_default", 1)
            ->get(1)->getRow();
    }

    public function active_price_count(int $id, int $unit_id): int
    {
        $prices = $this->db->prefixTable("gd_prices");
        return $this->db->table($prices)->where("price_list_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status", "active")->countAllResults();
    }

    /**
     * Garante no máximo uma lista padrão ativa por unidade (transacional).
     */
    public function mark_as_default(int $id, int $unit_id): void
    {
        $lock_name = "gd_price_list_default_" . $unit_id;
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$lock_name])->getRow();
        if (!$lock || (int) $lock->l !== 1) {
            throw new \RuntimeException("Could not acquire default-price-list lock.");
        }
        try {
            $this->db->transStart();
            $this->db->table($this->table)->where("unit_id", $unit_id)->where("id !=", $id)->update(["is_default" => 0]);
            $this->db->table($this->table)->where("id", $id)->where("deleted", 0)->update(["is_default" => 1]);
            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Could not set the default price list.");
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }
    }
}
