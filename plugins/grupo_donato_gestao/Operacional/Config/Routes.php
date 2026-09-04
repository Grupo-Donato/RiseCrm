<?php

if (defined("GRUPO_DONATO_OPERACIONAL_ROUTES_LOADED")) {
    return;
}

define("GRUPO_DONATO_OPERACIONAL_ROUTES_LOADED", true);

if (!isset($routes)) {
    $routes = \Config\Services::routes(true);
}

$routes->group("", ["namespace" => "grupo_donato_gestao\Operacional\Controllers"], function ($routes) {
    $routes->get("matricula-online/(:segment)", "Bombeiros::matricula_publica/$1");
    $routes->post("matricula-online/(:segment)", "Bombeiros::salvar_matricula_publica/$1");
});

/*
 * COMPAT (temporário): redireciona as URLs da marca anterior para as novas.
 * Nenhum código novo gera essas URLs; este bloco existe apenas para não quebrar
 * favoritos/históricos remanescentes e PODE ser removido após a transição.
 */
$routes->get("siamesa_gerencial_plugin", static function () {
    $qs = (string) (service("request")->getServer("QUERY_STRING") ?? "");
    $qs = str_replace("siamesa_tab=", "gd_tab=", $qs);
    return redirect()->to(site_url("grupo_donato/operacional" . ($qs !== "" ? "?" . $qs : "")));
});
$routes->match(["get", "post"], "siamesa_gerencial_plugin/(:any)", static function ($path = "") {
    return redirect()->to(site_url("grupo_donato/operacional/" . $path));
});

$routes->group("grupo_donato/operacional", ["namespace" => "grupo_donato_gestao\Operacional\Controllers"], function ($routes) {
    $routes->get("", "Bombeiros::index");
    $routes->get("/", "Bombeiros::index");
    $routes->get("index", "Bombeiros::index");

    $routes->get("lista_responsaveis", "Bombeiros::lista_responsaveis");
    $routes->get("lista_pagamentos", "Bombeiros::lista_pagamentos");
    $routes->get("financeiro_resumo", "Bombeiros::financeiro_resumo");
    $routes->get("custos", "Bombeiros::custos");
    $routes->get("unidades", "Bombeiros::unidades");

    // GD Academy: a entrada continua no shell; o detalhe usa telas profundas.
    $routes->get("evento-novo", "Bombeiros::academy_event_new");
    $routes->get("evento/(:num)/categoria/(:num)/partida/(:num)/(:segment)", "Bombeiros::academy_match_section/$1/$2/$3/$4");
    $routes->get("evento/(:num)/categoria/(:num)/partida/(:num)", "Bombeiros::academy_match/$1/$2/$3");
    $routes->get("evento/(:num)/categoria/(:num)/avaliacao/(:num)", "Bombeiros::academy_evaluation/$1/$2/$3");
    $routes->get("evento/(:num)/categoria/(:num)/(:segment)", "Bombeiros::academy_category_section/$1/$2/$3");
    $routes->get("evento/(:num)/categoria/(:num)", "Bombeiros::academy_category/$1/$2");
    $routes->get("evento/(:num)/(:segment)", "Bombeiros::academy_event_section/$1/$2");
    $routes->get("evento/(:num)", "Bombeiros::academy_event/$1");

    // Compatibilidade: Eventos permanece acessível pela entrada operacional.
    $routes->post("eventos_list_data", "Bombeiros::eventos_list_data");
    $routes->post("evento_modal_form", "Bombeiros::evento_modal_form");
    $routes->post("save_event", "Bombeiros::save_event");
    $routes->post("save_event_category", "Bombeiros::save_event_category");
    $routes->post("save_event_match", "Bombeiros::save_event_match");
    $routes->post("save_event_match_score", "Bombeiros::save_event_match_score");
    $routes->post("save_event_staff", "Bombeiros::save_event_staff");
    $routes->post("academy_student_search", "Bombeiros::academy_student_search");
    $routes->post("add_event_participant", "Bombeiros::add_event_participant");
    $routes->post("update_event_participant", "Bombeiros::update_event_participant");
    $routes->post("save_event_confirmation", "Bombeiros::save_event_confirmation");
    $routes->post("event_charge", "Bombeiros::event_charge");
    $routes->post("event_payment", "Bombeiros::event_payment");
    $routes->post("event_financial_status", "Bombeiros::event_financial_status");
    $routes->post("event_finance_list_data", "Bombeiros::event_finance_list_data");
    $routes->post("event_charge_modal", "Bombeiros::event_charge_modal");
    $routes->post("event_payment_modal", "Bombeiros::event_payment_modal");
    $routes->post("event_reverse_payment", "Bombeiros::event_reverse_payment");
    $routes->get("event_payment_receipt/(:num)", "Bombeiros::event_payment_receipt/$1");
    $routes->post("save_event_evaluation", "Bombeiros::save_event_evaluation");
    $routes->post("save_event_stat", "Bombeiros::save_event_stat");
    $routes->post("save_event_checklist", "Bombeiros::save_event_checklist");
    $routes->post("toggle_event_checklist", "Bombeiros::toggle_event_checklist");
    $routes->post("cancel_event", "Bombeiros::cancel_event");
    $routes->post("finalize_event", "Bombeiros::finalize_event");
    $routes->post("event_family_account", "Bombeiros::event_family_account");
    $routes->post("student_sport_history", "Bombeiros::student_sport_history");
    $routes->post("student_sport_report", "Bombeiros::student_sport_report");

    $routes->post("trocar_unidade", "Bombeiros::trocar_unidade");

    $routes->post("alunos_list_data", "Bombeiros::alunos_list_data");
    $routes->post("alunos_por_turma", "Bombeiros::alunos_por_turma");
    $routes->post("responsaveis_list_data", "Bombeiros::responsaveis_list_data");
    $routes->post("unidades_list_data", "Bombeiros::unidades_list_data");
    $routes->post("pagamentos_list_data", "Bombeiros::pagamentos_list_data");
    $routes->post("pagamentos_mensais_resumo", "Bombeiros::pagamentos_mensais_resumo");
    $routes->post("inadimplencia_list_data", "Bombeiros::inadimplencia_list_data");
    $routes->post("custos_list_data", "Bombeiros::custos_list_data");
    $routes->post("custos_resumo", "Bombeiros::custos_resumo");

    $routes->post("aluno_modal_form", "Bombeiros::aluno_modal_form");
    $routes->post("responsavel_modal_form", "Bombeiros::responsavel_modal_form");
    $routes->post("unidade_modal_form", "Bombeiros::unidade_modal_form");
    $routes->post("custo_modal_form", "Bombeiros::custo_modal_form");
    $routes->post("baixa_pagamento_modal_form", "Bombeiros::baixa_pagamento_modal_form");
    $routes->post("comprovante_modal_form", "Bombeiros::comprovante_modal_form");
    $routes->post("importar_modal_form", "Bombeiros::importar_modal_form");

    $routes->post("save_aluno", "Bombeiros::save_aluno");
    $routes->post("salvar", "Bombeiros::save_aluno");
    $routes->post("save_responsavel", "Bombeiros::save_responsavel");
    $routes->post("salvar_responsavel", "Bombeiros::save_responsavel");
    $routes->post("save_unidade", "Bombeiros::save_unidade");
    $routes->post("salvar_unidade", "Bombeiros::save_unidade");
    $routes->post("save_custo", "Bombeiros::save_custo");
    $routes->post("salvar_custo", "Bombeiros::save_custo");

    $routes->post("delete_aluno", "Bombeiros::delete_aluno");
    $routes->post("deletar", "Bombeiros::delete_aluno");
    $routes->post("delete_responsavel", "Bombeiros::delete_responsavel");
    $routes->post("deletar_responsavel", "Bombeiros::delete_responsavel");
    $routes->post("delete_unidade", "Bombeiros::delete_unidade");
    $routes->post("deletar_unidade", "Bombeiros::delete_unidade");
    $routes->post("delete_custo", "Bombeiros::delete_custo");
    $routes->post("deletar_custo", "Bombeiros::delete_custo");

    $routes->post("lista_chamada", "Bombeiros::lista_chamada");
    $routes->post("salvar_presenca", "Bombeiros::salvar_presenca");
    $routes->post("baixar_pagamento", "Bombeiros::baixar_pagamento");
    $routes->post("marcar_pagamento_pendente", "Bombeiros::marcar_pagamento_pendente");
    $routes->post("gerar_mensalidades_periodo", "Bombeiros::gerar_mensalidades_periodo");
    $routes->post("criar_cobranca_mensal_aluno", "Bombeiros::criar_cobranca_mensal_aluno");
    $routes->post("toggle_pagamento_mensal", "Bombeiros::toggle_pagamento_mensal");
    $routes->post("importar_csv", "Bombeiros::importar_csv");
    $routes->post("importar_preview", "Bombeiros::importar_preview");
    $routes->post("confirmar_importacao", "Bombeiros::confirmar_importacao");
    $routes->get("importacao_relatorio", "Bombeiros::importacao_relatorio");

    $routes->post("cancelados_list_data", "Bombeiros::cancelados_list_data");
    $routes->post("concluidos_list_data", "Bombeiros::concluidos_list_data");
    $routes->post("reativar_aluno", "Bombeiros::reativar_aluno");
    $routes->post("materiais_list_data", "Bombeiros::materiais_list_data");
    $routes->post("atualizar_material", "Bombeiros::atualizar_material");

    $routes->get("leads_palestra", "Bombeiros::leads_palestra");
    $routes->post("leads_palestra_list_data", "Bombeiros::leads_palestra_list_data");
    $routes->post("lead_palestra_modal_form", "Bombeiros::lead_palestra_modal_form");
    $routes->post("save_lead_palestra", "Bombeiros::save_lead_palestra");
    $routes->post("delete_lead_palestra", "Bombeiros::delete_lead_palestra");
    $routes->post("converter_lead_em_aluno", "Bombeiros::converter_lead_em_aluno");

    $routes->post("buscar_dados_comprovante", "Bombeiros::buscar_dados_comprovante");
    $routes->post("gerar_comprovante", "Bombeiros::gerar_comprovante");
    $routes->get("foto_aluno/(:num)", "Bombeiros::foto_aluno/$1");
    $routes->get("baixar_exame_medico/(:num)", "Bombeiros::baixar_exame_medico/$1");
    $routes->get("baixar_comprovante/(:num)", "Bombeiros::baixar_comprovante/$1");
    $routes->get("baixar_comprovante_pdf/(:num)", "Bombeiros::baixar_comprovante_pdf/$1");
    $routes->get("visualizar_comprovante/(:num)", "Bombeiros::visualizar_comprovante/$1");

    $routes->get("matricula_publica/(:segment)", "Bombeiros::matricula_publica/$1");
    $routes->post("salvar_matricula_publica/(:segment)", "Bombeiros::salvar_matricula_publica/$1");
});
