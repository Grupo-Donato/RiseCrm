<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_prices_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_prices"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function get_details(array $options = []): array
    {
        $unit_id = (int) get_array_value($options, "unit_id");
        $pr = $this->table;
        $prod = $this->db->prefixTable("gd_products");
        $var = $this->db->prefixTable("gd_product_variants");
        $res = $this->db->prefixTable("gd_resources");

        $base = function () use ($options, $unit_id, $pr, $prod, $var, $res) {
            $b = $this->db->table($pr)
                ->join($prod, "$prod.id = $pr.product_id AND $prod.unit_id = $pr.unit_id AND $prod.deleted = 0", "left", false)
                ->join($var, "$var.id = $pr.variant_id AND $var.product_id = $pr.product_id AND $var.unit_id = $pr.unit_id AND $var.deleted = 0", "left", false)
                ->join($res, "$res.id = $pr.resource_id AND $res.unit_id = $pr.unit_id AND $res.deleted = 0", "left", false)
                ->where("$pr.unit_id", $unit_id)->where("$pr.deleted", 0);
            $id = (int) get_array_value($options, "id");
            if ($id) { $b->where("$pr.id", $id); }
            $list_id = (int) get_array_value($options, "price_list_id");
            if ($list_id) { $b->where("$pr.price_list_id", $list_id); }
            foreach (["product_id", "resource_id", "status"] as $field) {
                $value = get_array_value($options, $field);
                if ($value) { $b->where("$pr.$field", $value); }
            }
            $category_id = (int) get_array_value($options, "category_id");
            if ($category_id) { $b->where("$prod.category_id", $category_id); }
            $product_type = get_array_value($options, "product_type");
            if ($product_type) { $b->where("$prod.product_type", $product_type); }
            return $b;
        };

        $records_total = $this->db->table($pr)->where("unit_id", $unit_id)->where("deleted", 0);
        $list_id = (int) get_array_value($options, "price_list_id");
        if ($list_id) { $records_total->where("price_list_id", $list_id); }
        $records_total = $records_total->countAllResults();

        $records_filtered = $base()->countAllResults(false);
        $builder = $base()->select("$pr.*, $prod.name AS product_name, $prod.code AS product_code, $var.name AS variant_name, $res.name AS resource_name");
        $order_map = ["amount" => "$pr.amount", "minimum_quantity" => "$pr.minimum_quantity", "valid_from" => "$pr.valid_from", "status" => "$pr.status", "id" => "$pr.id", "product_name" => "$prod.name"];
        $order = $order_map[(string) get_array_value($options, "order_by")] ?? "$prod.name";
        $dir = get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC";
        $builder->orderBy($order, $dir)->orderBy("$pr.minimum_quantity", "ASC");
        $limit = max(1, min(100, (int) (get_array_value($options, "limit") ?: 25)));
        $builder->limit($limit, max(0, (int) get_array_value($options, "skip")));
        return ["data" => $builder->get()->getResult(), "recordsTotal" => $records_total, "recordsFiltered" => $records_filtered];
    }

    /** Preços vigentes (resumo) de um produto, para a tela de detalhe. */
    public function active_for_product(int $product_id, int $unit_id): array
    {
        $pr = $this->table;
        $pl = $this->db->prefixTable("gd_price_lists");
        $today = gmdate("Y-m-d");
        return $this->db->table($pr)
            ->select("$pr.*, $pl.name AS price_list_name, $pl.currency AS currency")
            ->join($pl, "$pl.id = $pr.price_list_id AND $pl.unit_id = $pr.unit_id AND $pl.deleted = 0 AND $pl.status = 'active'", "inner", false)
            ->where("$pr.product_id", $product_id)->where("$pr.unit_id", $unit_id)
            ->where("$pr.deleted", 0)->where("$pr.status", "active")
            ->where("COALESCE($pr.valid_from,'0000-01-01') <=", $today)
            ->where("COALESCE($pr.valid_until,'9999-12-31') >=", $today)
            ->where("COALESCE($pl.valid_from,'0000-01-01') <=", $today)
            ->where("COALESCE($pl.valid_until,'9999-12-31') >=", $today)
            ->orderBy("$pr.minimum_quantity", "ASC")->get()->getResult();
    }

    /** Preços específicos ativos de um recurso, para a tela de detalhe. */
    public function active_for_resource(int $resource_id, int $unit_id): array
    {
        $pr = $this->table;
        $prod = $this->db->prefixTable("gd_products");
        $pl = $this->db->prefixTable("gd_price_lists");
        $today = gmdate("Y-m-d");
        return $this->db->table($pr)
            ->select("$pr.*, $prod.name AS product_name")
            ->join($prod, "$prod.id = $pr.product_id AND $prod.unit_id = $pr.unit_id AND $prod.deleted = 0 AND $prod.status = 'active'", "inner", false)
            ->join($pl, "$pl.id = $pr.price_list_id AND $pl.unit_id = $pr.unit_id AND $pl.deleted = 0 AND $pl.status = 'active'", "inner", false)
            ->where("$pr.resource_id", $resource_id)->where("$pr.unit_id", $unit_id)
            ->where("$pr.deleted", 0)->where("$pr.status", "active")
            ->where("COALESCE($pr.valid_from,'0000-01-01') <=", $today)
            ->where("COALESCE($pr.valid_until,'9999-12-31') >=", $today)
            ->where("COALESCE($pl.valid_from,'0000-01-01') <=", $today)
            ->where("COALESCE($pl.valid_until,'9999-12-31') >=", $today)
            ->orderBy("$pr.minimum_quantity", "ASC")->get()->getResult();
    }

    /**
     * Preços ativos sobrepostos ao período informado, para o mesmo escopo exato
     * (lista, produto, variação null-aware, recurso null-aware, min_qty).
     * Usado pela detecção de sobreposição dentro de transação.
     *
     * @return object[]
     */
    public function overlapping(int $price_list_id, int $product_id, ?int $variant_id, ?int $resource_id, string $minimum_quantity, ?string $valid_from, ?string $valid_until, int $exclude_id = 0): array
    {
        $from = $valid_from ?: "0000-01-01";
        $until = $valid_until ?: "9999-12-31";
        $b = $this->db->table($this->table)
            ->where("price_list_id", $price_list_id)
            ->where("product_id", $product_id)
            ->where("IFNULL(variant_id,0) =", (int) $variant_id)
            ->where("IFNULL(resource_id,0) =", (int) $resource_id)
            ->where("minimum_quantity", $minimum_quantity)
            ->where("deleted", 0)->where("status", "active")
            ->where("COALESCE(valid_from,'0000-01-01') <=", $until)
            ->where("COALESCE(valid_until,'9999-12-31') >=", $from);
        if ($exclude_id) { $b->where("id !=", $exclude_id); }
        return $b->get()->getResult();
    }

    /**
     * Candidatos para resolução: preços ativos de um produto numa lista, válidos
     * na data, cuja min_qty não ultrapasse a quantidade. Filtro de escopo
     * (variação/recurso) é aplicado pelo PricingService por precedência.
     *
     * @return object[]
     */
    public function resolution_candidates(int $unit_id, int $price_list_id, int $product_id, string $reference_date, string $quantity): array
    {
        return $this->db->table($this->table)
            ->where("unit_id", $unit_id)
            ->where("price_list_id", $price_list_id)
            ->where("product_id", $product_id)
            ->where("deleted", 0)->where("status", "active")
            ->where("minimum_quantity <=", $quantity)
            ->where("COALESCE(valid_from,'0000-01-01') <=", $reference_date)
            ->where("COALESCE(valid_until,'9999-12-31') >=", $reference_date)
            ->get()->getResult();
    }
}
