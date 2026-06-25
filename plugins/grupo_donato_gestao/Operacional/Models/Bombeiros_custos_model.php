<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_custos_model extends Crud_model
{
    protected $table = null;

    public function __construct()
    {
        $this->table = "grupo_donato_custos_unidade";
        parent::__construct($this->table);
    }

    public function get_details($options = [])
    {
        $custos_table = $this->db->prefixTable("grupo_donato_custos_unidade");
        $unidades_table = $this->db->prefixTable("grupo_donato_unidades");
        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $custos_table.id=" . (int) $id;
        }

        $unit_id = $this->_get_clean_value($options, "unit_id");
        if ($unit_id) {
            $where .= " AND $custos_table.unit_id=" . (int) $unit_id;
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $custos_table.status=" . $this->db->escape($status);
        }

        $categoria = $this->_get_clean_value($options, "categoria");
        if ($categoria) {
            $where .= " AND $custos_table.categoria=" . $this->db->escape($categoria);
        }

        $mes_referencia = $this->_get_clean_value($options, "mes_referencia");
        if ($mes_referencia) {
            $where .= " AND COALESCE($custos_table.mes_referencia, MONTH($custos_table.data_custo))=" . (int) $mes_referencia;
        }

        $ano_referencia = $this->_get_clean_value($options, "ano_referencia");
        if ($ano_referencia) {
            $where .= " AND COALESCE($custos_table.ano_referencia, YEAR($custos_table.data_custo))=" . (int) $ano_referencia;
        }

        $sql = "SELECT $custos_table.*,
                $unidades_table.nome_unidade,
                $unidades_table.cidade AS unidade_cidade
            FROM $custos_table
            LEFT JOIN $unidades_table ON $unidades_table.id=$custos_table.unit_id
            WHERE $custos_table.deleted=0 $where
            ORDER BY COALESCE($custos_table.ano_referencia, YEAR($custos_table.data_custo)) DESC,
                COALESCE($custos_table.mes_referencia, MONTH($custos_table.data_custo)) DESC,
                $custos_table.data_custo DESC,
                $custos_table.id DESC";

        return $this->db->query($sql);
    }

    public function get_totals($unit_id = 0, $mes_referencia = 0, $ano_referencia = 0)
    {
        $custos_table = $this->db->prefixTable("grupo_donato_custos_unidade");
        $where = "WHERE $custos_table.deleted=0 AND ($custos_table.status IS NULL OR $custos_table.status!='Cancelado')";

        if ($unit_id) {
            $where .= " AND $custos_table.unit_id=" . (int) $unit_id;
        }
        if ($mes_referencia) {
            $where .= " AND COALESCE($custos_table.mes_referencia, MONTH($custos_table.data_custo))=" . (int) $mes_referencia;
        }
        if ($ano_referencia) {
            $where .= " AND COALESCE($custos_table.ano_referencia, YEAR($custos_table.data_custo))=" . (int) $ano_referencia;
        }

        $sql = "SELECT
                SUM($custos_table.valor) AS total_custos,
                SUM(CASE WHEN $custos_table.status='Pago' THEN $custos_table.valor ELSE 0 END) AS total_pago,
                SUM(CASE WHEN $custos_table.status='Previsto' THEN $custos_table.valor ELSE 0 END) AS total_previsto,
                COUNT(*) AS total_registros
            FROM $custos_table
            $where";

        return $this->db->query($sql)->getRow();
    }
}
