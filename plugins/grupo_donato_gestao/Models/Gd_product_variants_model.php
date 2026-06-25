<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_product_variants_model extends Gd_Model
{
    protected array $searchable_fields = ["code", "name", "barcode"];

    public function __construct() { parent::__construct("gd_product_variants"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /**
     * Variações de um produto (lista curta, fixa por produto). Client-side.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_details(array $options = [])
    {
        $v = $this->table;
        $builder = $this->db->table($v)->where("$v.deleted", 0);

        $id = get_array_value($options, "id");
        if ($id) { $builder->where("$v.id", $id); }

        $product_id = get_array_value($options, "product_id");
        if ($product_id) { $builder->where("$v.product_id", $product_id); }

        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id) { $builder->where("$v.unit_id", $unit_id); }

        $builder->orderBy("$v.sort_order", "ASC")->orderBy("$v.name", "ASC");
        return $builder->get();
    }

    public function is_duplicate_code(string $code, int $product_id, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("code", trim($code))->where("product_id", $product_id)->where("deleted", 0);
        if ($exclude_id) { $builder->where("id !=", $exclude_id); }
        return $builder->countAllResults() > 0;
    }

    public function active_price_count(int $id, int $unit_id): int
    {
        $prices = $this->db->prefixTable("gd_prices");
        return $this->db->table($prices)->where("variant_id", $id)->where("unit_id", $unit_id)
            ->where("deleted", 0)->where("status", "active")->countAllResults();
    }

    /**
     * Garante no máximo uma variação padrão ativa por produto (transacional).
     */
    public function mark_as_default(int $id, int $product_id): void
    {
        $lock_name = "gd_variant_default_" . $product_id;
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$lock_name])->getRow();
        if (!$lock || (int) $lock->l !== 1) {
            throw new \RuntimeException("Could not acquire default-variant lock.");
        }
        try {
            $this->db->transStart();
            $this->db->table($this->table)->where("product_id", $product_id)->where("id !=", $id)->update(["is_default" => 0]);
            $this->db->table($this->table)->where("id", $id)->where("deleted", 0)->update(["is_default" => 1]);
            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Could not set the default variant.");
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }
    }
}
