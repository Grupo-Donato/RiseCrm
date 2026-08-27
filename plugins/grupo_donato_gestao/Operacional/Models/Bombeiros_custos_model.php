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

        $queries = [];
        $queries[] = "SELECT
                'operacional' AS source_type,
                $custos_table.id,
                $custos_table.unit_id,
                $custos_table.descricao,
                $custos_table.categoria,
                $custos_table.valor,
                $custos_table.data_custo,
                $custos_table.mes_referencia,
                $custos_table.ano_referencia,
                $custos_table.status,
                $custos_table.forma_pagamento,
                $custos_table.observacao,
                COALESCE($custos_table.ano_referencia, YEAR($custos_table.data_custo)) AS ref_ano,
                COALESCE($custos_table.mes_referencia, MONTH($custos_table.data_custo)) AS ref_mes,
                $unidades_table.nome_unidade,
                $unidades_table.cidade AS unidade_cidade
            FROM $custos_table
            LEFT JOIN $unidades_table ON $unidades_table.id=$custos_table.unit_id
            WHERE $custos_table.deleted=0 $where";

        $finance_sql = $id ? "" : $this->_finance_expenses_details_sql($options);
        if ($finance_sql) {
            $queries[] = $finance_sql;
        }

        $sql = implode(" UNION ALL ", $queries) . "
            ORDER BY ref_ano DESC,
                ref_mes DESC,
                data_custo DESC,
                id DESC";

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

        $operacional = $this->db->query($sql)->getRow();
        $financeiro = $this->_finance_expenses_totals($unit_id, [
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia
        ]);

        return (object) [
            "total_custos" => (float) ($operacional->total_custos ?? 0) + (float) ($financeiro->total_custos ?? 0),
            "total_pago" => (float) ($operacional->total_pago ?? 0) + (float) ($financeiro->total_pago ?? 0),
            "total_previsto" => (float) ($operacional->total_previsto ?? 0) + (float) ($financeiro->total_previsto ?? 0),
            "total_registros" => (int) ($operacional->total_registros ?? 0) + (int) ($financeiro->total_registros ?? 0)
        ];
    }

    public function get_resumo($unit_id = 0, $options = [])
    {
        $custos_table = $this->db->prefixTable("grupo_donato_custos_unidade");
        $where = "WHERE $custos_table.deleted=0";

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

        $sql = "SELECT
                SUM(CASE WHEN $custos_table.status IS NULL OR $custos_table.status!='Cancelado' THEN 1 ELSE 0 END) AS qtd_lancados,
                SUM(CASE WHEN $custos_table.status='Pago' THEN 1 ELSE 0 END) AS qtd_pagos,
                SUM(CASE WHEN $custos_table.status='Previsto' THEN 1 ELSE 0 END) AS qtd_previstos,
                SUM(CASE WHEN $custos_table.status='Cancelado' THEN 1 ELSE 0 END) AS qtd_cancelados,
                SUM(CASE WHEN $custos_table.status='Pago' THEN $custos_table.valor ELSE 0 END) AS total_pago,
                SUM(CASE WHEN $custos_table.status='Previsto' THEN $custos_table.valor ELSE 0 END) AS total_previsto,
                SUM(CASE WHEN $custos_table.status IS NULL OR $custos_table.status!='Cancelado' THEN $custos_table.valor ELSE 0 END) AS total_geral
            FROM $custos_table
            $where";

        $operacional = $this->db->query($sql)->getRow();
        $financeiro = $this->_finance_expenses_resumo($unit_id, $options);

        return (object) [
            "qtd_lancados" => (int) ($operacional->qtd_lancados ?? 0) + (int) ($financeiro->qtd_lancados ?? 0),
            "qtd_pagos" => (int) ($operacional->qtd_pagos ?? 0) + (int) ($financeiro->qtd_pagos ?? 0),
            "qtd_previstos" => (int) ($operacional->qtd_previstos ?? 0) + (int) ($financeiro->qtd_previstos ?? 0),
            "qtd_cancelados" => (int) ($operacional->qtd_cancelados ?? 0) + (int) ($financeiro->qtd_cancelados ?? 0),
            "total_pago" => (float) ($operacional->total_pago ?? 0) + (float) ($financeiro->total_pago ?? 0),
            "total_previsto" => (float) ($operacional->total_previsto ?? 0) + (float) ($financeiro->total_previsto ?? 0),
            "total_geral" => (float) ($operacional->total_geral ?? 0) + (float) ($financeiro->total_geral ?? 0)
        ];
    }

    private function _finance_expenses_details_sql($options = [])
    {
        $expenses_table = $this->_finance_expenses_table();
        if (!$expenses_table) {
            return "";
        }

        $accounts_table = $this->db->prefixTable("gd_financial_accounts");
        $areas_table = $this->db->prefixTable("gd_business_areas");
        $centers_table = $this->db->prefixTable("gd_cost_centers");
        $units_table = $this->db->prefixTable("gd_units");
        $date_sql = "COALESCE($expenses_table.paid_date, $expenses_table.expense_date, $expenses_table.due_date)";
        $category_sql = "COALESCE($centers_table.name, $areas_table.name, 'Financeiro')";
        $where = $this->_finance_expenses_where($this->_get_clean_value($options, "unit_id"), $options, $date_sql, $category_sql);

        return "SELECT
                'financeiro' AS source_type,
                $expenses_table.id,
                $expenses_table.unit_id,
                $expenses_table.description AS descricao,
                $category_sql AS categoria,
                $expenses_table.amount AS valor,
                $date_sql AS data_custo,
                MONTH($date_sql) AS mes_referencia,
                YEAR($date_sql) AS ano_referencia,
                CASE
                    WHEN $expenses_table.status='paid' THEN 'Pago'
                    WHEN $expenses_table.status='cancelled' THEN 'Cancelado'
                    ELSE 'Previsto'
                END AS status,
                $expenses_table.payment_method AS forma_pagamento,
                $expenses_table.notes AS observacao,
                YEAR($date_sql) AS ref_ano,
                MONTH($date_sql) AS ref_mes,
                $units_table.name AS nome_unidade,
                NULL AS unidade_cidade
            FROM $expenses_table
            LEFT JOIN $accounts_table ON $accounts_table.id=$expenses_table.financial_account_id AND $accounts_table.unit_id=$expenses_table.unit_id
            LEFT JOIN $areas_table ON $areas_table.id=$expenses_table.business_area_id
            LEFT JOIN $centers_table ON $centers_table.id=$expenses_table.cost_center_id AND $centers_table.unit_id=$expenses_table.unit_id
            LEFT JOIN $units_table ON $units_table.id=$expenses_table.unit_id
            $where";
    }

    private function _finance_expenses_resumo($unit_id = 0, $options = [])
    {
        $expenses_table = $this->_finance_expenses_table();
        if (!$expenses_table) {
            return (object) [];
        }

        $areas_table = $this->db->prefixTable("gd_business_areas");
        $centers_table = $this->db->prefixTable("gd_cost_centers");
        $date_sql = "COALESCE($expenses_table.paid_date, $expenses_table.expense_date, $expenses_table.due_date)";
        $category_sql = "COALESCE($centers_table.name, $areas_table.name, 'Financeiro')";
        $where = $this->_finance_expenses_where($unit_id, $options, $date_sql, $category_sql);

        $sql = "SELECT
                SUM(CASE WHEN $expenses_table.status!='cancelled' THEN 1 ELSE 0 END) AS qtd_lancados,
                SUM(CASE WHEN $expenses_table.status='paid' THEN 1 ELSE 0 END) AS qtd_pagos,
                SUM(CASE WHEN $expenses_table.status='pending' THEN 1 ELSE 0 END) AS qtd_previstos,
                SUM(CASE WHEN $expenses_table.status='cancelled' THEN 1 ELSE 0 END) AS qtd_cancelados,
                SUM(CASE WHEN $expenses_table.status='paid' THEN $expenses_table.amount ELSE 0 END) AS total_pago,
                SUM(CASE WHEN $expenses_table.status='pending' THEN $expenses_table.amount ELSE 0 END) AS total_previsto,
                SUM(CASE WHEN $expenses_table.status!='cancelled' THEN $expenses_table.amount ELSE 0 END) AS total_geral
            FROM $expenses_table
            LEFT JOIN $areas_table ON $areas_table.id=$expenses_table.business_area_id
            LEFT JOIN $centers_table ON $centers_table.id=$expenses_table.cost_center_id AND $centers_table.unit_id=$expenses_table.unit_id
            $where";

        return $this->db->query($sql)->getRow();
    }

    private function _finance_expenses_totals($unit_id = 0, $options = [])
    {
        $expenses_table = $this->_finance_expenses_table();
        if (!$expenses_table) {
            return (object) [];
        }

        $date_sql = "COALESCE($expenses_table.paid_date, $expenses_table.expense_date, $expenses_table.due_date)";
        $where = $this->_finance_expenses_where($unit_id, $options, $date_sql, "");

        $sql = "SELECT
                SUM(CASE WHEN $expenses_table.status!='cancelled' THEN $expenses_table.amount ELSE 0 END) AS total_custos,
                SUM(CASE WHEN $expenses_table.status='paid' THEN $expenses_table.amount ELSE 0 END) AS total_pago,
                SUM(CASE WHEN $expenses_table.status='pending' THEN $expenses_table.amount ELSE 0 END) AS total_previsto,
                SUM(CASE WHEN $expenses_table.status!='cancelled' THEN 1 ELSE 0 END) AS total_registros
            FROM $expenses_table
            $where";

        return $this->db->query($sql)->getRow();
    }

    private function _finance_expenses_where($unit_id, $options, $date_sql, $category_sql = "")
    {
        $expenses_table = $this->db->prefixTable("gd_expenses");
        $where = "WHERE $expenses_table.deleted=0";

        $unit_id = (int) $unit_id;
        if ($unit_id) {
            $unit_ids = $this->_finance_unit_ids($unit_id);
            if (!$unit_ids) {
                return $where . " AND 1=0";
            }
            $where .= " AND $expenses_table.unit_id IN (" . implode(",", $unit_ids) . ")";
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $finance_status = $this->_finance_status($status);
            if (!$finance_status) {
                return $where . " AND 1=0";
            }
            $where .= " AND $expenses_table.status=" . $this->db->escape($finance_status);
        }

        $categoria = $this->_get_clean_value($options, "categoria");
        if ($categoria && $category_sql) {
            $where .= " AND $category_sql=" . $this->db->escape($categoria);
        } elseif ($categoria) {
            return $where . " AND 1=0";
        }

        $mes_referencia = $this->_get_clean_value($options, "mes_referencia");
        if ($mes_referencia) {
            $where .= " AND MONTH($date_sql)=" . (int) $mes_referencia;
        }

        $ano_referencia = $this->_get_clean_value($options, "ano_referencia");
        if ($ano_referencia) {
            $where .= " AND YEAR($date_sql)=" . (int) $ano_referencia;
        }

        return $where;
    }

    private function _finance_expenses_table()
    {
        $table = $this->db->prefixTable("gd_expenses");
        return $this->db->tableExists($table) ? $table : "";
    }

    private function _finance_unit_ids($unit_id)
    {
        $unit_id = (int) $unit_id;
        $units_table = $this->db->prefixTable("gd_units");
        if (!$unit_id || !$this->db->tableExists($units_table)) {
            return [];
        }

        $ids = [];
        $direct_unit = $this->db->table($units_table)->where("id", $unit_id)->where("deleted", 0)->get(1)->getRow();
        $operational_units_table = $this->db->prefixTable("grupo_donato_unidades");
        if ($this->db->tableExists($operational_units_table)) {
            $operational_unit = $this->db->table($operational_units_table)->where("id", $unit_id)->where("deleted", 0)->get(1)->getRow();
            if ($operational_unit) {
                $operational_names = array_filter(array_map(static function ($name) {
                    return strtolower(trim((string) $name));
                }, [$operational_unit->nome_unidade ?? "", $operational_unit->cidade ?? ""]));

                if ($direct_unit) {
                    $direct_names = array_filter(array_map(static function ($name) {
                        return strtolower(trim((string) $name));
                    }, [$direct_unit->name ?? "", $direct_unit->legal_name ?? ""]));

                    if (array_intersect($operational_names, $direct_names)) {
                        $ids[] = $unit_id;
                    }
                }

                if ((int) ($operational_unit->is_default ?? 0) === 1) {
                    $default = $this->db->table($units_table)->select("id")->where("is_default", 1)->where("deleted", 0)->get(1)->getRow();
                    if ($default) {
                        $ids[] = (int) $default->id;
                    }
                }

                foreach ($operational_names as $name) {
                    $name = trim((string) $name);
                    if ($name === "") {
                        continue;
                    }

                    $matches = $this->db->table($units_table)
                        ->select("id")
                        ->where("deleted", 0)
                        ->groupStart()
                        ->where("LOWER(name)", strtolower($name))
                        ->orWhere("LOWER(legal_name)", strtolower($name))
                        ->groupEnd()
                        ->get()
                        ->getResult();

                    foreach ($matches as $match) {
                        $ids[] = (int) $match->id;
                    }
                }
            }
        } elseif ($direct_unit) {
            $ids[] = $unit_id;
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function _finance_status($status)
    {
        $map = [
            "Pago" => "paid",
            "Previsto" => "pending",
            "Cancelado" => "cancelled"
        ];

        return $map[$status] ?? "";
    }
}
