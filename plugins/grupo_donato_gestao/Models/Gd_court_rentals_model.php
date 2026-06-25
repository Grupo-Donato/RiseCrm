<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_court_rentals_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_court_rentals"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function optimistic_update(int $id, int $unit_id, int $lock_version, array $data): bool
    {
        $data["lock_version"] = $lock_version + 1;
        $data["updated_at"] = gmdate("Y-m-d H:i:s");
        $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->where("lock_version", $lock_version)->update($data);
        return $this->db->affectedRows() === 1;
    }
}
