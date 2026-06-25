<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_unidades_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_unidades";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $unidades_table = $this->db->prefixTable("grupo_donato_unidades");
        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $unidades_table.id=$id";
        }

        $slug = $this->_get_clean_value($options, "slug");
        if ($slug) {
            $where .= " AND $unidades_table.slug=" . $this->db->escape($slug);
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $unidades_table.status=" . $this->db->escape($status);
        }

        $is_default = $this->_get_clean_value($options, "is_default");
        if ($is_default !== null && $is_default !== "") {
            $where .= " AND $unidades_table.is_default=" . (int) $is_default;
        }

        $sql = "SELECT $unidades_table.*
            FROM $unidades_table
            WHERE $unidades_table.deleted=0 $where
            ORDER BY $unidades_table.is_default DESC, $unidades_table.nome_unidade ASC";

        return $this->db->query($sql);
    }
}
