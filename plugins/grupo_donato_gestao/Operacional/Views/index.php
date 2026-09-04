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
$gd_all_tab_targets = [
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
    "unidades" => "#bombeiros-tab-unidades",
    "eventos" => "#bombeiros-tab-eventos"
];
$gd_all_section_labels = [
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
    "unidades" => "Unidades",
    "eventos" => "Eventos"
];
$gd_allowed_sections = isset($gd_allowed_sections) && is_array($gd_allowed_sections) ? $gd_allowed_sections : array_keys($gd_all_tab_targets);
$gd_allowed_lookup = array_flip($gd_allowed_sections);
$gd_can_access_section = function ($section) use ($gd_allowed_lookup) {
    return isset($gd_allowed_lookup[$section]);
};
$gd_tab_targets = array_intersect_key($gd_all_tab_targets, $gd_allowed_lookup);
$gd_section_labels = array_intersect_key($gd_all_section_labels, $gd_allowed_lookup);
$gd_active_tab = $gd_active_tab ?? "dashboard";
if (!isset($gd_tab_targets[$gd_active_tab])) {
    $gd_active_tab = array_key_first($gd_tab_targets) ?: "alunos";
}
$gd_active_tab_target = $gd_tab_targets[$gd_active_tab] ?? "#bombeiros-tab-alunos";
$gd_can_render_tab = function ($tab) use ($gd_tab_targets) {
    return isset($gd_tab_targets[$tab]);
};
$gd_pane_class = function ($tab) use ($gd_active_tab) {
    return "tab-pane fade" . ($tab === $gd_active_tab ? " show active" : "");
};
$dashboard_resumo = $dashboard_resumo ?? [];
$qualidade_resumo = $qualidade_resumo ?? [];
$financeiro_resumo = $financeiro_resumo ?? [];
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

    .gd-alunos-view-toolbar {
        align-items: center;
        background: var(--gd-surface, #082a52) !important;
        border-color: var(--gd-border, #244d78) !important;
        color: var(--gd-text, #fff);
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: space-between;
    }

    .gd-alunos-view-toolbar h4 {
        color: var(--gd-text, #fff);
        font-weight: 600;
        margin: 0 0 3px;
    }

    .gd-alunos-view-toolbar p {
        color: var(--gd-muted, #b7c5d8) !important;
        margin: 0;
    }

    .gd-alunos-view-switch {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .gd-alunos-class-list {
        background: var(--gd-bg, #03182f);
        color: var(--gd-text, #fff);
        padding: 20px;
    }

    .gd-alunos-class-summary {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: space-between;
        margin-bottom: 15px;
    }

    .gd-alunos-class-summary p {
        margin: 0;
    }

    .gd-alunos-class-card {
        background: var(--gd-surface, #082a52);
        border: 1px solid var(--gd-border, #244d78);
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.22);
        margin-bottom: 15px;
        overflow: hidden;
    }

    .gd-alunos-class-card:last-child {
        margin-bottom: 0;
    }

    .gd-alunos-class-heading {
        align-items: center;
        background: var(--gd-surface-3, #0e3a6e);
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: space-between;
        padding: 13px 15px;
    }

    .gd-alunos-class-heading h3 {
        color: var(--gd-text, #fff);
        font-size: 16px;
        font-weight: 600;
        margin: 0;
    }

    .gd-alunos-class-card .table {
        background: var(--gd-surface, #082a52) !important;
        margin-bottom: 0;
        min-width: 960px;
    }

    .gd-alunos-class-card .table th,
    .gd-alunos-class-card .table td {
        border-color: var(--gd-border, #244d78) !important;
        color: var(--gd-text, #fff) !important;
        padding: 11px 9px;
        vertical-align: middle;
    }

    .gd-alunos-class-card .table th {
        background: var(--gd-surface-2, #0b315f) !important;
        color: var(--gd-muted, #b7c5d8) !important;
        font-size: 12px;
        font-weight: 700;
        text-transform: none;
        white-space: nowrap;
    }

    .gd-alunos-class-card .table tbody tr:hover td {
        background: var(--gd-surface-3, #0e3a6e) !important;
    }

    .gd-alunos-class-table th:nth-child(1) { width: 24%; }
    .gd-alunos-class-table th:nth-child(2) { width: 11%; }
    .gd-alunos-class-table th:nth-child(3) { width: 22%; }
    .gd-alunos-class-table th:nth-child(4) { width: 17%; }
    .gd-alunos-class-table th:nth-child(5) { width: 12%; }
    .gd-alunos-class-table th:nth-child(6) { width: 12%; }

    .gd-alunos-sort-button {
        align-items: center;
        background: transparent;
        border: 0;
        color: inherit;
        cursor: pointer;
        display: inline-flex;
        font: inherit;
        gap: 5px;
        justify-content: flex-start;
        padding: 0;
        text-align: inherit;
        width: 100%;
    }

    .gd-alunos-class-table th.text-right .gd-alunos-sort-button { justify-content: flex-end; }
    .gd-alunos-class-table th.text-center .gd-alunos-sort-button { justify-content: center; }

    .gd-alunos-sort-button:hover,
    .gd-alunos-sort-button:focus {
        color: var(--gd-gold-hover, #e4bc55);
        outline: none;
    }

    .gd-alunos-sort-icon {
        color: var(--gd-muted, #b7c5d8);
        font-size: 14px;
        line-height: 1;
    }

    .gd-alunos-class-table th[aria-sort="ascending"] .gd-alunos-sort-icon,
    .gd-alunos-class-table th[aria-sort="descending"] .gd-alunos-sort-icon {
        color: var(--gd-gold-hover, #e4bc55);
        font-weight: 700;
    }

    .gd-absence-indicator {
        align-items: center;
        display: inline-flex;
        gap: 6px;
        justify-content: center;
        min-height: 24px;
        white-space: nowrap;
    }

    .gd-absence-bars {
        align-items: flex-end;
        display: inline-flex;
        gap: 2px;
        height: 17px;
    }

    .gd-absence-bar {
        background: var(--gd-border, #244d78);
        border-radius: 2px;
        display: block;
        width: 4px;
    }

    .gd-absence-bar:nth-child(1) { height: 6px; }
    .gd-absence-bar:nth-child(2) { height: 9px; }
    .gd-absence-bar:nth-child(3) { height: 13px; }
    .gd-absence-bar:nth-child(4) { height: 17px; }

    .gd-absence-clear .gd-absence-bar.is-filled { background: var(--gd-success, #16a34a); }
    .gd-absence-warning .gd-absence-bar.is-filled { background: var(--gd-warning, #f59e0b); }
    .gd-absence-critical .gd-absence-bar.is-filled { background: var(--gd-danger, #ef4444); }

    .gd-absence-count {
        color: var(--gd-text, #fff);
        font-size: 12px;
        line-height: 1;
        min-width: 20px;
        text-align: center;
    }

    .gd-absence-critical .gd-absence-count {
        color: #ff8795;
    }

    .gd-absence-contact {
        background: rgba(239, 68, 68, .2);
        border-radius: 999px;
        color: #ff8795;
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        padding: 4px 6px;
    }

    .gd-alunos-class-card .gd-alunos-class-actions {
        white-space: nowrap;
        width: 100px;
    }

    .gd-alunos-class-actions a {
        margin: 2px;
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

        .gd-mobile-ready .gd-alunos-view-toolbar {
            align-items: flex-start;
            padding: 15px !important;
        }

        .gd-mobile-ready .gd-alunos-view-switch {
            width: 100%;
        }

        .gd-mobile-ready .gd-alunos-view-switch .btn {
            flex: 1 1 140px;
        }

        .gd-mobile-ready .gd-alunos-class-list {
            padding: 12px;
        }

        .gd-mobile-ready .gd-alunos-class-heading {
            padding: 12px;
        }

        .gd-mobile-ready .gd-alunos-class-card .table {
            min-width: 960px;
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
            <h1><?php echo esc($gd_section_labels[$gd_active_tab] ?? "Dashboard"); ?></h1>
            <div class="title-button-group skip-dropdown-migration">
                <?php
                if ($gd_can_access_section("importar")) {
                    echo modal_anchor(get_uri("grupo_donato/operacional/importar_modal_form"), "<i data-feather='upload' class='icon-16'></i> Importar", ["class" => "btn btn-default", "title" => "Importar planilha"]);
                }
                if ($gd_can_access_section("unidades")) {
                    echo modal_anchor(get_uri("grupo_donato/operacional/unidade_modal_form"), "<i data-feather='map-pin' class='icon-16'></i> Nova unidade", ["class" => "btn btn-default", "title" => "Nova unidade"]);
                }
                if ($gd_can_access_section("alunos")) {
                    echo modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> Novo aluno", ["class" => "btn btn-default", "title" => "Novo aluno"]);
                    echo anchor(get_uri("matricula-online/" . $unidade_atual_slug), "<i data-feather='link' class='icon-16'></i> Link telemarketing", ["id" => "gd-link-matricula-publica", "class" => "btn btn-default", "target" => "_blank", "rel" => "noopener", "title" => "Abrir link público de matrícula"]);
                }
                if ($gd_can_access_section("eventos") && !empty($event_can_manage)) {
                    echo modal_anchor(get_uri("grupo_donato/operacional/evento_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> Novo evento", ["class" => "btn btn-primary", "title" => "Novo evento"]);
                }
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
            <?php if ($gd_can_render_tab("dashboard")): ?>
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
            <?php endif; ?>

            <?php if ($gd_can_render_tab("alunos")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("alunos"); ?>" id="bombeiros-tab-alunos">
                <div class="gd-alunos-view-toolbar p20 border-bottom">
                    <div>
                        <h4>Visualização dos alunos</h4>
                        <p class="text-off">Escolha entre a lista completa e a organização por turma.</p>
                    </div>
                    <div class="gd-alunos-view-switch" role="group" aria-label="Visualização dos alunos">
                        <button type="button" class="btn btn-primary" data-gd-alunos-view="todos" aria-pressed="true">Todos os alunos</button>
                        <button type="button" class="btn btn-default" data-gd-alunos-view="turmas" aria-pressed="false">Por turma</button>
                    </div>
                </div>

                <div id="gd-alunos-view-todos">
                    <div class="table-responsive">
                        <table id="bombeiros-alunos-table" class="display" cellspacing="0" width="100%"></table>
                    </div>
                </div>

                <div id="gd-alunos-view-turmas" class="d-none">
                    <div id="gd-alunos-por-turma-content" aria-live="polite"></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("cancelados")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("cancelados"); ?>" id="bombeiros-tab-cancelados">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_cancelados'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("concluidos")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("concluidos"); ?>" id="bombeiros-tab-concluidos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_concluidos'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("responsaveis")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("responsaveis"); ?>" id="bombeiros-tab-responsaveis">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_responsaveis'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("presenca")): ?>
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
            <?php endif; ?>

            <?php if ($gd_can_render_tab("pagamentos")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("pagamentos"); ?>" id="bombeiros-tab-pagamentos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_pagamentos', ["dashboard_periodo" => $dashboard_periodo]); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("financeiro")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("financeiro"); ?>" id="bombeiros-tab-financeiro">
                <div id="bombeiros-financeiro-pane">
                    <?php echo view('grupo_donato_gestao\Operacional\Views\financeiro_resumo', $financeiro_resumo); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("custos")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("custos"); ?>" id="bombeiros-tab-custos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_custos', ["custos_resumo" => $custos_resumo ?? [], "dashboard_periodo" => $dashboard_periodo]); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("materiais")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("materiais"); ?>" id="bombeiros-tab-materiais">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_materiais'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("leads")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("leads"); ?>" id="bombeiros-tab-leads">
                <?php echo view('grupo_donato_gestao\Operacional\Views\lista_leads_palestra'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("unidades")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("unidades"); ?>" id="bombeiros-tab-unidades">
                <?php echo view('grupo_donato_gestao\Operacional\Views\unidades'); ?>
            </div>
            <?php endif; ?>

            <?php if ($gd_can_render_tab("eventos")): ?>
            <div role="tabpanel" class="<?php echo $gd_pane_class("eventos"); ?>" id="bombeiros-tab-eventos">
                <?php echo view('grupo_donato_gestao\Operacional\Views\eventos', [
                    "dashboard" => $eventos_dashboard ?? [],
                    "workspace" => $event_workspace ?? null,
                    "academy_responsibles" => $academy_responsibles ?? [],
                    "academy_event_categories" => $academy_event_categories ?? [],
                    "can_manage" => !empty($event_can_manage),
                    "can_lineup" => !empty($event_can_lineup),
                    "can_evaluate" => !empty($event_can_evaluate),
                    "can_finance" => !empty($event_can_finance),
                    "can_finalize" => !empty($event_can_finalize),
                ]); ?>
            </div>
            <?php endif; ?>
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
                var custosInitialized = false;
                if (window.initBombeirosCustosTable) {
                    custosInitialized = window.initBombeirosCustosTable();
                }
                if (!custosInitialized) {
                    window.reloadBombeirosTable("#bombeiros-custos-table");
                }
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
            if (!tabTarget || gdTabTargets.indexOf(tabTarget) === -1 || !$(tabTarget).length) {
                tabTarget = gdDefaultTabTarget;
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
            var tabTarget = gdDefaultTabTarget;
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
            var activeTab = activePaneId ? "#" + activePaneId : gdDefaultTabTarget;
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

        var gdAlunosViewStorageKey = "gdAlunosViewMode";
        var gdAlunosPorTurmaLoaded = false;
        var gdAlunosPorTurmaLoading = false;

        var loadGdAlunosPorTurma = function () {
            var $content = $("#gd-alunos-por-turma-content");
            if (!$content.length || gdAlunosPorTurmaLoaded || gdAlunosPorTurmaLoading) {
                return;
            }

            gdAlunosPorTurmaLoading = true;
            appLoader.show({container: "#gd-alunos-por-turma-content"});
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/alunos_por_turma"); ?>",
                type: "POST",
                success: function (html) {
                    $content.html(html);
                    gdAlunosPorTurmaLoaded = true;
                    gdAlunosPorTurmaLoading = false;
                    appLoader.hide();
                    feather.replace();
                },
                error: function () {
                    gdAlunosPorTurmaLoading = false;
                    appLoader.hide();
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });
        };

        var gdAlunosSortValue = function ($row, key) {
            var columnIndexes = {
                aluno: 0,
                matricula: 1,
                responsavel: 2,
                whatsapp: 3,
                faltas: 4,
                mensalidade: 5
            };
            var $cell = $row.children("td").eq(columnIndexes[key]);

            if (key === "faltas") {
                return parseInt($cell.find("[data-absence-count]").attr("data-absence-count") || "0", 10) || 0;
            }

            if (key === "mensalidade") {
                var money = $cell.text().replace(/[^0-9,-]/g, "").replace(/\./g, "").replace(",", ".");
                return parseFloat(money) || 0;
            }

            return $.trim($cell.text()).toLocaleLowerCase();
        };

        var sortGdAlunosClassTable = function ($table, $heading, key) {
            var direction = $heading.attr("aria-sort") === "ascending" ? "descending" : "ascending";
            var rows = $table.find("tbody > tr").get();

            rows.sort(function (a, b) {
                var aValue = gdAlunosSortValue($(a), key);
                var bValue = gdAlunosSortValue($(b), key);
                var comparison;

                if (typeof aValue === "number" && typeof bValue === "number") {
                    comparison = aValue - bValue;
                } else {
                    comparison = String(aValue).localeCompare(String(bValue), "pt-BR", {
                        numeric: true,
                        sensitivity: "base"
                    });
                }

                return direction === "ascending" ? comparison : -comparison;
            });

            $table.find("thead th").attr("aria-sort", "none").find(".gd-alunos-sort-icon").text("↕");
            $heading.attr("aria-sort", direction).find(".gd-alunos-sort-icon").text(direction === "ascending" ? "↑" : "↓");
            $table.find("tbody").append(rows);
        };

        $("body").off("click.gdAlunosSort", ".gd-alunos-sort-button").on("click.gdAlunosSort", ".gd-alunos-sort-button", function () {
            var $button = $(this);
            sortGdAlunosClassTable($button.closest("table"), $button.closest("th"), $button.attr("data-gd-alunos-sort-key"));
        });

        var setGdAlunosView = function (view) {
            var porTurma = view === "turmas";
            $("[data-gd-alunos-view]").each(function () {
                var isActive = $(this).attr("data-gd-alunos-view") === (porTurma ? "turmas" : "todos");
                $(this).toggleClass("btn-primary", isActive).toggleClass("btn-default", !isActive);
                $(this).attr("aria-pressed", isActive ? "true" : "false");
            });
            $("#gd-alunos-view-todos").toggleClass("d-none", porTurma);
            $("#gd-alunos-view-turmas").toggleClass("d-none", !porTurma);

            try {
                localStorage.setItem(gdAlunosViewStorageKey, porTurma ? "turmas" : "todos");
            } catch (e) {
            }

            if (porTurma) {
                loadGdAlunosPorTurma();
            } else {
                recalcGdResponsiveTables();
            }
        };

        $("[data-gd-alunos-view]").on("click", function () {
            setGdAlunosView($(this).attr("data-gd-alunos-view"));
        });

        $("body").off("click", "#gd-alunos-por-turma-content .gd-aluno-por-turma-delete").on("click", "#gd-alunos-por-turma-content .gd-aluno-por-turma-delete", function () {
            var $link = $(this);
            $link.appConfirmation({
                title: "Excluir este aluno?",
                btnConfirmLabel: "Excluir",
                btnCancelLabel: "Cancelar",
                onConfirm: function () {
                    appAjaxRequest({
                        url: $link.attr("data-action-url"),
                        type: "POST",
                        dataType: "json",
                        data: {id: $link.attr("data-id")},
                        success: function (result) {
                            if (result.success) {
                                appAlert.success(result.message);
                                if (window.reloadGdOperationalTables) {
                                    reloadGdOperationalTables();
                                }
                            } else {
                                appAlert.error(result.message);
                            }
                        }
                    });
                }
            });

            return false;
        });

        var gdAlunosInitialView = "todos";
        try {
            gdAlunosInitialView = localStorage.getItem(gdAlunosViewStorageKey) || "todos";
        } catch (e) {
        }
        setGdAlunosView(gdAlunosInitialView);

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
                data: {
                    dashboard_mes: $("#gd-dashboard-mes").val() || "<?php echo $dashboard_mes; ?>",
                    dashboard_ano: $("#gd-dashboard-ano").val() || "<?php echo $dashboard_ano; ?>"
                },
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

        if ($("#bombeiros-alunos-table").length && !$.fn.DataTable.isDataTable("#bombeiros-alunos-table")) {
            $("#bombeiros-alunos-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/alunos_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all"},
                    {title: "Responsável"},
                    {title: "WhatsApp", "class": "w140"},
                    {title: "Turma", "class": "w120"},
                    {
                        title: "Faltas este mês",
                        "class": "text-center w100",
                        type: "num",
                        render: function (data, type) {
                            if (type === "sort" || type === "type") {
                                var count = $("<div>").html(data).find("[data-absence-count]").attr("data-absence-count");
                                return parseInt(count || "0", 10) || 0;
                            }
                            return data;
                        }
                    },
                    {title: "Mensalidade", "class": "text-right w120"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6]
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
                var custosInitialized = false;
                if (window.initBombeirosCustosTable) {
                    custosInitialized = window.initBombeirosCustosTable();
                }
                if (!custosInitialized) {
                    window.reloadBombeirosTable("#bombeiros-custos-table");
                }
            }
            recalcGdResponsiveTables();
            feather.replace();
        });
    });
</script>
