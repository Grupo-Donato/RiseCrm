<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_person_unit_access_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_person_unit_access";
        parent::__construct($this->table);
    }

    public function get_access($user_id, $unit_id = 0)
    {
        $access_table = $this->db->prefixTable("grupo_donato_person_unit_access");
        $where = " AND $access_table.user_id=" . (int) $user_id;

        if ($unit_id) {
            $where .= " AND $access_table.unit_id=" . (int) $unit_id;
        }

        $sql = "SELECT $access_table.*
            FROM $access_table
            WHERE $access_table.deleted=0 $where
            ORDER BY FIELD($access_table.role, 'owner', 'director', 'manager', 'staff', 'viewer') ASC";

        return $this->db->query($sql);
    }

    public function has_any_configured_access()
    {
        $access_table = $this->db->prefixTable("grupo_donato_person_unit_access");
        $row = $this->db->query("SELECT id FROM $access_table WHERE deleted=0 LIMIT 1")->getRow();

        return (bool) $row;
    }
}
