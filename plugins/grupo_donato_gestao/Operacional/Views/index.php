<?php
$unidades_options = [];
foreach ($unidades_dropdown as $id => $text) {
    $unidades_options[] = ["id" => $id, "text" => $text];
}
$unidades_contexto_options = [];
foreach ($unidades_contexto_dropdown as $slug => $text) {
    $unidades_contexto_options[] = ["id" => $slug, "text" => $text];
}
$unidade_atual_slug = $unidade_atual->slug ?? "sao_bernardo_do_campo";
$dashboard_periodo = $dashboard_periodo ?? ["mes" => (int) date("m"), "ano" => (int) date("Y")];
$dashboard_mes = (int) ($dashboard_periodo["mes"] ?? date("m"));
$dashboard_ano = (int) ($dashboard_periodo["ano"] ?? date("Y"));
$dashboard_mes_options = [
    1 => "Janeiro",
    2 => "Fevereiro",
    3 => "Março",
    4 => "Abril",
    5 => "Maio",
    6 => "Junho",
    7 => "Julho",
    8 => "Agosto",
    9 => "Setembro",
    10 => "Outubro",
    11 => "Novembro",
    12 => "Dezembro"
];
$dashboard_ano_options = [];
for ($ano = (int) date("Y") - 3; $ano <= (int) date("Y") + 2; $ano++) {
    $dashboard_ano_options[$ano] = $ano;
}
$dashboard_ano_options[$dashboard_ano] = $dashboard_ano;
ksort($dashboard_ano_options);
$gd_tab_targets = [
    "dashboard" => "#bombeiros-tab-dashboard",
    "alunos" => "#bombeiros-tab-alunos",
    "cancelados" => "#bombeiros-tab-cancelados",
    "concluidos" => "#bombeiros-tab-concluidos",
    "responsaveis" => "#bombeiros-tab-responsaveis",
    "presenca" => "#bombeiros-tab-presenca",
    "pagamentos" => "#bombeiros-tab-pagamentos",
    "financeiro" => "#bombeiros-tab-financeiro",
    "custos" => "#bombeiros-tab-custos",
    "materiais" => "#bombeiros-tab-materiais",
    "leads" => "#bombeiros-tab-leads",
    "mensagens" => "#bombeiros-tab-mensagens",
    "unidades" => "#bombeiros-tab-unidades"
];
$gd_section_labels = [
    "dashboard" => "Dashboard",
    "alunos" => "Alunos",
    "cancelados" => "Cancelados",
    "concluidos" => "Concluídos",
    "responsaveis" => "Responsáveis",
    "presenca" => "Presença",
    "pagamentos" => "Pagamentos",
    "financeiro" => "Financeiro",
    "custos" => "Custos",
    "materiais" => "Materiais",
    "leads" => "Leads palestra",
    "mensagens" => "Mensagens",
    "unidades" => "Unidades"
];
$gd_active_tab = $gd_active_tab ?? "dashboard";
if (!isset($gd_tab_targets[$gd_active_tab])) {
    $gd_active_tab = "dashboard";
}
$gd_active_tab_target = $gd_tab_targets[$gd_active_tab];
$gd_pane_class = function ($tab) use ($gd_active_tab) {
    return "tab-pane fade" . ($tab === $gd_active_tab ? " show active" : "");
};
$dashboard_resultado = (float) ($dashboard_resumo["resultado_operacional"] ?? 0);
$dashboard_resultado_class = $dashboard_resultado > 0 ? "bg-success" : ($dashboard_resultado < 0 ? "bg-danger" : "bg-info");
$dashboard_resultado_icon = $dashboard_resultado >= 0 ? "trending-up" : "trending-down";
$dashboard_resultado_label = $dashboard_resultado > 0 ? "Lucro" : ($dashboard_resultado < 0 ? "Déficit" : "Equilíbrio");
?>

<style>
    .gd-mobile-section-nav {
        display: none;
    }

    .gd-mobile-filter-panel {
        display: none;
    }

    .gd-mobile-ready .dtr-details {
        width: 100%;
    }

    .gd-mobile-ready .dtr-details .dtr-title {
        display: block;
        font-weight: 600;
        margin-bottom: 2px;
    }

    .gd-mobile-ready .dtr-data,
    .gd-mobile-ready pre,
    .gd-mobile-ready code {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .gd-mobile-ready pre {
        white-space: pre-wrap;
    }

    .gd-mobile-ready .action-option,
    .gd-mobile-ready td.option a,
    .gd-mobile-ready td.option .btn {
        align-items: center;
        display: inline-flex;
        justify-content: center;
        min-height: 36px;
        min-width: 36px;
    }

    @media (max-width: 767.98px) {
        .gd-mobile-ready .page-title {
            padding: 14px 15px;
        }

        .gd-mobile-ready .page-title h1 {
            font-size: 20px;
            line-height: 1.25;
            margin-bottom: 10px;
        }

        .gd-mobile-ready .page-title h4 {
            font-size: 17px;
            line-height: 1.3;
        }

        .gd-mobile-ready .title-button-group {
            clear: both;
            display: flex;
            flex-direction: column;
            float: none !important;
            gap: 8px;
            width: 100%;
        }

        .gd-mobile-ready .title-button-group .btn,
        .gd-mobile-ready .title-button-group a.btn {
            justify-content: center;
            margin-left: 0 !important;
            width: 100%;
        }

        .gd-mobile-section-nav {
            display: block;
        }

        .gd-mobile-filter-panel {
            background: rgba(127, 127, 127, 0.06);
            border-bottom: 1px solid rgba(127, 127, 127, 0.18);
            display: block;
        }

        .gd-mobile-filter-panel .btn,
        .gd-mobile-filter-panel .form-control {
            width: 100%;
        }

        .gd-mobile-filter-actions {
            display: grid;
            gap: 8px;
            grid-template-columns: 1fr;
        }

        .gd-mobile-ready .p20 {
            padding: 15px !important;
        }

        .gd-mobile-ready .row > [class*="col-"] {
            margin-bottom: 10px;
        }

        .gd-mobile-ready .dashboard-icon-widget .card-body {
            align-items: center;
            display: flex;
            min-height: auto;
        }

        .gd-mobile-ready .dashboard-icon-widget .widget-details h1 {
            font-size: 20px;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .gd-mobile-ready .filter-section-flex-row,
        .gd-mobile-ready .filter-section-left,
        .gd-mobile-ready .filter-section-right {
            display: block !important;
            width: 100% !important;
        }

        .gd-mobile-ready .filter-item-box {
            margin: 0 0 8px !important;
            width: 100% !important;
        }

        .gd-mobile-ready .filter-item-box .btn,
        .gd-mobile-ready .filter-item-box .form-control,
        .gd-mobile-ready .filter-item-box .select2-container {
            width: 100% !important;
        }

        .gd-mobile-ready .dataTables_filter,
        .gd-mobile-ready .dt-search {
            text-align: left;
            width: 100%;
        }

        .gd-mobile-ready .dataTables_filter input,
        .gd-mobile-ready .dt-search input {
            margin-left: 0 !important;
            width: 100% !important;
        }

        .gd-mobile-ready .dataTables_info,
        .gd-mobile-ready .dataTables_length,
        .gd-mobile-ready .dataTables_paginate,
        .gd-mobile-ready .dt-buttons {
            float: none !important;
            margin-top: 8px;
            text-align: left;
            width: 100%;
        }

        .gd-mobile-ready table.dataTable > tbody > tr > td {
            overflow-wrap: anywhere;
            white-space: normal;
        }

        .gd-mobile-ready table.dataTable td.option {
            min-width: 44px;
            white-space: normal;
        }

        .gd-mobile-ready #bombeiros-pagamentos-table th.option,
        .gd-mobile-ready #bombeiros-pagamentos-table td.option {
            min-width: 128px;
            width: 128px !important;
        }

        .gd-mobile-ready #bombeiros-pagamentos-table td.option .btn {
            font-size: 12px;
            margin: 3px 0 0 !important;
            padding-left: 6px;
            padding-right: 6px;
            width: 100%;
        }

        .gd-mobile-ready #bombeiros-presenca-form thead {
            display: none;
        }

        .gd-mobile-ready #bombeiros-presenca-form tr {
            border-bottom: 1px solid rgba(127, 127, 127, 0.18);
            display: block;
            padding: 10px 0;
        }

        .gd-mobile-ready #bombeiros-presenca-form td,
        .gd-mobile-ready #bombeiros-presenca-form th {
            display: block;
            text-align: left !important;
            width: 100%;
        }

        .gd-mobile-ready #bombeiros-presenca-form td label {
            border: 1px solid rgba(127, 127, 127, 0.22);
            border-radius: 6px;
            display: block;
            margin: 8px 0 0 !important;
            padding: 8px 10px;
        }

        .gd-mobile-ready #bombeiros-carregar-chamada,
        .gd-mobile-ready #bombeiros-presenca-form button[type="submit"] {
            width: 100%;
        }

        #ajaxModal .modal-dialog {
            height: 100dvh;
            margin: 0;
            max-width: none;
            width: 100%;
        }

        #ajaxModal .modal-content {
            border-radius: 0;
            min-height: 100dvh;
        }

        #ajaxModal .modal-body {
            max-height: calc(100dvh - 120px);
            overflow-y: auto;
            padding: 15px;
        }

        #ajaxModal .modal-footer {
            background: var(--bs-body-bg, #fff);
            bottom: 0;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            position: sticky;
        }

        #ajaxModal .modal-footer .btn {
            flex: 1 1 120px;
        }

        #ajaxModal .btn {
            white-space: normal;
        }

        #ajaxModal .form-group .row > [class*="col-md-"],
        #ajaxModal .form-group .row > label[class*="col-md-"] {
            margin-bottom: 8px;
        }
    }
</style>

<div id="page-content" class="page-wrapper clearfix gd-mobile-ready gd-operacional-page">
    <div class="card">
        <div class="page-title clearfix">
            <h1>Grupo Donato — Operacional</h1>
            <div class="title-button-group skip-dropdown-migration">
                <?php
                echo modal_anchor(get_uri("grupo_donato/operacional/importar_modal_form"), "<i data-feather='upload' class='icon-16'></i> Importar", ["class" => "btn btn-default", "title" => "Importar planilha"]);
                echo modal_anchor(get_uri("grupo_donato/operacional/unidade_modal_form"), "<i data-feather='map-pin' class='icon-16'></i> Nova unidade", ["class" => "btn btn-default", "title" => "Nova unidade"]);
                echo modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> Novo aluno", ["class" => "btn btn-default", "title" => "Novo aluno"]);
                echo anchor(get_uri("matricula-online/" . $unidade_atual_slug), "<i data-feather='link' class='icon-16'></i> Link telemarketing", ["id" => "gd-link-matricula-publica", "class" => "btn btn-default", "target" => "_blank", "rel" => "noopener", "title" => "Abrir link público de matrícula"]);
                ?>
            </div>
        </div>

        <div class="p20 border-bottom">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label for="gd-unidade-contexto">Unidade</label>
                    <?php
                    echo form_dropdown("unidade_slug", $unidades_contexto_dropdown, $unidade_atual_slug, [
                        "id" => "gd-unidade-contexto",
                        "class" => "form-control"
                    ]);
                    ?>
                </div>
                <div class="col-md-7">
                    <div class="text-off">Contexto ativo: <strong id="gd-unidade-atual"><?php echo esc($unidade_atual->nome_unidade ?? "Sao Bernardo do Campo"); ?></strong></div>
                    <div class="text-off">slug: <code id="gd-unidade-slug"><?php echo esc($unidade_atual_slug); ?></code></div>
                </div>
            </div>
        </div>

        <div class="gd-mobile-section-nav p15 border-bottom">
            <label for="gd-mobile-section-selector">Seção</label>
            <select id="gd-mobile-section-selector" class="form-control">
                <?php foreach ($gd_section_labels as $tab => $label): ?>
                    <option value="<?php echo esc($gd_tab_targets[$tab], "attr"); ?>" <?php echo $tab === $gd_active_tab ? "selected" : ""; ?>><?php echo esc($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="tab-content">
            <div role="tabpanel" class="<?php echo $gd_pane_class("dashboard"); ?>" id="bombeiros-tab-dashboard">
                <div class="p20">
                    <div class="row align-items-end mb15">
                        <div class="col-md-3 col-sm-6">
                            <label for="gd-dashboard-mes">Mês</label>
                            <?php
                            echo form_dropdown("dashboard_mes", $dashboard_mes_options, $dashboard_mes, [
                                "id" => "gd-dashboard-mes",
                                "class" => "form-control"
                            ]);
                            ?>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label for="gd-dashboard-ano">Ano</label>
                            <?php
                            echo form_dropdown("dashboard_ano", $dashboard_ano_options, $dashboard_ano, [
                                "id" => "gd-dashboard-ano",
                                "class" => "form-control"
                            ]);
                            ?>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-primary"><i data-feather="users" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["alunos_ativos"]; ?></h1>
                                        <span>Alunos ativos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-secondary"><i data-feather="user-x" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["alunos_cancelados"]; ?></h1>
                                        <span>Cancelados</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-info"><i data-feather="award" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["alunos_concluidos"]; ?></h1>
                                        <span>Concluídos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-success"><i data-feather="check-circle" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo to_currency($dashboard_resumo["mensalidades_pagas"], "R$"); ?></h1>
                                        <span>Mensalidades pagas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-warning"><i data-feather="clock" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo to_currency($dashboard_resumo["mensalidades_pendentes"], "R$"); ?></h1>
                                        <span>Mensalidades pendentes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt15">
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-primary"><i data-feather="dollar-sign" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo to_currency($dashboard_resumo["faturamento_total"] ?? 0, "R$"); ?></h1>
                                        <span>Faturamento</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-danger"><i data-feather="trending-down" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo to_currency($dashboard_resumo["custos_total"] ?? 0, "R$"); ?></h1>
                                        <span>Custos</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon <?php echo $dashboard_resultado_class; ?>"><i data-feather="<?php echo $dashboard_resultado_icon; ?>" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo to_currency(abs($dashboard_resultado), "R$"); ?></h1>
                                        <span><?php echo $dashboard_resultado_label; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-info"><i data-feather="percent" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo number_format((float) ($dashboard_resumo["percentual_custos"] ?? 0), 1, ",", "."); ?>%</h1>
                                        <span>Custo/Faturamento</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt15">
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-info"><i data-feather="package" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["pendencia_uniforme"]; ?></h1>
                                        <span>Pendência uniforme</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-info"><i data-feather="book-open" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["pendencia_material_01"]; ?></h1>
                                        <span>Pendência material 01</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-info"><i data-feather="book" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["pendencia_material_02"]; ?></h1>
                                        <span>Pendência material 02</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-danger"><i data-feather="x-circle" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["faltas"]; ?></h1>
                                        <span>Faltas registradas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt15">
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-success"><i data-feather="check-square" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["presencas"]; ?></h1>
                                        <span>Presenças registradas</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-primary"><i data-feather="radio" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["leads_palestra"]; ?></h1>
                                        <span>Leads/palestra</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-success"><i data-feather="user-check" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["leads_matriculados"]; ?></h1>
                                        <span>Leads matriculados</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-warning"><i data-feather="percent" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo number_format((float) $dashboard_resumo["taxa_conversao_palestra"], 1, ",", "."); ?>%</h1>
                                        <span>Conversão palestra</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <div class="card dashboard-icon-widget">
                                <div class="card-body">
                                    <div class="widget-icon bg-danger"><i data-feather="alert-triangle" class="icon"></i></div>
                                    <div class="widget-details">
                                        <h1><?php echo (int) $dashboard_resumo["inadimplentes"]; ?></h1>
                                        <span>Inadimplentes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php
                    $qualidade_alertas = array_filter($qualidade_resumo, function ($total) {
                        return (int) $total > 0;
                    });
                    ?>
                    <div class="mt20 pt15 border-top">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="text-off me-2">
                                <i data-feather="info" class="icon-16"></i>
                                <strong>Obs. de qualidade dos dados</strong>
                            </div>
                            <?php if ($qualidade_alertas): ?>
                                <?php foreach ($qualidade_alertas as $label => $total): ?>
                                    <span class="badge bg-warning"><?php echo esc($label); ?>: <?php echo (int) $total; ?></span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="badge bg-success">Sem alertas</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("alunos"); ?>" id="bombeiros-tab-alunos">
                <div class="table-responsive">
                    <table id="bombeiros-alunos-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("cancelados"); ?>" id="bombeiros-tab-cancelados">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_cancelados'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("concluidos"); ?>" id="bombeiros-tab-concluidos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_concluidos'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("responsaveis"); ?>" id="bombeiros-tab-responsaveis">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_responsaveis'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("presenca"); ?>" id="bombeiros-tab-presenca">
                <div class="p20">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="bombeiros-chamada-data">Data da aula</label>
                                <?php
                                echo form_input([
                                    "id" => "bombeiros-chamada-data",
                                    "name" => "data",
                                    "type" => "date",
                                    "value" => date("Y-m-d"),
                                    "class" => "form-control"
                                ]);
                                ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="bombeiros-chamada-turma">Turma</label>
                                <?php
                                echo form_dropdown("turma", bombeiros_turmas_grouped(), "", ["id" => "bombeiros-chamada-turma", "class" => "form-control"]);
                                ?>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="button" id="bombeiros-carregar-chamada" class="btn btn-default d-block">
                                    <i data-feather="list" class="icon-16"></i> Carregar chamada
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="bombeiros-chamada-area" class="pt10">
                        <div class="text-off">Selecione a data e a turma para carregar a lista.</div>
                    </div>
                </div>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("pagamentos"); ?>" id="bombeiros-tab-pagamentos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_pagamentos'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("financeiro"); ?>" id="bombeiros-tab-financeiro">
                <div id="bombeiros-financeiro-pane">
                    <?php echo view('grupo_donato_gestao\Operacional\Views\financeiro_resumo', $financeiro_resumo); ?>
                </div>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("custos"); ?>" id="bombeiros-tab-custos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_custos'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("materiais"); ?>" id="bombeiros-tab-materiais">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_materiais'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("leads"); ?>" id="bombeiros-tab-leads">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_leads_palestra'); ?>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("mensagens"); ?>" id="bombeiros-tab-mensagens">
                <div class="p20">
                    <div class="row">
                        <div class="col-md-4">
                            <?php echo view('grupo_donato_gestao\Operacional\Views\mensagens_status', ["titulo" => "Templates", "disponivel" => $mensagens_contexto["templates"]["disponivel"] ?? false, "rows" => $mensagens_contexto["templates"]["rows"] ?? []]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo view('grupo_donato_gestao\Operacional\Views\mensagens_status', ["titulo" => "Conversas", "disponivel" => $mensagens_contexto["mensagens"]["disponivel"] ?? false, "rows" => $mensagens_contexto["mensagens"]["rows"] ?? []]); ?>
                        </div>
                        <div class="col-md-4">
                            <?php echo view('grupo_donato_gestao\Operacional\Views\mensagens_status', ["titulo" => "Histórico", "disponivel" => $mensagens_contexto["historico"]["disponivel"] ?? false, "rows" => $mensagens_contexto["historico"]["rows"] ?? []]); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div role="tabpanel" class="<?php echo $gd_pane_class("unidades"); ?>" id="bombeiros-tab-unidades">
                <?php echo view('grupo_donato_gestao\Operacional\Views\unidades'); ?>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        window.markBombeirosTableLoading = function (selector) {
            var $table = $(selector);
            if (!$table.length) {
                return;
            }

            var token = Date.now();
            $table.data("gdTableLoading", true);
            $table.data("gdTableLoadingToken", token);
            $table.off(".gdTableLoading").on("draw.dt.gdTableLoading error.dt.gdTableLoading", function () {
                $table.off(".gdTableLoading");
                $table.data("gdTableLoading", false);
                $table.data("gdTableLoaded", true);
            });

            setTimeout(function () {
                if ($table.data("gdTableLoading") && $table.data("gdTableLoadingToken") === token) {
                    $table.off(".gdTableLoading");
                    $table.data("gdTableLoading", false);
                }
            }, 15000);
        };

        window.reloadBombeirosTable = function (selector) {
            var $table = $(selector);
            if ($table.length && $.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
                if ($table.data("gdTableLoading")) {
                    return;
                }
                if (typeof $appFilterXhrRequest !== "undefined" && $appFilterXhrRequest !== "new") {
                    return;
                }

                if (window.markBombeirosTableLoading) {
                    window.markBombeirosTableLoading(selector);
                }

                $table.appTable({reload: true});
            }
        };

        var gdDefaultTabTarget = <?php echo json_encode($gd_active_tab_target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var gdTabTargets = <?php echo json_encode(array_values($gd_tab_targets), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        var recalcGdResponsiveTables = function () {
            if (!$.fn.dataTable) {
                return;
            }

            var visibleTables = $.fn.dataTable.tables({visible: true, api: true});
            visibleTables.columns.adjust();
            if (visibleTables.responsive && visibleTables.responsive.recalc) {
                visibleTables.responsive.recalc();
            }
        };

        var syncGdMobileSectionSelector = function (tabTarget) {
            $("#gd-mobile-section-selector").val(tabTarget);
        };

        var handleGdPaneShown = function (tabTarget) {
            if (tabTarget === "#bombeiros-tab-unidades") {
                if (window.initBombeirosUnidadesTable) {
                    window.initBombeirosUnidadesTable();
                }
                window.reloadBombeirosTable("#bombeiros-unidades-table");
            }
            if (tabTarget === "#bombeiros-tab-custos") {
                if (window.initBombeirosCustosTable) {
                    window.initBombeirosCustosTable();
                }
                window.reloadBombeirosTable("#bombeiros-custos-table");
            }
            if (tabTarget === "#bombeiros-tab-pagamentos") {
                if (window.initBombeirosPagamentosTable) {
                    window.initBombeirosPagamentosTable();
                }
                window.reloadBombeirosTable("#bombeiros-pagamentos-table");
            }
            recalcGdResponsiveTables();
            syncGdMobileSectionSelector(tabTarget);
            feather.replace();
        };

        var activateGdPane = function (tabTarget) {
            if (!tabTarget || !$(tabTarget).length) {
                tabTarget = "#bombeiros-tab-dashboard";
            }

            $(gdTabTargets.join(",")).removeClass("show active");
            $(tabTarget).addClass("show active");
            handleGdPaneShown(tabTarget);
        };

        $("#gd-mobile-section-selector").on("change", function () {
            var tabTarget = $(this).val();
            activateGdPane(tabTarget);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, "", tabTarget);
            }
        });

        var restoreGdActiveTab = function () {
            var tabTarget = gdDefaultTabTarget || "#bombeiros-tab-dashboard";
            var storedTabTarget = "";
            try {
                storedTabTarget = sessionStorage.getItem("gdGerencialActiveTab") || "";
                sessionStorage.removeItem("gdGerencialActiveTab");
            } catch (e) {
                storedTabTarget = "";
            }

            var hasTabQuery = new URLSearchParams(window.location.search).has("gd_tab");
            if (!hasTabQuery && storedTabTarget) {
                tabTarget = storedTabTarget;
            }

            if (window.location.hash && gdTabTargets.indexOf(window.location.hash) !== -1) {
                tabTarget = window.location.hash;
            }

            activateGdPane(tabTarget);
        };

        window.hardReloadGdOperational = function (delay) {
            if (window.gdHardReloadPending) {
                return;
            }

            window.gdHardReloadPending = true;
            var activePaneId = $(".tab-content > .tab-pane.active").attr("id");
            var activeTab = activePaneId ? "#" + activePaneId : "#bombeiros-tab-dashboard";
            try {
                sessionStorage.setItem("gdGerencialActiveTab", activeTab);
            } catch (e) {
            }

            setTimeout(function () {
                window.location.reload();
            }, delay === undefined ? 600 : delay);
        };

        restoreGdActiveTab();

        window.bombeirosUnidadesOptions = <?php echo json_encode($unidades_options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        window.gdUnidadesContextoOptions = <?php echo json_encode($unidades_contexto_options, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        $("#gd-dashboard-mes, #gd-dashboard-ano").on("change", function () {
            var mes = $("#gd-dashboard-mes").val();
            var ano = $("#gd-dashboard-ano").val();
            if (!mes || !ano) {
                return;
            }

            try {
                sessionStorage.setItem("gdGerencialActiveTab", "#bombeiros-tab-dashboard");
            } catch (e) {
            }

            var url = new URL(window.location.href);
            url.searchParams.set("gd_tab", "dashboard");
            url.searchParams.set("dashboard_mes", mes);
            url.searchParams.set("dashboard_ano", ano);
            window.location.href = url.toString();
        });

        $("#gd-unidade-contexto").on("change", function () {
            var slug = $(this).val();
            if (!slug) {
                return;
            }

            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/trocar_unidade"); ?>",
                type: "POST",
                dataType: "json",
                data: {unidade_slug: slug},
                success: function (result) {
                    if (!result.success) {
                        appAlert.error(result.message);
                        return;
                    }

                    window.hardReloadGdOperational(0);
                }
            });
        });

        window.refreshBombeirosUnidadeFilter = function (option) {
            if (!option || !option.id) {
                return;
            }

            option.id = String(option.id);
            var options = window.bombeirosUnidadesOptions || [];
            var found = false;

            options = $.grep(options, function (item) {
                if (String(item.id) === option.id) {
                    found = true;
                    return option.status === "Ativo";
                }
                return true;
            });

            if (option.status === "Ativo") {
                if (found) {
                    $.each(options, function (index, item) {
                        if (String(item.id) === option.id) {
                            item.text = option.text;
                        }
                    });
                } else {
                    options.push({id: option.id, text: option.text});
                }
            }

            var blankOptions = $.grep(options, function (item) {
                return !item.id;
            });
            var unidadeOptions = $.grep(options, function (item) {
                return item.id;
            }).sort(function (a, b) {
                return String(a.text).localeCompare(String(b.text));
            });
            options = blankOptions.concat(unidadeOptions);
            window.bombeirosUnidadesOptions = options;

            var tableSettings = window.InstanceCollection ? window.InstanceCollection["bombeiros-alunos-table"] : null;
            if (tableSettings && tableSettings.filterDropdown) {
                $.each(tableSettings.filterDropdown, function (index, dropdown) {
                    if (dropdown.name === "unidade_id") {
                        dropdown.options = options;
                    }
                });
            }

            var $dropdown = $("#bombeiros-alunos-table_wrapper").find("[name='unidade_id']");
            if (!$dropdown.length || !$.fn.appDropdown) {
                return;
            }

            var currentValue = $dropdown.val() || "";
            if (option.status !== "Ativo" && currentValue === option.id) {
                currentValue = "";
                if (tableSettings) {
                    tableSettings.filterParams.unidade_id = "";
                }
            }

            if ($dropdown.data("select2")) {
                $dropdown.select2("destroy");
            }

            $dropdown.val(currentValue);
            $dropdown.appDropdown({
                list_data: options,
                onChangeCallback: function (value) {
                    var settings = window.InstanceCollection ? window.InstanceCollection["bombeiros-alunos-table"] : null;
                    if (settings) {
                        settings.filterParams.unidade_id = value;
                    }
                    window.reloadBombeirosTable("#bombeiros-alunos-table");
                }
            });

            if ($dropdown.data("select2")) {
                $dropdown.select2("val", currentValue);
            }
        };

        window.reloadBombeirosFinanceiro = function () {
            var $pane = $("#bombeiros-financeiro-pane");
            if (!$pane.length) {
                return;
            }

            appLoader.show({container: "#bombeiros-financeiro-pane"});
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/financeiro_resumo"); ?>",
                type: "GET",
                success: function (html) {
                    $pane.html(html);
                    appLoader.hide();
                    feather.replace();
                },
                error: function () {
                    appLoader.hide();
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });
        };

        if (!$.fn.DataTable.isDataTable("#bombeiros-alunos-table")) {
            $("#bombeiros-alunos-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/alunos_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all"},
                    {title: "Unidade"},
                    {title: "Responsável"},
                    {title: "WhatsApp", "class": "w140"},
                    {title: "Turma", "class": "w120"},
                    {title: "Camisa", "class": "w80"},
                    {title: "Mensalidade", "class": "text-right w120"},
                    {title: "Origem", "class": "text-center w120"},
                    {title: "Status", "class": "text-center w100"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
            });
        }

        window.reloadGdOperationalTables = function () {
            [
                "#bombeiros-alunos-table",
                "#bombeiros-cancelados-table",
                "#bombeiros-concluidos-table",
                "#bombeiros-responsaveis-table",
                "#bombeiros-pagamentos-table",
                "#bombeiros-inadimplencia-table",
                "#bombeiros-custos-table",
                "#bombeiros-materiais-table",
                "#bombeiros-leads-palestra-table"
            ].forEach(function (selector) {
                if (window.reloadBombeirosTable) {
                    reloadBombeirosTable(selector);
                }
            });
            if (window.reloadBombeirosFinanceiro) {
                reloadBombeirosFinanceiro();
            }
            if (window.reloadBombeirosPagamentosResumo) {
                reloadBombeirosPagamentosResumo();
            }
            if (window.hardReloadGdOperational) {
                hardReloadGdOperational();
            }
        };

        $("#bombeiros-carregar-chamada").on("click", function () {
            var data = $("#bombeiros-chamada-data").val();
            var turma = $("#bombeiros-chamada-turma").val();

            if (!data || !turma) {
                appAlert.error("Informe a data e a turma.");
                return;
            }

            appLoader.show({container: "#bombeiros-chamada-area"});
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/lista_chamada"); ?>",
                type: "POST",
                data: {data: data, turma: turma},
                success: function (html) {
                    $("#bombeiros-chamada-area").html(html);
                    appLoader.hide();
                    feather.replace();
                },
                error: function () {
                    appLoader.hide();
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });
        });

        $('a[data-bs-toggle="tab"]').on("shown.bs.tab", function (event) {
            if ($(event.target).attr("href") === "#bombeiros-tab-unidades") {
                if (window.initBombeirosUnidadesTable) {
                    window.initBombeirosUnidadesTable();
                }
                window.reloadBombeirosTable("#bombeiros-unidades-table");
            }
            if ($(event.target).attr("href") === "#bombeiros-tab-custos") {
                if (window.initBombeirosCustosTable) {
                    window.initBombeirosCustosTable();
                }
                window.reloadBombeirosTable("#bombeiros-custos-table");
            }
            recalcGdResponsiveTables();
            feather.replace();
        });
    });
</script>
