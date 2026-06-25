<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_school_profiles_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_school_profiles"); }
    public function get_scoped(int $id, int $unit_id): ?object { return $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->get(1)->getRow(); }
    public function by_person(int $person_id, int $unit_id): ?object { return $this->db->table($this->table)->where("person_id", $person_id)->where("unit_id", $unit_id)->where("deleted", 0)->get(1)->getRow(); }
}
