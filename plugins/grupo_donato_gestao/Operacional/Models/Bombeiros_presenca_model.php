<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_presenca_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_presenca";
        parent::__construct($this->table);
    }

    public function get_by_date($data_aula, $unidade_id = 0)
    {
        $presenca_table = $this->db->prefixTable("grupo_donato_presenca");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = " AND $presenca_table.data_aula=" . $this->db->escape($data_aula);

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=" . (int) $unidade_id;
        }

        $sql = "SELECT $presenca_table.*
            FROM $presenca_table
            INNER JOIN $alunos_table ON $alunos_table.id=$presenca_table.aluno_id
            WHERE $alunos_table.deleted=0 $where";

        return $this->db->query($sql)->getResult();
    }

    public function get_totals($unidade_id = 0, $mes_referencia = 0, $ano_referencia = 0)
    {
        $presenca_table = $this->db->prefixTable("grupo_donato_presenca");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = "";

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=" . (int) $unidade_id;
        }
        if ($mes_referencia) {
            $where .= " AND MONTH($presenca_table.data_aula)=" . (int) $mes_referencia;
        }
        if ($ano_referencia) {
            $where .= " AND YEAR($presenca_table.data_aula)=" . (int) $ano_referencia;
        }

        $sql = "SELECT
                SUM(CASE WHEN $presenca_table.status_tipo='presente' OR ($presenca_table.status=1 AND ($presenca_table.status_tipo IS NULL OR $presenca_table.status_tipo='presente')) THEN 1 ELSE 0 END) AS presencas,
                SUM(CASE WHEN $presenca_table.status_tipo='falta' OR ($presenca_table.status=0 AND ($presenca_table.status_tipo IS NULL OR $presenca_table.status_tipo='falta')) THEN 1 ELSE 0 END) AS faltas,
                SUM(CASE WHEN $presenca_table.status_tipo='feriado' THEN 1 ELSE 0 END) AS feriados,
                SUM(CASE WHEN $presenca_table.status_tipo='aula_cancelada' THEN 1 ELSE 0 END) AS aulas_canceladas,
                SUM(CASE WHEN $presenca_table.status_tipo='sem_registro' THEN 1 ELSE 0 END) AS sem_registro
            FROM $presenca_table
            INNER JOIN $alunos_table ON $alunos_table.id=$presenca_table.aluno_id
            WHERE $alunos_table.deleted=0 $where";

        return $this->db->query($sql)->getRow();
    }
}
