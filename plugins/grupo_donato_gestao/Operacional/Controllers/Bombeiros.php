<?php

namespace grupo_donato_gestao\Operacional\Controllers;

use App\Controllers\Security_Controller;

class Bombeiros extends Security_Controller
{
    private const DEFAULT_UNIT_SLUG = "sao_bernardo_do_campo";
    private const DEFAULT_UNIT_NAME = "Sao Bernardo do Campo";

    public $General_files_model;
    public $Bombeiros_unidades_model;
    public $Bombeiros_responsaveis_model;
    public $Bombeiros_alunos_model;
    public $Bombeiros_cobrancas_model;
    public $Bombeiros_presenca_model;
    public $Bombeiros_comprovantes_model;
    public $Bombeiros_iara_adapter_model;
    public $Bombeiros_person_unit_access_model;
    public $Bombeiros_leads_palestra_model;
    public $Bombeiros_custos_model;

    public function __construct()
    {
        $public_matricula_request = $this->_is_public_matricula_request();
        parent::__construct(!$public_matricula_request);

        if (!$public_matricula_request) {
            $this->access_only_team_members();
        }

        if (function_exists("bombeiros_install_or_update")) {
            bombeiros_install_or_update();
        }

        $this->General_files_model = model("App\Models\General_files_model");
        $this->Bombeiros_unidades_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_unidades_model");
        $this->Bombeiros_responsaveis_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_responsaveis_model");
        $this->Bombeiros_alunos_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_alunos_model");
        $this->Bombeiros_cobrancas_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_cobrancas_model");
        $this->Bombeiros_presenca_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_presenca_model");
        $this->Bombeiros_comprovantes_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_comprovantes_model");
        $this->Bombeiros_iara_adapter_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_iara_adapter_model");
        $this->Bombeiros_person_unit_access_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_person_unit_access_model");
        $this->Bombeiros_leads_palestra_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_leads_palestra_model");
        $this->Bombeiros_custos_model = model("grupo_donato_gestao\Operacional\Models\Bombeiros_custos_model");
    }

    public function index()
    {
        $unidade_atual = $this->_active_unit();
        $dashboard_periodo = $this->_dashboard_periodo();
        $gd_active_tab = $this->_gd_active_tab();
        $view_data["unidade_atual"] = $unidade_atual;
        $view_data["unidades_contexto_dropdown"] = $this->_unidades_contexto_dropdown();
        $view_data["unidades_dropdown"] = $this->_unidades_dropdown(false);
        $view_data["gd_active_tab"] = $gd_active_tab;
        $view_data["dashboard_periodo"] = $dashboard_periodo;
        $view_data["dashboard_resumo"] = $this->_dashboard_resumo_data($dashboard_periodo["mes"], $dashboard_periodo["ano"]);
        $view_data["qualidade_resumo"] = $this->_qualidade_resumo_data();
        $view_data["financeiro_resumo"] = $this->_financeiro_resumo_data();
        $view_data["mensagens_contexto"] = $this->_mensagens_contexto_data();
        return $this->template->render('grupo_donato_gestao\Operacional\Views\index', $view_data);
    }

    public function matricula_publica($slug = "")
    {
        $unidade = $this->_public_unidade($slug);
        if (!$unidade) {
            return $this->response->setStatusCode(404)->setBody("Link de matrícula não encontrado.");
        }

        $this->_set_unidade_ativa($unidade->slug);

        return view('grupo_donato_gestao\Operacional\Views\public_matricula', [
            "unidade" => $unidade,
            "post_url" => get_uri("matricula-online/" . $unidade->slug),
            "turmas" => $this->_turmas_matricula_options(),
            "melhor_horario_options" => $this->_melhor_horario_ligacao_options(),
            "defaults" => [
                "curso_nome" => "ACADEMIA DE TREINAMENTO MIRIM",
                "num_parcelas" => 12,
                "valor_mensalidade" => "237.00",
                "valor_inscricao" => "100.00",
                "data_inicio" => date("Y-m-d")
            ]
        ]);
    }

    public function salvar_matricula_publica($slug = "")
    {
        $unidade = $this->_public_unidade($slug);
        if (!$unidade) {
            echo json_encode(["success" => false, "message" => "Link de matrícula não encontrado."]);
            return;
        }

        if (!$this->_bool_value($this->request->getPost("li_ciente"))) {
            echo json_encode(["success" => false, "message" => "Confirme a ciência dos termos para concluir a matrícula."]);
            return;
        }

        if (!trim((string) $this->request->getPost("assinatura_contratante"))) {
            echo json_encode(["success" => false, "message" => "Informe a assinatura digital do responsável."]);
            return;
        }

        $this->_set_unidade_ativa($unidade->slug);
        $this->save_aluno(true, (int) $unidade->id, "telemarketing");
    }

    public function trocar_unidade()
    {
        $this->validate_submitted_data(["unidade_slug" => "required"]);

        $slug = $this->_slugify($this->request->getPost("unidade_slug"));
        $unidade = $this->Bombeiros_unidades_model->get_details(["slug" => $slug, "status" => "Ativo"])->getRow();

        if (!$unidade) {
            echo json_encode(["success" => false, "message" => "Unidade não encontrada ou inativa."]);
            return;
        }
        if (!$this->_usuario_tem_acesso_unidade($unidade->id)) {
            echo json_encode(["success" => false, "message" => "Você não tem acesso a esta unidade."]);
            return;
        }

        $this->_set_unidade_ativa($unidade->slug);

        echo json_encode([
            "success" => true,
            "message" => "Unidade ativa: " . $unidade->nome_unidade,
            "unidade" => $this->_unit_context_payload($unidade)
        ]);
    }

    public function lista_responsaveis()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\lista_responsaveis');
    }

    public function lista_pagamentos()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\lista_pagamentos');
    }

    public function financeiro_resumo()
    {
        $view_data = $this->_financeiro_resumo_data();
        return $this->template->view('grupo_donato_gestao\Operacional\Views\financeiro_resumo', $view_data);
    }

    public function custos()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\lista_custos');
    }

    public function unidades()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\unidades');
    }

    public function leads_palestra()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\lista_leads_palestra');
    }

    public function templates_mensagem()
    {
        $view_name = "dbo.templates_mensagem";
        $disponivel = $this->Bombeiros_iara_adapter_model->tabela_ou_view_existe($view_name);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\mensagens_status', [
            "titulo" => "Templates de mensagem",
            "disponivel" => $disponivel,
            "rows" => $disponivel ? $this->Bombeiros_iara_adapter_model->listar_view($view_name) : []
        ]);
    }

    public function mensagens()
    {
        $view_name = "dbo.mensagens";
        $disponivel = $this->Bombeiros_iara_adapter_model->tabela_ou_view_existe($view_name);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\mensagens_status', [
            "titulo" => "Mensagens",
            "disponivel" => $disponivel,
            "rows" => $disponivel ? $this->Bombeiros_iara_adapter_model->listar_view($view_name) : []
        ]);
    }

    public function historico_mensagens()
    {
        $view_name = "dbo.historico_mensagens";
        $disponivel = $this->Bombeiros_iara_adapter_model->tabela_ou_view_existe($view_name);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\mensagens_status', [
            "titulo" => "Histórico de mensagens",
            "disponivel" => $disponivel,
            "rows" => $disponivel ? $this->Bombeiros_iara_adapter_model->listar_view($view_name) : []
        ]);
    }

    public function alunos_list_data()
    {
        $options = ["unidade_id" => $this->_active_unit_id(), "status_not_in" => ["Cancelado", "Concluido"]];

        $list_data = $this->Bombeiros_alunos_model->get_details($options)->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_aluno_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function responsaveis_list_data()
    {
        $list_data = $this->Bombeiros_responsaveis_model->get_details(["unidade_id" => $this->_active_unit_id()])->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_responsavel_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function unidades_list_data()
    {
        $list_data = $this->Bombeiros_unidades_model->get_details()->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_unidade_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function pagamentos_list_data()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_view_finance")) {
            echo json_encode(["data" => []]);
            return;
        }

        $mes_referencia = (int) ($this->request->getPost("mes_referencia") ?: date("m"));
        $ano_referencia = (int) ($this->request->getPost("ano_referencia") ?: date("Y"));
        $status_pagamento = trim($this->request->getPost("status_pagamento") ?: "");
        $turma = trim($this->request->getPost("turma") ?: "");

        if ($mes_referencia < 1 || $mes_referencia > 12) {
            $mes_referencia = (int) date("m");
        }
        if ($ano_referencia < 2000 || $ano_referencia > 2100) {
            $ano_referencia = (int) date("Y");
        }
        if ($status_pagamento === "nao_pago") {
            $status_pagamento = "aberto";
        }
        if (!in_array($status_pagamento, ["pago", "aberto", "vencido"], true)) {
            $status_pagamento = "";
        }

        $this->_sincronizar_mensalidades_tela($mes_referencia, $ano_referencia);

        $list_data = $this->Bombeiros_cobrancas_model->get_pagamentos_mensais_alunos([
            "unidade_id" => $this->_active_unit_id(),
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia,
            "status_pagamento" => $status_pagamento,
            "turma" => $turma
        ])->getResult();
        $result = [];
        $added_keys = [];

        foreach ($list_data as $data) {
            $row_key = !empty($data->cobranca_id)
                ? "cobranca:" . (int) $data->cobranca_id
                : "aluno:" . (int) $data->aluno_id . ":" . $ano_referencia . "-" . $mes_referencia;

            if (isset($added_keys[$row_key])) {
                continue;
            }

            $added_keys[$row_key] = true;
            $result[] = $this->_pagamento_mensal_row($data, $mes_referencia, $ano_referencia);
        }

        echo json_encode(["data" => $result]);
    }

    public function pagamentos_mensais_resumo()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_view_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para visualizar pagamentos nesta unidade."]);
            return;
        }

        $mes_referencia = (int) ($this->request->getPost("mes_referencia") ?: date("m"));
        $ano_referencia = (int) ($this->request->getPost("ano_referencia") ?: date("Y"));
        $turma = trim($this->request->getPost("turma") ?: "");

        if ($mes_referencia < 1 || $mes_referencia > 12) {
            $mes_referencia = (int) date("m");
        }
        if ($ano_referencia < 2000 || $ano_referencia > 2100) {
            $ano_referencia = (int) date("Y");
        }

        $this->_sincronizar_mensalidades_tela($mes_referencia, $ano_referencia);

        echo json_encode([
            "success" => true,
            "data" => $this->_pagamentos_mensais_resumo_data($mes_referencia, $ano_referencia, $turma)
        ]);
    }

    public function inadimplencia_list_data()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_view_finance")) {
            echo json_encode(["data" => []]);
            return;
        }

        $list_data = $this->Bombeiros_cobrancas_model->get_details(["overdue" => true, "unidade_id" => $this->_active_unit_id()])->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_inadimplencia_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function cancelados_list_data()
    {
        $list_data = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $this->_active_unit_id(), "status" => "Cancelado"])->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_cancelado_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function concluidos_list_data()
    {
        $list_data = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $this->_active_unit_id(), "status" => "Concluido"])->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_concluido_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function materiais_list_data()
    {
        $list_data = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $this->_active_unit_id(), "status" => "Ativo"])->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_material_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function leads_palestra_list_data()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_view_leads")) {
            echo json_encode(["data" => []]);
            return;
        }

        $options = ["unit_id" => $this->_active_unit_id(), "hide_enrolled_contacts" => true];
        $status = $this->request->getPost("status");
        if ($status) {
            $options["status"] = $status;
        }
        $list_data = $this->Bombeiros_leads_palestra_model->get_details($options)->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_lead_palestra_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function custos_list_data()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_view_finance")) {
            echo json_encode(["data" => []]);
            return;
        }

        $options = ["unit_id" => $this->_active_unit_id()];
        $status = $this->request->getPost("status");
        if ($status) {
            $options["status"] = $status;
        }
        $categoria = $this->request->getPost("categoria");
        if ($categoria) {
            $options["categoria"] = $categoria;
        }

        $list_data = $this->Bombeiros_custos_model->get_details($options)->getResult();
        $result = [];

        foreach ($list_data as $data) {
            $result[] = $this->_custo_row($data);
        }

        echo json_encode(["data" => $result]);
    }

    public function aluno_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        $model_info = $id ? $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow() : $this->_empty_aluno();

        $view_data["model_info"] = $model_info ?: $this->_empty_aluno();
        $view_data["unidades_dropdown"] = $this->_unidades_dropdown();
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_aluno', $view_data);
    }

    public function responsavel_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        $view_data["model_info"] = $id ? $this->Bombeiros_responsaveis_model->get_one($id) : $this->Bombeiros_responsaveis_model->get_one(0);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_responsavel', $view_data);
    }

    public function unidade_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        $view_data["model_info"] = $id ? $this->Bombeiros_unidades_model->get_one($id) : $this->Bombeiros_unidades_model->get_one(0);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_unidade', $view_data);
    }

    public function lead_palestra_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        $view_data["model_info"] = $id ? $this->Bombeiros_leads_palestra_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow() : $this->Bombeiros_leads_palestra_model->get_one(0);
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_lead_palestra', $view_data);
    }

    public function custo_modal_form()
    {
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = $this->request->getPost("id");
        $model_info = $id ? $this->Bombeiros_custos_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow() : $this->_empty_custo();
        $view_data["model_info"] = $model_info ?: $this->_empty_custo();
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_custo', $view_data);
    }

    public function importar_modal_form()
    {
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_importar');
    }

    public function comprovante_modal_form()
    {
        $this->validate_submitted_data([
            "cobranca_id" => "required|numeric",
            "aluno_id" => "required|numeric"
        ]);

        $cobranca_id = $this->request->getPost("cobranca_id");
        $aluno_id = $this->request->getPost("aluno_id");
        $dados = $this->_dados_comprovante($cobranca_id, $aluno_id);

        if (!$dados) {
            echo "<div class='modal-body'><div class='alert alert-danger'>Cobrança não encontrada.</div></div>";
            return;
        }

        $view_data["model_info"] = $dados;
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_comprovante', $view_data);
    }

    public function baixa_pagamento_modal_form()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo "<div class='modal-body'><div class='alert alert-danger'>Você não tem permissão para baixar pagamentos nesta unidade.</div></div>";
            return;
        }

        $id = (int) $this->request->getPost("id");
        $cobranca = $this->Bombeiros_cobrancas_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$cobranca) {
            echo "<div class='modal-body'><div class='alert alert-danger'>Cobrança não encontrada.</div></div>";
            return;
        }

        $view_data["model_info"] = $cobranca;
        return $this->template->view('grupo_donato_gestao\Operacional\Views\modal_baixa_pagamento', $view_data);
    }

    public function save_aluno($public_matricula = false, $public_unidade_id = 0, $origem_matricula = "")
    {
        $db = db_connect();
        $public_matricula = (bool) $public_matricula;

        try {
            if (!$public_matricula && !$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_students")) {
                echo json_encode(["success" => false, "message" => "Você não tem permissão para gerenciar alunos nesta unidade."]);
                return;
            }

            $validation_rules = [
                "responsavel_nome" => "required",
                "responsavel_whats" => "required",
                "nome_aluno" => "required",
                "nascimento_aluno" => "required"
            ];
            if (!$public_matricula) {
                $validation_rules["id"] = "numeric";
            }
            $this->validate_submitted_data($validation_rules);

            $id = $public_matricula ? 0 : (int) $this->request->getPost("id");
            $unidade_id = $public_matricula ? (int) $public_unidade_id : (int) ($this->request->getPost("unidade_id") ?: $this->_active_unit_id());
            $responsavel_id = $public_matricula ? 0 : (int) $this->request->getPost("responsavel_id");
            $whats_limpo = $this->_digits($this->request->getPost("responsavel_whats"));
            $aluno_atual = null;
            if ($id) {
                $aluno_atual = $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
                if (!$aluno_atual) {
                    echo json_encode(["success" => false, "message" => "Aluno não encontrado na unidade ativa."]);
                    return;
                }
            }

            if (!$public_matricula && !$responsavel_id && $whats_limpo) {
                $existente = $this->Bombeiros_responsaveis_model->get_details(["whats" => $whats_limpo])->getRow();
                if ($existente) {
                    $responsavel_id = (int) $existente->id;
                }
            }

            $responsavel_nascimento = $this->_date_value($this->request->getPost("responsavel_nascimento"));
            if ($this->request->getPost("responsavel_nascimento") && !$responsavel_nascimento) {
                echo json_encode(["success" => false, "message" => "Data de nascimento do responsável inválida."]);
                return;
            }

            $nascimento_aluno = $this->_date_value($this->request->getPost("nascimento_aluno"));
            if (!$nascimento_aluno) {
                echo json_encode(["success" => false, "message" => "Data de nascimento do aluno inválida."]);
                return;
            }

            $data_inicio = $this->_date_value($this->request->getPost("data_inicio"));
            if ($this->request->getPost("data_inicio") && !$data_inicio) {
                echo json_encode(["success" => false, "message" => "Data de início inválida."]);
                return;
            }

            $data_inscricao = $this->_date_value($this->request->getPost("data_inscricao"));
            if ($this->request->getPost("data_inscricao") && !$data_inscricao) {
                echo json_encode(["success" => false, "message" => "Data da inscrição inválida."]);
                return;
            }

            $data_primeira_parcela = $this->_date_value($this->request->getPost("data_primeira_parcela"));
            if ($this->request->getPost("data_primeira_parcela") && !$data_primeira_parcela) {
                echo json_encode(["success" => false, "message" => "Data da primeira parcela inválida."]);
                return;
            }

            $dados_resp = [
                "nome" => trim($this->request->getPost("responsavel_nome")),
                "nascimento" => $responsavel_nascimento,
                "rg" => trim($this->request->getPost("responsavel_rg")),
                "cpf" => $this->_digits($this->request->getPost("responsavel_cpf")),
                "endereco" => trim($this->request->getPost("responsavel_endereco")),
                "numero" => trim($this->request->getPost("responsavel_numero")),
                "complemento" => trim($this->request->getPost("responsavel_complemento")),
                "bairro" => trim($this->request->getPost("responsavel_bairro")),
                "cep" => $this->_digits($this->request->getPost("responsavel_cep")),
                "cidade" => trim($this->request->getPost("responsavel_cidade")),
                "whats" => $whats_limpo,
                "celular" => $this->_digits($this->request->getPost("responsavel_celular")),
                "recado" => $this->_digits($this->request->getPost("responsavel_recado")),
                "email" => trim($this->request->getPost("responsavel_email")) ?: null,
                "status" => "Ativo",
                "deleted" => 0
            ];

            $valor_mensalidade = $public_matricula ? 237.00 : $this->_money_to_float($this->request->getPost("valor_mensalidade") ?: $this->request->getPost("valor_parcela"));
            if (!$valor_mensalidade) {
                $valor_mensalidade = 237.00;
            }
            $valor_mensal = $public_matricula ? $valor_mensalidade : $this->_money_to_float($this->request->getPost("valor_mensal"));
            if (!$valor_mensal) {
                $valor_mensal = $valor_mensalidade;
            }
            $num_parcelas = $public_matricula ? 12 : (int) ($this->request->getPost("num_parcelas") ?: 12);
            $num_parcelas = $num_parcelas > 0 ? $num_parcelas : 12;
            $origem_matricula = trim((string) $origem_matricula);
            if (!$origem_matricula) {
                $origem_matricula = trim((string) $this->request->getPost("origem_matricula"));
            }
            if (!$origem_matricula && $aluno_atual && !empty($aluno_atual->origem_matricula)) {
                $origem_matricula = $aluno_atual->origem_matricula;
            }
            $origem_matricula = $origem_matricula ?: "manual";

            $dados_aluno = [
                "unidade_id" => $unidade_id,
                "matricula" => trim((string) ($aluno_atual->matricula ?? "")),
                "nome_aluno" => trim($this->request->getPost("nome_aluno")),
                "rg_aluno" => trim($this->request->getPost("rg_aluno")),
                "cpf_aluno" => $this->_digits($this->request->getPost("cpf_aluno")),
                "nascimento_aluno" => $nascimento_aluno,
                "turma" => $this->request->getPost("horario"),
                "curso_nome" => trim($this->request->getPost("curso_nome")) ?: "ACADEMIA DE TREINAMENTO MIRIM",
                "num_parcelas" => $num_parcelas,
                "valor_mensalidade" => $valor_mensalidade,
                "valor_inscricao" => $public_matricula ? 100.00 : $this->_money_to_float($this->request->getPost("valor_inscricao")),
                "data_inscricao" => $data_inscricao,
                "valor_mensal" => $valor_mensal,
                "data_primeira_parcela" => $data_primeira_parcela,
                "data_inicio" => $data_inicio ?: date("Y-m-d"),
                "tamanho_camisa" => trim($this->request->getPost("tamanho_camisa")),
                "matricula_efetuada" => $public_matricula ? 0 : $this->_bool_value($this->request->getPost("matricula_efetuada")),
                "uniforme_efetuado" => $public_matricula ? 0 : $this->_bool_value($this->request->getPost("uniforme_efetuado")),
                "material_efetuado" => $public_matricula ? 0 : $this->_bool_value($this->request->getPost("material_efetuado")),
                "melhor_horario_ligacao" => trim($this->request->getPost("melhor_horario_ligacao")),
                "cidade_assinatura" => trim($this->request->getPost("cidade_assinatura")),
                "estado_assinatura" => strtoupper(trim($this->request->getPost("estado_assinatura"))),
                "dia_assinatura" => trim($this->request->getPost("dia_assinatura")),
                "mes_assinatura" => trim($this->request->getPost("mes_assinatura")),
                "ano_assinatura" => trim($this->request->getPost("ano_assinatura")),
                "assinatura_contratada" => trim($this->request->getPost("assinatura_contratada")),
                "assinatura_contratante" => trim($this->request->getPost("assinatura_contratante")),
                "li_ciente" => $this->_bool_value($this->request->getPost("li_ciente")),
                "origem_matricula" => $origem_matricula,
                "status" => $public_matricula ? "Ativo" : $this->_normalizar_status_aluno($this->request->getPost("status") ?: "Ativo")
            ];
            if ($dados_aluno["status"] === "Cancelado") {
                $dados_aluno["data_cancelamento"] = $this->_date_value($this->request->getPost("data_cancelamento")) ?: date("Y-m-d");
                $dados_aluno["motivo_cancelamento"] = trim($this->request->getPost("motivo_cancelamento"));
                $dados_aluno["observacao_cancelamento"] = trim($this->request->getPost("observacao_cancelamento"));
                $dados_aluno["cancelado_por"] = $this->login_user->id ?? null;
            } else {
                $dados_aluno["data_cancelamento"] = null;
                $dados_aluno["motivo_cancelamento"] = null;
                $dados_aluno["observacao_cancelamento"] = null;
                $dados_aluno["cancelado_por"] = null;
            }

            $matricula_lock_acquired = false;
            if (!$dados_aluno["matricula"]) {
                $this->_adquirir_trava_matricula($db);
                $matricula_lock_acquired = true;
                $dados_aluno["matricula"] = $this->_proxima_matricula_aluno($db);
            }

            $db->transStart();

            $responsavel_id = $this->Bombeiros_responsaveis_model->ci_save($dados_resp, $responsavel_id);
            if (!$responsavel_id) {
                throw new \RuntimeException("Não foi possível salvar o responsável.");
            }
            $dados_aluno["responsavel_id"] = $responsavel_id;

            if ($id) {
                $save_id = $this->Bombeiros_alunos_model->ci_save($dados_aluno, $id);
                $message = "Dados atualizados com sucesso.";
            } else {
                $dados_aluno["data_matricula"] = date("Y-m-d");
                $dados_aluno["deleted"] = 0;
                $save_id = $this->Bombeiros_alunos_model->ci_save($dados_aluno);
                $message = "Matrícula realizada com sucesso.";
            }

            if (!$save_id) {
                throw new \RuntimeException("Não foi possível salvar o aluno.");
            }

            if (!$id) {
                $this->_gerar_cobrancas_matricula($save_id, $dados_aluno["data_inicio"], $valor_mensalidade, [
                    "num_parcelas" => $num_parcelas,
                    "data_primeira_parcela" => $data_primeira_parcela,
                    "valor_inscricao" => $dados_aluno["valor_inscricao"],
                    "data_inscricao" => $data_inscricao,
                    "matricula_efetuada" => $dados_aluno["matricula_efetuada"],
                    "uniforme_efetuado" => $dados_aluno["uniforme_efetuado"]
                ]);
            }

            $db->transComplete();

            if ($matricula_lock_acquired) {
                $this->_liberar_trava_matricula($db);
                $matricula_lock_acquired = false;
            }

            if ($db->transStatus() === false) {
                throw new \RuntimeException(app_lang("error_occurred"));
            }

            if (($dados_aluno["status"] ?? "") === "Ativo") {
                $this->_garantir_mensalidade_atual_aluno($save_id, (int) ($dados_aluno["unidade_id"] ?? $this->_active_unit_id()));
            }

            echo json_encode(["success" => true, "data" => $this->_aluno_row_data($save_id), "id" => $save_id, "message" => $message]);
        } catch (\Throwable $e) {
            if (isset($db)) {
                $db->transRollback();
                if (!empty($matricula_lock_acquired)) {
                    $this->_liberar_trava_matricula($db);
                }
            }
            log_message("error", "Erro ao salvar aluno Bombeiros: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
        }
    }

    public function save_responsavel()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "nome" => "required",
            "whats" => "required"
        ]);

        $id = $this->request->getPost("id");
        $data = [
            "nome" => trim($this->request->getPost("nome")),
            "nascimento" => $this->_date_value($this->request->getPost("nascimento")),
            "rg" => trim($this->request->getPost("rg")),
            "cpf" => $this->_digits($this->request->getPost("cpf")),
            "whats" => $this->_digits($this->request->getPost("whats")),
            "celular" => $this->_digits($this->request->getPost("celular")),
            "email" => trim($this->request->getPost("email")),
            "endereco" => trim($this->request->getPost("endereco")),
            "numero" => trim($this->request->getPost("numero")),
            "complemento" => trim($this->request->getPost("complemento")),
            "bairro" => trim($this->request->getPost("bairro")),
            "cep" => $this->_digits($this->request->getPost("cep")),
            "cidade" => trim($this->request->getPost("cidade")),
            "recado" => $this->_digits($this->request->getPost("recado")),
            "status" => $this->request->getPost("status") ?: "Ativo",
            "deleted" => 0
        ];

        $save_id = $this->Bombeiros_responsaveis_model->ci_save($data, $id);
        if ($save_id) {
            echo json_encode(["success" => true, "data" => $this->_responsavel_row_data($save_id), "id" => $save_id, "message" => app_lang("record_saved")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    public function save_unidade()
    {
        $this->validate_submitted_data([
            "id" => "numeric",
            "nome_unidade" => "required"
        ]);

        $id = $this->request->getPost("id");
        $nome_unidade = trim($this->request->getPost("nome_unidade"));
        $cidade = trim($this->request->getPost("cidade"));
        if (!$cidade) {
            $cidade = $nome_unidade;
        }
        // Slug baseado no NOME da unidade (mais distintivo que a cidade) e garantido único,
        // pois todo o contexto multiunidade é indexado por slug.
        $base_slug = $this->request->getPost("slug") ?: $nome_unidade ?: $cidade;
        $slug = $this->_unique_unit_slug($base_slug, (int) $id);
        $is_default = $this->_bool_value($this->request->getPost("is_default"));

        $data = [
            "slug" => $slug,
            "is_default" => $is_default,
            "nome_unidade" => $nome_unidade,
            "cidade" => $cidade,
            "endereco" => trim($this->request->getPost("endereco")),
            "status" => $this->request->getPost("status") ?: "Ativo",
            "deleted" => 0
        ];

        try {
            $save_id = $this->Bombeiros_unidades_model->ci_save($data, $id);
            if ($save_id) {
                if ($is_default) {
                    db_connect()->query("UPDATE `" . db_connect()->prefixTable("grupo_donato_unidades") . "` SET is_default=0 WHERE id!=" . (int) $save_id);
                    $this->session->set("grupo_donato_operacional_unidade_slug", $slug);
                }
                $unidade = $this->Bombeiros_unidades_model->get_details(["id" => $save_id])->getRow();
                echo json_encode([
                    "success" => true,
                    "data" => $this->_unidade_row($unidade),
                    "id" => $save_id,
                    "dropdown_option" => $this->_unidade_dropdown_option($unidade),
                    "message" => app_lang("record_saved")
                ]);
            } else {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
            }
        } catch (\Throwable $e) {
            log_message("error", "Erro ao salvar unidade Bombeiros: " . $e->getMessage());
            echo json_encode(["success" => false, "message" => "Erro ao salvar unidade. Verifique os dados e tente novamente."]);
        }
    }

    public function delete_aluno()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int) $this->request->getPost("id");
        $aluno = $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$aluno) {
            echo json_encode(["success" => false, "message" => "Aluno não encontrado na unidade ativa."]);
            return;
        }

        if ($this->Bombeiros_alunos_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
        }
    }

    public function delete_responsavel()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = $this->request->getPost("id");
        $tem_alunos = $this->Bombeiros_alunos_model->get_all_where(["responsavel_id" => $id, "deleted" => 0])->getNumRows();
        if ($tem_alunos) {
            echo json_encode(["success" => false, "message" => "Existem alunos vinculados a este responsável."]);
            return;
        }

        if ($this->Bombeiros_responsaveis_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
        }
    }

    public function delete_unidade()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = $this->request->getPost("id");
        $tem_alunos = $this->Bombeiros_alunos_model->get_all_where(["unidade_id" => $id, "deleted" => 0])->getNumRows();
        if ($tem_alunos) {
            echo json_encode(["success" => false, "message" => "Existem alunos nesta unidade."]);
            return;
        }

        if ($this->Bombeiros_unidades_model->delete($id)) {
            echo json_encode(["success" => true, "message" => app_lang("record_deleted")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("record_cannot_be_deleted")]);
        }
    }

    public function save_custo()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para gerenciar custos nesta unidade."]);
            return;
        }

        $this->validate_submitted_data([
            "id" => "numeric",
            "descricao" => "required",
            "valor" => "required",
            "data_custo" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        if ($id) {
            $custo = $this->Bombeiros_custos_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow();
            if (!$custo) {
                echo json_encode(["success" => false, "message" => "Custo não encontrado na unidade ativa."]);
                return;
            }
        }

        $data_custo = $this->_date_value($this->request->getPost("data_custo"));
        if (!$data_custo) {
            echo json_encode(["success" => false, "message" => "Data do custo inválida."]);
            return;
        }

        $valor = $this->_money_to_float($this->request->getPost("valor"));
        if ($valor <= 0) {
            echo json_encode(["success" => false, "message" => "Informe um valor de custo maior que zero."]);
            return;
        }

        $mes_referencia = (int) ($this->request->getPost("mes_referencia") ?: date("m", strtotime($data_custo)));
        $ano_referencia = (int) ($this->request->getPost("ano_referencia") ?: date("Y", strtotime($data_custo)));
        if ($mes_referencia < 1 || $mes_referencia > 12) {
            $mes_referencia = (int) date("m", strtotime($data_custo));
        }
        if ($ano_referencia < 2000 || $ano_referencia > 2100) {
            $ano_referencia = (int) date("Y", strtotime($data_custo));
        }

        $status = $this->_normalizar_status_custo($this->request->getPost("status"));
        $data = [
            "unit_id" => $this->_active_unit_id(),
            "descricao" => trim($this->request->getPost("descricao")),
            "categoria" => trim($this->request->getPost("categoria")) ?: "Operacional",
            "valor" => $valor,
            "data_custo" => $data_custo,
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia,
            "status" => $status,
            "forma_pagamento" => trim($this->request->getPost("forma_pagamento")),
            "observacao" => trim($this->request->getPost("observacao")),
            "deleted" => 0
        ];

        $save_id = $this->Bombeiros_custos_model->ci_save($data, $id);
        if ($save_id) {
            echo json_encode(["success" => true, "data" => $this->_custo_row_data($save_id), "id" => $save_id, "message" => app_lang("record_saved")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    public function delete_custo()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para gerenciar custos nesta unidade."]);
            return;
        }

        $id = (int) $this->request->getPost("id");
        $custo = $this->Bombeiros_custos_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow();
        if (!$custo) {
            echo json_encode(["success" => false, "message" => "Custo não encontrado na unidade ativa."]);
            return;
        }

        echo json_encode(["success" => (bool) $this->Bombeiros_custos_model->delete($id), "message" => app_lang("record_deleted")]);
    }

    public function save_lead_palestra()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_leads")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para gerenciar leads nesta unidade."]);
            return;
        }

        $this->validate_submitted_data([
            "id" => "numeric",
            "responsavel_nome" => "required",
            "aluno_nome" => "required"
        ]);

        $id = (int) $this->request->getPost("id");
        $telefone = trim((string) $this->request->getPost("telefone"));
        $telefone_normalizado = $this->_digits($telefone);
        $aluno_vinculado = null;
        if ($telefone_normalizado) {
            $aluno_vinculado = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $this->_active_unit_id(), "query" => $telefone_normalizado])->getRow();
        }
        if (!$aluno_vinculado && trim($this->request->getPost("aluno_nome"))) {
            $aluno_vinculado = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $this->_active_unit_id(), "query" => trim($this->request->getPost("aluno_nome"))])->getRow();
        }
        $data = [
            "unit_id" => $this->_active_unit_id(),
            "responsavel_nome" => trim($this->request->getPost("responsavel_nome")),
            "aluno_nome" => trim($this->request->getPost("aluno_nome")),
            "telefone" => $telefone,
            "telefone_normalizado" => $telefone_normalizado,
            "status" => $aluno_vinculado ? "matriculado" : $this->_normalizar_status_lead($this->request->getPost("status")),
            "compareceu_palestra" => 1,
            "aluno_id" => $aluno_vinculado->id ?? null,
            "responsavel_id" => $aluno_vinculado->responsavel_id ?? null,
            "origem" => trim($this->request->getPost("origem")) ?: "manual",
            "observacao" => trim($this->request->getPost("observacao")),
            "data_evento" => $this->_date_value($this->request->getPost("data_evento")) ?: date("Y-m-d"),
            "deleted" => 0
        ];

        $save_id = $this->Bombeiros_leads_palestra_model->ci_save($data, $id);
        if ($save_id) {
            $lead = $this->Bombeiros_leads_palestra_model->get_details(["id" => $save_id, "unit_id" => $this->_active_unit_id()])->getRow();
            echo json_encode(["success" => true, "data" => $this->_lead_palestra_row($lead), "id" => $save_id, "message" => app_lang("record_saved")]);
        } else {
            echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
        }
    }

    public function delete_lead_palestra()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_leads")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para gerenciar leads nesta unidade."]);
            return;
        }

        $id = (int) $this->request->getPost("id");
        $lead = $this->Bombeiros_leads_palestra_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow();
        if (!$lead) {
            echo json_encode(["success" => false, "message" => "Lead não encontrado na unidade ativa."]);
            return;
        }

        echo json_encode(["success" => (bool) $this->Bombeiros_leads_palestra_model->delete($id), "message" => app_lang("record_deleted")]);
    }

    public function converter_lead_em_aluno()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_students")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para criar alunos nesta unidade."]);
            return;
        }

        $id = (int) $this->request->getPost("id");
        $lead = $this->Bombeiros_leads_palestra_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow();
        if (!$lead) {
            echo json_encode(["success" => false, "message" => "Lead não encontrado."]);
            return;
        }

        if ($lead->responsavel_id) {
            $responsavel_id = $lead->responsavel_id;
        } else {
            $dados_responsavel = [
                "nome" => $lead->responsavel_nome ?: "Responsável não informado",
                "whats" => $lead->telefone_normalizado,
                "status" => "Ativo",
                "deleted" => 0
            ];
            $responsavel_id = $this->Bombeiros_responsaveis_model->ci_save($dados_responsavel);
        }

        if ($lead->aluno_id) {
            $aluno_id = $lead->aluno_id;
        } else {
            $db = db_connect();
            $matricula_lock_acquired = false;
            try {
                $this->_adquirir_trava_matricula($db);
                $matricula_lock_acquired = true;
                $dados_aluno = [
                    "unidade_id" => $this->_active_unit_id(),
                    "responsavel_id" => $responsavel_id,
                    "matricula" => $this->_proxima_matricula_aluno($db),
                    "nome_aluno" => $lead->aluno_nome ?: "Aluno sem nome",
                    "data_matricula" => date("Y-m-d"),
                    "data_inicio" => date("Y-m-d"),
                    "status" => "Ativo",
                    "deleted" => 0
                ];
                $aluno_id = $this->Bombeiros_alunos_model->ci_save($dados_aluno);
            } finally {
                if ($matricula_lock_acquired) {
                    $this->_liberar_trava_matricula($db);
                }
            }
        }

        $dados_lead = [
            "status" => "matriculado",
            "aluno_id" => $aluno_id,
            "responsavel_id" => $responsavel_id
        ];
        $this->Bombeiros_leads_palestra_model->ci_save($dados_lead, $id);

        echo json_encode(["success" => true, "message" => "Lead convertido em matrícula."]);
    }

    public function reativar_aluno()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_students")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para reativar alunos nesta unidade."]);
            return;
        }

        $id = (int) $this->request->getPost("id");
        $aluno = $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$aluno) {
            echo json_encode(["success" => false, "message" => "Aluno não encontrado na unidade ativa."]);
            return;
        }

        $dados_aluno = [
            "status" => "Ativo",
            "data_cancelamento" => null,
            "motivo_cancelamento" => null,
            "observacao_cancelamento" => null,
            "cancelado_por" => null
        ];
        $success = $this->Bombeiros_alunos_model->ci_save($dados_aluno, $id);

        echo json_encode(["success" => (bool) $success, "message" => $success ? "Aluno reativado." : app_lang("error_occurred")]);
    }

    public function atualizar_material()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_students")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para alterar materiais nesta unidade."]);
            return;
        }

        $id = (int) $this->request->getPost("id");
        $item = $this->request->getPost("item");
        $status = $this->_normalizar_material($this->request->getPost("status"));
        $allowed = ["camiseta", "material_01", "material_02", "todos"];
        if (!in_array($item, $allowed, true)) {
            echo json_encode(["success" => false, "message" => "Item inválido."]);
            return;
        }

        $aluno = $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$aluno) {
            echo json_encode(["success" => false, "message" => "Aluno não encontrado na unidade ativa."]);
            return;
        }

        $data = [];
        $items = $item === "todos" ? ["camiseta", "material_01", "material_02"] : [$item];
        foreach ($items as $material_item) {
            $data[$material_item] = $status;
            $data[$material_item . "_status"] = $status;
            $data[$material_item . "_data"] = $status === "entregue" ? date("Y-m-d") : null;
        }
        if ($item === "camiseta") {
            $data["uniforme_efetuado"] = $status === "entregue" ? 1 : 0;
        }
        if ($item === "material_01") {
            $data["material_efetuado"] = $status === "entregue" ? 1 : 0;
        }
        if ($item === "todos") {
            $data["uniforme_efetuado"] = $status === "entregue" ? 1 : 0;
            $data["material_efetuado"] = $status === "entregue" ? 1 : 0;
        }

        $success = $this->Bombeiros_alunos_model->ci_save($data, $id);
        echo json_encode(["success" => (bool) $success, "message" => $success ? "Material atualizado." : app_lang("error_occurred")]);
    }

    public function lista_chamada()
    {
        $this->validate_submitted_data([
            "data" => "required",
            "turma" => "required"
        ]);

        $data = $this->request->getPost("data");
        $turma = $this->request->getPost("turma");
        $unidade_id = $this->_active_unit_id();
        $alunos = $this->Bombeiros_alunos_model->get_details(["turma" => $turma, "status" => "Ativo", "unidade_id" => $unidade_id])->getResult();
        $presencas = $this->Bombeiros_presenca_model->get_by_date($data, $unidade_id);
        $historico = [];

        foreach ($presencas as $presenca) {
            $historico[$presenca->aluno_id] = $presenca->status_tipo ?: ((int) $presenca->status ? "presente" : "falta");
        }

        return $this->template->view('grupo_donato_gestao\Operacional\Views\lista_chamada', [
            "alunos" => $alunos,
            "historico" => $historico,
            "data_aula" => $data,
            "turma" => $turma
        ]);
    }

    public function salvar_presenca()
    {
        $this->validate_submitted_data(["data_aula" => "required"]);

        $db = db_connect();
        $data_aula = $this->request->getPost("data_aula");
        $turma = $this->_normalizar_horario_operacional($this->request->getPost("turma"));
        $presencas = $this->request->getPost("presencas");

        if (!$presencas || !is_array($presencas)) {
            echo json_encode(["success" => false, "message" => "Nenhuma presença selecionada."]);
            return;
        }

        try {
            $db->transStart();
            foreach ($presencas as $aluno_id => $status) {
                $where = ["aluno_id" => (int) $aluno_id, "data_aula" => $data_aula];
                $registro = $this->Bombeiros_presenca_model->get_one_where($where);
                $status_tipo = $this->_normalizar_presenca($status);
                $data = $where + ["status" => $status_tipo === "presente" ? 1 : 0, "status_tipo" => $status_tipo, "turma" => $turma];
                $this->Bombeiros_presenca_model->ci_save($data, $registro && $registro->id ? $registro->id : 0);
            }
            $db->transComplete();

            echo json_encode(["success" => $db->transStatus(), "message" => $db->transStatus() ? "Chamada salva." : app_lang("error_occurred")]);
        } catch (\Exception $e) {
            echo json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function baixar_pagamento()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para baixar pagamentos nesta unidade."]);
            return;
        }

        $id = $this->request->getPost("id");
        $cobranca = $this->Bombeiros_cobrancas_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$cobranca) {
            echo json_encode(["success" => false, "message" => "Cobrança não encontrada na unidade ativa."]);
            return;
        }

        $dados_baixa = [
            "status" => "Pago",
            "data_pagamento" => $this->_date_value($this->request->getPost("data_pagamento")) ?: date("Y-m-d H:i:s"),
            "forma_pagamento" => trim($this->request->getPost("forma_pagamento")) ?: ($cobranca->forma_pagamento ?? null),
            "observacao" => trim($this->request->getPost("observacao")) ?: ($cobranca->observacao ?? null),
            "valor" => $this->_money_to_float($this->request->getPost("valor")) ?: (float) $cobranca->valor
        ];
        $success = $this->Bombeiros_cobrancas_model->ci_save($dados_baixa, $id);

        echo json_encode(["success" => (bool) $success, "message" => $success ? "Pagamento baixado com sucesso." : app_lang("error_occurred")]);
    }

    public function marcar_pagamento_pendente()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para alterar pagamentos nesta unidade."]);
            return;
        }

        $id = $this->request->getPost("id");
        $cobranca = $this->Bombeiros_cobrancas_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$cobranca) {
            echo json_encode(["success" => false, "message" => "Cobrança não encontrada na unidade ativa."]);
            return;
        }

        $dados_cobranca = [
            "status" => "Pendente",
            "data_pagamento" => null,
            "forma_pagamento" => null
        ];
        $success = $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca, $id);

        echo json_encode(["success" => (bool) $success, "message" => $success ? "Pagamento marcado como pendente." : app_lang("error_occurred")]);
    }

    public function gerar_mensalidades_periodo()
    {
        $this->validate_submitted_data([
            "mes_referencia" => "required|numeric",
            "ano_referencia" => "required|numeric"
        ]);

        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para gerar cobranças nesta unidade."]);
            return;
        }

        $mes_referencia = (int) $this->request->getPost("mes_referencia");
        $ano_referencia = (int) $this->request->getPost("ano_referencia");
        if ($mes_referencia < 1 || $mes_referencia > 12 || $ano_referencia < 2000 || $ano_referencia > 2100) {
            echo json_encode(["success" => false, "message" => "Competência inválida."]);
            return;
        }

        $criadas = $this->_garantir_mensalidades_periodo($this->_active_unit_id(), $mes_referencia, $ano_referencia);
        $message = $criadas ? "$criadas cobrança(s) criada(s) para o mês." : "Nenhuma cobrança nova foi criada para este mês.";

        echo json_encode(["success" => true, "message" => $message, "total_criadas" => $criadas]);
    }

    public function criar_cobranca_mensal_aluno()
    {
        $this->validate_submitted_data([
            "aluno_id" => "required|numeric",
            "mes_referencia" => "required|numeric",
            "ano_referencia" => "required|numeric"
        ]);

        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para criar cobranças nesta unidade."]);
            return;
        }

        $aluno_id = (int) $this->request->getPost("aluno_id");
        $mes_referencia = (int) $this->request->getPost("mes_referencia");
        $ano_referencia = (int) $this->request->getPost("ano_referencia");

        if ($mes_referencia < 1 || $mes_referencia > 12 || $ano_referencia < 2000 || $ano_referencia > 2100) {
            echo json_encode(["success" => false, "message" => "Competência inválida."]);
            return;
        }

        $unidade_id = $this->_active_unit_id();
        $aluno = $this->Bombeiros_alunos_model->get_details([
            "id" => $aluno_id,
            "unidade_id" => $unidade_id,
            "status" => "Ativo"
        ])->getRow();

        if (!$aluno) {
            echo json_encode(["success" => false, "message" => "Aluno ativo não encontrado na unidade atual."]);
            return;
        }

        if (!$this->_aluno_deve_receber_cobranca_periodo($aluno, $mes_referencia, $ano_referencia)) {
            echo json_encode(["success" => false, "message" => "Este aluno ainda não deve receber cobrança nesta competência."]);
            return;
        }

        $db = db_connect();
        $lock_name = "grupo_donato_mensalidade_aluno_" . $unidade_id . "_" . $aluno_id . "_" . $ano_referencia . "_" . $mes_referencia;
        $lock = $db->query("SELECT GET_LOCK(" . $db->escape($lock_name) . ", 10) AS lock_status")->getRow();
        if ((int) ($lock->lock_status ?? 0) !== 1) {
            echo json_encode(["success" => false, "message" => "Não foi possível bloquear a criação da cobrança. Tente novamente."]);
            return;
        }

        try {
            if ($this->_buscar_cobranca_mensal_aluno($aluno_id, $mes_referencia, $ano_referencia)) {
                echo json_encode(["success" => false, "message" => "Este aluno já possui cobrança neste mês."]);
                return;
            }

            $cobranca = $this->_garantir_cobranca_mensal_aluno($aluno, $unidade_id, $mes_referencia, $ano_referencia);
            echo json_encode([
                "success" => (bool) $cobranca,
                "message" => $cobranca ? "Cobrança do mês criada." : app_lang("error_occurred"),
                "cobranca_id" => $cobranca->id ?? 0
            ]);
        } finally {
            $db->query("SELECT RELEASE_LOCK(" . $db->escape($lock_name) . ")");
        }
    }

    public function toggle_pagamento_mensal()
    {
        $this->validate_submitted_data([
            "aluno_id" => "required|numeric",
            "mes_referencia" => "required|numeric",
            "ano_referencia" => "required|numeric"
        ]);

        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_manage_finance")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para alterar pagamentos nesta unidade."]);
            return;
        }

        $aluno_id = (int) $this->request->getPost("aluno_id");
        $mes_referencia = (int) $this->request->getPost("mes_referencia");
        $ano_referencia = (int) $this->request->getPost("ano_referencia");
        $pago = (int) $this->request->getPost("pago") === 1;

        if ($mes_referencia < 1 || $mes_referencia > 12 || $ano_referencia < 2000 || $ano_referencia > 2100) {
            echo json_encode(["success" => false, "message" => "Competência inválida."]);
            return;
        }

        $unidade_id = $this->_active_unit_id();
        $aluno = $this->Bombeiros_alunos_model->get_details([
            "id" => $aluno_id,
            "unidade_id" => $unidade_id,
            "status" => "Ativo"
        ])->getRow();

        if (!$aluno) {
            echo json_encode(["success" => false, "message" => "Aluno ativo não encontrado na unidade atual."]);
            return;
        }

        $cobranca = $this->_buscar_cobranca_mensal_aluno($aluno_id, $mes_referencia, $ano_referencia);
        if (!$cobranca) {
            if ($this->_aluno_deve_receber_cobranca_periodo($aluno, $mes_referencia, $ano_referencia)) {
                $cobranca = $this->_garantir_cobranca_mensal_aluno($aluno, $unidade_id, $mes_referencia, $ano_referencia);
            }
            if (!$cobranca) {
                echo json_encode(["success" => false, "message" => "Este aluno não possui cobrança nesta competência."]);
                return;
            }
        }

        $valor = (float) ($cobranca->valor ?: ($aluno->valor_mensalidade ?: ($aluno->valor_mensal ?: 237.00)));
        $dados_cobranca = [
            "status" => $pago ? "Pago" : "Pendente",
            "valor" => $valor ?: 237.00,
            "competencia" => sprintf("%02d/%04d", $mes_referencia, $ano_referencia),
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia,
            "tipo" => "Mensalidade"
        ];

        if ($pago) {
            $dados_cobranca["data_pagamento"] = date("Y-m-d H:i:s");
        } else {
            $dados_cobranca["data_pagamento"] = null;
            $dados_cobranca["forma_pagamento"] = null;
        }

        $success = $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca, $cobranca->id);
        $message = $pago ? "Pagamento marcado como pago." : "Pagamento marcado como não pago.";

        echo json_encode(["success" => (bool) $success, "message" => $success ? $message : app_lang("error_occurred")]);
    }

    public function importar_csv()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_import_data")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para importar dados nesta unidade."]);
            return;
        }

        $file = $this->request->getFile("file");
        if (!$file || !$file->isValid()) {
            echo json_encode(["success" => false, "message" => "Arquivo CSV inválido."]);
            return;
        }

        $file_path = $file->getTempName();
        $extension = strtolower($file->getClientExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        if ($extension === "json") {
            $payload = json_decode(file_get_contents($file_path), true);
            if (!is_array($payload)) {
                echo json_encode(["success" => false, "message" => "JSON inválido."]);
                return;
            }

            $normalizado = $this->_normalizar_payload_importacao($payload);
            $preview = $this->_preview_importacao($normalizado);
            $this->_salvar_payload_importacao_temporario($normalizado);

            echo json_encode([
                "success" => true,
                "preview_html" => view("grupo_donato_gestao\Operacional\Views\importacao_preview", ["preview" => $preview]),
                "message" => "JSON validado. Confirme a prévia antes de importar."
            ]);
            return;
        }

        $content = file_get_contents($file_path);
        if (!mb_check_encoding($content, "UTF-8")) {
            $content = mb_convert_encoding($content, "UTF-8", "ISO-8859-1");
            file_put_contents($file_path, $content);
        }

        $handle = fopen($file_path, "r");
        if (!$handle) {
            echo json_encode(["success" => false, "message" => "Não foi possível ler o arquivo."]);
            return;
        }

        fgetcsv($handle, 3000, ";");
        $importados = 0;
        $unidade_padrao = $this->_active_unit_id();

        if (!$unidade_padrao) {
            fclose($handle);
            echo json_encode(["success" => false, "message" => "Cadastre uma unidade antes de importar alunos."]);
            return;
        }

        try {
            while (($row = fgetcsv($handle, 3000, ";")) !== false) {
                if (count($row) < 26) {
                    continue;
                }

                $cpf_resp = $this->_digits($row[3]);
                $resp_id = 0;

                if ($cpf_resp) {
                    $existente = $this->Bombeiros_responsaveis_model->get_details(["cpf" => $cpf_resp])->getRow();
                    $resp_id = $existente ? $existente->id : 0;
                }

                $dados_resp = [
                    "nome" => mb_convert_case(trim($row[0]), MB_CASE_TITLE, "UTF-8"),
                    "nascimento" => $this->_date_value($row[1]),
                    "rg" => trim($row[2]),
                    "cpf" => $cpf_resp,
                    "endereco" => trim($row[4]),
                    "numero" => trim($row[5]),
                    "complemento" => trim($row[6]),
                    "bairro" => trim($row[7]),
                    "cep" => $this->_digits($row[8]),
                    "cidade" => trim($row[9]),
                    "whats" => $this->_digits($row[10]),
                    "celular" => $this->_digits($row[11]),
                    "email" => strtolower(trim($row[12])),
                    "status" => "Ativo",
                    "deleted" => 0
                ];
                $resp_id = $this->Bombeiros_responsaveis_model->ci_save($dados_resp, $resp_id);

                $dados_aluno = [
                    "unidade_id" => $unidade_padrao,
                    "responsavel_id" => $resp_id,
                    "nome_aluno" => mb_convert_case(trim($row[13]), MB_CASE_TITLE, "UTF-8"),
                    "nascimento_aluno" => $this->_date_value($row[14]),
                    "rg_aluno" => trim($row[15] ?? ""),
                    "cpf_aluno" => $this->_digits($row[16] ?? ""),
                    "turma" => trim($row[18] ?? ""),
                    "valor_mensalidade" => $this->_money_to_float($row[19] ?? "0"),
                    "data_matricula" => date("Y-m-d"),
                    "data_inicio" => $this->_date_value($row[24] ?? ""),
                    "tamanho_camisa" => trim($row[25] ?? ""),
                    "status" => "Ativo",
                    "deleted" => 0
                ];
                $this->Bombeiros_alunos_model->ci_save($dados_aluno);

                $importados++;
            }

            fclose($handle);
            $mensalidades_criadas = $this->_garantir_mensalidades_mes_atual($unidade_padrao);
            $message = "$importados registros processados.";
            if ($mensalidades_criadas) {
                $message .= " $mensalidades_criadas cobrança(s) do mês criada(s).";
            }
            echo json_encode(["success" => true, "message" => $message]);
        } catch (\Exception $e) {
            fclose($handle);
            echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
        }
    }

    public function importar_preview()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_import_data")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para importar dados nesta unidade."]);
            return;
        }

        $file = $this->request->getFile("file");
        if (!$file || !$file->isValid()) {
            echo json_encode(["success" => false, "message" => "Arquivo inválido."]);
            return;
        }

        $extension = strtolower($file->getClientExtension() ?: pathinfo($file->getName(), PATHINFO_EXTENSION));
        if ($extension !== "json") {
            echo json_encode(["success" => false, "message" => "A prévia operacional aceita JSON. Para CSV legado, use a rotina antiga."]);
            return;
        }

        $payload = json_decode(file_get_contents($file->getTempName()), true);
        if (!is_array($payload)) {
            echo json_encode(["success" => false, "message" => "JSON inválido."]);
            return;
        }

        $normalizado = $this->_normalizar_payload_importacao($payload);
        $preview = $this->_preview_importacao($normalizado);
        $this->_salvar_payload_importacao_temporario($normalizado);

        echo json_encode([
            "success" => true,
            "preview_html" => view("grupo_donato_gestao\Operacional\Views\importacao_preview", ["preview" => $preview]),
            "message" => "Prévia gerada. Revise antes de confirmar."
        ]);
    }

    public function confirmar_importacao()
    {
        if (!$this->_usuario_tem_acesso_unidade($this->_active_unit_id(), "can_import_data")) {
            echo json_encode(["success" => false, "message" => "Você não tem permissão para importar dados nesta unidade."]);
            return;
        }

        $payload = $this->_carregar_payload_importacao_temporario();
        if (!$payload || !is_array($payload)) {
            echo json_encode(["success" => false, "message" => "Nenhuma prévia pendente para confirmar."]);
            return;
        }

        $relatorio = $this->_executar_importacao_normalizada($payload);
        $this->_salvar_relatorio_importacao_temporario($relatorio);
        $this->_limpar_payload_importacao_temporario();

        echo json_encode([
            "success" => empty($relatorio["erros_criticos"]),
            "report_html" => view("grupo_donato_gestao\Operacional\Views\importacao_relatorio", ["relatorio" => $relatorio]),
            "message" => empty($relatorio["erros_criticos"]) ? "Importação concluída." : "Importação revertida por erro crítico."
        ]);
    }

    public function importacao_relatorio()
    {
        $relatorio = $this->_carregar_relatorio_importacao_temporario() ?: [];
        return $this->template->view("grupo_donato_gestao\Operacional\Views\importacao_relatorio", ["relatorio" => $relatorio]);
    }

    public function buscar_dados_comprovante()
    {
        $this->validate_submitted_data([
            "cobranca_id" => "required|numeric",
            "aluno_id" => "required|numeric"
        ]);

        $dados = $this->_dados_comprovante($this->request->getPost("cobranca_id"), $this->request->getPost("aluno_id"));
        if (!$dados) {
            echo json_encode(["success" => false, "message" => "Cobrança não encontrada."]);
            return;
        }

        echo json_encode(["success" => true, "data" => $dados]);
    }

    private function _importar_json_normalizado($file_path)
    {
        $payload = json_decode(file_get_contents($file_path), true);
        if (!is_array($payload)) {
            echo json_encode(["success" => false, "message" => "JSON inválido."]);
            return;
        }

        $db = db_connect();
        $unidade_id = $this->_active_unit_id();
        if (!$unidade_id) {
            echo json_encode(["success" => false, "message" => "Selecione uma unidade antes de importar."]);
            return;
        }

        $responsaveis = $payload["responsaveis"] ?? [];
        $alunos = $payload["alunos"] ?? [];
        $pagamentos = $payload["pagamentos"] ?? [];
        $materiais = $payload["materiais"] ?? [];
        $presencas = $payload["presencas"] ?? [];

        $responsavel_map = [];
        $aluno_map = [];
        $importados = [
            "responsaveis" => 0,
            "alunos" => 0,
            "pagamentos" => 0,
            "materiais" => 0,
            "presencas" => 0
        ];

        try {
            $db->transStart();

            foreach ($responsaveis as $responsavel) {
                $cpf = $this->_digits($responsavel["cpf"] ?? "");
                $telefone = $this->_digits($responsavel["telefone"] ?? ($responsavel["whats"] ?? ""));
                $existente = null;
                if ($cpf) {
                    $existente = $this->Bombeiros_responsaveis_model->get_details(["cpf" => $cpf])->getRow();
                }
                if (!$existente && $telefone) {
                    $existente = $this->Bombeiros_responsaveis_model->get_details(["whats" => $telefone])->getRow();
                }

                $dados_responsavel = [
                    "nome" => trim($responsavel["nome"] ?? ($responsavel["responsavel_nome"] ?? "")),
                    "cpf" => $cpf,
                    "whats" => $telefone,
                    "celular" => $this->_digits($responsavel["celular"] ?? ""),
                    "email" => trim($responsavel["email"] ?? ""),
                    "endereco" => trim($responsavel["endereco"] ?? ""),
                    "status" => "Ativo",
                    "deleted" => 0
                ];
                $save_id = $this->Bombeiros_responsaveis_model->ci_save($dados_responsavel, $existente->id ?? 0);

                if ($cpf) {
                    $responsavel_map["cpf:" . $cpf] = $save_id;
                }
                if ($telefone) {
                    $responsavel_map["telefone:" . $telefone] = $save_id;
                }
                if (!empty($responsavel["id"])) {
                    $responsavel_map["id:" . $responsavel["id"]] = $save_id;
                }
                $importados["responsaveis"]++;
            }

            foreach ($alunos as $aluno) {
                $matricula = trim((string) ($aluno["matricula"] ?? ""));
                $responsavel_id = 0;
                $cpf_resp = $this->_digits($aluno["responsavel_cpf"] ?? "");
                $telefone_resp = $this->_digits($aluno["responsavel_telefone"] ?? ($aluno["telefone"] ?? ""));
                if ($cpf_resp && isset($responsavel_map["cpf:" . $cpf_resp])) {
                    $responsavel_id = $responsavel_map["cpf:" . $cpf_resp];
                } elseif ($telefone_resp && isset($responsavel_map["telefone:" . $telefone_resp])) {
                    $responsavel_id = $responsavel_map["telefone:" . $telefone_resp];
                } elseif ($cpf_resp) {
                    $existente = $this->Bombeiros_responsaveis_model->get_details(["cpf" => $cpf_resp])->getRow();
                    $responsavel_id = $existente->id ?? 0;
                } elseif ($telefone_resp) {
                    $existente = $this->Bombeiros_responsaveis_model->get_details(["whats" => $telefone_resp])->getRow();
                    $responsavel_id = $existente->id ?? 0;
                }

                if (!$responsavel_id) {
                    $dados_responsavel = [
                        "nome" => trim($aluno["responsavel_nome"] ?? "Responsável não informado"),
                        "cpf" => $cpf_resp,
                        "whats" => $telefone_resp,
                        "status" => "Ativo",
                        "deleted" => 0
                    ];
                    $responsavel_id = $this->Bombeiros_responsaveis_model->ci_save($dados_responsavel);
                }

                $existente_aluno = $matricula ? $this->Bombeiros_alunos_model->get_details(["matricula" => $matricula, "unidade_id" => $unidade_id])->getRow() : null;
                $dados_aluno = [
                    "unidade_id" => $unidade_id,
                    "responsavel_id" => $responsavel_id,
                    "matricula" => $matricula ?: null,
                    "nome_aluno" => trim($aluno["aluno_nome"] ?? ($aluno["nome"] ?? "")),
                    "nascimento_aluno" => $this->_date_value($aluno["data_nascimento"] ?? ($aluno["nascimento_aluno"] ?? "")),
                    "turma" => trim($aluno["horario"] ?? ($aluno["turma"] ?? "")),
                    "horario" => trim($aluno["horario"] ?? ""),
                    "pelotao" => trim($aluno["pelotao"] ?? ""),
                    "valor_mensalidade" => $this->_money_to_float($aluno["mensalidade"] ?? ($aluno["valor_mensalidade"] ?? 0)),
                    "tamanho_camisa" => trim($aluno["camiseta"] ?? ($aluno["tamanho_camisa"] ?? "")),
                    "camiseta" => trim($aluno["camiseta"] ?? ""),
                    "material_01" => trim($aluno["material_01"] ?? ""),
                    "material_02" => trim($aluno["material_02"] ?? ""),
                    "data_matricula" => $this->_date_value($aluno["data_matricula"] ?? "") ?: date("Y-m-d"),
                    "data_inicio" => $this->_date_value($aluno["data_inicio"] ?? ""),
                    "status" => $this->_normalizar_status_aluno($aluno["status"] ?? "Ativo"),
                    "deleted" => 0
                ];
                $save_id = $this->Bombeiros_alunos_model->ci_save($dados_aluno, $existente_aluno->id ?? 0);

                if ($matricula) {
                    $aluno_map[$matricula] = $save_id;
                }
                $importados["alunos"]++;
            }

            foreach ($pagamentos as $pagamento) {
                $matricula = trim((string) ($pagamento["matricula"] ?? ""));
                if (!$matricula || empty($aluno_map[$matricula])) {
                    continue;
                }

                $status = $this->_normalizar_pagamento($pagamento["status"] ?? "");
                $dados_cobranca = [
                    "aluno_id" => $aluno_map[$matricula],
                    "vencimento" => $this->_date_value($pagamento["vencimento"] ?? ($pagamento["data_vencimento"] ?? "")) ?: date("Y-m-d"),
                    "valor" => $this->_money_to_float($pagamento["valor"] ?? 0),
                    "competencia" => trim($pagamento["competencia"] ?? ""),
                    "status" => $status,
                    "tipo" => trim($pagamento["tipo"] ?? "Mensalidade"),
                    "data_pagamento" => $status === "Pago" ? ($this->_date_value($pagamento["data_pagamento"] ?? "") ?: date("Y-m-d")) : null,
                    "forma_pagamento" => trim($pagamento["forma_pagamento"] ?? "")
                ];
                $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca);
                $importados["pagamentos"]++;
            }

            foreach ($materiais as $material) {
                $matricula = trim((string) ($material["matricula"] ?? ""));
                if (!$matricula || empty($aluno_map[$matricula])) {
                    continue;
                }

                $dados_material = [
                    "camiseta" => $this->_normalizar_material($material["camiseta"] ?? ""),
                    "material_01" => $this->_normalizar_material($material["material_01"] ?? ""),
                    "material_02" => $this->_normalizar_material($material["material_02"] ?? ""),
                    "uniforme_efetuado" => $this->_normalizar_material($material["camiseta"] ?? "") === "entregue" ? 1 : 0,
                    "material_efetuado" => $this->_normalizar_material($material["material_01"] ?? "") === "entregue" ? 1 : 0
                ];
                $this->Bombeiros_alunos_model->ci_save($dados_material, $aluno_map[$matricula]);
                $importados["materiais"]++;
            }

            foreach ($presencas as $presenca) {
                $matricula = trim((string) ($presenca["matricula"] ?? ""));
                if (!$matricula || empty($aluno_map[$matricula])) {
                    continue;
                }

                $data_aula = $this->_date_value($presenca["data"] ?? ($presenca["data_aula"] ?? ""));
                if (!$data_aula) {
                    continue;
                }

                $where = ["aluno_id" => $aluno_map[$matricula], "data_aula" => $data_aula];
                $registro = $this->Bombeiros_presenca_model->get_one_where($where);
                $status_tipo = $this->_normalizar_presenca($presenca["status"] ?? "");
                if ($status_tipo === "sem_registro") {
                    continue;
                }
                $dados_presenca = $where + [
                    "status" => $status_tipo === "presente" ? 1 : 0,
                    "status_tipo" => $status_tipo
                ];
                $this->Bombeiros_presenca_model->ci_save($dados_presenca, $registro && $registro->id ? $registro->id : 0);
                $importados["presencas"]++;
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException(app_lang("error_occurred"));
            }

            echo json_encode(["success" => true, "message" => "JSON importado: " . json_encode($importados, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {
            $db->transRollback();
            echo json_encode(["success" => false, "message" => "Erro na importação JSON: " . $e->getMessage()]);
        }
    }

    public function gerar_comprovante()
    {
        $this->validate_submitted_data([
            "cobranca_id" => "required|numeric",
            "aluno_id" => "required|numeric",
            "responsavel_nome" => "required",
            "aluno_nome" => "required",
            "valor" => "required",
            "forma_pagamento" => "required"
        ]);

        $db = db_connect();
        $cobranca_id = $this->request->getPost("cobranca_id");
        $aluno_id = $this->request->getPost("aluno_id");
        $aluno = $this->Bombeiros_alunos_model->get_details(["id" => $aluno_id, "unidade_id" => $this->_active_unit_id()])->getRow();

        if (!$aluno || !$aluno->responsavel_id) {
            echo json_encode(["success" => false, "message" => "Responsável do aluno não encontrado."]);
            return;
        }

        $responsavel_cpf = $this->_format_cpf($this->_digits($this->request->getPost("responsavel_cpf")));
        $numero_comprovante = "COMP-" . date("Ymd") . "-" . str_pad($cobranca_id, 4, "0", STR_PAD_LEFT);
        $valor = $this->_money_to_float($this->request->getPost("valor"));
        $data_emissao = $this->_date_value($this->request->getPost("data_emissao")) ?: date("Y-m-d");
        $data_conferencia = $this->_date_value($this->request->getPost("data_conferencia"));
        $forma_pagamento = $this->request->getPost("forma_pagamento");

        if (!in_array($forma_pagamento, ["BOLETO", "CRÉDITO", "DÉBITO", "PIX"], true)) {
            echo json_encode(["success" => false, "message" => "Forma de pagamento inválida."]);
            return;
        }

        $dados_comprovante = [
            "numero_comprovante" => $numero_comprovante,
            "data_emissao" => $data_emissao,
            "responsavel_id" => $aluno->responsavel_id,
            "responsavel_nome" => trim($this->request->getPost("responsavel_nome")),
            "responsavel_cpf" => $responsavel_cpf,
            "aluno_id" => $aluno_id,
            "aluno_nome" => trim($this->request->getPost("aluno_nome")),
            "aluno_nome_adicional" => trim($this->request->getPost("aluno_nome_adicional")) ?: null,
            "mensalidade_numero" => (int) $this->request->getPost("mensalidade_numero"),
            "valor" => $valor,
            "forma_pagamento" => $forma_pagamento,
            "conferido_por" => trim($this->request->getPost("conferido_por")) ?: null,
            "data_conferencia" => $data_conferencia,
            "cobranca_id" => $cobranca_id,
            "deleted" => 0
        ];

        try {
            $db->transStart();
            $comprovante_id = $this->Bombeiros_comprovantes_model->ci_save($dados_comprovante);
            $dados_baixa = [
                "status" => "Pago",
                "data_pagamento" => date("Y-m-d H:i:s")
            ];
            $this->Bombeiros_cobrancas_model->ci_save($dados_baixa, $cobranca_id);

            $html = view('grupo_donato_gestao\Operacional\Views\comprovante_template', $this->_comprovante_view_data($dados_comprovante));
            $upload_path = WRITEPATH . "uploads/comprovantes/";
            if (!is_dir($upload_path)) {
                @mkdir($upload_path, 0755, true);
            }

            $filename = "comprovante_" . $comprovante_id . "_" . time() . ".html";
            file_put_contents($upload_path . $filename, $html);
            $dados_arquivo = ["arquivo_path" => "uploads/comprovantes/" . $filename];
            $this->Bombeiros_comprovantes_model->ci_save($dados_arquivo, $comprovante_id);
            $db->transComplete();

            if ($db->transStatus() === false) {
                echo json_encode(["success" => false, "message" => app_lang("error_occurred")]);
                return;
            }

            echo json_encode([
                "success" => true,
                "message" => "Pagamento baixado e comprovante gerado.",
                "download_url" => get_uri("grupo_donato/operacional/baixar_comprovante/" . $comprovante_id),
                "pdf_url" => get_uri("grupo_donato/operacional/baixar_comprovante_pdf/" . $comprovante_id)
            ]);
        } catch (\Exception $e) {
            echo json_encode(["success" => false, "message" => "Erro: " . $e->getMessage()]);
        }
    }

    public function baixar_comprovante($comprovante_id)
    {
        $comprovante = $this->Bombeiros_comprovantes_model->get_details(["id" => $comprovante_id])->getRowArray();
        if (!$comprovante) {
            return "Comprovante não encontrado.";
        }

        $html = view('grupo_donato_gestao\Operacional\Views\comprovante_template', $this->_comprovante_view_data($comprovante));
        $filename = "Comprovante_Grupo_Donato_" . $comprovante["numero_comprovante"] . ".html";

        $this->response->setHeader("Content-Type", "text/html; charset=utf-8");
        $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");
        $this->response->setBody($html);
        return $this->response;
    }

    public function baixar_comprovante_pdf($comprovante_id)
    {
        $comprovante = $this->Bombeiros_comprovantes_model->get_details(["id" => $comprovante_id])->getRowArray();
        if (!$comprovante) {
            return "Comprovante não encontrado.";
        }

        $html = view('grupo_donato_gestao\Operacional\Views\comprovante_template', $this->_comprovante_view_data($comprovante));
        $filename = "Comprovante_Grupo_Donato_" . $comprovante["numero_comprovante"] . ".pdf";

        if (class_exists("\\Dompdf\\Dompdf")) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper("A4", "portrait");
            $dompdf->render();
            $this->response->setHeader("Content-Type", "application/pdf");
            $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");
            $this->response->setBody($dompdf->output());
            return $this->response;
        }

        if (class_exists("\\Mpdf\\Mpdf")) {
            $mpdf = new \Mpdf\Mpdf();
            $mpdf->WriteHTML($html);
            $this->response->setHeader("Content-Type", "application/pdf");
            $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");
            $this->response->setBody($mpdf->Output("", "S"));
            return $this->response;
        }

        if (class_exists("\\TCPDF")) {
            $pdf = new \TCPDF();
            $pdf->AddPage();
            $pdf->writeHTML($html);
            $this->response->setHeader("Content-Type", "application/pdf");
            $this->response->setHeader("Content-Disposition", "attachment; filename=\"" . $filename . "\"");
            $this->response->setBody($pdf->Output("", "S"));
            return $this->response;
        }

        $this->response->setHeader("Content-Type", "text/plain; charset=utf-8");
        $this->response->setBody("Geração de PDF não disponível neste ambiente.");
        return $this->response;
    }

    public function visualizar_comprovante($comprovante_id)
    {
        $comprovante = $this->Bombeiros_comprovantes_model->get_details(["id" => $comprovante_id])->getRowArray();
        if (!$comprovante) {
            return "Comprovante não encontrado.";
        }

        return view('grupo_donato_gestao\Operacional\Views\comprovante_template', $this->_comprovante_view_data($comprovante));
    }

    private function _aluno_row_data($id)
    {
        $data = $this->Bombeiros_alunos_model->get_details(["id" => $id, "unidade_id" => $this->_active_unit_id()])->getRow();
        return $this->_aluno_row($data);
    }

    private function _aluno_row($data)
    {
        $status_class = $this->_aluno_status_class($data->status);
        $matricula = $data->matricula ?: (string) $data->id;
        $origem_matricula = $this->_origem_matricula_label($data->origem_matricula ?? "manual");
        $options = modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Editar aluno",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
            "class" => "delete",
            "title" => app_lang("delete"),
            "data-id" => $data->id,
            "data-action-url" => get_uri("grupo_donato/operacional/delete_aluno"),
            "data-action" => "delete-confirmation"
        ]);

        return [
            esc($matricula),
            esc($data->nome_aluno),
            esc($data->nome_unidade ?: "-"),
            esc($data->responsavel_nome ?: "-"),
            esc($this->_format_phone($data->responsavel_whats)),
            esc($data->turma ?: "-"),
            esc($data->tamanho_camisa ?: "-"),
            "R$ " . number_format((float) $data->valor_mensalidade, 2, ",", "."),
            "<span class='badge bg-secondary'>" . esc($origem_matricula) . "</span>",
            "<span class='badge $status_class'>" . esc($this->_aluno_status_label($data->status)) . "</span>",
            $options
        ];
    }

    private function _cancelado_row($data)
    {
        $options = modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='eye' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Ver ficha",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='rotate-ccw' class='icon-16'></i>", [
            "class" => "edit bombeiros-reativar-aluno",
            "title" => "Reativar aluno",
            "data-id" => $data->id
        ]);

        return [
            esc($data->matricula ?: (string) $data->id),
            esc($data->nome_aluno),
            esc($data->responsavel_nome ?: "-"),
            $this->_format_date($data->data_cancelamento ?? ""),
            esc($data->motivo_cancelamento ?: "-"),
            esc($data->observacao_cancelamento ?: "-"),
            $options
        ];
    }

    private function _concluido_row($data)
    {
        $options = modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='eye' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Ver ficha",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='rotate-ccw' class='icon-16'></i>", [
            "class" => "edit bombeiros-reativar-aluno",
            "title" => "Reativar aluno",
            "data-id" => $data->id
        ]);

        return [
            esc($data->matricula ?: (string) $data->id),
            esc($data->nome_aluno),
            esc($data->responsavel_nome ?: "-"),
            esc($data->turma ?: "-"),
            $this->_format_date($data->data_inicio ?? ""),
            "R$ " . number_format((float) $data->valor_mensalidade, 2, ",", "."),
            "<span class='badge " . $this->_aluno_status_class($data->status) . "'>" . esc($this->_aluno_status_label($data->status)) . "</span>",
            $options
        ];
    }

    private function _aluno_status_class($status)
    {
        $classes = [
            "Ativo" => "bg-success",
            "Pendente" => "bg-warning",
            "Inadimplente" => "bg-danger",
            "Concluido" => "bg-info",
            "Cancelado" => "bg-secondary",
            "Inativo" => "bg-secondary"
        ];

        return $classes[$status] ?? "bg-secondary";
    }

    private function _aluno_status_label($status)
    {
        $labels = [
            "Concluido" => "Concluído"
        ];

        return $labels[$status] ?? $status;
    }

    private function _origem_matricula_label($origem)
    {
        $labels = [
            "telemarketing" => "Telemarketing",
            "manual" => "Manual",
            "ia" => "IA",
            "importacao" => "Importação"
        ];
        $origem = trim((string) $origem);

        return $labels[$origem] ?? ($origem ?: "Manual");
    }

    private function _material_row($data)
    {
        $options = js_anchor("<i data-feather='package' class='icon-16'></i>", [
            "class" => "edit bombeiros-atualizar-material",
            "title" => "Marcar uniforme como entregue",
            "data-id" => $data->id,
            "data-item" => "camiseta",
            "data-status" => "entregue"
        ]);
        $options .= js_anchor("<i data-feather='book-open' class='icon-16'></i>", [
            "class" => "edit bombeiros-atualizar-material",
            "title" => "Marcar material 01 como entregue",
            "data-id" => $data->id,
            "data-item" => "material_01",
            "data-status" => "entregue"
        ]);
        $options .= js_anchor("<i data-feather='book' class='icon-16'></i>", [
            "class" => "edit bombeiros-atualizar-material",
            "title" => "Marcar material 02 como entregue",
            "data-id" => $data->id,
            "data-item" => "material_02",
            "data-status" => "entregue"
        ]);
        $options .= js_anchor("<i data-feather='check-circle' class='icon-16'></i>", [
            "class" => "edit bombeiros-atualizar-material",
            "title" => "Marcar todos como entregues",
            "data-id" => $data->id,
            "data-item" => "todos",
            "data-status" => "entregue"
        ]);

        return [
            esc($data->matricula ?: (string) $data->id),
            esc($data->nome_aluno),
            esc($data->turma ?: "-"),
            $this->_material_badge($data->camiseta_status ?: $data->camiseta),
            $this->_material_badge($data->material_01_status ?: $data->material_01),
            $this->_material_badge($data->material_02_status ?: $data->material_02),
            esc($data->materiais_observacao ?: "-"),
            $options
        ];
    }

    private function _lead_palestra_row($data)
    {
        $options = modal_anchor(get_uri("grupo_donato/operacional/lead_palestra_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Editar lead",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='user-plus' class='icon-16'></i>", [
            "class" => "edit bombeiros-converter-lead",
            "title" => "Converter em matrícula",
            "data-id" => $data->id
        ]);

        return [
            esc($data->responsavel_nome ?: "-"),
            esc($data->aluno_nome ?: "-"),
            esc($this->_format_phone($data->telefone_normalizado ?: $data->telefone)),
            "<span class='badge " . $this->_lead_status_class($data->status) . "'>" . esc($this->_lead_status_label($data->status)) . "</span>",
            $data->aluno_id ? esc($data->matricula ?: "sim") : "-",
            $this->_format_date($data->data_evento),
            $options
        ];
    }

    private function _custo_row_data($id)
    {
        $data = $this->Bombeiros_custos_model->get_details(["id" => $id, "unit_id" => $this->_active_unit_id()])->getRow();
        return $this->_custo_row($data);
    }

    private function _custo_row($data)
    {
        $options = modal_anchor(get_uri("grupo_donato/operacional/custo_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Editar custo",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
            "class" => "delete",
            "title" => app_lang("delete"),
            "data-id" => $data->id,
            "data-action-url" => get_uri("grupo_donato/operacional/delete_custo"),
            "data-action" => "delete-confirmation"
        ]);

        return [
            esc($data->descricao ?: "-"),
            esc($data->categoria ?: "-"),
            $this->_format_date($data->data_custo),
            $this->_competencia_label($data->mes_referencia, $data->ano_referencia),
            "R$ " . number_format((float) $data->valor, 2, ",", "."),
            "<span class='badge " . $this->_custo_status_class($data->status) . "'>" . esc($this->_custo_status_label($data->status)) . "</span>",
            esc($data->forma_pagamento ?: "-"),
            esc($data->observacao ?: "-"),
            $options
        ];
    }

    private function _material_badge($status)
    {
        $status = $this->_normalizar_material($status);
        $classes = [
            "entregue" => "bg-success",
            "confirmado" => "bg-success",
            "pendente" => "bg-warning",
            "nao_entregue" => "bg-danger",
            "sem_registro" => "bg-secondary"
        ];
        $labels = [
            "entregue" => "Entregue",
            "confirmado" => "Confirmado",
            "pendente" => "Pendente",
            "nao_entregue" => "Não entregue",
            "sem_registro" => "Sem registro"
        ];

        return "<span class='badge " . ($classes[$status] ?? "bg-secondary") . "'>" . esc($labels[$status] ?? $status ?: "Sem registro") . "</span>";
    }

    private function _lead_status_class($status)
    {
        $classes = [
            "matriculado" => "bg-success",
            "compareceu_palestra" => "bg-primary",
            "em_negociacao" => "bg-warning",
            "nao_matriculado" => "bg-secondary",
            "perdido" => "bg-danger",
            "sem_status" => "bg-secondary"
        ];

        return $classes[$status] ?? "bg-secondary";
    }

    private function _lead_status_label($status)
    {
        $labels = [
            "matriculado" => "Matriculado",
            "compareceu_palestra" => "Compareceu",
            "em_negociacao" => "Em negociação",
            "nao_matriculado" => "Não matriculado",
            "perdido" => "Perdido",
            "sem_status" => "Sem status"
        ];

        return $labels[$status] ?? "Sem status";
    }

    private function _custo_status_class($status)
    {
        $classes = [
            "Pago" => "bg-success",
            "Previsto" => "bg-warning",
            "Cancelado" => "bg-secondary"
        ];

        return $classes[$status] ?? "bg-secondary";
    }

    private function _custo_status_label($status)
    {
        $labels = [
            "Pago" => "Pago",
            "Previsto" => "Previsto",
            "Cancelado" => "Cancelado"
        ];

        return $labels[$status] ?? "Previsto";
    }

    private function _responsavel_row_data($id)
    {
        $data = $this->Bombeiros_responsaveis_model->get_details(["id" => $id])->getRow();
        return $this->_responsavel_row($data);
    }

    private function _responsavel_row($data)
    {
        $whats = $this->_digits($data->whats);
        $whatsapp = $whats ? anchor("https://wa.me/55" . $whats, esc($this->_format_phone($whats)), ["target" => "_blank", "title" => "Abrir WhatsApp"]) : "-";
        $options = modal_anchor(get_uri("grupo_donato/operacional/responsavel_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Editar responsável",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
            "class" => "delete",
            "title" => app_lang("delete"),
            "data-id" => $data->id,
            "data-action-url" => get_uri("grupo_donato/operacional/delete_responsavel"),
            "data-action" => "delete-confirmation"
        ]);

        return [
            "#" . $data->id,
            esc($data->nome),
            esc($this->_format_cpf($data->cpf)),
            $whatsapp,
            esc($this->_format_phone($data->celular)),
            esc($data->email ?: "-"),
            esc($data->endereco ?: "-"),
            $options
        ];
    }

    private function _unidade_row_data($id)
    {
        $data = $this->Bombeiros_unidades_model->get_details(["id" => $id])->getRow();
        return $this->_unidade_row($data);
    }

    private function _unidade_row($data)
    {
        $status_class = $data->status === "Ativo" ? "bg-success" : "bg-secondary";
        $default_badge = !empty($data->is_default) ? "<span class='badge bg-info'>Padrão</span>" : "-";
        $options = modal_anchor(get_uri("grupo_donato/operacional/unidade_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Editar unidade",
            "data-post-id" => $data->id
        ]);
        $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
            "class" => "delete",
            "title" => app_lang("delete"),
            "data-id" => $data->id,
            "data-action-url" => get_uri("grupo_donato/operacional/delete_unidade"),
            "data-action" => "delete-confirmation"
        ]);

        return [
            esc($data->nome_unidade),
            esc($data->slug ?: "-"),
            esc($data->cidade),
            esc($data->endereco ?: "-"),
            $default_badge,
            "<span class='badge $status_class'>" . esc($data->status) . "</span>",
            $options
        ];
    }

    private function _unidade_dropdown_option($data)
    {
        return [
            "id" => (string) $data->id,
            "slug" => (string) $data->slug,
            "text" => $data->nome_unidade . " - " . $data->cidade,
            "status" => $data->status
        ];
    }

    private function _pagamento_mensal_row($data, $mes_referencia, $ano_referencia)
    {
        $tem_cobranca = !empty($data->cobranca_id);
        $status = $tem_cobranca ? ($data->cobranca_status ?: "Pendente") : "";
        $pago = $status === "Pago";
        $a_receber = in_array($status, ["Pendente", "Vencido"], true);
        $competencia = $data->cobranca_competencia ?: sprintf("%02d/%04d", $mes_referencia, $ano_referencia);
        $vencimento = $data->cobranca_vencimento ?? "";
        $valor = $tem_cobranca ? (float) $data->cobranca_valor : 0;
        $status_label = "Sem cobrança";
        $status_class = "bg-secondary";

        if ($tem_cobranca && $pago) {
            $status_label = "Pago";
            $status_class = "bg-success";
        } elseif ($tem_cobranca && $a_receber && ($status === "Vencido" || ($vencimento && $vencimento < date("Y-m-d")))) {
            $status_label = "Vencido";
            $status_class = "bg-danger";
        } elseif ($tem_cobranca && $a_receber) {
            $status_label = "Em aberto";
            $status_class = "bg-warning";
        } elseif ($tem_cobranca && $status === "Isento") {
            $status_label = "Isento";
            $status_class = "bg-info";
        } elseif ($tem_cobranca && $status === "Cancelado") {
            $status_label = "Cancelado";
            $status_class = "bg-secondary";
        } elseif ($tem_cobranca) {
            $status_label = $status ?: "Sem status";
            $status_class = "bg-secondary";
        }

        $whats = $this->_digits($data->responsavel_whats);
        $whatsapp = $whats ? anchor("https://wa.me/55" . $whats, esc($this->_format_phone($whats)), ["target" => "_blank", "title" => "Abrir WhatsApp"]) : "-";
        $turma_pelotao = trim(($data->turma ?: "") . (!empty($data->pelotao) ? " / " . $data->pelotao : ""));
        $options = "";

        if ($tem_cobranca && $pago) {
            $options .= js_anchor("<i data-feather='rotate-ccw' class='icon-16'></i> Desfazer baixa", [
                "class" => "btn btn-default btn-sm bombeiros-marcar-pendente",
                "title" => "Desfazer baixa",
                "data-id" => $data->cobranca_id
            ]);
        } elseif ($tem_cobranca) {
            $options .= modal_anchor(get_uri("grupo_donato/operacional/baixa_pagamento_modal_form"), "<i data-feather='check-circle' class='icon-16'></i> Baixar pagamento", [
                "class" => "btn btn-primary btn-sm",
                "title" => "Baixar pagamento",
                "data-post-id" => $data->cobranca_id
            ]);
        } else {
            $options .= js_anchor("<i data-feather='plus-circle' class='icon-16'></i> Criar cobrança", [
                "class" => "btn btn-default btn-sm bombeiros-criar-cobranca-mensal",
                "title" => "Criar cobrança deste mês",
                "data-aluno-id" => $data->aluno_id,
                "data-mes" => $mes_referencia,
                "data-ano" => $ano_referencia
            ]);
        }

        if ($tem_cobranca) {
            $options .= modal_anchor(get_uri("grupo_donato/operacional/comprovante_modal_form"), "<i data-feather='file-text' class='icon-16'></i>", [
                "class" => "btn btn-default btn-sm ml5",
                "title" => "Gerar comprovante",
                "data-post-cobranca_id" => $data->cobranca_id,
                "data-post-aluno_id" => $data->aluno_id
            ]);
        }

        return [
            esc($data->matricula ?: "-"),
            esc($data->nome_aluno),
            esc($data->responsavel_nome ?: "-"),
            $whatsapp,
            esc($turma_pelotao ?: "-"),
            esc($competencia),
            esc($data->cobranca_descricao ?: "-"),
            $tem_cobranca ? $this->_format_date($vencimento) : "-",
            $tem_cobranca ? "R$ " . number_format($valor, 2, ",", ".") : "-",
            "<span class='badge $status_class'>$status_label</span>",
            $tem_cobranca && $data->data_pagamento ? $this->_format_date($data->data_pagamento) : "-",
            esc($data->forma_pagamento ?: "-"),
            esc($data->observacao ?: "-"),
            $options
        ];
    }

    private function _pagamento_row($data)
    {
        $status_classes = [
            "Pago" => "bg-success",
            "Pendente" => "bg-warning",
            "Vencido" => "bg-danger",
            "Isento" => "bg-info",
            "Cancelado" => "bg-secondary",
            "Sem registro" => "bg-secondary"
        ];
        $status_class = $status_classes[$data->status] ?? "bg-secondary";
        $options = "";

        if ($data->status !== "Pago") {
            $options .= js_anchor("<i data-feather='check-circle' class='icon-16'></i>", [
                "class" => "edit bombeiros-baixar-pagamento",
                "title" => "Baixar pagamento",
                "data-id" => $data->id
            ]);
        } else {
            $options .= js_anchor("<i data-feather='rotate-ccw' class='icon-16'></i>", [
                "class" => "edit bombeiros-marcar-pendente",
                "title" => "Marcar como pendente",
                "data-id" => $data->id
            ]);
        }

        $options .= modal_anchor(get_uri("grupo_donato/operacional/comprovante_modal_form"), "<i data-feather='file-text' class='icon-16'></i>", [
            "class" => "edit",
            "title" => "Gerar comprovante",
            "data-post-cobranca_id" => $data->id,
            "data-post-aluno_id" => $data->aluno_id
        ]);

        return [
            esc($data->nome_aluno),
            esc($data->responsavel_nome ?: "-"),
            $this->_format_date($data->vencimento),
            esc($data->tipo ?: "Mensalidade"),
            esc($data->competencia ?: "-"),
            "R$ " . number_format((float) $data->valor, 2, ",", "."),
            "<span class='badge $status_class'>" . esc($data->status) . "</span>",
            $options
        ];
    }

    private function _inadimplencia_row($data)
    {
        $whats = $this->_digits($data->responsavel_whats);
        $mensagem = "Olá " . $data->responsavel_nome . ", notamos que a parcela de " . ($data->competencia ?: date("m/Y", strtotime($data->vencimento))) . " do aluno " . $data->nome_aluno . " está em aberto. Podemos ajudar?";
        $link = $whats ? anchor("https://wa.me/55" . $whats . "?text=" . urlencode($mensagem), "<i data-feather='message-circle' class='icon-16'></i>", ["target" => "_blank", "title" => "Notificar via WhatsApp"]) : "-";

        return [
            esc($data->nome_aluno),
            esc($data->responsavel_nome ?: "-"),
            $this->_format_date($data->vencimento),
            esc($data->competencia ?: "-"),
            "R$ " . number_format((float) $data->valor, 2, ",", "."),
            $link
        ];
    }

    private function _financeiro_resumo_data()
    {
        $unidade_id = $this->_active_unit_id();
        $totais = $this->Bombeiros_cobrancas_model->get_totals($unidade_id);
        $inadimplentes = $this->Bombeiros_cobrancas_model->get_details(["overdue" => true, "unidade_id" => $unidade_id])->getResult();
        $total_inadimplencia = 0;

        foreach ($inadimplentes as $inadimplente) {
            $total_inadimplencia += (float) $inadimplente->valor;
        }

        return [
            "total_pago" => (float) ($totais->total_pago ?? 0),
            "total_pendente" => (float) ($totais->total_pendente ?? 0),
            "total_inadimplencia" => $total_inadimplencia,
            "total_parcelas_atraso" => count($inadimplentes)
        ];
    }

    private function _pagamentos_mensais_resumo_data($mes_referencia, $ano_referencia, $turma = "")
    {
        $resumo = $this->Bombeiros_cobrancas_model->get_pagamentos_mensais_resumo([
            "unidade_id" => $this->_active_unit_id(),
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia,
            "turma" => $turma
        ]);

        return [
            "total_alunos" => (int) ($resumo->total_alunos ?? 0),
            "total_pagos" => (int) ($resumo->total_pagos ?? 0),
            "total_em_aberto" => (int) ($resumo->total_em_aberto ?? 0),
            "total_vencidos" => (int) ($resumo->total_vencidos ?? 0),
            "total_sem_cobranca" => (int) ($resumo->total_sem_cobranca ?? 0),
            "total_recebido" => (float) ($resumo->total_recebido ?? 0),
            "total_a_receber" => (float) ($resumo->total_a_receber ?? 0),
            "valor_previsto" => (float) ($resumo->valor_previsto ?? 0),
            "total_recebido_formatado" => "R$ " . number_format((float) ($resumo->total_recebido ?? 0), 2, ",", "."),
            "total_a_receber_formatado" => "R$ " . number_format((float) ($resumo->total_a_receber ?? 0), 2, ",", "."),
            "valor_previsto_formatado" => "R$ " . number_format((float) ($resumo->valor_previsto ?? 0), 2, ",", ".")
        ];
    }

    private function _garantir_mensalidades_mes_atual($unidade_id = 0)
    {
        return $this->_garantir_mensalidades_periodo($unidade_id, (int) date("m"), (int) date("Y"));
    }

    private function _sincronizar_mensalidades_tela($mes, $ano)
    {
        if ($this->_deve_garantir_mensalidades_periodo($mes, $ano)) {
            $this->_garantir_mensalidades_periodo($this->_active_unit_id(), $mes, $ano);
        }
    }

    private function _deve_garantir_mensalidades_periodo($mes, $ano)
    {
        $mes = (int) $mes;
        $ano = (int) $ano;
        if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            return false;
        }

        $periodo = strtotime(sprintf("%04d-%02d-01", $ano, $mes));
        $periodo_atual = strtotime(date("Y-m-01"));

        return $periodo !== false && $periodo_atual !== false && $periodo >= $periodo_atual;
    }

    private function _garantir_mensalidade_atual_aluno($aluno_id, $unidade_id = 0)
    {
        $aluno_id = (int) $aluno_id;
        $unidade_id = (int) $unidade_id;
        if (!$aluno_id || !$unidade_id) {
            return null;
        }

        $aluno = $this->Bombeiros_alunos_model->get_details([
            "id" => $aluno_id,
            "unidade_id" => $unidade_id,
            "status" => "Ativo"
        ])->getRow();
        $mes = (int) date("m");
        $ano = (int) date("Y");

        if (!$this->_aluno_deve_receber_cobranca_periodo($aluno, $mes, $ano)) {
            return null;
        }

        return $this->_garantir_cobranca_mensal_aluno($aluno, $unidade_id, $mes, $ano);
    }

    private function _garantir_mensalidades_periodo($unidade_id = 0, $mes = 0, $ano = 0)
    {
        $unidade_id = (int) $unidade_id;
        if (!$unidade_id) {
            return 0;
        }

        $db = db_connect();
        $mes = (int) ($mes ?: date("m"));
        $ano = (int) ($ano ?: date("Y"));
        $inicio_competencia = sprintf("%04d-%02d-01", $ano, $mes);
        $fim_competencia = date("Y-m-t", strtotime($inicio_competencia));

        if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            return 0;
        }

        $lock_name = "grupo_donato_mensalidades_" . $unidade_id . "_" . $ano . "_" . $mes;
        $lock = $db->query("SELECT GET_LOCK(" . $db->escape($lock_name) . ", 10) AS lock_status")->getRow();
        if ((int) ($lock->lock_status ?? 0) !== 1) {
            return 0;
        }

        $criadas = 0;
        try {
            $alunos_table = $db->prefixTable("grupo_donato_alunos");
            $cobrancas_table = $db->prefixTable("grupo_donato_cobrancas");
            $inicio_cobranca_sql = "COALESCE($alunos_table.data_primeira_parcela, $alunos_table.data_inicio, $alunos_table.data_matricula, DATE($alunos_table.created_at))";
            $sql = "SELECT $alunos_table.*,
                    (
                        SELECT MAX(c.vencimento)
                        FROM $cobrancas_table c
                        WHERE c.aluno_id=$alunos_table.id
                            AND c.tipo='Mensalidade'
                    ) AS ultima_mensalidade_vencimento
                FROM $alunos_table
                WHERE $alunos_table.deleted=0
                    AND $alunos_table.status='Ativo'
                    AND $alunos_table.unidade_id=" . (int) $unidade_id . "
                    AND ($inicio_cobranca_sql IS NULL OR $inicio_cobranca_sql <= " . $db->escape($fim_competencia) . ")
                    AND NOT EXISTS (
                        SELECT 1
                        FROM $cobrancas_table c2
                        WHERE c2.aluno_id=$alunos_table.id
                            AND c2.tipo='Mensalidade'
                            AND COALESCE(c2.mes_referencia, MONTH(c2.vencimento))=" . (int) $mes . "
                            AND COALESCE(c2.ano_referencia, YEAR(c2.vencimento))=" . (int) $ano . "
                    )";
            $alunos = $db->query($sql)->getResult();
            foreach ($alunos as $aluno) {
                $valor = (float) ($aluno->valor_mensalidade ?: ($aluno->valor_mensal ?: 237.00));
                $vencimento = $this->_vencimento_mensalidade_mes($aluno, $mes, $ano);
                $dados_cobranca = [
                    "aluno_id" => (int) $aluno->id,
                    "responsavel_id" => $aluno->responsavel_id ?: null,
                    "unit_id" => $unidade_id,
                    "vencimento" => $vencimento,
                    "valor" => $valor ?: 237.00,
                    "competencia" => sprintf("%02d/%04d", $mes, $ano),
                    "mes_referencia" => $mes,
                    "ano_referencia" => $ano,
                    "descricao" => "Mensalidade " . sprintf("%02d/%04d", $mes, $ano),
                    "status" => "Pendente",
                    "tipo" => "Mensalidade"
                ];
                $cobranca_id = $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca);
                if ($cobranca_id) {
                    $criadas++;
                }
            }
        } finally {
            $db->query("SELECT RELEASE_LOCK(" . $db->escape($lock_name) . ")");
        }

        return $criadas;
    }

    private function _buscar_cobranca_mensal_aluno($aluno_id, $mes, $ano)
    {
        $aluno_id = (int) $aluno_id;
        $mes = (int) $mes;
        $ano = (int) $ano;

        if (!$aluno_id || $mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            return null;
        }

        $db = db_connect();
        $cobrancas_table = $db->prefixTable("grupo_donato_cobrancas");

        return $db->query("SELECT *
            FROM $cobrancas_table
            WHERE aluno_id=" . (int) $aluno_id . "
                AND tipo='Mensalidade'
                AND COALESCE(mes_referencia, MONTH(vencimento))=" . (int) $mes . "
                AND COALESCE(ano_referencia, YEAR(vencimento))=" . (int) $ano . "
            ORDER BY CASE WHEN status='Pago' THEN 0 ELSE 1 END, id DESC
            LIMIT 1")->getRow();
    }

    private function _aluno_deve_receber_cobranca_periodo($aluno, $mes, $ano)
    {
        if (!$aluno || empty($aluno->id) || ($aluno->status ?? "Ativo") !== "Ativo") {
            return false;
        }

        $mes = (int) $mes;
        $ano = (int) $ano;
        if ($mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            return false;
        }

        $inicio_cobranca = $this->_inicio_cobranca_aluno($aluno);
        if (!$inicio_cobranca) {
            return true;
        }

        $fim_competencia = date("Y-m-t", strtotime(sprintf("%04d-%02d-01", $ano, $mes)));
        return $inicio_cobranca <= $fim_competencia;
    }

    private function _inicio_cobranca_aluno($aluno)
    {
        foreach (["data_primeira_parcela", "data_inicio", "data_matricula", "created_at"] as $campo) {
            $valor = $aluno->$campo ?? null;
            if (!$valor) {
                continue;
            }

            $data = $this->_date_value(substr((string) $valor, 0, 10));
            if ($data) {
                return $data;
            }
        }

        return null;
    }

    private function _garantir_cobranca_mensal_aluno($aluno, $unidade_id, $mes, $ano)
    {
        $unidade_id = (int) $unidade_id;
        $mes = (int) $mes;
        $ano = (int) $ano;

        if (!$aluno || empty($aluno->id) || !$unidade_id || $mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            return null;
        }

        $db = db_connect();
        $cobrancas_table = $db->prefixTable("grupo_donato_cobrancas");
        $cobranca = $this->_buscar_cobranca_mensal_aluno($aluno->id, $mes, $ano);

        if ($cobranca) {
            return $cobranca;
        }

        if (!property_exists($aluno, "ultima_mensalidade_vencimento")) {
            $ultima = $db->query("SELECT MAX(vencimento) AS ultima_mensalidade_vencimento
                FROM $cobrancas_table
                WHERE aluno_id=" . (int) $aluno->id . "
                    AND tipo='Mensalidade'")->getRow();
            $aluno->ultima_mensalidade_vencimento = $ultima->ultima_mensalidade_vencimento ?? null;
        }

        $valor = (float) ($aluno->valor_mensalidade ?: ($aluno->valor_mensal ?: 237.00));
        $vencimento = $this->_vencimento_mensalidade_mes($aluno, $mes, $ano);
        $dados_cobranca = [
            "aluno_id" => (int) $aluno->id,
            "responsavel_id" => $aluno->responsavel_id ?: null,
            "unit_id" => $unidade_id,
            "vencimento" => $vencimento,
            "valor" => $valor ?: 237.00,
            "competencia" => sprintf("%02d/%04d", $mes, $ano),
            "mes_referencia" => $mes,
            "ano_referencia" => $ano,
            "descricao" => "Mensalidade " . sprintf("%02d/%04d", $mes, $ano),
            "status" => "Pendente",
            "tipo" => "Mensalidade"
        ];
        $cobranca_id = $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca);

        if (!$cobranca_id) {
            return null;
        }

        return $this->Bombeiros_cobrancas_model->get_details([
            "id" => $cobranca_id,
            "unidade_id" => $unidade_id
        ])->getRow();
    }

    private function _vencimento_mensalidade_mes($aluno, $mes, $ano)
    {
        $base = ($aluno->data_primeira_parcela ?? null) ?: (($aluno->ultima_mensalidade_vencimento ?? null) ?: null);
        $dia = $base ? (int) date("d", strtotime($base)) : 10;
        $ultimo_dia = (int) date("t", strtotime(sprintf("%04d-%02d-01", $ano, $mes)));
        $dia = max(1, min($dia, $ultimo_dia));

        return sprintf("%04d-%02d-%02d", $ano, $mes, $dia);
    }

    private function _dados_comprovante($cobranca_id, $aluno_id)
    {
        $cobranca = $this->Bombeiros_cobrancas_model->get_details(["id" => $cobranca_id, "aluno_id" => $aluno_id, "unidade_id" => $this->_active_unit_id()])->getRow();
        if (!$cobranca) {
            return null;
        }

        $mensalidade_num = 1;
        if (!empty($cobranca->competencia) && preg_match('/^(\d+)\//', $cobranca->competencia, $matches)) {
            $mensalidade_num = (int) $matches[1];
        }

        return [
            "cobranca_id" => $cobranca->id,
            "aluno_id" => $cobranca->aluno_id,
            "responsavel_nome" => $cobranca->responsavel_nome,
            "responsavel_cpf" => $this->_format_cpf($cobranca->responsavel_cpf),
            "aluno_nome" => $cobranca->nome_aluno,
            "valor" => number_format((float) $cobranca->valor, 2, ",", "."),
            "mensalidade_numero" => $mensalidade_num,
            "data_emissao" => date("Y-m-d"),
            "conferido_por" => "",
            "data_conferencia" => date("Y-m-d")
        ];
    }

    private function _gerar_cobrancas_matricula($aluno_id, $data_inicio, $valor_mensalidade, $options = [])
    {
        $num_parcelas = (int) (get_array_value($options, "num_parcelas") ?: $this->request->getPost("num_parcelas") ?: 12);
        $num_parcelas = $num_parcelas > 0 ? $num_parcelas : 12;
        $primeiro_vencimento = $this->_date_value(get_array_value($options, "data_primeira_parcela") ?: $this->request->getPost("data_primeira_parcela")) ?: $data_inicio;
        $aluno_info = $this->Bombeiros_alunos_model->get_details(["id" => $aluno_id])->getRow();
        $unit_id = (int) ($aluno_info->unidade_id ?? $this->_active_unit_id());
        $responsavel_id = (int) ($aluno_info->responsavel_id ?? 0);

        for ($i = 0; $i < $num_parcelas; $i++) {
            $vencimento = date("Y-m-d", strtotime($primeiro_vencimento . " +$i month"));
            $competencia = date("m/Y", strtotime($vencimento));
            $dados_cobranca = [
                "aluno_id" => $aluno_id,
                "responsavel_id" => $responsavel_id ?: null,
                "unit_id" => $unit_id ?: null,
                "vencimento" => $vencimento,
                "valor" => $valor_mensalidade,
                "competencia" => $competencia,
                "mes_referencia" => (int) date("m", strtotime($vencimento)),
                "ano_referencia" => (int) date("Y", strtotime($vencimento)),
                "descricao" => ($i + 1) . "ª parcela",
                "status" => "Pendente",
                "tipo" => "Mensalidade"
            ];
            $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca);
        }

        $valor_inscricao = array_key_exists("valor_inscricao", $options)
            ? (float) $options["valor_inscricao"]
            : $this->_money_to_float($this->request->getPost("valor_inscricao"));
        if ($valor_inscricao > 0) {
            $vencimento_inscricao = $this->_date_value(get_array_value($options, "data_inscricao") ?: $this->request->getPost("data_inscricao")) ?: date("Y-m-d");
            $dados_inscricao = [
                "aluno_id" => $aluno_id,
                "responsavel_id" => $responsavel_id ?: null,
                "unit_id" => $unit_id ?: null,
                "vencimento" => $vencimento_inscricao,
                "valor" => $valor_inscricao,
                "competencia" => date("m/Y", strtotime($vencimento_inscricao)),
                "mes_referencia" => (int) date("m", strtotime($vencimento_inscricao)),
                "ano_referencia" => (int) date("Y", strtotime($vencimento_inscricao)),
                "descricao" => "Matrícula",
                "status" => $this->_bool_value(array_key_exists("matricula_efetuada", $options) ? $options["matricula_efetuada"] : $this->request->getPost("matricula_efetuada")) ? "Pago" : "Pendente",
                "tipo" => "Matrícula"
            ];
            $this->Bombeiros_cobrancas_model->ci_save($dados_inscricao);
        }

        $dados_camiseta = [
            "aluno_id" => $aluno_id,
            "responsavel_id" => $responsavel_id ?: null,
            "unit_id" => $unit_id ?: null,
            "vencimento" => date("Y-m-d"),
            "valor" => 67.00,
            "competencia" => date("m/Y"),
            "mes_referencia" => (int) date("m"),
            "ano_referencia" => (int) date("Y"),
            "descricao" => "Camiseta",
            "status" => $this->_bool_value(array_key_exists("uniforme_efetuado", $options) ? $options["uniforme_efetuado"] : $this->request->getPost("uniforme_efetuado")) ? "Pago" : "Pendente",
            "tipo" => "Camiseta"
        ];
        $this->Bombeiros_cobrancas_model->ci_save($dados_camiseta);
    }

    private function _comprovante_view_data($data)
    {
        $row = is_array($data) ? $data : (array) $data;

        return [
            "numero_comprovante" => $row["numero_comprovante"] ?? "",
            "data_emissao" => !empty($row["data_emissao"]) ? date("d/m/Y", strtotime($row["data_emissao"])) : "",
            "responsavel_nome" => $row["responsavel_nome"] ?? "",
            "responsavel_cpf" => $row["responsavel_cpf"] ?? "",
            "aluno_nome" => $row["aluno_nome"] ?? "",
            "aluno_nome_adicional" => $row["aluno_nome_adicional"] ?? "",
            "mensalidade_numero" => $row["mensalidade_numero"] ?? 1,
            "valor" => (float) ($row["valor"] ?? 0),
            "forma_pagamento" => $row["forma_pagamento"] ?? "",
            "conferido_por" => $row["conferido_por"] ?? "",
            "data_conferencia" => !empty($row["data_conferencia"]) ? date("d/m/Y", strtotime($row["data_conferencia"])) : ""
        ];
    }

    private function _unidades_dropdown($include_all = false)
    {
        $dropdown = $include_all ? ["" => "Todas as unidades"] : ["" => "-"];
        $unidades = $this->Bombeiros_unidades_model->get_details(["status" => "Ativo"])->getResult();

        foreach ($unidades as $unidade) {
            $dropdown[$unidade->id] = $unidade->nome_unidade . " - " . $unidade->cidade;
        }

        return $dropdown;
    }

    private function _unidades_contexto_dropdown()
    {
        $dropdown = [];
        $unidades = $this->Bombeiros_unidades_model->get_details(["status" => "Ativo"])->getResult();

        foreach ($unidades as $unidade) {
            $slug = $unidade->slug ?: $this->_slugify($unidade->cidade ?: $unidade->nome_unidade);
            $dropdown[$slug] = $unidade->nome_unidade . " - " . $unidade->cidade;
        }

        return $dropdown;
    }

    private function _turmas_matricula_options()
    {
        return [
            "" => "Selecione",
            "08:30-11:00" => "08:30-11:00",
            "13:30-16:00" => "13:30-16:00"
        ];
    }

    private function _melhor_horario_ligacao_options()
    {
        return [
            "" => "Selecione",
            "manha" => "Manhã",
            "tarde" => "Tarde",
            "qualquer" => "Qualquer horário"
        ];
    }

    private function _public_unidade($slug)
    {
        $slug = $this->_slugify($slug ?: self::DEFAULT_UNIT_SLUG);
        return $this->Bombeiros_unidades_model->get_details(["slug" => $slug, "status" => "Ativo"])->getRow();
    }

    private function _is_public_matricula_request()
    {
        $uri = function_exists("uri_string") ? uri_string() : (parse_url($_SERVER["REQUEST_URI"] ?? "", PHP_URL_PATH) ?: "");
        $uri = trim($uri, "/");

        return $uri === "matricula-online"
            || strpos($uri, "matricula-online/") === 0
            || strpos($uri, "grupo_donato/operacional/matricula_publica") === 0
            || strpos($uri, "grupo_donato/operacional/salvar_matricula_publica") === 0;
    }

    private function _get_unidade_ativa()
    {
        $slug = $this->session->get("grupo_donato_operacional_unidade_slug");
        if (!$slug) {
            $iara_default = $this->Bombeiros_iara_adapter_model->unidade_padrao();
            $default = $iara_default ?: $this->Bombeiros_unidades_model->get_details(["is_default" => 1, "status" => "Ativo"])->getRow();
            $default_slug = $default->slug ?? ($default->unidade_slug ?? null);
            $slug = $default_slug ?: self::DEFAULT_UNIT_SLUG;
            $this->session->set("grupo_donato_operacional_unidade_slug", $slug);
        }

        $unidade = $this->Bombeiros_unidades_model->get_details(["slug" => $slug, "status" => "Ativo"])->getRow();
        if ($unidade) {
            return $unidade;
        }

        $unidade = $this->Bombeiros_unidades_model->get_details(["slug" => self::DEFAULT_UNIT_SLUG, "status" => "Ativo"])->getRow();
        if (!$unidade) {
            $unidade = $this->Bombeiros_unidades_model->get_details(["status" => "Ativo"])->getRow();
        }

        if ($unidade && $unidade->slug) {
            $this->session->set("grupo_donato_operacional_unidade_slug", $unidade->slug);
        }

        return $unidade ?: (object) [
            "id" => 0,
            "slug" => self::DEFAULT_UNIT_SLUG,
            "nome_unidade" => self::DEFAULT_UNIT_NAME,
            "cidade" => self::DEFAULT_UNIT_NAME,
            "is_default" => 1
        ];
    }

    private function _set_unidade_ativa($slug)
    {
        $slug = $this->_slugify($slug);
        $this->session->set("grupo_donato_operacional_unidade_slug", $slug);
        $this->Bombeiros_iara_adapter_model->set_current_unit($slug);
    }

    private function _get_unidade_id_ativa()
    {
        $unidade = $this->_get_unidade_ativa();
        return (int) ($unidade->id ?? 0);
    }

    private function _usuario_tem_acesso_unidade($unit_id, $permissao = null)
    {
        $user_id = (int) ($this->login_user->id ?? 0);
        if (!$user_id || !$unit_id) {
            return true;
        }

        $iara_access = $this->Bombeiros_iara_adapter_model->person_unit_access($user_id, $unit_id);
        if ($iara_access) {
            return $this->_access_rows_allow($iara_access, $permissao);
        }

        // Compatibilidade: enquanto não houver nenhuma regra de acesso cadastrada,
        // o plugin mantém o comportamento histórico do RISE com access_only_team_members().
        if (!$this->Bombeiros_person_unit_access_model->has_any_configured_access()) {
            return true;
        }

        $access = $this->Bombeiros_person_unit_access_model->get_access($user_id, $unit_id)->getResult();
        if (!$access) {
            return false;
        }

        return $this->_access_rows_allow($access, $permissao);
    }

    private function _access_rows_allow($access, $permissao = null)
    {
        foreach ($access as $row) {
            if (in_array($row->role, ["owner", "director"], true)) {
                return true;
            }
            if (!$permissao) {
                return true;
            }
            if ($row->role === "manager" && in_array($permissao, ["can_view_finance", "can_manage_finance", "can_view_students", "can_manage_students", "can_view_leads", "can_manage_leads"], true)) {
                return true;
            }
            if ($row->role === "staff" && in_array($permissao, ["can_view_students", "can_manage_students"], true)) {
                return true;
            }
            if ($row->role === "viewer" && strpos($permissao, "can_view_") === 0) {
                return true;
            }
            if (!empty($row->$permissao)) {
                return true;
            }
        }

        return false;
    }

    private function _active_unit()
    {
        return $this->_get_unidade_ativa();
    }

    private function _active_unit_id()
    {
        return $this->_get_unidade_id_ativa();
    }

    private function _unit_context_payload($unidade)
    {
        return [
            "id" => (int) ($unidade->id ?? 0),
            "slug" => (string) ($unidade->slug ?? self::DEFAULT_UNIT_SLUG),
            "nome" => (string) ($unidade->nome_unidade ?? self::DEFAULT_UNIT_NAME),
            "cidade" => (string) ($unidade->cidade ?? ""),
            "is_default" => (int) ($unidade->is_default ?? 0)
        ];
    }

    private function _first_unidade_id()
    {
        $unidade = $this->_active_unit();
        return $unidade ? $unidade->id : 0;
    }

    private function _adquirir_trava_matricula($db)
    {
        $lock = $db->query("SELECT GET_LOCK('grupo_donato_matricula_aluno', 10) AS lock_status")->getRow();
        if ((int) ($lock->lock_status ?? 0) !== 1) {
            throw new \RuntimeException("Não foi possível reservar a próxima matrícula. Tente novamente.");
        }
    }

    private function _liberar_trava_matricula($db)
    {
        $db->query("SELECT RELEASE_LOCK('grupo_donato_matricula_aluno')");
    }

    private function _proxima_matricula_aluno($db = null)
    {
        $db = $db ?: db_connect();
        $alunos_table = $db->prefixTable("grupo_donato_alunos");
        $row = $db->query("SELECT MAX(CAST(matricula AS UNSIGNED)) AS maior FROM $alunos_table WHERE matricula REGEXP '^[0-9]+$'")->getRow();
        $next = (int) ($row->maior ?? 0) + 1;

        return str_pad((string) $next, 4, "0", STR_PAD_LEFT);
    }

    private function _empty_aluno()
    {
        $fields = [
            "id", "responsavel_id", "unidade_id", "nome_aluno", "nascimento_aluno", "rg_aluno", "cpf_aluno",
            "matricula",
            "turma", "curso_nome", "num_parcelas", "valor_mensalidade", "valor_inscricao", "data_inscricao",
            "valor_mensal", "data_primeira_parcela", "data_inicio", "tamanho_camisa", "matricula_efetuada",
            "uniforme_efetuado", "material_efetuado", "melhor_horario_ligacao", "cidade_assinatura",
            "estado_assinatura", "dia_assinatura", "mes_assinatura", "ano_assinatura", "assinatura_contratada",
            "assinatura_contratante", "li_ciente", "origem_matricula", "status", "data_cancelamento", "motivo_cancelamento",
            "observacao_cancelamento", "responsavel_nome",
            "responsavel_nascimento", "responsavel_rg", "responsavel_cpf", "responsavel_whats",
            "responsavel_celular", "responsavel_email", "responsavel_endereco", "responsavel_numero",
            "responsavel_complemento", "responsavel_bairro", "responsavel_cep", "responsavel_cidade",
            "responsavel_recado"
        ];
        $aluno = new \stdClass();
        foreach ($fields as $field) {
            $aluno->$field = "";
        }
        $aluno->matricula = $this->_proxima_matricula_aluno();
        $aluno->status = "Ativo";
        // Curso e pagamento sem pré-preenchimento: curso_nome, num_parcelas,
        // valor_mensalidade, valor_inscricao e valor_mensal ficam vazios ("")
        // para serem informados manualmente no cadastro de novo aluno.
        $aluno->data_inicio = date("Y-m-d");
        $aluno->origem_matricula = "manual";
        $aluno->unidade_id = $this->_active_unit_id();

        return $aluno;
    }

    private function _empty_custo()
    {
        $custo = new \stdClass();
        $custo->id = 0;
        $custo->unit_id = $this->_active_unit_id();
        $custo->descricao = "";
        $custo->categoria = "Operacional";
        $custo->valor = "";
        $custo->data_custo = date("Y-m-d");
        $custo->mes_referencia = (int) date("m");
        $custo->ano_referencia = (int) date("Y");
        $custo->status = "Pago";
        $custo->forma_pagamento = "";
        $custo->observacao = "";

        return $custo;
    }

    private function _dashboard_periodo()
    {
        $mes = (int) ($this->request->getGet("dashboard_mes") ?: date("m"));
        if ($mes < 1 || $mes > 12) {
            $mes = (int) date("m");
        }

        $ano = (int) ($this->request->getGet("dashboard_ano") ?: date("Y"));
        if ($ano < 2000 || $ano > 2100) {
            $ano = (int) date("Y");
        }

        return ["mes" => $mes, "ano" => $ano];
    }

    private function _gd_active_tab()
    {
        $active_tab = strtolower((string) $this->request->getGet("gd_tab"));
        $allowed_tabs = [
            "dashboard", "alunos", "cancelados", "concluidos", "responsaveis", "presenca",
            "pagamentos", "financeiro", "custos", "materiais", "leads", "mensagens", "unidades"
        ];

        return in_array($active_tab, $allowed_tabs, true) ? $active_tab : "dashboard";
    }

    private function _dashboard_resumo_data($mes_referencia = 0, $ano_referencia = 0)
    {
        $unidade_id = $this->_active_unit_id();
        $mes_atual = (int) date("m");
        $ano_atual = (int) date("Y");
        $mes_referencia = (int) $mes_referencia ?: $mes_atual;
        $ano_referencia = (int) $ano_referencia ?: $ano_atual;

        $alunos = $this->Bombeiros_alunos_model->get_dashboard_counts($unidade_id);
        $financeiro = $this->Bombeiros_cobrancas_model->get_totals($unidade_id, $mes_referencia, $ano_referencia, "Mensalidade");
        $faturamento = $this->Bombeiros_cobrancas_model->get_totals($unidade_id, $mes_referencia, $ano_referencia);
        $custos = $this->Bombeiros_custos_model->get_totals($unidade_id, $mes_referencia, $ano_referencia);
        $presenca = $this->Bombeiros_presenca_model->get_totals($unidade_id, $mes_referencia, $ano_referencia);
        $leads = $this->Bombeiros_leads_palestra_model->get_totals($unidade_id, $mes_referencia, $ano_referencia);
        $responsaveis = $this->Bombeiros_responsaveis_model->get_details(["unidade_id" => $unidade_id])->getNumRows();
        $leads_total = (int) ($leads->total ?? 0);
        $leads_matriculados = (int) ($leads->matriculados ?? 0);
        $faturamento_total = (float) ($faturamento->total_pago ?? 0);
        $custos_total = (float) ($custos->total_custos ?? 0);
        $resultado_operacional = $faturamento_total - $custos_total;
        $percentual_custos = $faturamento_total > 0 ? round(($custos_total / $faturamento_total) * 100, 1) : 0;

        return [
            "mes_referencia" => $mes_referencia,
            "ano_referencia" => $ano_referencia,
            "total_alunos" => (int) ($alunos->alunos_ativos ?? 0) + (int) ($alunos->alunos_cancelados ?? 0) + (int) ($alunos->alunos_concluidos ?? 0),
            "alunos_ativos" => (int) ($alunos->alunos_ativos ?? 0),
            "alunos_cancelados" => (int) ($alunos->alunos_cancelados ?? 0),
            "alunos_concluidos" => (int) ($alunos->alunos_concluidos ?? 0),
            "responsaveis" => $responsaveis,
            "mensalidades_pagas" => (float) ($financeiro->total_pago ?? 0),
            "mensalidades_pendentes" => (float) ($financeiro->total_pendente ?? 0),
            "faturamento_total" => $faturamento_total,
            "custos_total" => $custos_total,
            "resultado_operacional" => $resultado_operacional,
            "percentual_custos" => $percentual_custos,
            "total_custos_cadastrados" => (int) ($custos->total_registros ?? 0),
            "inadimplentes" => (int) ($financeiro->inadimplentes ?? 0),
            "pendencia_uniforme" => (int) ($alunos->pendencia_uniforme ?? 0),
            "pendencia_material_01" => (int) ($alunos->pendencia_material_01 ?? 0),
            "pendencia_material_02" => (int) ($alunos->pendencia_material_02 ?? 0),
            "presencas" => (int) ($presenca->presencas ?? 0),
            "faltas" => (int) ($presenca->faltas ?? 0),
            "leads_palestra" => $leads_total,
            "leads_matriculados" => $leads_matriculados,
            "leads_nao_matriculados" => (int) ($leads->nao_matriculados ?? 0),
            "taxa_conversao_palestra" => $leads_total ? round(($leads_matriculados / $leads_total) * 100, 1) : 0
        ];
    }

    private function _mensagens_contexto_data()
    {
        $map = [
            "templates" => "dbo.templates_mensagem",
            "mensagens" => "dbo.mensagens",
            "historico" => "dbo.historico_mensagens"
        ];
        $data = [];

        foreach ($map as $key => $view_name) {
            $disponivel = $this->Bombeiros_iara_adapter_model->tabela_ou_view_existe($view_name);
            $data[$key] = [
                "disponivel" => $disponivel,
                "rows" => $disponivel ? $this->Bombeiros_iara_adapter_model->listar_view($view_name, 20) : []
            ];
        }

        return $data;
    }

    private function _qualidade_resumo_data()
    {
        $db = db_connect();
        $alunos_table = $db->prefixTable("grupo_donato_alunos");
        $responsaveis_table = $db->prefixTable("grupo_donato_responsaveis");
        $cobrancas_table = $db->prefixTable("grupo_donato_cobrancas");
        $presenca_table = $db->prefixTable("grupo_donato_presenca");
        $unidade_id = $this->_active_unit_id();
        $unit_where = $unidade_id ? " AND $alunos_table.unidade_id=" . (int) $unidade_id : "";

        $aluno_sem_telefone = $db->query("SELECT COUNT(*) AS total
            FROM $alunos_table
            LEFT JOIN $responsaveis_table ON $responsaveis_table.id=$alunos_table.responsavel_id
            WHERE $alunos_table.deleted=0 AND $alunos_table.status='Ativo' $unit_where
                AND COALESCE($responsaveis_table.whats, $responsaveis_table.celular, $alunos_table.telefone_1, $alunos_table.telefone_2, '')=''")->getRow();

        $responsavel_sem_cpf = $db->query("SELECT COUNT(DISTINCT $responsaveis_table.id) AS total
            FROM $responsaveis_table
            INNER JOIN $alunos_table ON $alunos_table.responsavel_id=$responsaveis_table.id AND $alunos_table.deleted=0 $unit_where
            WHERE $responsaveis_table.deleted=0 AND COALESCE($responsaveis_table.cpf, '')=''")->getRow();

        $matricula_duplicada = $db->query("SELECT COUNT(*) AS total FROM (
                SELECT matricula
                FROM $alunos_table
                WHERE deleted=0 $unit_where AND COALESCE(matricula, '')!=''
                GROUP BY matricula
                HAVING COUNT(*) > 1
            ) duplicadas")->getRow();

        $pagamento_sem_matricula = $db->query("SELECT COUNT(*) AS total
            FROM $cobrancas_table
            LEFT JOIN $alunos_table ON $alunos_table.id=$cobrancas_table.aluno_id
            WHERE $alunos_table.id IS NULL OR $alunos_table.deleted=1")->getRow();

        $ativo_sem_presenca = $db->query("SELECT COUNT(*) AS total
            FROM $alunos_table
            LEFT JOIN $presenca_table ON $presenca_table.aluno_id=$alunos_table.id
            WHERE $alunos_table.deleted=0 AND $alunos_table.status='Ativo' $unit_where
                AND $presenca_table.id IS NULL")->getRow();

        $telefone_invalido = $db->query("SELECT COUNT(DISTINCT $responsaveis_table.id) AS total
            FROM $responsaveis_table
            INNER JOIN $alunos_table ON $alunos_table.responsavel_id=$responsaveis_table.id AND $alunos_table.deleted=0 $unit_where
            WHERE $responsaveis_table.deleted=0
                AND COALESCE($responsaveis_table.whats, $responsaveis_table.celular, '')!=''
                AND LENGTH(REGEXP_REPLACE(COALESCE($responsaveis_table.whats, $responsaveis_table.celular), '[^0-9]', '')) NOT IN (10, 11)")->getRow();

        return [
            "Aluno sem telefone" => (int) ($aluno_sem_telefone->total ?? 0),
            "Responsável sem CPF" => (int) ($responsavel_sem_cpf->total ?? 0),
            "Matrícula duplicada" => (int) ($matricula_duplicada->total ?? 0),
            "Pagamento sem matrícula" => (int) ($pagamento_sem_matricula->total ?? 0),
            "Aluno ativo sem presença" => (int) ($ativo_sem_presenca->total ?? 0),
            "Telefone inválido" => (int) ($telefone_invalido->total ?? 0)
        ];
    }

    private function _slugify($value)
    {
        $value = trim((string) $value);
        if (function_exists("iconv")) {
            $converted = @iconv("UTF-8", "ASCII//TRANSLIT", $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace("/[^a-z0-9]+/", "_", $value);
        $value = trim($value, "_");

        return $value ?: self::DEFAULT_UNIT_SLUG;
    }

    /**
     * Garante slug único por unidade. O contexto multiunidade inteiro (dropdown,
     * sessão, troca de unidade, URL pública) é indexado por slug; duas unidades com
     * o mesmo slug colidiriam e impediriam selecionar/filtrar cada uma.
     */
    private function _unique_unit_slug($base, $exclude_id = 0)
    {
        $base = $this->_slugify($base);
        $db = db_connect();
        $table = $db->prefixTable("grupo_donato_unidades");
        $exclude_id = (int) $exclude_id;
        $slug = $base;
        $suffix = 2;
        while (true) {
            $query = $db->table($table)->where("slug", $slug)->where("deleted", 0);
            if ($exclude_id > 0) {
                $query->where("id !=", $exclude_id);
            }
            if ((int) $query->countAllResults() === 0) {
                return $slug;
            }
            $slug = $base . "_" . $suffix;
            $suffix++;
        }
    }

    private function _normalizar_presenca($status)
    {
        $status = strtolower(trim((string) $status));
        $map = [
            "1" => "presente",
            "0" => "falta",
            "ok" => "presente",
            "p" => "presente",
            "presente" => "presente",
            "f" => "falta",
            "falta" => "falta",
            "ausente" => "falta",
            "feriado" => "feriado",
            "cancelada" => "aula_cancelada",
            "cancelado" => "aula_cancelada",
            "aula_cancelada" => "aula_cancelada",
            "" => "sem_registro",
            "sem_registro" => "sem_registro",
            "sem registro" => "sem_registro",
            "null" => "sem_registro"
        ];

        return $map[$status] ?? "sem_registro";
    }

    private function _normalizar_pagamento($status, $status_raw = "")
    {
        $status = strtolower(trim((string) $status));
        $status_raw = strtolower(trim((string) $status_raw));
        $status_sem_ordinais = str_replace(["ª", "º"], "", $status);
        $raw_sem_ordinais = str_replace(["ª", "º"], "", $status_raw);
        $status_efetivo = $status ?: $status_raw;

        if (preg_match('/\d+a?\s*parcela/', $raw_sem_ordinais)) {
            return "Pago";
        }

        if (!$status_efetivo || in_array($status_efetivo, ["sem_registro", "sem registro", "sem_lancamento", "sem lançamento", "sem lancamento"], true)) {
            return "Sem registro";
        }
        if (in_array($status_efetivo, ["a ser pago", "pendente", "em aberto", "a_ser_pago"], true)) {
            return "Pendente";
        }
        if (in_array($status_efetivo, ["pago", "ok", "pago_registrado", "registrado"], true)
            || preg_match('/^\d+a?\s*parcela/', $status_sem_ordinais)) {
            return "Pago";
        }
        if ($status_efetivo === "isento") {
            return "Isento";
        }
        if ($status_efetivo === "cancelado") {
            return "Cancelado";
        }

        return "Pendente";
    }

    private function _normalizar_material($status)
    {
        $status = strtolower(trim((string) $status));
        $map = [
            "" => "sem_registro",
            "confirmado" => "entregue",
            "ok" => "entregue",
            "pago" => "entregue",
            "pago_registrado" => "entregue",
            "registrado" => "entregue",
            "concluido" => "entregue",
            "concluído" => "entregue",
            "efetuado" => "entregue",
            "pendente" => "pendente",
            "a ser pago" => "pendente",
            "entregue" => "entregue",
            "nao_entregue" => "nao_entregue",
            "não entregue" => "nao_entregue",
            "sem_registro" => "sem_registro",
            "sem registro" => "sem_registro"
        ];

        return $map[$status] ?? $status;
    }

    private function _normalizar_payload_importacao($payload)
    {
        $normalizado = [
            "responsaveis" => [],
            "alunos" => [],
            "pagamentos" => [],
            "materiais" => [],
            "presencas" => [],
            "presencas_palestra" => [],
            "qualidade_dados" => $payload["qualidade_dados"] ?? []
        ];

        $alunos_payload = $this->_lista_importacao($payload["alunos"] ?? []);
        $primeiro_aluno = $alunos_payload[0] ?? [];
        $formato_original = is_array($primeiro_aluno) && (
            isset($primeiro_aluno["curso"]) ||
            isset($primeiro_aluno["taxas_e_materiais"]) ||
            isset($primeiro_aluno["presencas_aulas"]) ||
            (isset($primeiro_aluno["pagamentos"]) && is_array($primeiro_aluno["pagamentos"]) && isset($primeiro_aluno["pagamentos"]["mensalidades"])) ||
            isset($primeiro_aluno["aluno"]["nome_completo"]) ||
            isset($primeiro_aluno["responsavel"]["nome_completo"])
        );

        if (!$formato_original) {
            foreach ($this->_lista_importacao($payload["responsaveis"] ?? []) as $responsavel) {
                $normalizado["responsaveis"][] = $this->_normalizar_responsavel_importacao($responsavel);
            }
            foreach ($alunos_payload as $aluno) {
                $normalizado["alunos"][] = $this->_normalizar_aluno_importacao($aluno);
            }
            foreach ($this->_lista_importacao($payload["pagamentos"] ?? []) as $pagamento) {
                $normalizado["pagamentos"][] = $this->_normalizar_pagamento_importacao($pagamento);
            }
            foreach ($this->_lista_importacao($payload["materiais"] ?? []) as $material) {
                $normalizado["materiais"][] = $this->_normalizar_material_importacao($material);
            }
            foreach ($this->_lista_importacao($payload["presencas"] ?? []) as $presenca) {
                $presenca = $this->_normalizar_presenca_importacao($presenca);
                if ($presenca) {
                    $normalizado["presencas"][] = $presenca;
                }
            }
            foreach ($this->_lista_importacao($payload["presencas_palestra"] ?? []) as $lead) {
                $normalizado["presencas_palestra"][] = $this->_normalizar_lead_importacao($lead);
            }

            return $normalizado;
        }

        foreach ($alunos_payload as $registro) {
            $aluno_dados = $registro["aluno"] ?? [];
            $responsavel_dados = $registro["responsavel"] ?? [];
            $curso = is_array($registro["curso"] ?? null) ? $registro["curso"] : [];
            $taxas = is_array($registro["taxas_e_materiais"] ?? null) ? $registro["taxas_e_materiais"] : (is_array($registro["materiais"] ?? null) ? $registro["materiais"] : []);
            $matricula = trim((string) ($registro["matricula"] ?? ($aluno_dados["matricula"] ?? "")));
            $responsavel = $this->_normalizar_responsavel_importacao($responsavel_dados);
            $responsavel["matricula"] = $matricula;
            $normalizado["responsaveis"][] = $responsavel;

            $aluno = array_merge($aluno_dados, [
                "matricula" => $matricula,
                "aluno_nome" => $aluno_dados["nome_completo"] ?? ($aluno_dados["nome"] ?? ""),
                "status" => $registro["status"] ?? ($aluno_dados["status"] ?? ""),
                "responsavel_nome" => $responsavel["nome"] ?? "",
                "responsavel_cpf" => $responsavel["cpf_normalizado"] ?? "",
                "responsavel_telefone" => $responsavel["telefone_normalizado"] ?? "",
                "mensalidade" => $curso["mensalidade"] ?? ($registro["mensalidade"] ?? ($aluno_dados["mensalidade"] ?? "")),
                "materiais" => $taxas,
                "turma" => $curso["horario_raw"] ?? ($curso["horario"] ?? ($aluno_dados["turma"] ?? ($aluno_dados["horario"] ?? ($registro["horario"] ?? "")))),
                "pelotao" => $curso["pelotao_inferido"] ?? ($curso["pelotao"] ?? ($registro["pelotao"] ?? "")),
                "data_matricula" => $curso["data_matricula"] ?? ($registro["data_matricula"] ?? ""),
                "data_inicio" => $curso["data_inicio"] ?? ($registro["data_inicio"] ?? ""),
                "camiseta" => $this->_material_status_importacao($taxas["camiseta"] ?? ""),
                "material_01" => $this->_material_status_importacao($taxas["material_01"] ?? ""),
                "material_02" => $this->_material_status_importacao($taxas["material_02"] ?? "")
            ]);
            $normalizado["alunos"][] = $this->_normalizar_aluno_importacao($aluno);

            if ($taxas) {
                $normalizado["materiais"][] = $this->_normalizar_material_importacao($taxas + ["matricula" => $matricula]);
            }

            $pagamentos = $this->_pagamentos_aluno_importacao($registro["pagamentos"] ?? []);
            foreach ($pagamentos as $pagamento) {
                $normalizado["pagamentos"][] = $this->_normalizar_pagamento_importacao($pagamento + ["matricula" => $matricula]);
            }

            $presencas = $registro["presencas_aulas"]["registros"] ?? ($registro["presencas"] ?? []);
            foreach ($this->_lista_importacao($presencas) as $presenca) {
                $presenca = $this->_normalizar_presenca_importacao($presenca + ["matricula" => $matricula, "turma" => $aluno["turma"] ?? ""]);
                if ($presenca) {
                    $normalizado["presencas"][] = $presenca;
                }
            }
        }

        foreach ($this->_lista_importacao($payload["presencas_palestra"] ?? []) as $lead) {
            $normalizado["presencas_palestra"][] = $this->_normalizar_lead_importacao($lead);
        }

        return $normalizado;
    }

    private function _lista_importacao($value)
    {
        if (!$value || !is_array($value)) {
            return [];
        }

        return $this->_is_assoc($value) ? [$value] : $value;
    }

    private function _pagamentos_aluno_importacao($pagamentos)
    {
        if (!$pagamentos || !is_array($pagamentos)) {
            return [];
        }

        if (isset($pagamentos["mensalidades"])) {
            return $this->_lista_importacao($pagamentos["mensalidades"]);
        }

        return $this->_lista_importacao($pagamentos);
    }

    private function _material_status_importacao($material)
    {
        if (is_array($material)) {
            $material = $material["status"] ?? ($material["status_raw"] ?? ($material["valor"] ?? ""));
        }

        return $this->_normalizar_material($material);
    }

    private function _normalizar_responsavel_importacao($responsavel)
    {
        $telefones = $this->_lista_importacao($responsavel["telefones"] ?? []);
        $primeiro_telefone = $telefones[0] ?? [];
        $segundo_telefone = $telefones[1] ?? [];
        $telefone_fallback = is_array($responsavel["telefone"] ?? null) ? "" : ($responsavel["telefone"] ?? "");
        $telefone_original = trim((string) ($primeiro_telefone["raw"] ?? ($telefone_fallback ?: ($responsavel["whats"] ?? ($responsavel["celular"] ?? "")))));
        $telefone_normalizado = trim((string) ($primeiro_telefone["digits"] ?? ""));
        if (!$telefone_normalizado) {
            $telefone_normalizado = $this->_digits($telefone_original);
        }
        $telefone_secundario = trim((string) ($segundo_telefone["digits"] ?? ($responsavel["telefone_2"] ?? ($responsavel["celular"] ?? ""))));
        $cpf_original = trim((string) ($responsavel["cpf"] ?? ""));

        return [
            "id" => $responsavel["id"] ?? "",
            "nome" => trim((string) ($responsavel["nome_completo"] ?? ($responsavel["nome"] ?? ($responsavel["responsavel_nome"] ?? "")))),
            "telefone_original" => $telefone_original,
            "telefone_normalizado" => $this->_digits($telefone_normalizado ?: $telefone_original),
            "telefone_secundario" => $this->_digits($telefone_secundario),
            "cpf_original" => $cpf_original,
            "cpf_normalizado" => $this->_digits($cpf_original),
            "email" => trim((string) ($responsavel["email"] ?? "")),
            "endereco" => trim((string) ($responsavel["endereco"] ?? "")),
            "observacao" => trim((string) ($responsavel["observacao"] ?? ""))
        ];
    }

    private function _normalizar_aluno_importacao($aluno)
    {
        $materiais = is_array($aluno["materiais"] ?? null) ? $aluno["materiais"] : [];
        $curso = is_array($aluno["curso"] ?? null) ? $aluno["curso"] : [];
        $taxas = is_array($aluno["taxas_e_materiais"] ?? null) ? $aluno["taxas_e_materiais"] : $materiais;
        $matricula = trim((string) ($aluno["matricula"] ?? ""));

        return [
            "matricula" => $matricula,
            "aluno_nome" => trim((string) ($aluno["aluno_nome"] ?? ($aluno["nome_completo"] ?? ($aluno["nome"] ?? ($aluno["nome_aluno"] ?? ""))))),
            "status" => $this->_normalizar_status_aluno($aluno["status"] ?? "ativo"),
            "data_nascimento" => $this->_date_value($aluno["data_nascimento"] ?? ($aluno["nascimento_aluno"] ?? "")),
            "responsavel_nome" => trim((string) ($aluno["responsavel_nome"] ?? "")),
            "responsavel_cpf" => $this->_digits($aluno["responsavel_cpf"] ?? ""),
            "responsavel_telefone" => $this->_digits($aluno["responsavel_telefone"] ?? ($aluno["telefone"] ?? "")),
            "mensalidade" => $this->_money_to_float($aluno["mensalidade"] ?? ($aluno["valor_mensalidade"] ?? ($curso["mensalidade"] ?? 0))),
            "turma" => $this->_normalizar_horario_operacional($aluno["turma"] ?? ($aluno["horario"] ?? ($curso["horario_raw"] ?? ($curso["horario"] ?? "")))),
            "pelotao" => trim((string) ($aluno["pelotao"] ?? ($curso["pelotao_inferido"] ?? ($curso["pelotao"] ?? "")))),
            "data_matricula" => $this->_date_value($aluno["data_matricula"] ?? ($curso["data_matricula"] ?? "")),
            "data_inicio" => $this->_date_value($aluno["data_inicio"] ?? ($curso["data_inicio"] ?? "")),
            "data_cancelamento" => $this->_date_value($aluno["data_cancelamento"] ?? ""),
            "motivo_cancelamento" => trim((string) ($aluno["motivo_cancelamento"] ?? "")),
            "observacao_cancelamento" => trim((string) ($aluno["observacao_cancelamento"] ?? "")),
            "camiseta" => $this->_material_status_importacao($aluno["camiseta"] ?? ($taxas["camiseta"] ?? "")),
            "material_01" => $this->_material_status_importacao($aluno["material_01"] ?? ($taxas["material_01"] ?? "")),
            "material_02" => $this->_material_status_importacao($aluno["material_02"] ?? ($taxas["material_02"] ?? ""))
        ];
    }

    private function _normalizar_pagamento_importacao($pagamento)
    {
        $status_raw = $pagamento["status_raw"] ?? ($pagamento["descricao"] ?? "");
        $status = $this->_normalizar_pagamento($pagamento["status"] ?? ($pagamento["descricao"] ?? ""), $status_raw);
        $valor = $this->_money_to_float($pagamento["valor"] ?? ($pagamento["valor_mensalidade"] ?? ""));
        $competencia = trim((string) ($pagamento["competencia"] ?? ($pagamento["competencia_label"] ?? ($pagamento["mes"] ?? ""))));
        $referencia = $this->_referencia_from_competencia($competencia, $pagamento["vencimento"] ?? ($pagamento["data_vencimento"] ?? ""));
        $vencimento = $this->_date_value($pagamento["vencimento"] ?? ($pagamento["data_vencimento"] ?? ""));
        if (!$vencimento && !empty($referencia["mes"]) && !empty($referencia["ano"])) {
            $vencimento = sprintf("%04d-%02d-01", (int) $referencia["ano"], (int) $referencia["mes"]);
        }

        return [
            "matricula" => trim((string) ($pagamento["matricula"] ?? "")),
            "competencia" => $competencia,
            "mes_referencia" => $referencia["mes"],
            "ano_referencia" => $referencia["ano"],
            "descricao" => trim((string) ($pagamento["descricao"] ?? ($pagamento["competencia_label"] ?? ($status_raw ?: ($pagamento["status"] ?? ""))))),
            "valor" => $valor,
            "status" => $status,
            "vencimento" => $vencimento,
            "data_pagamento" => $this->_date_value($pagamento["data_pagamento"] ?? ""),
            "forma_pagamento" => trim((string) ($pagamento["forma_pagamento"] ?? "")),
            "observacao" => trim((string) ($pagamento["observacao"] ?? "")),
            "origem_importacao" => "json_planilha"
        ];
    }

    private function _normalizar_material_importacao($material)
    {
        return [
            "matricula" => trim((string) ($material["matricula"] ?? "")),
            "camiseta" => $this->_material_status_importacao($material["camiseta"] ?? ($material["uniforme"] ?? "")),
            "material_01" => $this->_material_status_importacao($material["material_01"] ?? ""),
            "material_02" => $this->_material_status_importacao($material["material_02"] ?? ""),
            "observacao" => trim((string) ($material["observacao"] ?? ""))
        ];
    }

    private function _normalizar_presenca_importacao($presenca)
    {
        $status = $this->_normalizar_presenca($presenca["status"] ?? ($presenca["status_raw"] ?? ""));
        if ($status === "sem_registro") {
            return null;
        }

        $data_aula = $this->_date_value($presenca["data"] ?? ($presenca["data_aula"] ?? ""));
        if (!$data_aula) {
            return null;
        }

        return [
            "matricula" => trim((string) ($presenca["matricula"] ?? "")),
            "data_aula" => $data_aula,
            "status_tipo" => $status,
            "turma" => $this->_normalizar_horario_operacional($presenca["turma"] ?? ($presenca["horario"] ?? "")),
            "observacao" => trim((string) ($presenca["observacao"] ?? ""))
        ];
    }

    private function _normalizar_lead_importacao($lead)
    {
        $telefone = is_array($lead["telefone"] ?? null) ? $lead["telefone"] : [];
        $telefone_fallback = is_array($lead["telefone"] ?? null) ? "" : ($lead["telefone"] ?? "");
        $telefone_original = trim((string) ($telefone["raw"] ?? ($telefone_fallback ?: ($lead["whats"] ?? ""))));
        $telefone_normalizado = trim((string) ($telefone["digits"] ?? ""));
        if (!$telefone_normalizado) {
            $telefone_normalizado = $this->_digits($telefone_original);
        }

        return [
            "responsavel_nome" => trim((string) ($lead["responsavel"] ?? ($lead["responsavel_nome"] ?? ($lead["nome_responsavel"] ?? "")))),
            "aluno_nome" => trim((string) ($lead["aluno"] ?? ($lead["aluno_nome"] ?? ($lead["nome_aluno"] ?? "")))),
            "telefone" => $telefone_original,
            "telefone_normalizado" => $this->_digits($telefone_normalizado ?: $telefone_original),
            "status" => $this->_normalizar_status_lead($lead["status_matricula"] ?? ($lead["status"] ?? ($lead["status_matricula_raw"] ?? ""))),
            "compareceu_palestra" => 1,
            "origem" => trim((string) ($lead["origem"] ?? "json_planilha")),
            "observacao" => trim((string) ($lead["observacao"] ?? "")),
            "data_evento" => $this->_date_value($lead["data_evento"] ?? ($lead["data"] ?? ""))
        ];
    }

    private function _preview_importacao($normalizado)
    {
        $unit_id = $this->_active_unit_id();
        $matriculas = [];
        $duplicidades = [];
        $invalidos = [];
        $criados = 0;
        $atualizados = 0;
        $ativos = 0;
        $cancelados = 0;
        $concluidos = 0;

        foreach ($normalizado["alunos"] as $aluno) {
            if (empty($aluno["aluno_nome"])) {
                $invalidos[] = "Aluno sem nome: -";
                continue;
            }
            $matricula_sera_gerada = $this->_matricula_deve_ser_gerada($aluno["matricula"] ?? "");
            if (!$matricula_sera_gerada && isset($matriculas[$aluno["matricula"]])) {
                $duplicidades[] = "Matrícula duplicada no arquivo: " . $aluno["matricula"];
            }
            if (!$matricula_sera_gerada) {
                $matriculas[$aluno["matricula"]] = true;
            }
            $existente = $matricula_sera_gerada ? null : $this->Bombeiros_alunos_model->get_details(["matricula" => $aluno["matricula"], "unidade_id" => $unit_id])->getRow();
            $existente ? $atualizados++ : $criados++;
            if ($aluno["status"] === "Cancelado") {
                $cancelados++;
            } elseif ($aluno["status"] === "Concluido") {
                $concluidos++;
            } elseif ($aluno["status"] === "Ativo") {
                $ativos++;
            }
        }

        $pagos = 0;
        $pendentes = 0;
        $sem_registro = 0;
        foreach ($normalizado["pagamentos"] as $pagamento) {
            if ($pagamento["status"] === "Pago") {
                $pagos++;
            } elseif ($pagamento["status"] === "Pendente" || $pagamento["status"] === "Vencido") {
                $pendentes++;
            } else {
                $sem_registro++;
            }
        }

        return [
            "responsaveis" => count($normalizado["responsaveis"]),
            "alunos_ativos" => $ativos,
            "alunos_cancelados" => $cancelados,
            "alunos_concluidos" => $concluidos,
            "pagamentos_pagos" => $pagos,
            "pagamentos_pendentes" => $pendentes,
            "pagamentos_ignorados" => $sem_registro,
            "materiais" => count($normalizado["materiais"]),
            "presencas" => count($normalizado["presencas"]),
            "presencas_palestra" => count($normalizado["presencas_palestra"]),
            "duplicidades" => $duplicidades,
            "invalidos" => $invalidos,
            "alunos_criados" => $criados,
            "alunos_atualizados" => $atualizados,
            "qualidade_dados" => $normalizado["qualidade_dados"]
        ];
    }

    private function _executar_importacao_normalizada($normalizado)
    {
        $db = db_connect();
        $unit_id = $this->_active_unit_id();
        $relatorio = $this->_relatorio_importacao_vazio();
        $responsavel_map = [];
        $aluno_map = [];
        $matricula_lock_acquired = false;

        try {
            $db->transStart();

            foreach ($normalizado["responsaveis"] as $responsavel) {
                try {
                    $cpf = $responsavel["cpf_normalizado"] ?? "";
                    $telefone = $responsavel["telefone_normalizado"] ?? "";
                    $existente = $cpf ? $this->Bombeiros_responsaveis_model->get_details(["cpf" => $cpf])->getRow() : null;
                    if (!$existente && $telefone) {
                        $existente = $this->Bombeiros_responsaveis_model->get_details(["whats" => $telefone])->getRow();
                    }

                    $dados_responsavel = [
                        "nome" => $responsavel["nome"] ?: "Responsável não informado",
                        "cpf" => $cpf,
                        "whats" => $telefone,
                        "celular" => $responsavel["telefone_secundario"] ?? "",
                        "email" => $responsavel["email"] ?? "",
                        "endereco" => $responsavel["endereco"] ?? "",
                        "status" => "Ativo",
                        "deleted" => 0
                    ];
                    $save_id = $this->Bombeiros_responsaveis_model->ci_save($dados_responsavel, $existente->id ?? 0);

                    if ($cpf) {
                        $responsavel_map["cpf:" . $cpf] = $save_id;
                    }
                    if ($telefone) {
                        $responsavel_map["telefone:" . $telefone] = $save_id;
                    }
                    if (!empty($responsavel["matricula"])) {
                        $responsavel_map["matricula:" . $responsavel["matricula"]] = $save_id;
                    }
                    $relatorio[$existente ? "responsaveis_atualizados" : "responsaveis_criados"]++;
                } catch (\Throwable $e) {
                    $relatorio["erros"][] = "Responsável ignorado: " . $e->getMessage();
                }
            }

            foreach ($normalizado["alunos"] as $aluno) {
                try {
                    if (!$aluno["aluno_nome"]) {
                        $relatorio["alunos_ignorados"]++;
                        $relatorio["erros"][] = "Aluno sem nome ignorado.";
                        continue;
                    }

                    $matricula_original = $aluno["matricula"] ?? "";
                    if ($this->_matricula_deve_ser_gerada($matricula_original)) {
                        if (!$matricula_lock_acquired) {
                            $this->_adquirir_trava_matricula($db);
                            $matricula_lock_acquired = true;
                        }
                        $aluno["matricula"] = $this->_proxima_matricula_aluno($db);
                    } else {
                        $aluno["matricula"] = trim((string) $matricula_original);
                    }

                    $responsavel_id = $responsavel_map["matricula:" . $aluno["matricula"]] ?? 0;
                    if (!$responsavel_id && !empty($aluno["responsavel_cpf"])) {
                        $responsavel_id = $responsavel_map["cpf:" . $aluno["responsavel_cpf"]] ?? 0;
                    }
                    if (!$responsavel_id && !empty($aluno["responsavel_telefone"])) {
                        $responsavel_id = $responsavel_map["telefone:" . $aluno["responsavel_telefone"]] ?? 0;
                    }
                    if (!$responsavel_id) {
                        $dados_responsavel = [
                            "nome" => $aluno["responsavel_nome"] ?: "Responsável não informado",
                            "cpf" => $aluno["responsavel_cpf"],
                            "whats" => $aluno["responsavel_telefone"],
                            "status" => "Ativo",
                            "deleted" => 0
                        ];
                        $responsavel_id = $this->Bombeiros_responsaveis_model->ci_save($dados_responsavel);
                        $relatorio["responsaveis_criados"]++;
                    }

                    $existente = $this->Bombeiros_alunos_model->get_details(["matricula" => $aluno["matricula"], "unidade_id" => $unit_id])->getRow();
                    $dados_aluno = [
                        "unidade_id" => $unit_id,
                        "responsavel_id" => $responsavel_id,
                        "matricula" => $aluno["matricula"],
                        "nome_aluno" => $aluno["aluno_nome"],
                        "nascimento_aluno" => $aluno["data_nascimento"],
                        "turma" => $aluno["turma"],
                        "horario" => $aluno["turma"],
                        "pelotao" => $aluno["pelotao"],
                        "valor_mensalidade" => $aluno["mensalidade"],
                        "tamanho_camisa" => $aluno["camiseta"],
                        "camiseta" => $aluno["camiseta"],
                        "camiseta_status" => $aluno["camiseta"],
                        "material_01" => $aluno["material_01"],
                        "material_01_status" => $aluno["material_01"],
                        "material_02" => $aluno["material_02"],
                        "material_02_status" => $aluno["material_02"],
                        "uniforme_efetuado" => $aluno["camiseta"] === "entregue" ? 1 : 0,
                        "material_efetuado" => $aluno["material_01"] === "entregue" ? 1 : 0,
                        "data_matricula" => $aluno["data_matricula"] ?: date("Y-m-d"),
                        "data_inicio" => $aluno["data_inicio"],
                        "status" => $aluno["status"],
                        "data_cancelamento" => $aluno["status"] === "Cancelado" ? ($aluno["data_cancelamento"] ?: date("Y-m-d")) : null,
                        "motivo_cancelamento" => $aluno["motivo_cancelamento"],
                        "observacao_cancelamento" => $aluno["observacao_cancelamento"],
                        "deleted" => 0
                    ];
                    $save_id = $this->Bombeiros_alunos_model->ci_save($dados_aluno, $existente->id ?? 0);

                    $aluno_map[$aluno["matricula"]] = $save_id;
                    $relatorio[$existente ? "alunos_atualizados" : "alunos_criados"]++;
                } catch (\Throwable $e) {
                    $relatorio["alunos_ignorados"]++;
                    $relatorio["erros"][] = "Aluno " . ($aluno["matricula"] ?? "-") . " ignorado: " . $e->getMessage();
                }
            }

            foreach ($normalizado["pagamentos"] as $pagamento) {
                try {
                    if (!$this->_pagamento_importavel($pagamento)) {
                        $relatorio["pagamentos_ignorados"]++;
                        continue;
                    }
                    $aluno_id = $aluno_map[$pagamento["matricula"]] ?? $this->_aluno_id_por_matricula($pagamento["matricula"], $unit_id);
                    if (!$aluno_id) {
                        $relatorio["pagamentos_ignorados"]++;
                        $relatorio["erros"][] = "Pagamento sem matrícula válida ignorado: " . ($pagamento["matricula"] ?: "-");
                        continue;
                    }

                    $existente = $this->_cobranca_existente($aluno_id, $pagamento);
                    $aluno_info = $this->Bombeiros_alunos_model->get_details(["id" => $aluno_id, "unidade_id" => $unit_id])->getRow();
                    $dados_cobranca = [
                        "aluno_id" => $aluno_id,
                        "responsavel_id" => $aluno_info->responsavel_id ?? null,
                        "unit_id" => $unit_id,
                        "vencimento" => $pagamento["vencimento"] ?: date("Y-m-d"),
                        "valor" => $pagamento["valor"],
                        "competencia" => $pagamento["competencia"],
                        "mes_referencia" => $pagamento["mes_referencia"],
                        "ano_referencia" => $pagamento["ano_referencia"],
                        "descricao" => $pagamento["descricao"],
                        "status" => $pagamento["status"],
                        "tipo" => "Mensalidade",
                        "data_pagamento" => $pagamento["status"] === "Pago" ? ($pagamento["data_pagamento"] ?: date("Y-m-d")) : null,
                        "forma_pagamento" => $pagamento["forma_pagamento"],
                        "observacao" => $pagamento["observacao"],
                        "origem_importacao" => $pagamento["origem_importacao"]
                    ];
                    $this->Bombeiros_cobrancas_model->ci_save($dados_cobranca, $existente->id ?? 0);
                    $relatorio[$existente ? "pagamentos_atualizados" : "pagamentos_criados"]++;
                } catch (\Throwable $e) {
                    $relatorio["pagamentos_ignorados"]++;
                    $relatorio["erros"][] = "Pagamento ignorado: " . $e->getMessage();
                }
            }

            foreach ($normalizado["materiais"] as $material) {
                $aluno_id = $aluno_map[$material["matricula"]] ?? $this->_aluno_id_por_matricula($material["matricula"], $unit_id);
                if (!$aluno_id) {
                    continue;
                }
                $dados_material = [
                    "camiseta" => $material["camiseta"],
                    "camiseta_status" => $material["camiseta"],
                    "material_01" => $material["material_01"],
                    "material_01_status" => $material["material_01"],
                    "material_02" => $material["material_02"],
                    "material_02_status" => $material["material_02"],
                    "materiais_observacao" => $material["observacao"],
                    "uniforme_efetuado" => $material["camiseta"] === "entregue" ? 1 : 0,
                    "material_efetuado" => $material["material_01"] === "entregue" ? 1 : 0
                ];
                $this->Bombeiros_alunos_model->ci_save($dados_material, $aluno_id);
                $relatorio["materiais_atualizados"]++;
            }

            foreach ($normalizado["presencas"] as $presenca) {
                $aluno_id = $aluno_map[$presenca["matricula"]] ?? $this->_aluno_id_por_matricula($presenca["matricula"], $unit_id);
                if (!$aluno_id) {
                    $relatorio["presencas_ignoradas"]++;
                    continue;
                }
                $where = ["aluno_id" => $aluno_id, "data_aula" => $presenca["data_aula"]];
                $registro = $this->Bombeiros_presenca_model->get_one_where($where);
                $dados_presenca = $where + [
                    "status" => $presenca["status_tipo"] === "presente" ? 1 : 0,
                    "status_tipo" => $presenca["status_tipo"],
                    "turma" => $presenca["turma"],
                    "observacao" => $presenca["observacao"]
                ];
                $this->Bombeiros_presenca_model->ci_save($dados_presenca, $registro && $registro->id ? $registro->id : 0);
                $relatorio["presencas_criadas"]++;
            }

            foreach ($normalizado["presencas_palestra"] as $lead) {
                $this->_salvar_lead_importado($lead, $unit_id, $relatorio);
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \RuntimeException(app_lang("error_occurred"));
            }
            $relatorio["mensalidades_criadas"] += $this->_garantir_mensalidades_mes_atual($unit_id);
            $relatorio["alertas_qualidade"] = $normalizado["qualidade_dados"];
        } catch (\Throwable $e) {
            $db->transRollback();
            $relatorio["erros_criticos"][] = $e->getMessage();
        } finally {
            if ($matricula_lock_acquired) {
                $this->_liberar_trava_matricula($db);
            }
        }

        return $relatorio;
    }

    private function _relatorio_importacao_vazio()
    {
        return [
            "responsaveis_criados" => 0,
            "responsaveis_atualizados" => 0,
            "alunos_criados" => 0,
            "alunos_atualizados" => 0,
            "alunos_ignorados" => 0,
            "pagamentos_criados" => 0,
            "pagamentos_atualizados" => 0,
            "pagamentos_ignorados" => 0,
            "mensalidades_criadas" => 0,
            "materiais_atualizados" => 0,
            "presencas_criadas" => 0,
            "presencas_ignoradas" => 0,
            "leads_criados" => 0,
            "leads_atualizados" => 0,
            "duplicidades" => [],
            "erros" => [],
            "erros_criticos" => [],
            "alertas_qualidade" => []
        ];
    }

    private function _importacao_temp_dir()
    {
        $dir = WRITEPATH . "uploads/grupo_donato_importacoes/";
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    private function _importacao_temp_path($prefix)
    {
        $user_id = (int) ($this->login_user->id ?? 0);
        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $token = md5(uniqid((string) mt_rand(), true));
        }

        return $this->_importacao_temp_dir() . $prefix . "_" . $user_id . "_" . $token . ".json";
    }

    private function _salvar_payload_importacao_temporario($payload)
    {
        $this->_limpar_payload_importacao_temporario();
        $path = $this->_importacao_temp_path("payload");
        file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->session->set("grupo_donato_import_payload_file", $path);
    }

    private function _carregar_payload_importacao_temporario()
    {
        return $this->_carregar_json_importacao_temporario($this->session->get("grupo_donato_import_payload_file"));
    }

    private function _limpar_payload_importacao_temporario()
    {
        $path = $this->session->get("grupo_donato_import_payload_file");
        if ($this->_importacao_temp_path_valido($path) && file_exists($path)) {
            @unlink($path);
        }
        $this->session->remove("grupo_donato_import_payload_file");
    }

    private function _salvar_relatorio_importacao_temporario($relatorio)
    {
        $old_path = $this->session->get("grupo_donato_import_report_file");
        if ($this->_importacao_temp_path_valido($old_path) && file_exists($old_path)) {
            @unlink($old_path);
        }

        $path = $this->_importacao_temp_path("report");
        file_put_contents($path, json_encode($relatorio, JSON_UNESCAPED_UNICODE));
        $this->session->set("grupo_donato_import_report_file", $path);
    }

    private function _carregar_relatorio_importacao_temporario()
    {
        return $this->_carregar_json_importacao_temporario($this->session->get("grupo_donato_import_report_file"));
    }

    private function _carregar_json_importacao_temporario($path)
    {
        if (!$this->_importacao_temp_path_valido($path) || !file_exists($path)) {
            return null;
        }

        $data = json_decode(file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }

    private function _importacao_temp_path_valido($path)
    {
        if (!$path || !is_string($path)) {
            return false;
        }

        $dir = rtrim($this->_importacao_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return strpos($path, $dir) === 0 && strpos($path, "..") === false;
    }

    private function _pagamento_importavel($pagamento)
    {
        if (($pagamento["status"] ?? "") === "Sem registro") {
            return false;
        }

        return !empty($pagamento["matricula"]) && (!empty($pagamento["valor"]) || !empty($pagamento["competencia"]));
    }

    private function _aluno_id_por_matricula($matricula, $unit_id)
    {
        if (!$matricula) {
            return 0;
        }
        $aluno = $this->Bombeiros_alunos_model->get_details(["matricula" => $matricula, "unidade_id" => $unit_id])->getRow();
        return $aluno ? (int) $aluno->id : 0;
    }

    private function _matricula_deve_ser_gerada($matricula)
    {
        $matricula = strtolower(trim((string) $matricula));
        return in_array($matricula, ["", "sem_registro", "sem registro", "null", "n/a", "na", "nao informado", "não informado"], true);
    }

    private function _cobranca_existente($aluno_id, $pagamento)
    {
        $db = db_connect();
        $table = $db->prefixTable("grupo_donato_cobrancas");
        $where = "aluno_id=" . (int) $aluno_id . " AND tipo='Mensalidade'";
        if (!empty($pagamento["mes_referencia"]) && !empty($pagamento["ano_referencia"])) {
            $where .= " AND mes_referencia=" . (int) $pagamento["mes_referencia"] . " AND ano_referencia=" . (int) $pagamento["ano_referencia"];
        } elseif (!empty($pagamento["competencia"])) {
            $where .= " AND competencia=" . $db->escape($pagamento["competencia"]);
        } else {
            return null;
        }

        return $db->query("SELECT * FROM $table WHERE $where LIMIT 1")->getRow();
    }

    private function _salvar_lead_importado($lead, $unit_id, &$relatorio)
    {
        $aluno = null;
        if (!empty($lead["telefone_normalizado"])) {
            $aluno = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $unit_id, "query" => $lead["telefone_normalizado"]])->getRow();
        }
        if (!$aluno && !empty($lead["aluno_nome"])) {
            $aluno = $this->Bombeiros_alunos_model->get_details(["unidade_id" => $unit_id, "query" => $lead["aluno_nome"]])->getRow();
        }

        $existente = $lead["telefone_normalizado"] ? $this->Bombeiros_leads_palestra_model->get_details(["unit_id" => $unit_id, "telefone_normalizado" => $lead["telefone_normalizado"]])->getRow() : null;
        $status = $aluno ? "matriculado" : $lead["status"];
        $dados_lead = [
            "unit_id" => $unit_id,
            "responsavel_nome" => $lead["responsavel_nome"],
            "aluno_nome" => $lead["aluno_nome"],
            "telefone" => $lead["telefone"],
            "telefone_normalizado" => $lead["telefone_normalizado"],
            "status" => $status,
            "compareceu_palestra" => 1,
            "aluno_id" => $aluno->id ?? null,
            "responsavel_id" => $aluno->responsavel_id ?? null,
            "origem" => $lead["origem"],
            "observacao" => $lead["observacao"],
            "data_evento" => $lead["data_evento"]
        ];
        $this->Bombeiros_leads_palestra_model->ci_save($dados_lead, $existente->id ?? 0);
        $relatorio[$existente ? "leads_atualizados" : "leads_criados"]++;
    }

    private function _is_assoc($array)
    {
        return is_array($array) && array_keys($array) !== range(0, count($array) - 1);
    }

    private function _normalizar_status_aluno($status)
    {
        $status = strtolower(trim((string) $status));
        $status = strtr($status, [
            "á" => "a", "à" => "a", "â" => "a", "ã" => "a",
            "é" => "e", "ê" => "e",
            "í" => "i", "Í" => "i",
            "ó" => "o", "ô" => "o", "õ" => "o",
            "ú" => "u",
            "ç" => "c"
        ]);
        if (in_array($status, ["cancelado", "cancelada"], true)) {
            return "Cancelado";
        }
        if (in_array($status, ["inativo", "inativa"], true)) {
            return "Inativo";
        }
        if ($status === "pendente") {
            return "Pendente";
        }
        if (in_array($status, ["inadimplente", "inadimplencia"], true)) {
            return "Inadimplente";
        }
        if (in_array($status, ["concluido", "concluida", "finalizado", "finalizada"], true)) {
            return "Concluido";
        }

        return "Ativo";
    }

    private function _normalizar_status_lead($status)
    {
        $status = strtolower(trim((string) $status));
        $map = [
            "matriculado" => "matriculado",
            "compareceu" => "compareceu_palestra",
            "compareceu_palestra" => "compareceu_palestra",
            "nao_matriculado" => "nao_matriculado",
            "não_matriculado" => "nao_matriculado",
            "não matriculado" => "nao_matriculado",
            "nao matriculado" => "nao_matriculado",
            "em_negociacao" => "em_negociacao",
            "em negociação" => "em_negociacao",
            "perdido" => "perdido"
        ];
        return $map[$status] ?? "sem_status";
    }

    private function _normalizar_status_custo($status)
    {
        $status = strtolower(trim((string) $status));
        $status = strtr($status, [
            "á" => "a", "à" => "a", "â" => "a", "ã" => "a",
            "é" => "e", "ê" => "e",
            "í" => "i",
            "ó" => "o", "ô" => "o", "õ" => "o",
            "ú" => "u",
            "ç" => "c"
        ]);

        $map = [
            "pago" => "Pago",
            "paga" => "Pago",
            "previsto" => "Previsto",
            "prevista" => "Previsto",
            "cancelado" => "Cancelado",
            "cancelada" => "Cancelado"
        ];

        return $map[$status] ?? "Previsto";
    }

    private function _competencia_label($mes, $ano)
    {
        $mes = (int) $mes;
        $ano = (int) $ano;
        if ($mes < 1 || $mes > 12 || !$ano) {
            return "-";
        }

        return sprintf("%02d/%04d", $mes, $ano);
    }

    private function _referencia_from_competencia($competencia, $vencimento = "")
    {
        $competencia = trim((string) $competencia);
        if (preg_match('/^(\d{4})-(\d{1,2})$/', $competencia, $matches)) {
            return ["mes" => (int) $matches[2], "ano" => (int) $matches[1]];
        }
        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $competencia, $matches)) {
            return ["mes" => (int) $matches[1], "ano" => (int) $matches[2]];
        }
        $data = $this->_date_value($vencimento);
        if ($data) {
            return ["mes" => (int) date("m", strtotime($data)), "ano" => (int) date("Y", strtotime($data))];
        }
        return ["mes" => null, "ano" => null];
    }

    private function _normalizar_horario_operacional($value)
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace(["às", "as", " ate ", " até ", " - "], "-", $value);
        $value = str_replace("h", ":", $value);
        $value = preg_replace('/\s+/', '', $value);
        $value = str_replace(["–", "—"], "-", $value);

        if (strpos($value, "08:30") !== false || strpos($value, "8:30") !== false) {
            return "08:30-11:00";
        }
        if (strpos($value, "13:30") !== false) {
            return "13:30-16:00";
        }

        return "";
    }

    private function _money_to_float($value)
    {
        if ($value === null || $value === "") {
            return 0;
        }

        $value = trim((string) $value);
        $value = preg_replace("/[^\d,.\-]/", "", $value);
        if ($value === "" || $value === "-" || $value === "," || $value === ".") {
            return 0;
        }

        $last_comma = strrpos($value, ",");
        $last_dot = strrpos($value, ".");

        if ($last_comma !== false && $last_dot !== false) {
            if ($last_comma > $last_dot) {
                $value = str_replace(".", "", $value);
                $value = str_replace(",", ".", $value);
            } else {
                $value = str_replace(",", "", $value);
            }
        } elseif ($last_comma !== false) {
            $decimals = strlen($value) - $last_comma - 1;
            if ($decimals > 0 && $decimals <= 2) {
                $value = str_replace(".", "", $value);
                $value = str_replace(",", ".", $value);
            } else {
                $value = str_replace(",", "", $value);
            }
        } elseif ($last_dot !== false) {
            $decimals = strlen($value) - $last_dot - 1;
            if (substr_count($value, ".") > 1 || $decimals === 3) {
                $value = str_replace(".", "", $value);
            }
        }

        return is_numeric($value) ? (float) $value : 0;
    }

    private function _digits($value)
    {
        return preg_replace("/\D/", "", (string) $value);
    }

    private function _bool_value($value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ["1", "true", "sim", "yes", "on", "checked", "x"], true) ? 1 : 0;
    }

    private function _date_value($value)
    {
        if (!$value) {
            return null;
        }

        $value = trim((string) $value);
        if (strpos($value, "/") !== false) {
            $parts = explode("/", $value);
            if (count($parts) === 3) {
                $value = $parts[2] . "-" . $parts[1] . "-" . $parts[0];
            }
        }

        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
            return null;
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        if ($year < 1900 || !checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf("%04d-%02d-%02d", $year, $month, $day);
    }

    private function _format_date($value)
    {
        return $value ? date("d/m/Y", strtotime($value)) : "-";
    }

    private function _format_cpf($cpf)
    {
        $cpf = $this->_digits($cpf);
        if (strlen($cpf) === 11) {
            return substr($cpf, 0, 3) . "." . substr($cpf, 3, 3) . "." . substr($cpf, 6, 3) . "-" . substr($cpf, 9, 2);
        }

        return $cpf;
    }

    private function _format_phone($phone)
    {
        $phone = $this->_digits($phone);
        if (strlen($phone) === 11) {
            return "(" . substr($phone, 0, 2) . ") " . substr($phone, 2, 5) . "-" . substr($phone, 7);
        }
        if (strlen($phone) === 10) {
            return "(" . substr($phone, 0, 2) . ") " . substr($phone, 2, 4) . "-" . substr($phone, 6);
        }

        return $phone;
    }

    private function _get_api_key()
    {
        $this->_carregar_env_plugin();

        $key = $this->_env_value("GEMINI_API_KEY");
        if (!$key) {
            $key = $this->_env_value("GOOGLE_API_KEY");
        }
        if (!$key && function_exists("get_setting")) {
            $key = get_setting("bombeiros_gemini_api_key") ?: get_setting("gemini_api_key");
        }

        $key = trim((string) $key);
        if (!$key) {
            log_message("error", "Chave GEMINI_API_KEY não encontrada para o plugin Bombeiros. Configure em /opt/apps/rise/shared/.deploy.env, no .env do plugin ou em get_setting('bombeiros_gemini_api_key').");
            throw new \Exception("Configuração de API ausente no servidor. Defina GEMINI_API_KEY no ambiente do servidor.");
        }

        return $key;
    }

    private function _env_value($name)
    {
        $value = getenv($name);
        if (!$value && isset($_ENV[$name])) {
            $value = $_ENV[$name];
        }
        if (!$value && isset($_SERVER[$name])) {
            $value = $_SERVER[$name];
        }

        return trim((string) $value);
    }

    private function _prompt_matricula($texto = "")
    {
        $prompt = "Você é um assistente administrativo especialista em matrículas Grupo Donato/Bombeiros e leitura de fichas escaneadas com preenchimento manuscrito. Analise a ficha de matrícula visualmente, prestando atenção aos campos preenchidos à mão, escrita cursiva, números parcialmente inclinados e valores marcados com X. Retorne APENAS um objeto JSON válido, sem markdown, sem comentários, sem texto antes/depois e sem colocar o objeto dentro de data ou outro wrapper. Use exatamente estes campos: responsavel_nome, responsavel_nascimento, responsavel_rg, responsavel_cpf, responsavel_endereco, responsavel_numero, responsavel_complemento, responsavel_bairro, responsavel_cep, responsavel_cidade, responsavel_whats, responsavel_celular, responsavel_recado, responsavel_email, nome_aluno, nascimento_aluno, rg_aluno, cpf_aluno, curso_nome, num_parcelas, valor_parcela, valor_mensalidade, valor_inscricao, data_inscricao, valor_mensal, data_primeira_parcela, data_inicio, horario, matricula_efetuada, uniforme_efetuado, material_efetuado, melhor_horario_ligacao, tamanho_camisa, cidade_assinatura, estado_assinatura, dia_assinatura, mes_assinatura, ano_assinatura, assinatura_contratada, assinatura_contratante, li_ciente, unidade. Regras: todos os campos devem existir no JSON; use string vazia para campos sem informação, exceto checkboxes que devem ser 0; priorize o texto manuscrito dentro das linhas dos campos; use os rótulos impressos apenas para identificar o campo correto; se um caractere manuscrito for ambíguo, escolha a leitura mais provável pelo contexto do formulário e do Brasil, mas não invente campos completamente vazios; valor_parcela e valor_mensalidade devem ter o mesmo valor da parcela do curso; datas completas em AAAA-MM-DD; se a data de assinatura tiver só o ano, preencha apenas ano_assinatura; valores monetários em número com ponto decimal, como 237.00; campos de checkbox/efetuado/li_ciente devem ser 1 quando marcados ou confirmados e 0 quando não marcados; horario deve usar formato 08:30-11:00 ou 13:30-16:00; melhor_horario_ligacao deve ser manha, tarde ou qualquer; não invente dados que estejam em branco; se o HTML tiver atributo value preenchido, esse valor conta como dado preenchido.";
        if ($texto) {
            $prompt .= " TEXTO: " . substr($texto, 0, 15000);
        }

        return $prompt;
    }

    private function _analisar_texto_com_gemini($texto)
    {
        try {
            $api_key = $this->_get_api_key();
        } catch (\Exception $gemini_error) {
            try {
                return $this->_analisar_texto_com_openrouter($texto);
            } catch (\Exception $openrouter_error) {
                return $this->response->setJSON([
                    "success" => false,
                    "message" => $gemini_error->getMessage() . " OpenRouter também indisponível: " . $openrouter_error->getMessage()
                ]);
            }
        }

        $prompt_text = $this->_prompt_matricula($texto);
        $payload = [
            "contents" => [["parts" => [["text" => $prompt_text]]]],
            "generationConfig" => ["response_mime_type" => "application/json"]
        ];

        return $this->_formatar_resposta_para_frontend($this->_executar_curl_gemini($api_key, $payload));
    }

    private function _get_openrouter_api_key()
    {
        $this->_carregar_env_plugin();

        $candidates = [
            "OPENROUTER_API_KEY" => $this->_env_value("OPENROUTER_API_KEY")
        ];

        if (function_exists("get_setting")) {
            $candidates["bombeiros_openrouter_api_key"] = get_setting("bombeiros_openrouter_api_key");
            $candidates["openrouter_api_key"] = get_setting("openrouter_api_key");
        }

        $invalid_sources = [];
        foreach ($candidates as $source => $key) {
            $key = trim((string) $key);
            if (!$key) {
                continue;
            }
            if ($this->_valid_openrouter_api_key($key)) {
                return $key;
            }

            $invalid_sources[] = $source . " (len " . strlen($key) . ")";
        }

        if ($invalid_sources && function_exists("log_message")) {
            log_message("error", "Bombeiros OpenRouter API key inválida em " . implode(", ", $invalid_sources));
        }

        throw new \Exception("OPENROUTER_API_KEY não encontrada ou inválida.");
    }

    private function _valid_openrouter_api_key($key)
    {
        $key = trim((string) $key);

        return strlen($key) > 30 && strpos($key, "sk-or-") === 0;
    }

    private function _get_openrouter_model()
    {
        $model = $this->_env_value("BOMBEIROS_OPENROUTER_MODEL") ?: $this->_env_value("OPENROUTER_MODEL");
        if (!$model && function_exists("get_setting")) {
            $model = get_setting("bombeiros_openrouter_model") ?: get_setting("openrouter_model");
        }

        return trim((string) ($model ?: "google/gemini-2.5-pro"));
    }

    private function _get_openrouter_pdf_engine()
    {
        $engine = $this->_env_value("BOMBEIROS_OPENROUTER_PDF_ENGINE");
        if (!$engine && function_exists("get_setting")) {
            $engine = get_setting("bombeiros_openrouter_pdf_engine");
        }

        return trim((string) ($engine ?: "native"));
    }

    private function _get_openrouter_max_tokens()
    {
        $max_tokens = $this->_env_value("BOMBEIROS_OPENROUTER_MAX_TOKENS");
        if (!$max_tokens && function_exists("get_setting")) {
            $max_tokens = get_setting("bombeiros_openrouter_max_tokens") ?: get_setting("openrouter_max_tokens");
        }

        $max_tokens = (int) $max_tokens;
        if ($max_tokens < 2048 || $max_tokens > 8192) {
            $max_tokens = 4096;
        }

        return $max_tokens;
    }

    private function _analisar_texto_com_openrouter($texto)
    {
        $payload = [
            "model" => $this->_get_openrouter_model(),
            "messages" => [[
                "role" => "user",
                "content" => $this->_prompt_matricula($texto)
            ]],
            "response_format" => ["type" => "json_object"],
            "plugins" => $this->_openrouter_json_plugins(),
            "stream" => false,
            "temperature" => 0.1,
            "max_tokens" => $this->_get_openrouter_max_tokens()
        ];

        return $this->_formatar_resposta_para_frontend($this->_executar_curl_openrouter($this->_get_openrouter_api_key(), $payload));
    }

    private function _analisar_arquivo_com_openrouter($path)
    {
        $mime_type = mime_content_type($path) ?: "application/octet-stream";
        $data_url = "data:" . $mime_type . ";base64," . base64_encode(file_get_contents($path));
        $content = [
            ["type" => "text", "text" => $this->_prompt_matricula()]
        ];

        if (strpos($mime_type, "image/") === 0) {
            $content[] = ["type" => "image_url", "image_url" => ["url" => $data_url]];
            $plugins = $this->_openrouter_json_plugins();
        } else {
            $content[] = ["type" => "file", "file" => ["filename" => basename($path), "file_data" => $data_url]];
            $plugins = array_merge([[
                "id" => "file-parser",
                "pdf" => ["engine" => $this->_get_openrouter_pdf_engine()]
            ]], $this->_openrouter_json_plugins());
        }

        $payload = [
            "model" => $this->_get_openrouter_model(),
            "messages" => [[
                "role" => "user",
                "content" => $content
            ]],
            "response_format" => ["type" => "json_object"],
            "stream" => false,
            "temperature" => 0.1,
            "max_tokens" => $this->_get_openrouter_max_tokens()
        ];

        $payload["plugins"] = $plugins;

        return $this->_formatar_resposta_para_frontend($this->_executar_curl_openrouter($this->_get_openrouter_api_key(), $payload));
    }

    private function _executar_curl_openrouter($api_key, $payload)
    {
        $ch = curl_init("https://openrouter.ai/api/v1/chat/completions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $api_key,
            "HTTP-Referer: " . get_uri("grupo_donato/operacional"),
            "X-Title: RISE Bombeiros"
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $erro = curl_error($ch);
            curl_close($ch);
            return $this->response->setJSON(["success" => false, "message" => "Erro cURL OpenRouter: " . $erro]);
        }
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json_response = json_decode($response, true);
        if (!$json_response) {
            return $this->response->setJSON(["success" => false, "message" => "Resposta inválida da API OpenRouter."]);
        }
        if (isset($json_response["error"]["message"])) {
            return $this->response->setJSON(["success" => false, "message" => "Erro OpenRouter: " . $json_response["error"]["message"]]);
        }
        if ($http_status >= 400) {
            return $this->response->setJSON(["success" => false, "message" => "Erro OpenRouter HTTP " . $http_status . "."]);
        }

        $raw_text = $this->_openrouter_message_text($json_response["choices"][0]["message"]["content"] ?? "");
        $dados = $this->_json_from_ai_text($raw_text);
        if (is_array($dados)) {
            return $this->response->setJSON(["success" => true, "data" => $dados]);
        }

        $this->_log_openrouter_json_invalido($raw_text, $json_response);

        return $this->response->setJSON(["success" => false, "message" => "JSON inválido do OpenRouter. A IA respondeu, mas fora do formato esperado. Tente novamente com a matrícula mais nítida ou envie a imagem/PDF original."]);
    }

    private function _openrouter_json_plugins()
    {
        return [["id" => "response-healing"]];
    }

    private function _openrouter_message_text($content)
    {
        if (is_string($content)) {
            return $content;
        }
        if (!is_array($content)) {
            return "";
        }

        $parts = [];
        foreach ($content as $part) {
            if (is_string($part)) {
                $parts[] = $part;
            } else if (is_array($part)) {
                foreach (["text", "content", "value"] as $key) {
                    if (isset($part[$key]) && is_string($part[$key])) {
                        $parts[] = $part[$key];
                        break;
                    }
                }
            }
        }

        return implode("\n", $parts);
    }

    private function _json_from_ai_text($text)
    {
        $text = trim((string) $text);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        $json = $this->_first_json_object($text);
        if ($json) {
            $decoded = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded_string = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($decoded_string)) {
            return $this->_json_from_ai_text($decoded_string);
        }

        return null;
    }

    private function _log_openrouter_json_invalido($raw_text, $json_response)
    {
        if (!function_exists("log_message")) {
            return;
        }

        $finish_reason = $json_response["choices"][0]["finish_reason"] ?? "";
        $model = $json_response["model"] ?? "";
        $preview = $this->_mask_sensitive_log_text(mb_substr((string) $raw_text, 0, 700, "UTF-8"));
        log_message("error", "JSON inválido do OpenRouter no Bombeiros. Modelo: " . $model . ". Finish reason: " . $finish_reason . ". Tamanho: " . strlen((string) $raw_text) . ". Erro JSON: " . json_last_error_msg() . ". Prévia: " . $preview);
    }

    private function _mask_sensitive_log_text($text)
    {
        $text = preg_replace("/[0-9]/", "#", (string) $text);
        $text = preg_replace("/\s+/", " ", $text);

        return trim($text);
    }

    private function _first_json_object($text)
    {
        $start = strpos($text, "{");
        if ($start === false) {
            return "";
        }

        $depth = 0;
        $in_string = false;
        $escape = false;
        $length = strlen($text);
        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($char === "\\") {
                $escape = true;
                continue;
            }
            if ($char === '"') {
                $in_string = !$in_string;
                continue;
            }
            if ($in_string) {
                continue;
            }
            if ($char === "{") {
                $depth++;
            } else if ($char === "}") {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return "";
    }

    private function _executar_curl_gemini($api_key, $payload)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=" . $api_key;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $erro = curl_error($ch);
            curl_close($ch);
            return $this->response->setJSON(["success" => false, "message" => "Erro cURL: " . $erro]);
        }
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json_response = json_decode($response, true);
        if (!$json_response) {
            return $this->response->setJSON(["success" => false, "message" => "Resposta inválida da API Gemini."]);
        }
        if (isset($json_response["error"]["message"])) {
            return $this->response->setJSON(["success" => false, "message" => "Erro Gemini: " . $json_response["error"]["message"]]);
        }
        if ($http_status >= 400) {
            return $this->response->setJSON(["success" => false, "message" => "Erro Gemini HTTP " . $http_status . "."]);
        }

        if (isset($json_response["candidates"][0]["content"]["parts"][0]["text"])) {
            $raw_text = $json_response["candidates"][0]["content"]["parts"][0]["text"];
            $clean_json = str_replace(["```json", "```"], "", $raw_text);
            $dados = json_decode($clean_json, true);

            if ($dados) {
                return $this->response->setJSON(["success" => true, "data" => $dados]);
            }

            return $this->response->setJSON(["success" => false, "message" => "JSON inválido.", "raw" => $raw_text]);
        }

        return $this->response->setJSON(["success" => false, "message" => "Erro IA.", "debug" => $json_response]);
    }

    private function _formatar_resposta_para_frontend($json_response)
    {
        $body = $json_response->getBody();
        $data = json_decode($body, true);

        if (!isset($data["success"]) || $data["success"] === false) {
            return $json_response;
        }

        $dados_ia = $this->_normalizar_dados_matricula_ia($data["data"]);

        return $this->response->setJSON(["success" => true, "data" => $dados_ia]);
    }

    private function _normalizar_dados_matricula_ia($dados)
    {
        if (!is_array($dados)) {
            return [];
        }

        $aliases = [
            "contratante_nome" => "responsavel_nome",
            "contratante_nascimento" => "responsavel_nascimento",
            "contratante_rg" => "responsavel_rg",
            "contratante_cpf" => "responsavel_cpf",
            "contratante_endereco" => "responsavel_endereco",
            "contratante_numero" => "responsavel_numero",
            "contratante_complemento" => "responsavel_complemento",
            "contratante_bairro" => "responsavel_bairro",
            "contratante_cep" => "responsavel_cep",
            "contratante_cidade" => "responsavel_cidade",
            "contratante_whatsapp" => "responsavel_whats",
            "contratante_celular" => "responsavel_celular",
            "contratante_recado" => "responsavel_recado",
            "contratante_email" => "responsavel_email",
            "aluno_nome" => "nome_aluno",
            "aluno_nascimento" => "nascimento_aluno",
            "aluno_rg" => "rg_aluno",
            "aluno_cpf" => "cpf_aluno",
            "curso_contratado" => "curso_nome",
            "curso_nome" => "curso_nome",
            "curso_parcelas" => "num_parcelas",
            "curso_valor" => "valor_mensalidade",
            "valor_parcela" => "valor_mensalidade",
            "data_primeira_parcela" => "data_primeira_parcela",
            "primeira_parcela_data" => "data_primeira_parcela",
            "inicio_data" => "data_inicio",
            "inscricao_valor" => "valor_inscricao",
            "inscricao_data" => "data_inscricao",
            "camiseta" => "tamanho_camisa"
        ];

        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $dados) && (!array_key_exists($to, $dados) || $dados[$to] === "" || $dados[$to] === null)) {
                $dados[$to] = $dados[$from];
            }
        }

        foreach (["responsavel_nascimento", "nascimento_aluno", "data_inscricao", "data_primeira_parcela", "data_inicio"] as $campo) {
            if (!empty($dados[$campo])) {
                $dados[$campo] = $this->_normalizar_data_ia($dados[$campo]);
            }
        }

        foreach (["responsavel_cpf", "responsavel_cep", "responsavel_whats", "responsavel_celular", "responsavel_recado", "cpf_aluno"] as $campo) {
            if (isset($dados[$campo])) {
                $dados[$campo] = $this->_digits($dados[$campo]);
            }
        }

        foreach (["matricula_efetuada", "uniforme_efetuado", "material_efetuado", "li_ciente"] as $campo) {
            $dados[$campo] = $this->_bool_value($dados[$campo] ?? 0);
        }

        if (isset($dados["horario"])) {
            $dados["horario"] = $this->_normalizar_horario_ia($dados["horario"]);
        }
        if (isset($dados["melhor_horario_ligacao"])) {
            $dados["melhor_horario_ligacao"] = $this->_normalizar_melhor_horario_ia($dados["melhor_horario_ligacao"]);
        }
        if (isset($dados["estado_assinatura"])) {
            $dados["estado_assinatura"] = strtoupper(trim((string) $dados["estado_assinatura"]));
        }

        return $dados;
    }

    private function _normalizar_data_ia($value)
    {
        $date = $this->_date_value($value);
        if ($date) {
            return $date;
        }

        $timestamp = strtotime(str_replace("/", "-", (string) $value));
        return $timestamp ? date("Y-m-d", $timestamp) : trim((string) $value);
    }

    private function _normalizar_horario_ia($value)
    {
        $value = trim(str_replace(["às", "ás", " ate ", " até ", " a "], ["-", "-", "-", "-", "-"], mb_strtolower((string) $value, "UTF-8")));
        $value = preg_replace("/\s+/", "", $value);
        if (preg_match("/(08:?30).*(11:?00)/", $value)) {
            return "08:30-11:00";
        }
        if (preg_match("/(13:?30).*(16:?00)/", $value)) {
            return "13:30-16:00";
        }

        return "";
    }

    private function _normalizar_melhor_horario_ia($value)
    {
        $value = mb_strtolower(trim((string) $value), "UTF-8");
        if (strpos($value, "manh") !== false) {
            return "manha";
        }
        if (strpos($value, "tard") !== false) {
            return "tarde";
        }
        if (strpos($value, "qualquer") !== false) {
            return "qualquer";
        }

        return $value;
    }

    private function _carregar_env_plugin()
    {
        // Chaves aceitas no .env de infraestrutura externa da IA (IARA/OpenRouter).
        $infra_keys = ["OPENROUTER_API_KEY", "BOMBEIROS_OPENROUTER_MODEL", "OPENROUTER_MODEL", "BOMBEIROS_OPENROUTER_PDF_ENGINE", "BOMBEIROS_OPENROUTER_MAX_TOKENS", "OPENROUTER_MAX_TOKENS"];
        $paths = [
            ["path" => __DIR__ . "/../.env", "keys" => null],
            // Caminho atual da infraestrutura de IA (servidor externo Grupo Donato).
            ["path" => "/opt/apps/AI_Atendent/grupo_donato-iara-infra/.env", "keys" => $infra_keys],
            // COMPAT: caminho legado do servidor externo, mantido como fallback até a
            // pasta de infraestrutura ser renomeada no servidor de IA (ver relatório).
            ["path" => "/opt/apps/AI_Atendent/siamesa-iara-infra/.env", "keys" => $infra_keys]
        ];
        if (defined("FCPATH")) {
            $paths[] = ["path" => rtrim(FCPATH, "/") . "/.env", "keys" => null];
            $paths[] = ["path" => dirname(rtrim(FCPATH, "/")) . "/shared/.deploy.env", "keys" => null];
        }

        foreach ($paths as $source) {
            $path = $source["path"];
            $allowed_keys = $source["keys"];
            if (!file_exists($path)) {
                continue;
            }

            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, "#") === 0 || strpos($line, "=") === false) {
                    continue;
                }
                if (strpos($line, "export ") === 0) {
                    $line = trim(substr($line, 7));
                }

                [$name, $value] = explode("=", $line, 2);
                $name = trim($name);
                if (!$name) {
                    continue;
                }
                if (is_array($allowed_keys) && !in_array($name, $allowed_keys, true)) {
                    continue;
                }

                $value = trim(trim($value), "\"'");
                putenv(sprintf("%s=%s", $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    private function _read_docx($file_path)
    {
        if (!file_exists($file_path)) {
            return "Arquivo não existe no servidor.";
        }

        $zip = new \ZipArchive();
        if ($zip->open($file_path) === true) {
            $index = $zip->locateName("word/document.xml");
            if ($index !== false) {
                $data = $zip->getFromIndex($index);
                $zip->close();

                $dom = new \DOMDocument();
                $dom->loadXML($data, LIBXML_NOENT | LIBXML_XINCLUDE | LIBXML_NOERROR | LIBXML_NOWARNING);
                return strip_tags($dom->saveXML());
            }

            $zip->close();
            return "Erro: O arquivo DOCX não tem o formato padrão.";
        }

        return "Erro: Falha ao abrir o arquivo. Verifique a extensão php-zip.";
    }
}
