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

    /**
     * Retorna o financeiro do aluno em uma única linha do tempo.
     *
     * As cobranças operacionais antigas e as cobranças dos campeonatos vivem
     * em tabelas diferentes. A tela de aluno precisa de uma leitura única,
     * mas não deve duplicar nem migrar registros automaticamente. Por isso,
     * os eventos sem recebível aparecem como "aguardando emissão" e não entram
     * no saldo em aberto até que a cobrança seja realmente criada.
     */
    public function get_student_overview_finance(int $student_id, int $legacy_unit_id = 0, int $academy_unit_id = 0): array
    {
        $rows = [];
        $awaiting_charge_total = 0.0;
        $awaiting_charge_count = 0;
        $legacy_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $today = date("Y-m-d");

        if ($this->db->tableExists($legacy_table)) {
            $legacy_query = $this->db->table($legacy_table)
                ->select("id, vencimento, valor, competencia, mes_referencia, ano_referencia, descricao, status, tipo, data_pagamento, forma_pagamento, observacao")
                ->where("aluno_id", $student_id);
            if ($this->db->fieldExists("deleted", $legacy_table)) {
                $legacy_query->where("deleted", 0);
            }

            $legacy_charges = $legacy_query->get()->getResult();
            $monthly_winners = [];
            foreach ($legacy_charges as $charge) {
                if (mb_strtolower(trim((string) ($charge->tipo ?? ""))) !== "mensalidade") {
                    continue;
                }
                $month_key = $this->_overview_month_key($charge);
                if ($month_key === "") {
                    continue;
                }
                $current = $monthly_winners[$month_key] ?? null;
                $is_paid = (string) ($charge->status ?? "") === "Pago";
                $current_is_paid = $current && (string) ($current->status ?? "") === "Pago";
                if (!$current || ($is_paid && !$current_is_paid) || ($is_paid === $current_is_paid && (int) $charge->id > (int) $current->id)) {
                    $monthly_winners[$month_key] = $charge;
                }
            }

            foreach ($legacy_charges as $charge) {
                if ((string) ($charge->status ?? "") === "Cancelado" && (string) ($charge->vencimento ?? "") > $today) {
                    continue;
                }
                if (mb_strtolower(trim((string) ($charge->tipo ?? ""))) === "mensalidade") {
                    $month_key = $this->_overview_month_key($charge);
                    if ($month_key !== "" && (int) ($monthly_winners[$month_key]->id ?? 0) !== (int) $charge->id) {
                        continue;
                    }
                }
                $original_amount = (float) ($charge->valor ?? 0);
                $legacy_type = (string) ($charge->tipo ?? "");
                $legacy_description = (string) ($charge->descricao ?? "");
                $legacy_category = $this->_overview_finance_category($legacy_type, $legacy_description);
                $status = $this->_overview_finance_status(
                    (string) ($charge->status ?? ""),
                    (string) ($charge->vencimento ?? ""),
                    $original_amount,
                    (string) ($charge->status ?? "") === "Pago" ? $original_amount : 0.0,
                    $today
                );
                $rows[] = [
                    "id" => "legacy-" . (int) $charge->id,
                    "source" => "operacional",
                    "source_id" => (int) $charge->id,
                    "charge_id" => (int) $charge->id,
                    "aluno_id" => $student_id,
                    "category" => $legacy_category,
                    "description" => $this->_overview_finance_description($legacy_category, $legacy_description, $legacy_type),
                    "reference" => (string) ($charge->competencia ?: (($charge->ano_referencia ?? "") && ($charge->mes_referencia ?? "") ? sprintf("%04d-%02d", $charge->ano_referencia, $charge->mes_referencia) : "")),
                    "issue_date" => "",
                    "due_date" => (string) ($charge->vencimento ?? ""),
                    "payment_date" => (string) ($charge->data_pagamento ?? ""),
                    "payment_method" => (string) ($charge->forma_pagamento ?? ""),
                    "original_amount" => $original_amount,
                    "paid_amount" => $status === "paid" ? $original_amount : 0.0,
                    "balance_amount" => in_array($status, ["paid", "cancelled", "exempt"], true) ? 0.0 : $original_amount,
                    "status" => $status,
                    "status_raw" => (string) ($charge->status ?? ""),
                    "notes" => (string) ($charge->observacao ?? ""),
                    "is_real_charge" => true,
                ];
            }
        }

        $participants_table = $this->db->prefixTable("gd_academy_event_participants");
        $events_table = $this->db->prefixTable("gd_academy_events");
        $categories_table = $this->db->prefixTable("gd_academy_event_categories");
        $receivables_table = $this->db->prefixTable("gd_receivables");
        $allocations_table = $this->db->prefixTable("gd_payment_allocations");
        $payments_table = $this->db->prefixTable("gd_payments");
        $academy_unit_id = $academy_unit_id ?: $legacy_unit_id;

        if ($academy_unit_id > 0
            && $this->db->tableExists($participants_table)
            && $this->db->tableExists($events_table)
            && $this->db->tableExists($receivables_table)) {
            $category_join = $this->db->tableExists($categories_table)
                ? "LEFT JOIN `$categories_table` c ON c.id=p.category_id AND c.unit_id=p.unit_id AND c.deleted=0"
                : "";
            $category_name = $this->db->tableExists($categories_table) ? "c.name category_name" : "'' category_name";
            $last_payment = $this->db->tableExists($allocations_table) && $this->db->tableExists($payments_table)
                ? "(SELECT MAX(pay.payment_date) FROM `$allocations_table` pa INNER JOIN `$payments_table` pay ON pay.id=pa.payment_id AND pay.unit_id=pa.unit_id AND pay.status='confirmed' AND pay.deleted=0 WHERE pa.unit_id=r.unit_id AND pa.receivable_id=r.id AND pa.status='active') last_payment_date"
                : "'' last_payment_date";
            $event_rows = $this->db->query(
                "SELECT p.id participant_id, p.amount participant_amount, p.financial_status participant_financial_status,
                    p.receivable_id, e.id event_id, e.name event_name, e.starts_on event_date, e.status event_status,
                    {$category_name}, r.id receivable_id_real, r.description, r.reference_month, r.issue_date,
                    r.due_date, r.original_amount, r.paid_amount, r.balance_amount, r.status receivable_status,
                    r.notes, {$last_payment}
                FROM `$participants_table` p
                INNER JOIN `$events_table` e ON e.id=p.event_id AND e.unit_id=p.unit_id AND e.deleted=0
                {$category_join}
                LEFT JOIN `$receivables_table` r ON r.id=p.receivable_id AND r.unit_id=p.unit_id AND r.deleted=0
                WHERE p.unit_id=? AND p.student_id=? AND p.deleted=0
                ORDER BY COALESCE(r.due_date, e.starts_on) DESC, p.id DESC",
                [$academy_unit_id, $student_id]
            )->getResult();

            foreach ($event_rows as $event_row) {
                $has_receivable = (int) ($event_row->receivable_id_real ?? 0) > 0;
                $event_description = (string) ($event_row->description ?: $event_row->event_name ?: "Campeonato");
                $amount = (float) ($event_row->original_amount ?? $event_row->participant_amount ?? 0);

                if ($has_receivable) {
                    $paid_amount = (float) ($event_row->paid_amount ?? 0);
                    $balance_amount = (float) ($event_row->balance_amount ?? max(0, $amount - $paid_amount));
                    $status = $this->_overview_finance_status(
                        (string) ($event_row->receivable_status ?? ""),
                        (string) ($event_row->due_date ?? ""),
                        $amount,
                        $paid_amount,
                        $today,
                        true
                    );
                    if (in_array($status, ["paid", "cancelled", "exempt"], true)) {
                        $balance_amount = 0.0;
                    }
                    $rows[] = [
                        "id" => "academy-" . (int) $event_row->receivable_id_real,
                        "source" => "academy",
                        "source_id" => (int) $event_row->receivable_id_real,
                        "charge_id" => 0,
                        "aluno_id" => $student_id,
                        "participant_id" => (int) ($event_row->participant_id ?? 0),
                        "event_id" => (int) ($event_row->event_id ?? 0),
                        "category" => "campeonato",
                        "description" => $event_description,
                        "reference" => (string) ($event_row->reference_month ?? ""),
                        "issue_date" => (string) ($event_row->issue_date ?? ""),
                        "due_date" => (string) ($event_row->due_date ?: $event_row->event_date ?: ""),
                        "payment_date" => (string) ($event_row->last_payment_date ?? ""),
                        "payment_method" => "",
                        "original_amount" => $amount,
                        "paid_amount" => $paid_amount,
                        "balance_amount" => $balance_amount,
                        "status" => $status,
                        "status_raw" => (string) ($event_row->receivable_status ?? ""),
                        "notes" => (string) ($event_row->notes ?? ""),
                        "event_name" => (string) ($event_row->event_name ?? ""),
                        "event_date" => (string) ($event_row->event_date ?? ""),
                        "category_name" => (string) ($event_row->category_name ?? ""),
                        "is_real_charge" => true,
                    ];
                    continue;
                }

                $participant_status = strtolower(trim((string) ($event_row->participant_financial_status ?? "")));
                if ($amount <= 0 || strtolower((string) ($event_row->event_status ?? "")) === "cancelled" || in_array($participant_status, ["cancelled", "canceled", "refused", "rejected"], true)) {
                    continue;
                }

                $awaiting_charge_total += $amount;
                $awaiting_charge_count++;
                $rows[] = [
                    "id" => "academy-pending-" . (int) ($event_row->participant_id ?? 0),
                    "source" => "academy",
                    "source_id" => 0,
                    "charge_id" => 0,
                    "aluno_id" => $student_id,
                    "participant_id" => (int) ($event_row->participant_id ?? 0),
                    "event_id" => (int) ($event_row->event_id ?? 0),
                    "category" => "campeonato",
                    "description" => $event_description,
                    "reference" => "",
                    "issue_date" => "",
                    "due_date" => (string) ($event_row->event_date ?? ""),
                    "payment_date" => "",
                    "payment_method" => "",
                    "original_amount" => $amount,
                    "paid_amount" => 0.0,
                    "balance_amount" => 0.0,
                    "status" => "awaiting_charge",
                    "status_raw" => (string) ($event_row->participant_financial_status ?? ""),
                    "notes" => "Participante convocado sem cobrança emitida.",
                    "event_name" => (string) ($event_row->event_name ?? ""),
                    "event_date" => (string) ($event_row->event_date ?? ""),
                    "category_name" => (string) ($event_row->category_name ?? ""),
                    "is_real_charge" => false,
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right["due_date"] ?? ""), (string) ($left["due_date"] ?? ""));
        });

        $total_amount = 0.0;
        $paid_amount = 0.0;
        $balance_amount = 0.0;
        $open_count = 0;
        $overdue_count = 0;
        $groups = [];
        foreach ($rows as $row) {
            $group = (string) ($row["category"] ?? "outros");
            $groups[$group][] = $row;
            if (empty($row["is_real_charge"])) continue;
            if (in_array($row["status"], ["cancelled", "exempt"], true)) continue;
            $total_amount += (float) $row["original_amount"];
            $paid_amount += (float) $row["paid_amount"];
            $balance_amount += (float) $row["balance_amount"];
            if ((float) $row["balance_amount"] > 0) $open_count++;
            if ($row["status"] === "overdue") $overdue_count++;
        }

        return [
            "rows" => $rows,
            "groups" => $groups,
            "summary" => [
                "total_amount" => $total_amount,
                "paid_amount" => $paid_amount,
                "balance_amount" => $balance_amount,
                "open_count" => $open_count,
                "overdue_count" => $overdue_count,
                "awaiting_charge_total" => $awaiting_charge_total,
                "awaiting_charge_count" => $awaiting_charge_count,
            ],
        ];
    }

    /**
     * Lista as cobranças de todos os alunos para a aba Pagamentos.
     * Mantém a mesma normalização do detalhe individual, porém em duas
     * consultas em lote para não gerar uma consulta por aluno.
     */
    public function get_payment_overview_rows(int $legacy_unit_id = 0, int $academy_unit_id = 0, array $filters = []): array
    {
        $rows = [];
        $today = date("Y-m-d");
        $month = (int) ($filters["mes_referencia"] ?? 0);
        $year = (int) ($filters["ano_referencia"] ?? 0);
        $status_filter = strtolower(trim((string) ($filters["status_pagamento"] ?? "")));
        $category_filter = strtolower(trim((string) ($filters["categoria"] ?? "")));
        $turma = trim((string) ($filters["turma"] ?? ""));
        $legacy_table = $this->db->prefixTable("grupo_donato_cobrancas");
        $students_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsibles_table = $this->db->prefixTable("grupo_donato_responsaveis");

        if ($legacy_unit_id > 0 && $this->db->tableExists($legacy_table)) {
            $where = "a.deleted=0 AND a.status='Ativo' AND a.unidade_id=?";
            $params = [$legacy_unit_id];
            if ($turma !== "") {
                $where .= " AND a.turma=?";
                $params[] = $turma;
            }
            if ($month >= 1 && $month <= 12) {
                $where .= " AND COALESCE(c.mes_referencia, MONTH(c.vencimento))=?";
                $params[] = $month;
            }
            if ($year >= 2000 && $year <= 2100) {
                $where .= " AND COALESCE(c.ano_referencia, YEAR(c.vencimento))=?";
                $params[] = $year;
            }

            $legacy_rows = $this->db->query(
                "SELECT c.id charge_id, c.vencimento due_date, c.valor original_amount, c.competencia,
                    c.mes_referencia, c.ano_referencia, c.descricao, c.status raw_status, c.tipo,
                    c.data_pagamento payment_date, c.forma_pagamento payment_method, c.observacao notes,
                    a.id aluno_id, a.matricula, a.nome_aluno, a.nascimento_aluno, a.turma, a.pelotao,
                    r.nome responsavel_nome, r.whats responsavel_whats
                FROM `$legacy_table` c
                INNER JOIN `$students_table` a ON a.id=c.aluno_id
                LEFT JOIN `$responsibles_table` r ON r.id=a.responsavel_id
                WHERE {$where}
                ORDER BY c.vencimento DESC, a.nome_aluno ASC, c.id DESC",
                $params
            )->getResult();

            $monthly_winners = [];
            foreach ($legacy_rows as $charge) {
                if (mb_strtolower(trim((string) ($charge->tipo ?? ""))) !== "mensalidade") {
                    continue;
                }
                $raw_month_key = $this->_overview_month_key($charge);
                if ($raw_month_key === "") {
                    continue;
                }
                $month_key = (int) ($charge->aluno_id ?? 0) . ":" . $raw_month_key;
                $current = $monthly_winners[$month_key] ?? null;
                $is_paid = (string) ($charge->raw_status ?? "") === "Pago";
                $current_is_paid = $current && (string) ($current->raw_status ?? "") === "Pago";
                if (!$current || ($is_paid && !$current_is_paid) || ($is_paid === $current_is_paid && (int) $charge->charge_id > (int) $current->charge_id)) {
                    $monthly_winners[$month_key] = $charge;
                }
            }

            foreach ($legacy_rows as $charge) {
                if ((string) ($charge->raw_status ?? "") === "Cancelado" && (string) ($charge->due_date ?? "") > $today) {
                    continue;
                }
                if (mb_strtolower(trim((string) ($charge->tipo ?? ""))) === "mensalidade") {
                    $raw_month_key = $this->_overview_month_key($charge);
                    $month_key = $raw_month_key === "" ? "" : (int) ($charge->aluno_id ?? 0) . ":" . $raw_month_key;
                    if ($month_key !== "" && (int) ($monthly_winners[$month_key]->charge_id ?? 0) !== (int) $charge->charge_id) {
                        continue;
                    }
                }
                $original_amount = (float) ($charge->original_amount ?? 0);
                $legacy_type = (string) ($charge->tipo ?? "");
                $legacy_description = (string) ($charge->descricao ?? "");
                $legacy_category = $this->_overview_finance_category($legacy_type, $legacy_description);
                $status = $this->_overview_finance_status(
                    (string) ($charge->raw_status ?? ""),
                    (string) ($charge->due_date ?? ""),
                    $original_amount,
                    (string) ($charge->raw_status ?? "") === "Pago" ? $original_amount : 0.0,
                    $today
                );
                $row = [
                    "id" => "legacy-" . (int) $charge->charge_id,
                    "source" => "operacional",
                    "source_id" => (int) $charge->charge_id,
                    "charge_id" => (int) $charge->charge_id,
                    "participant_id" => 0,
                    "aluno_id" => (int) $charge->aluno_id,
                    "matricula" => (string) ($charge->matricula ?? ""),
                    "nome_aluno" => (string) ($charge->nome_aluno ?? ""),
                    "nascimento_aluno" => (string) ($charge->nascimento_aluno ?? ""),
                    "turma" => (string) ($charge->turma ?? ""),
                    "pelotao" => (string) ($charge->pelotao ?? ""),
                    "responsavel_nome" => (string) ($charge->responsavel_nome ?? ""),
                    "responsavel_whats" => (string) ($charge->responsavel_whats ?? ""),
                    "category" => $legacy_category,
                    "description" => $this->_overview_finance_description($legacy_category, $legacy_description, $legacy_type),
                    "reference" => (string) ($charge->competencia ?: (($charge->ano_referencia ?? "") && ($charge->mes_referencia ?? "") ? sprintf("%04d-%02d", $charge->ano_referencia, $charge->mes_referencia) : "")),
                    "due_date" => (string) ($charge->due_date ?? ""),
                    "payment_date" => (string) ($charge->payment_date ?? ""),
                    "payment_method" => (string) ($charge->payment_method ?? ""),
                    "original_amount" => $original_amount,
                    "paid_amount" => $status === "paid" ? $original_amount : 0.0,
                    "balance_amount" => in_array($status, ["paid", "cancelled", "exempt"], true) ? 0.0 : $original_amount,
                    "status" => $status,
                    "status_raw" => (string) ($charge->raw_status ?? ""),
                    "notes" => (string) ($charge->notes ?? ""),
                    "is_real_charge" => true,
                ];
                if ($this->_overview_payment_row_matches($row, $status_filter, $category_filter)) $rows[] = $row;
            }
        }

        $participants_table = $this->db->prefixTable("gd_academy_event_participants");
        $events_table = $this->db->prefixTable("gd_academy_events");
        $categories_table = $this->db->prefixTable("gd_academy_event_categories");
        $receivables_table = $this->db->prefixTable("gd_receivables");
        $allocations_table = $this->db->prefixTable("gd_payment_allocations");
        $payments_table = $this->db->prefixTable("gd_payments");
        $academy_unit_id = $academy_unit_id ?: $legacy_unit_id;

        if ($academy_unit_id > 0
            && $this->db->tableExists($participants_table)
            && $this->db->tableExists($events_table)
            && $this->db->tableExists($receivables_table)) {
            $category_join = $this->db->tableExists($categories_table)
                ? "LEFT JOIN `$categories_table` c ON c.id=p.category_id AND c.unit_id=p.unit_id AND c.deleted=0"
                : "";
            $category_name = $this->db->tableExists($categories_table) ? "c.name category_name" : "'' category_name";
            $last_payment = $this->db->tableExists($allocations_table) && $this->db->tableExists($payments_table)
                ? "(SELECT MAX(pay.payment_date) FROM `$allocations_table` pa INNER JOIN `$payments_table` pay ON pay.id=pa.payment_id AND pay.unit_id=pa.unit_id AND pay.status='confirmed' AND pay.deleted=0 WHERE pa.unit_id=r.unit_id AND pa.receivable_id=r.id AND pa.status='active') last_payment_date"
                : "'' last_payment_date";
            $where = "p.unit_id=? AND p.student_id IS NOT NULL AND p.deleted=0 AND s.id IS NOT NULL";
            $params = [$academy_unit_id];
            if ($turma !== "") {
                $where .= " AND s.turma=?";
                $params[] = $turma;
            }
            if ($month >= 1 && $month <= 12) {
                $where .= " AND MONTH(COALESCE(r.due_date, e.starts_on))=?";
                $params[] = $month;
            }
            if ($year >= 2000 && $year <= 2100) {
                $where .= " AND YEAR(COALESCE(r.due_date, e.starts_on))=?";
                $params[] = $year;
            }

            $event_rows = $this->db->query(
                "SELECT p.id participant_id, p.amount participant_amount, p.financial_status participant_financial_status,
                    p.receivable_id, e.id event_id, e.name event_name, e.starts_on event_date, e.status event_status,
                    {$category_name}, r.id receivable_id_real, r.description, r.reference_month, r.issue_date,
                    r.due_date, r.original_amount, r.paid_amount, r.balance_amount, r.status receivable_status,
                    r.notes, {$last_payment}, s.id aluno_id, s.matricula, s.nome_aluno, s.nascimento_aluno,
                    s.turma, s.pelotao, resp.nome responsavel_nome, resp.whats responsavel_whats
                FROM `$participants_table` p
                INNER JOIN `$events_table` e ON e.id=p.event_id AND e.unit_id=p.unit_id AND e.deleted=0
                INNER JOIN `$students_table` s ON s.id=p.student_id AND s.unidade_id=? AND s.deleted=0 AND s.status='Ativo'
                LEFT JOIN `$responsibles_table` resp ON resp.id=s.responsavel_id
                {$category_join}
                LEFT JOIN `$receivables_table` r ON r.id=p.receivable_id AND r.unit_id=p.unit_id AND r.deleted=0
                WHERE {$where}
                ORDER BY COALESCE(r.due_date, e.starts_on) DESC, s.nome_aluno ASC, p.id DESC",
                array_merge([$legacy_unit_id], $params)
            )->getResult();

            foreach ($event_rows as $event_row) {
                $has_receivable = (int) ($event_row->receivable_id_real ?? 0) > 0;
                $event_description = (string) ($event_row->description ?: $event_row->event_name ?: "Campeonato");
                $amount = (float) ($event_row->original_amount ?? $event_row->participant_amount ?? 0);
                if ($has_receivable) {
                    $paid_amount = (float) ($event_row->paid_amount ?? 0);
                    $balance_amount = (float) ($event_row->balance_amount ?? max(0, $amount - $paid_amount));
                    $status = $this->_overview_finance_status(
                        (string) ($event_row->receivable_status ?? ""),
                        (string) ($event_row->due_date ?? ""),
                        $amount,
                        $paid_amount,
                        $today,
                        true
                    );
                    $row = [
                        "id" => "academy-" . (int) $event_row->receivable_id_real,
                        "source" => "academy",
                        "source_id" => (int) $event_row->receivable_id_real,
                        "charge_id" => 0,
                        "participant_id" => (int) ($event_row->participant_id ?? 0),
                        "aluno_id" => (int) ($event_row->aluno_id ?? 0),
                        "matricula" => (string) ($event_row->matricula ?? ""),
                        "nome_aluno" => (string) ($event_row->nome_aluno ?? ""),
                        "nascimento_aluno" => (string) ($event_row->nascimento_aluno ?? ""),
                        "turma" => (string) ($event_row->turma ?? ""),
                        "pelotao" => (string) ($event_row->pelotao ?? ""),
                        "responsavel_nome" => (string) ($event_row->responsavel_nome ?? ""),
                        "responsavel_whats" => (string) ($event_row->responsavel_whats ?? ""),
                        "category" => "campeonato",
                        "description" => $event_description,
                        "reference" => (string) ($event_row->reference_month ?? ""),
                        "due_date" => (string) ($event_row->due_date ?: $event_row->event_date ?: ""),
                        "payment_date" => (string) ($event_row->last_payment_date ?? ""),
                        "payment_method" => "",
                        "original_amount" => $amount,
                        "paid_amount" => $paid_amount,
                        "balance_amount" => in_array($status, ["paid", "cancelled", "exempt"], true) ? 0.0 : $balance_amount,
                        "status" => $status,
                        "status_raw" => (string) ($event_row->receivable_status ?? ""),
                        "notes" => (string) ($event_row->notes ?? ""),
                        "event_id" => (int) ($event_row->event_id ?? 0),
                        "event_name" => (string) ($event_row->event_name ?? ""),
                        "event_date" => (string) ($event_row->event_date ?? ""),
                        "event_status" => (string) ($event_row->event_status ?? ""),
                        "category_name" => (string) ($event_row->category_name ?? ""),
                        "is_real_charge" => true,
                    ];
                    if ($this->_overview_payment_row_matches($row, $status_filter, $category_filter)) $rows[] = $row;
                    continue;
                }

                $participant_status = strtolower(trim((string) ($event_row->participant_financial_status ?? "")));
                if ($amount <= 0 || strtolower((string) ($event_row->event_status ?? "")) === "cancelled" || in_array($participant_status, ["cancelled", "canceled", "refused", "rejected"], true)) continue;
                $row = [
                    "id" => "academy-pending-" . (int) ($event_row->participant_id ?? 0),
                    "source" => "academy",
                    "source_id" => 0,
                    "charge_id" => 0,
                    "participant_id" => (int) ($event_row->participant_id ?? 0),
                    "aluno_id" => (int) ($event_row->aluno_id ?? 0),
                    "matricula" => (string) ($event_row->matricula ?? ""),
                    "nome_aluno" => (string) ($event_row->nome_aluno ?? ""),
                    "nascimento_aluno" => (string) ($event_row->nascimento_aluno ?? ""),
                    "turma" => (string) ($event_row->turma ?? ""),
                    "pelotao" => (string) ($event_row->pelotao ?? ""),
                    "responsavel_nome" => (string) ($event_row->responsavel_nome ?? ""),
                    "responsavel_whats" => (string) ($event_row->responsavel_whats ?? ""),
                    "category" => "campeonato",
                    "description" => $event_description,
                    "reference" => "",
                    "due_date" => (string) ($event_row->event_date ?? ""),
                    "payment_date" => "",
                    "payment_method" => "",
                    "original_amount" => $amount,
                    "paid_amount" => 0.0,
                    "balance_amount" => 0.0,
                    "status" => "awaiting_charge",
                    "status_raw" => (string) ($event_row->participant_financial_status ?? ""),
                    "notes" => "Participante convocado sem cobrança emitida.",
                    "event_id" => (int) ($event_row->event_id ?? 0),
                    "event_name" => (string) ($event_row->event_name ?? ""),
                    "event_date" => (string) ($event_row->event_date ?? ""),
                    "event_status" => (string) ($event_row->event_status ?? ""),
                    "category_name" => (string) ($event_row->category_name ?? ""),
                    "is_real_charge" => false,
                ];
                if ($this->_overview_payment_row_matches($row, $status_filter, $category_filter)) $rows[] = $row;
            }
        }

        usort($rows, static function (array $left, array $right): int {
            $date_compare = strcmp((string) ($right["due_date"] ?? ""), (string) ($left["due_date"] ?? ""));
            return $date_compare !== 0 ? $date_compare : strcasecmp((string) ($left["nome_aluno"] ?? ""), (string) ($right["nome_aluno"] ?? ""));
        });

        $students = [];
        $total_amount = 0.0;
        $paid_amount = 0.0;
        $balance_amount = 0.0;
        $total_paid = 0;
        $total_open = 0;
        $total_overdue = 0;
        $awaiting_count = 0;
        $awaiting_amount = 0.0;
        foreach ($rows as $row) {
            $students[(int) ($row["aluno_id"] ?? 0)] = true;
            if ($row["status"] === "paid") $total_paid++;
            if (in_array($row["status"], ["open", "partial"], true)) $total_open++;
            if ($row["status"] === "overdue") $total_overdue++;
            if (empty($row["is_real_charge"])) {
                $awaiting_count++;
                $awaiting_amount += (float) $row["original_amount"];
                continue;
            }
            if (in_array($row["status"], ["cancelled", "exempt"], true)) continue;
            $total_amount += (float) $row["original_amount"];
            $paid_amount += (float) $row["paid_amount"];
            $balance_amount += (float) $row["balance_amount"];
        }

        return [
            "rows" => $rows,
            "summary" => [
                "total_cobrancas" => count($rows),
                "total_alunos" => count($students),
                "total_pagos" => $total_paid,
                "total_em_aberto" => $total_open,
                "total_vencidos" => $total_overdue,
                "total_recebido" => $paid_amount,
                "total_a_receber" => $balance_amount,
                "total_previsto" => $total_amount,
                "total_aguardando_cobranca" => $awaiting_count,
                "valor_aguardando_cobranca" => $awaiting_amount,
            ],
        ];
    }

    private function _overview_month_key($charge): string
    {
        $year = (int) ($charge->ano_referencia ?? 0);
        $month = (int) ($charge->mes_referencia ?? 0);
        if ($year >= 2000 && $year <= 2100 && $month >= 1 && $month <= 12) {
            return sprintf("%04d-%02d", $year, $month);
        }

        $competencia = trim((string) ($charge->competencia ?? ""));
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $competencia, $matches)) {
            return sprintf("%04d-%02d", (int) $matches[2], (int) $matches[1]);
        }

        $due_date = trim((string) ($charge->vencimento ?? $charge->due_date ?? ""));
        return preg_match('/^(\d{4})-(\d{2})-/', $due_date, $matches)
            ? sprintf("%04d-%02d", (int) $matches[1], (int) $matches[2])
            : "";
    }

    private function _overview_payment_row_matches(array $row, string $status_filter, string $category_filter): bool
    {
        if ($category_filter !== "" && (string) ($row["category"] ?? "") !== $category_filter) return false;
        if ($status_filter === "pago" && (string) ($row["status"] ?? "") !== "paid") return false;
        if ($status_filter === "aberto" && !in_array((string) ($row["status"] ?? ""), ["open", "partial", "awaiting_charge"], true)) return false;
        if ($status_filter === "vencido" && (string) ($row["status"] ?? "") !== "overdue") return false;
        if ($status_filter === "aguardando" && (string) ($row["status"] ?? "") !== "awaiting_charge") return false;
        return true;
    }

    private function _overview_finance_category(string $type, string $description): string
    {
        $value = mb_strtolower(trim($type . " " . $description));
        if (strpos($value, "passeio") !== false) return "passeio";
        if (strpos($value, "caneleira") !== false || strpos($value, "material 01") !== false) return "caneleira";
        if (strpos($value, "meião") !== false || strpos($value, "meiao") !== false || strpos($value, "material 02") !== false) return "meiao";
        if (strpos($value, "camiseta") !== false || strpos($value, "uniforme") !== false) return "uniforme";
        if (strpos($value, "mensalidade") !== false) return "mensalidade";
        if (strpos($value, "matrícula") !== false || strpos($value, "matricula") !== false || strpos($value, "inscri") !== false) return "matricula";
        return "outros";
    }

    private function _overview_finance_description(string $category, string $description, string $type): string
    {
        // Cobranças antigas podem ter sido gravadas como "1ª parcela",
        // "2ª parcela" etc. A competência continua visível separadamente,
        // mas a nomenclatura da interface passa a ser mensalidade.
        if ($category === "mensalidade") {
            return "Mensalidade";
        }

        return trim($description) !== "" ? trim($description) : (trim($type) !== "" ? trim($type) : "Cobrança");
    }

    private function _overview_finance_status(string $raw_status, string $due_date, float $original_amount, float $paid_amount, string $today, bool $modern = false): string
    {
        $status = mb_strtolower(trim($raw_status));
        if (in_array($status, ["cancelado", "cancelled", "canceled", "void"], true)) return "cancelled";
        if (in_array($status, ["isento", "exempt"], true)) return "exempt";
        if ($status === "pago" || $status === "paid" || ($original_amount > 0 && $paid_amount >= $original_amount)) return "paid";
        if ($modern && $paid_amount > 0) return "partial";
        if ($status === "vencido" || $status === "overdue" || ($due_date !== "" && $due_date < $today)) return "overdue";
        return "open";
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

    /**
     * Procura alunos ativos de outra unidade para criar uma representação
     * local. O filtro de unidade é obrigatório para não expor a base inteira
     * em uma única busca.
     */
    public function search_cross_unit_students($query, int $target_unit_id, int $source_unit_id, int $limit = 20): array
    {
        $query = trim((string) $query);
        if ($target_unit_id <= 0 || $source_unit_id <= 0 || $source_unit_id === $target_unit_id || mb_strlen($query) < 2) {
            return [];
        }

        $alunos_table = $this->db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $this->db->prefixTable("grupo_donato_responsaveis");
        $unidades_table = $this->db->prefixTable("grupo_donato_unidades");
        $query_like = $this->db->escapeLikeString($query);
        $query_digits = preg_replace('/\D+/', '', $query);
        $cpf_condition = "";
        if (mb_strlen($query_digits) >= 3) {
            $cpf_condition = " OR $alunos_table.cpf_aluno LIKE '%" . $this->db->escapeLikeString($query_digits) . "%'
                    OR $responsaveis_table.cpf LIKE '%" . $this->db->escapeLikeString($query_digits) . "%'";
        }
        $limit = min(50, max(1, $limit));

        $sql = "SELECT $alunos_table.id,
                $alunos_table.unidade_id,
                $alunos_table.responsavel_id,
                $alunos_table.matricula,
                $alunos_table.nome_aluno,
                $alunos_table.nascimento_aluno,
                $alunos_table.rg_aluno,
                $alunos_table.cpf_aluno,
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
                $unidades_table.cidade AS unidade_cidade
            FROM $alunos_table
            INNER JOIN $unidades_table ON $unidades_table.id=$alunos_table.unidade_id
                AND $unidades_table.deleted=0 AND $unidades_table.status='Ativo'
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$alunos_table.responsavel_id
            WHERE $alunos_table.deleted=0
                AND $alunos_table.unidade_id=" . (int) $source_unit_id . "
                AND $alunos_table.unidade_id<>" . (int) $target_unit_id . "
                AND $alunos_table.status NOT IN ('Cancelado', 'Concluido')
                AND (
                    $alunos_table.matricula LIKE '%$query_like%' ESCAPE '!'
                    OR $alunos_table.nome_aluno LIKE '%$query_like%' ESCAPE '!'
                    OR $responsaveis_table.nome LIKE '%$query_like%' ESCAPE '!'
                    OR $responsaveis_table.whats LIKE '%$query_like%' ESCAPE '!'
                    $cpf_condition
                )
            ORDER BY $alunos_table.nome_aluno ASC
            LIMIT " . $limit;

        return $this->db->query($sql)->getResultArray();
    }

    /** Busca uma matrícula de outra unidade depois que o usuário a escolheu. */
    public function get_cross_unit_student(int $student_id, int $source_unit_id)
    {
        if ($student_id <= 0 || $source_unit_id <= 0) {
            return null;
        }

        return $this->get_details([
            "id" => $student_id,
            "unidade_id" => $source_unit_id,
            "status_not_in" => ["Cancelado", "Concluido"]
        ])->getRow();
    }
}
