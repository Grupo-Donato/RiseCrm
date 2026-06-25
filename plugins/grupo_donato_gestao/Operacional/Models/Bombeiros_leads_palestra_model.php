<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_leads_palestra_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_leads_palestra";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $leads_table = $this->db->prefixTable("grupo_donato_leads_palestra");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");
        $where = "";

        if (!empty($options["hide_enrolled_contacts"])) {
            $where .= $this->_not_enrolled_contact_constraint($leads_table);
        }

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $leads_table.id=" . (int) $id;
        }

        $unit_id = $this->_get_clean_value($options, "unit_id");
        if ($unit_id) {
            $where .= " AND $leads_table.unit_id=" . (int) $unit_id;
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $leads_table.status=" . $this->db->escape($status);
        }

        $telefone = $this->_get_clean_value($options, "telefone_normalizado");
        if ($telefone) {
            $where .= " AND $leads_table.telefone_normalizado=" . $this->db->escape($telefone);
        }

        $sql = "SELECT $leads_table.*,
                $alunos_table.matricula,
                $alunos_table.nome_aluno AS aluno_matriculado_nome,
                $responsaveis_table.nome AS responsavel_vinculado_nome
            FROM $leads_table
            LEFT JOIN $alunos_table ON $alunos_table.id=$leads_table.aluno_id
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$leads_table.responsavel_id
            WHERE $leads_table.deleted=0 $where
            ORDER BY $leads_table.data_evento DESC, $leads_table.responsavel_nome ASC, $leads_table.aluno_nome ASC";

        return $this->db->query($sql);
    }

    public function get_totals($unit_id = 0, $mes_referencia = 0, $ano_referencia = 0)
    {
        $leads_table = $this->db->prefixTable("grupo_donato_leads_palestra");
        $where = "WHERE $leads_table.deleted=0";

        if ($unit_id) {
            $where .= " AND $leads_table.unit_id=" . (int) $unit_id;
        }
        if ($mes_referencia) {
            $where .= " AND MONTH($leads_table.data_evento)=" . (int) $mes_referencia;
        }
        if ($ano_referencia) {
            $where .= " AND YEAR($leads_table.data_evento)=" . (int) $ano_referencia;
        }

        $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status='matriculado' THEN 1 ELSE 0 END) AS matriculados,
                SUM(CASE WHEN status IN ('nao_matriculado', 'perdido') THEN 1 ELSE 0 END) AS nao_matriculados,
                SUM(CASE WHEN status='em_negociacao' THEN 1 ELSE 0 END) AS em_negociacao,
                SUM(CASE WHEN status='sem_status' THEN 1 ELSE 0 END) AS sem_status
            FROM $leads_table
            $where";

        return $this->db->query($sql)->getRow();
    }

    private function _not_enrolled_contact_constraint($leads_table)
    {
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");

        if (!$this->db->tableExists($alunos_table) || !$this->db->tableExists($responsaveis_table)) {
            return "";
        }

        $lead_phone = $this->_lead_phone_match_expr($leads_table);
        $contact_matches = [];

        foreach (["whats", "celular", "recado"] as $field) {
            $responsavel_phone = $this->_phone_match_expr("enrolled_responsaveis.$field");
            $contact_matches[] = "($lead_phone!='' AND $responsavel_phone!='' AND $responsavel_phone=$lead_phone)";
        }

        foreach (["telefone_1", "telefone_2"] as $field) {
            $aluno_phone = $this->_phone_match_expr("enrolled_alunos.$field");
            $contact_matches[] = "($lead_phone!='' AND $aluno_phone!='' AND $aluno_phone=$lead_phone)";
        }

        return " AND NOT EXISTS (
                SELECT 1
                FROM $alunos_table AS enrolled_alunos
                LEFT JOIN $responsaveis_table AS enrolled_responsaveis
                    ON enrolled_responsaveis.id=enrolled_alunos.responsavel_id
                    AND enrolled_responsaveis.deleted=0
                WHERE enrolled_alunos.deleted=0
                    AND (" . implode(" OR ", $contact_matches) . ")
            )";
    }

    private function _lead_phone_match_expr($table)
    {
        return $this->_phone_match_expr("COALESCE(NULLIF($table.telefone_normalizado, ''), $table.telefone)");
    }

    private function _phone_match_expr($value_expr)
    {
        $digits = $this->_digits_expr($value_expr);
        $without_country_code = "CASE WHEN CHAR_LENGTH($digits)>11 AND LEFT($digits, 2)='55' THEN SUBSTRING($digits, 3) ELSE $digits END";

        return "TRIM(LEADING '0' FROM ($without_country_code))";
    }

    private function _digits_expr($value_expr)
    {
        $expr = "COALESCE($value_expr, '')";
        foreach (["+", " ", "-", "(", ")", ".", "/"] as $char) {
            $expr = "REPLACE($expr, " . $this->db->escape($char) . ", '')";
        }

        return $expr;
    }
}
