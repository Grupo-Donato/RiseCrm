<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_comprovantes_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_comprovantes";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $comprovantes_table = $this->db->prefixTable("grupo_donato_comprovantes");
        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $comprovantes_table.id=$id";
        }

        $sql = "SELECT $comprovantes_table.*
            FROM $comprovantes_table
            WHERE $comprovantes_table.deleted=0 $where
            ORDER BY $comprovantes_table.id DESC";

        return $this->db->query($sql);
    }
}
