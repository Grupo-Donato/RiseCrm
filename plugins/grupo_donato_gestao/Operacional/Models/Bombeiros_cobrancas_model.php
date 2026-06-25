<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_cobrancas_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_cobrancas";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $cobrancas_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");
        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $cobrancas_table.id=$id";
        }

        $aluno_id = $this->_get_clean_value($options, "aluno_id");
        if ($aluno_id) {
            $where .= " AND $cobrancas_table.aluno_id=$aluno_id";
        }

        $unidade_id = $this->_get_clean_value($options, "unidade_id");
        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=" . (int) $unidade_id;
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $cobrancas_table.status=" . $this->db->escape($status);
        }

        $competencia = $this->_get_clean_value($options, "competencia");
        if ($competencia) {
            $where .= " AND $cobrancas_table.competencia=" . $this->db->escape($competencia);
        }

        $tipo = $this->_get_clean_value($options, "tipo");
        if ($tipo) {
            $where .= " AND $cobrancas_table.tipo=" . $this->db->escape($tipo);
        }

        $mes_referencia = $this->_get_clean_value($options, "mes_referencia");
        if ($mes_referencia) {
            $where .= " AND COALESCE($cobrancas_table.mes_referencia, MONTH($cobrancas_table.vencimento))=" . (int) $mes_referencia;
        }

        $ano_referencia = $this->_get_clean_value($options, "ano_referencia");
        if ($ano_referencia) {
            $where .= " AND COALESCE($cobrancas_table.ano_referencia, YEAR($cobrancas_table.vencimento))=" . (int) $ano_referencia;
        }

        $turma = $this->_get_clean_value($options, "turma");
        if ($turma) {
            $where .= " AND $alunos_table.turma=" . $this->db->escape($turma);
        }

        if (get_array_value($options, "overdue")) {
            $where .= " AND $cobrancas_table.vencimento < " . $this->db->escape(date("Y-m-d"));
            $where .= " AND $cobrancas_table.status IN ('Pendente','Vencido')";
        }

        $sql = "SELECT $cobrancas_table.*,
                $alunos_table.nome_aluno,
                $alunos_table.responsavel_id,
                $alunos_table.matricula,
                $alunos_table.turma,
                $responsaveis_table.nome AS responsavel_nome,
                $responsaveis_table.cpf AS responsavel_cpf,
                $responsaveis_table.whats AS responsavel_whats
            FROM $cobrancas_table
            INNER JOIN $alunos_table ON $alunos_table.id=$cobrancas_table.aluno_id
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$alunos_table.responsavel_id
            WHERE $alunos_table.deleted=0 $where
            ORDER BY $alunos_table.nome_aluno ASC, $cobrancas_table.vencimento ASC";

        return $this->db->query($sql);
    }

    public function get_pagamentos_mensais_alunos($options = [])
    {
        $cobrancas_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");

        $mes_referencia = (int) ($this->_get_clean_value($options, "mes_referencia") ?: date("m"));
        $ano_referencia = (int) ($this->_get_clean_value($options, "ano_referencia") ?: date("Y"));
        $unidade_id = (int) $this->_get_clean_value($options, "unidade_id");
        $status_pagamento = $this->_get_clean_value($options, "status_pagamento");
        $turma = $this->_get_clean_value($options, "turma");
        $hoje = $this->db->escape(date("Y-m-d"));
        $fim_competencia = $this->db->escape(date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $ano_referencia, $mes_referencia))));
        $inicio_cobranca_sql = "COALESCE($alunos_table.data_primeira_parcela, $alunos_table.data_inicio, $alunos_table.data_matricula, DATE($alunos_table.created_at))";
        $status_receber_sql = "$cobrancas_table.status IN ('Pendente','Vencido')";

        $where = " AND $alunos_table.status='Ativo'";
        $where .= " AND ($inicio_cobranca_sql IS NULL OR $inicio_cobranca_sql <= $fim_competencia)";

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=$unidade_id";
        }

        if ($turma) {
            $where .= " AND $alunos_table.turma=" . $this->db->escape($turma);
        }

        if ($status_pagamento === "pago") {
            $where .= " AND $cobrancas_table.id IS NOT NULL AND $cobrancas_table.status='Pago'";
        } elseif ($status_pagamento === "aberto") {
            $where .= " AND $cobrancas_table.id IS NOT NULL AND $status_receber_sql AND $cobrancas_table.status!='Vencido' AND $cobrancas_table.vencimento >= $hoje";
        } elseif ($status_pagamento === "vencido") {
            $where .= " AND $cobrancas_table.id IS NOT NULL AND $status_receber_sql AND ($cobrancas_table.status='Vencido' OR $cobrancas_table.vencimento < $hoje)";
        } elseif ($status_pagamento === "sem_cobranca") {
            $where .= " AND $cobrancas_table.id IS NULL";
        } else {
            $where .= " AND $cobrancas_table.id IS NOT NULL";
        }

        $mensalidade_mes_sql = "SELECT aluno_id,
                COALESCE(NULLIF(MAX(CASE WHEN status='Pago' THEN id ELSE 0 END), 0), MAX(id)) AS cobranca_id
            FROM $cobrancas_table
            WHERE tipo='Mensalidade'
                AND COALESCE(mes_referencia, MONTH(vencimento))=$mes_referencia
                AND COALESCE(ano_referencia, YEAR(vencimento))=$ano_referencia
            GROUP BY aluno_id";

        $sql = "SELECT
                $alunos_table.id AS aluno_id,
                $alunos_table.matricula,
                $alunos_table.nome_aluno,
                $alunos_table.turma,
                $alunos_table.pelotao,
                $alunos_table.valor_mensalidade,
                $alunos_table.valor_mensal,
                $alunos_table.responsavel_id,
                $responsaveis_table.nome AS responsavel_nome,
                $responsaveis_table.whats AS responsavel_whats,
                $cobrancas_table.id AS cobranca_id,
                $cobrancas_table.vencimento AS cobranca_vencimento,
                $cobrancas_table.valor AS cobranca_valor,
                $cobrancas_table.competencia AS cobranca_competencia,
                $cobrancas_table.descricao AS cobranca_descricao,
                $cobrancas_table.status AS cobranca_status,
                $cobrancas_table.data_pagamento,
                $cobrancas_table.forma_pagamento,
                $cobrancas_table.observacao
            FROM $alunos_table
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$alunos_table.responsavel_id
            LEFT JOIN ($mensalidade_mes_sql) mensalidade_mes ON mensalidade_mes.aluno_id=$alunos_table.id
            LEFT JOIN $cobrancas_table ON $cobrancas_table.id=mensalidade_mes.cobranca_id
            WHERE $alunos_table.deleted=0 $where
            ORDER BY $alunos_table.nome_aluno ASC";

        return $this->db->query($sql);
    }

    public function get_pagamentos_mensais_resumo($options = [])
    {
        $cobrancas_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");

        $mes_referencia = (int) ($this->_get_clean_value($options, "mes_referencia") ?: date("m"));
        $ano_referencia = (int) ($this->_get_clean_value($options, "ano_referencia") ?: date("Y"));
        $unidade_id = (int) $this->_get_clean_value($options, "unidade_id");
        $turma = $this->_get_clean_value($options, "turma");
        $hoje = $this->db->escape(date("Y-m-d"));
        $fim_competencia = $this->db->escape(date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $ano_referencia, $mes_referencia))));
        $inicio_cobranca_sql = "COALESCE($alunos_table.data_primeira_parcela, $alunos_table.data_inicio, $alunos_table.data_matricula, DATE($alunos_table.created_at))";
        $status_receber_sql = "$cobrancas_table.status IN ('Pendente','Vencido')";

        $where = " AND $alunos_table.status='Ativo'";
        $where .= " AND ($inicio_cobranca_sql IS NULL OR $inicio_cobranca_sql <= $fim_competencia)";

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=$unidade_id";
        }

        if ($turma) {
            $where .= " AND $alunos_table.turma=" . $this->db->escape($turma);
        }

        $mensalidade_mes_sql = "SELECT aluno_id,
                COALESCE(NULLIF(MAX(CASE WHEN status='Pago' THEN id ELSE 0 END), 0), MAX(id)) AS cobranca_id
            FROM $cobrancas_table
            WHERE tipo='Mensalidade'
                AND COALESCE(mes_referencia, MONTH(vencimento))=$mes_referencia
                AND COALESCE(ano_referencia, YEAR(vencimento))=$ano_referencia
            GROUP BY aluno_id";

        $sql = "SELECT
                COUNT(DISTINCT CASE WHEN $cobrancas_table.id IS NOT NULL THEN $alunos_table.id ELSE NULL END) AS total_alunos,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $cobrancas_table.status='Pago' THEN 1 ELSE 0 END) AS total_pagos,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $status_receber_sql AND $cobrancas_table.status!='Vencido' AND $cobrancas_table.vencimento >= $hoje THEN 1 ELSE 0 END) AS total_em_aberto,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $status_receber_sql AND ($cobrancas_table.status='Vencido' OR $cobrancas_table.vencimento < $hoje) THEN 1 ELSE 0 END) AS total_vencidos,
                SUM(CASE WHEN $cobrancas_table.id IS NULL THEN 1 ELSE 0 END) AS total_sem_cobranca,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $cobrancas_table.status='Pago' THEN $cobrancas_table.valor ELSE 0 END) AS total_recebido,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $status_receber_sql THEN $cobrancas_table.valor ELSE 0 END) AS total_a_receber,
                SUM(CASE WHEN $cobrancas_table.id IS NOT NULL AND $cobrancas_table.status IN ('Pago','Pendente','Vencido') THEN $cobrancas_table.valor ELSE 0 END) AS valor_previsto
            FROM $alunos_table
            LEFT JOIN ($mensalidade_mes_sql) mensalidade_mes ON mensalidade_mes.aluno_id=$alunos_table.id
            LEFT JOIN $cobrancas_table ON $cobrancas_table.id=mensalidade_mes.cobranca_id
            WHERE $alunos_table.deleted=0 $where";

        return $this->db->query($sql)->getRow();
    }

    public function get_totals($unidade_id = 0, $mes_referencia = 0, $ano_referencia = 0, $tipo = "")
    {
        $cobrancas_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = "";

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=" . (int) $unidade_id;
        }
        if ($mes_referencia) {
            $where .= " AND COALESCE($cobrancas_table.mes_referencia, MONTH($cobrancas_table.vencimento))=" . (int) $mes_referencia;
        }
        if ($ano_referencia) {
            $where .= " AND COALESCE($cobrancas_table.ano_referencia, YEAR($cobrancas_table.vencimento))=" . (int) $ano_referencia;
        }
        if ($tipo) {
            $where .= " AND $cobrancas_table.tipo=" . $this->db->escape($tipo);
        }

        $sql = "SELECT
                SUM(CASE WHEN $cobrancas_table.status='Pago' THEN $cobrancas_table.valor ELSE 0 END) AS total_pago,
                SUM(CASE WHEN $cobrancas_table.status IN ('Pendente','Vencido') THEN $cobrancas_table.valor ELSE 0 END) AS total_pendente,
                SUM(CASE WHEN $cobrancas_table.status IN ('Pendente','Vencido') AND $cobrancas_table.vencimento < CURDATE() THEN 1 ELSE 0 END) AS inadimplentes
            FROM $cobrancas_table
            INNER JOIN $alunos_table ON $alunos_table.id=$cobrancas_table.aluno_id
            WHERE $alunos_table.deleted=0 $where";

        return $this->db->query($sql)->getRow();
    }
}
