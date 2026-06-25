<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_alunos_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_alunos";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");
        $unidades_table = $this->db->prefixTable("grupo_donato_unidades");
        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $alunos_table.id=$id";
        }

        $matricula = $this->_get_clean_value($options, "matricula");
        if ($matricula) {
            $where .= " AND $alunos_table.matricula=" . $this->db->escape($matricula);
        }

        $unidade_id = $this->_get_clean_value($options, "unidade_id");
        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=$unidade_id";
        }

        $turma = $this->_get_clean_value($options, "turma");
        if ($turma) {
            $where .= " AND $alunos_table.turma=" . $this->db->escape($turma);
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $alunos_table.status=" . $this->db->escape($status);
        }

        $status_in = get_array_value($options, "status_in");
        if ($status_in && is_array($status_in)) {
            $escaped = array_map(function ($item) {
                return $this->db->escape($item);
            }, $status_in);
            $where .= " AND $alunos_table.status IN (" . implode(",", $escaped) . ")";
        }

        $status_not_in = get_array_value($options, "status_not_in");
        if ($status_not_in && is_array($status_not_in)) {
            $escaped = array_map(function ($item) {
                return $this->db->escape($item);
            }, $status_not_in);
            $where .= " AND $alunos_table.status NOT IN (" . implode(",", $escaped) . ")";
        }

        $query = $this->_get_clean_value($options, "query");
        if ($query) {
            $query_like = $this->db->escapeLikeString($query);
            $where .= " AND (";
            $where .= "$alunos_table.matricula LIKE '%$query_like%' ESCAPE '!'";
            $where .= " OR $alunos_table.nome_aluno LIKE '%$query_like%' ESCAPE '!'";
            $where .= " OR $responsaveis_table.nome LIKE '%$query_like%' ESCAPE '!'";
            $where .= " OR $responsaveis_table.whats LIKE '%$query_like%' ESCAPE '!'";
            $where .= " OR $responsaveis_table.celular LIKE '%$query_like%' ESCAPE '!'";
            $where .= ")";
        }

        $sql = "SELECT $alunos_table.*,
                $responsaveis_table.nome AS responsavel_nome,
                $responsaveis_table.nascimento AS responsavel_nascimento,
                $responsaveis_table.rg AS responsavel_rg,
                $responsaveis_table.cpf AS responsavel_cpf,
                $responsaveis_table.whats AS responsavel_whats,
                $responsaveis_table.celular AS responsavel_celular,
                $responsaveis_table.email AS responsavel_email,
                $responsaveis_table.endereco AS responsavel_endereco,
                $responsaveis_table.numero AS responsavel_numero,
                $responsaveis_table.complemento AS responsavel_complemento,
                $responsaveis_table.bairro AS responsavel_bairro,
                $responsaveis_table.cep AS responsavel_cep,
                $responsaveis_table.cidade AS responsavel_cidade,
                $responsaveis_table.recado AS responsavel_recado,
                $unidades_table.nome_unidade,
                $unidades_table.slug AS unidade_slug,
                $unidades_table.cidade AS unidade_cidade
            FROM $alunos_table
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$alunos_table.responsavel_id
            LEFT JOIN $unidades_table ON $unidades_table.id=$alunos_table.unidade_id
            WHERE $alunos_table.deleted=0 $where
            ORDER BY $alunos_table.nome_aluno ASC";

        return $this->db->query($sql);
    }

    public function get_dashboard_counts($unidade_id = 0)
    {
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = "WHERE deleted=0";

        if ($unidade_id) {
            $where .= " AND unidade_id=" . (int) $unidade_id;
        }

        $sql = "SELECT
                SUM(CASE WHEN status='Ativo' THEN 1 ELSE 0 END) AS alunos_ativos,
                SUM(CASE WHEN status='Cancelado' THEN 1 ELSE 0 END) AS alunos_cancelados,
                SUM(CASE WHEN status='Concluido' THEN 1 ELSE 0 END) AS alunos_concluidos,
                SUM(CASE WHEN status='Ativo' AND (COALESCE(camiseta_status, camiseta, '')='' OR LOWER(COALESCE(camiseta_status, camiseta)) IN ('pendente','nao_entregue','não entregue','a ser pago','sem_registro')) THEN 1 ELSE 0 END) AS pendencia_uniforme,
                SUM(CASE WHEN status='Ativo' AND (COALESCE(material_01_status, material_01, '')='' OR LOWER(COALESCE(material_01_status, material_01)) IN ('pendente','nao_entregue','não entregue','a ser pago','sem_registro')) THEN 1 ELSE 0 END) AS pendencia_material_01,
                SUM(CASE WHEN status='Ativo' AND (COALESCE(material_02_status, material_02, '')='' OR LOWER(COALESCE(material_02_status, material_02)) IN ('pendente','nao_entregue','não entregue','a ser pago','sem_registro')) THEN 1 ELSE 0 END) AS pendencia_material_02
            FROM $alunos_table
            $where";

        return $this->db->query($sql)->getRow();
    }

    public function get_materials($options = [])
    {
        $options["status"] = $this->_get_clean_value($options, "status") ?: "Ativo";
        return $this->get_details($options);
    }
}
