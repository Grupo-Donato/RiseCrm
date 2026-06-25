<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_responsaveis_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_responsaveis";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = "";
        $join = "";
        $select = "$responsaveis_table.*";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $responsaveis_table.id=$id";
        }

        $whats = $this->_get_clean_value($options, "whats");
        if ($whats) {
            $where .= " AND $responsaveis_table.whats=" . $this->db->escape($whats);
        }

        $cpf = $this->_get_clean_value($options, "cpf");
        if ($cpf) {
            $where .= " AND $responsaveis_table.cpf=" . $this->db->escape($cpf);
        }

        $unidade_id = $this->_get_clean_value($options, "unidade_id");
        if ($unidade_id) {
            $join = " INNER JOIN $alunos_table ON $alunos_table.responsavel_id=$responsaveis_table.id AND $alunos_table.deleted=0 AND $alunos_table.unidade_id=" . (int) $unidade_id;
            $select = "DISTINCT $responsaveis_table.*";
        }

        $sql = "SELECT $select
            FROM $responsaveis_table
            $join
            WHERE $responsaveis_table.deleted=0 $where
            ORDER BY $responsaveis_table.nome ASC";

        return $this->db->query($sql);
    }
}
