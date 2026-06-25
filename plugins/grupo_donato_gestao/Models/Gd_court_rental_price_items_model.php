<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_court_rental_price_items_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_court_rental_price_items"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /** @return array<object> */
    public function for_rental(int $rental_id, int $unit_id, bool $include_deleted = false): array
    {
        $builder = $this->db->table($this->table)->where("rental_id", $rental_id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->orderBy("id", "ASC")->get()->getResult();
    }
}
