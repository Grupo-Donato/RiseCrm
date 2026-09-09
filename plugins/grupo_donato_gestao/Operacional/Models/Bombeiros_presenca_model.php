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

    /**
     * Conta a sequência atual de faltas de cada aluno.
     * Os status recebidos devem estar ordenados da aula mais recente para a mais antiga.
     */
    public static function count_consecutive_absences(array $statuses)
    {
        $count = 0;

        foreach ($statuses as $status) {
            $status = strtolower(trim((string) $status));

            if ($status === "falta") {
                $count++;
                continue;
            }

            // Estes registros não representam uma aula frequentada ou perdida.
            if ($status === "feriado" || $status === "aula_cancelada") {
                continue;
            }

            // Presença, sem registro ou status desconhecido encerra a sequência.
            break;
        }

        return $count;
    }

    public function get_consecutive_absence_counts($unidade_id = 0, array $aluno_ids = [])
    {
        $presenca_table = $this->db->prefixTable("grupo_donato_presenca");
        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $where = "$alunos_table.deleted=0";

        if ($unidade_id) {
            $where .= " AND $alunos_table.unidade_id=" . (int) $unidade_id;
        }

        $aluno_ids = array_values(array_unique(array_filter(array_map("intval", $aluno_ids), static function ($id) {
            return $id > 0;
        })));
        if ($aluno_ids) {
            $where .= " AND $presenca_table.aluno_id IN (" . implode(",", $aluno_ids) . ")";
        }

        $sql = "SELECT $presenca_table.aluno_id,
                    $presenca_table.status,
                    $presenca_table.status_tipo,
                    $presenca_table.data_aula,
                    $presenca_table.id
                FROM $presenca_table
                INNER JOIN $alunos_table ON $alunos_table.id=$presenca_table.aluno_id
                WHERE $where
                ORDER BY $presenca_table.aluno_id ASC, $presenca_table.data_aula DESC, $presenca_table.id DESC";

        $statuses_by_student = [];
        foreach ($this->db->query($sql)->getResult() as $row) {
            $student_id = (int) $row->aluno_id;
            $status = trim((string) ($row->status_tipo ?? ""));
            if (!$status) {
                $status = (int) $row->status ? "presente" : "falta";
            }
            $statuses_by_student[$student_id][] = $status;
        }

        $counts = [];
        foreach ($statuses_by_student as $student_id => $statuses) {
            $counts[(int) $student_id] = self::count_consecutive_absences($statuses);
        }

        return $counts;
    }

    /**
     * Mantém compatibilidade com chamadas antigas que usam este nome.
     */
    public function get_absence_counts($unidade_id = 0)
    {
        return $this->get_consecutive_absence_counts($unidade_id);
    }
}
