<?php

/** Dashboard executivo: total da empresa + uma leitura igual para cada produto. */
$active_unit_name = $active_unit && isset($active_unit->name) && $active_unit->name
    ? esc((string) $active_unit->name)
    : "Unidade não selecionada";
$money = static fn ($value): string => "R$ " . number_format((float) $value, 2, ",", ".");
$number = static fn ($value): string => number_format((float) $value, 0, ",", ".");
$catalog = $catalog ?? ["active_products" => 0, "active_categories" => 0, "active_variants" => 0, "priced_products" => 0];
$finance_totals = $finance_totals ?? ["charges" => 0, "paid_count" => 0, "open_count" => 0, "overdue_count" => 0, "billed_amount" => 0, "paid_amount" => 0, "balance_amount" => 0, "overdue_amount" => 0];
$source_labels = [
    "enrollment" => "GD Academy",
    "court_rental" => "Locações de quadras",
    "barbecue_rental" => "Churrasqueiras",
    "manual" => "Outras cobranças",
    "other" => "Outras cobranças",
];
$academy_url = get_uri("grupo_donato/operacional?gd_tab=alunos");
$academy_student_modal_url = get_uri("grupo_donato/operacional/aluno_modal_form");

$product_cards = [
    [
        "name" => "GD Academy",
        "description" => "Escola, turmas e presença",
        "icon" => "book-open",
        "class" => "gd-product-academy",
        "finance" => $finance_by_source["enrollment"] ?? [],
        "stats" => [
            ["label" => "Alunos ativos", "value" => $academy["active_students"] ?? 0],
            ["label" => "Turmas ativas", "value" => $academy["active_classes"] ?? 0],
            ["label" => "Aulas hoje", "value" => $academy["classes_today"] ?? 0],
            ["label" => "Presenças lançadas", "value" => $academy["attendance_today"] ?? 0],
        ],
        "href" => $academy_url,
        "can_link" => $can_students || $can_classes,
    ],
    [
        "name" => "Locações de quadras",
        "description" => "Avulsos, mensalistas e agenda",
        "icon" => "grid",
        "class" => "gd-product-courts",
        "finance" => $finance_by_source["court_rental"] ?? [],
        "stats" => [
            ["label" => "Mensalistas ativos", "value" => $courts["recurring"] ?? 0],
            ["label" => "Avulsos ativos", "value" => $courts["single"] ?? 0],
            ["label" => "Reservas hoje", "value" => $courts["bookings_today"] ?? 0],
            ["label" => "Próximos 7 dias", "value" => $courts["next_7_days"] ?? 0],
        ],
        "href" => get_uri($can_court_rentals ? "grupo_donato/court-rentals" : "grupo_donato/bookings"),
        "can_link" => $can_court_rentals || $can_bookings,
    ],
    [
        "name" => "Churrasqueiras",
        "description" => "Locações e ocupação dos espaços",
        "icon" => "sun",
        "class" => "gd-product-barbecues",
        "finance" => $finance_by_source["barbecue_rental"] ?? [],
        "stats" => [
            ["label" => "Mensalistas ativos", "value" => $barbecues["recurring"] ?? 0],
            ["label" => "Avulsos ativos", "value" => $barbecues["single"] ?? 0],
            ["label" => "Reservas hoje", "value" => $barbecues["bookings_today"] ?? 0],
            ["label" => "Próximos 7 dias", "value" => $barbecues["next_7_days"] ?? 0],
        ],
        "href" => get_uri($can_barbecue_rentals ? "grupo_donato/barbecue-rentals" : "grupo_donato/bookings"),
        "can_link" => $can_barbecue_rentals || $can_bookings,
    ],
];

if (!empty($can_catalog)) {
    $product_cards[] = [
        "name" => "Produtos e servicos",
        "description" => "Catalogo, variacoes e precos",
        "icon" => "package",
        "class" => "gd-product-catalog",
        "finance" => ["charges" => 0, "paid_count" => 0, "open_count" => 0, "overdue_count" => 0, "billed_amount" => 0, "paid_amount" => 0, "balance_amount" => 0, "overdue_amount" => 0],
        "stats" => [
            ["label" => "Produtos ativos", "value" => $catalog["active_products"] ?? 0],
            ["label" => "Categorias ativas", "value" => $catalog["active_categories"] ?? 0],
            ["label" => "Variacoes ativas", "value" => $catalog["active_variants"] ?? 0],
            ["label" => "Com preco vigente", "value" => $catalog["priced_products"] ?? 0],
        ],
        "href" => get_uri("grupo_donato/catalog/products"),
        "can_link" => true,
    ];
}

$product_rows = [
    ["label" => "GD Academy", "finance" => $finance_by_source["enrollment"] ?? []],
    ["label" => "Locações de quadras", "finance" => $finance_by_source["court_rental"] ?? []],
    ["label" => "Churrasqueiras", "finance" => $finance_by_source["barbecue_rental"] ?? []],
];
$other_finance = $finance_by_source["manual"] ?? [];
foreach (["charges", "paid_count", "open_count", "overdue_count", "billed_amount", "paid_amount", "balance_amount", "overdue_amount", "received_period", "payment_count"] as $finance_field) {
    $other_finance[$finance_field] = (float) ($finance_by_source["manual"][$finance_field] ?? 0) + (float) ($finance_by_source["other"][$finance_field] ?? 0);
}
$product_rows[] = ["label" => "Outras cobranças", "finance" => $other_finance];
$trend_max = 0.0;
foreach ($trend as $month) {
    $trend_max = max($trend_max, (float) ($month["received"] ?? 0), (float) ($month["expenses"] ?? 0));
}
$trend_max = $trend_max > 0 ? $trend_max : 1;

$shortcuts = [];
if ($can_students) { $shortcuts[] = modal_anchor($academy_student_modal_url, '<i data-feather="user-plus" class="icon-14"></i> Novo aluno', ["class" => "btn btn-primary btn-sm me-2 mb-2", "title" => "Novo aluno"]); }
if ($can_bookings) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="calendar" class="icon-14"></i> Nova reserva', ["class" => "btn btn-default btn-sm me-2 mb-2", "title" => "Nova reserva"]); }
if ($can_court_rentals) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/court-rentals/monthly-modal"), '<i data-feather="repeat" class="icon-14"></i> Novo mensalista', ["class" => "btn btn-default btn-sm me-2 mb-2", "title" => "Novo mensalista"]); }
if ($can_payments) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/finance/payment-modal"), '<i data-feather="check-circle" class="icon-14"></i> Registrar pagamento', ["class" => "btn btn-default btn-sm me-2 mb-2", "title" => "Registrar pagamento"]); }
if ($can_expenses) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/finance/costs/modal"), '<i data-feather="arrow-down-circle" class="icon-14"></i> Registrar custo', ["class" => "btn btn-default btn-sm me-2 mb-2", "title" => "Registrar custo"]); }

$module_links = [];
$module_link = static function (string $label, string $url, string $icon) use (&$module_links): void {
    $module_links[] = anchor(get_uri($url), '<i data-feather="' . esc($icon) . '" class="icon-14"></i> ' . esc($label), ["class" => "btn btn-default btn-sm me-2 mb-2"]);
};
if (!empty($can_customers)) { $module_link("Clientes", "grupo_donato/customers", "briefcase"); }
if (!empty($can_people)) { $module_link("Pessoas", "grupo_donato/people", "users"); }
if (!empty($can_students)) { $module_link("Alunos", "grupo_donato/operacional?gd_tab=alunos", "user"); }
if (!empty($can_attendance)) { $module_link("Presenca", "grupo_donato/operacional?gd_tab=presenca", "check-square"); }
if (!empty($can_calendar)) { $module_link("Agenda", "grupo_donato/calendar", "calendar"); }
if (!empty($can_bookings)) { $module_link("Reservas", "grupo_donato/bookings", "clipboard"); }
if (!empty($can_booking_series)) { $module_link("Recorrencias", "grupo_donato/booking-series", "repeat"); }
if (!empty($can_court_rentals)) { $module_link("Locacoes de quadras", "grupo_donato/court-rentals", "grid"); }
if (!empty($can_barbecue_rentals)) { $module_link("Churrasqueiras", "grupo_donato/barbecue-rentals", "sun"); }
if (!empty($can_finance)) { $module_link("Financeiro", "grupo_donato/finance", "dollar-sign"); }
if (!empty($can_costs)) { $module_link("Custos", "grupo_donato/finance/costs", "arrow-down-circle"); }
if (!empty($can_catalog)) { $module_link("Produtos e servicos", "grupo_donato/catalog/products", "package"); }
if (!empty($can_resources)) { $module_link("Recursos", "grupo_donato/resources", "map"); }
if (!empty($can_pricing)) { $module_link("Tabelas de preco", "grupo_donato/pricing/lists", "tag"); }
if (!empty($can_settings)) { $module_link("Configuracoes", "grupo_donato/settings/general", "settings"); }
?>

<style>
    .gd-owner-dashboard {
        --owner-text: var(--gd-text, #ffffff);
        --owner-muted: var(--gd-muted, #b7c5d8);
        --owner-line: var(--gd-border, #244d78);
        --owner-surface: var(--gd-surface, #082a52);
        --owner-surface-2: var(--gd-surface-2, #0b315f);
        --owner-bg: var(--gd-bg, #03182f);
        --owner-gold: var(--gd-gold, #d2a63a);
        --owner-success: #8be28b;
        --owner-warning: #ffd166;
        --owner-danger: #ff8795;
        color: var(--owner-text);
        font-size: 14px;
    }
    .gd-owner-dashboard h1,
    .gd-owner-dashboard h2,
    .gd-owner-dashboard h3,
    .gd-owner-dashboard h4,
    .gd-owner-dashboard label { color: var(--owner-text); }
    .gd-owner-dashboard .card,
    .gd-owner-dashboard .card-body,
    .gd-owner-dashboard .table-responsive { background-color: var(--owner-surface) !important; border-color: var(--owner-line) !important; }
    .gd-owner-dashboard .gd-hero { background: linear-gradient(135deg, #0f3766 0%, #174f88 100%); color: #fff; border-radius: 12px; padding: 24px 26px; margin-bottom: 18px; box-shadow: 0 8px 24px rgba(13, 55, 101, .16); }
    .gd-owner-dashboard .gd-hero h1 { color: #fff; font-size: 26px; margin: 0 0 5px; font-weight: 600; }
    .gd-owner-dashboard .gd-hero p { color: rgba(255,255,255,.76); margin: 0; }
    .gd-owner-dashboard .gd-hero .form-control { min-width: 145px; border: 0; }
    .gd-owner-dashboard .gd-section-title { color: var(--owner-text); font-size: 17px; font-weight: 600; margin: 22px 0 11px; }
    .gd-owner-dashboard .gd-section-title small { color: var(--owner-muted); font-size: 12px; font-weight: 400; margin-left: 7px; }
    .gd-owner-dashboard .gd-summary-card { background: var(--owner-surface) !important; border: 1px solid var(--owner-line) !important; border-radius: 10px; min-height: 112px; overflow: hidden; position: relative; }
    .gd-owner-dashboard .gd-summary-card:before { content: ""; position: absolute; inset: 0 auto 0 0; width: 4px; background: #2d7dd2; }
    .gd-owner-dashboard .gd-summary-card.gd-positive:before { background: #19a974; }
    .gd-owner-dashboard .gd-summary-card.gd-warning:before { background: #f0a202; }
    .gd-owner-dashboard .gd-summary-card.gd-danger:before { background: #dd4b5e; }
    .gd-owner-dashboard .gd-summary-card.gd-neutral:before { background: #78879b; }
    .gd-owner-dashboard .gd-summary-card .card-body { background: var(--owner-surface) !important; color: var(--owner-text); padding: 17px 18px 15px 21px; }
    .gd-owner-dashboard .gd-summary-label { color: var(--owner-muted); font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
    .gd-owner-dashboard .gd-summary-value { color: var(--owner-text); font-size: 22px; font-weight: 600; line-height: 1.25; margin-top: 9px; }
    .gd-owner-dashboard .gd-summary-meta { color: var(--owner-muted); font-size: 12px; margin-top: 5px; }
    .gd-owner-dashboard .gd-pulse { background: var(--owner-surface) !important; border: 1px solid var(--owner-line); border-radius: 10px; padding: 15px 17px; height: 100%; }
    .gd-owner-dashboard .gd-pulse-label { color: var(--owner-muted); font-size: 13px; }
    .gd-owner-dashboard .gd-pulse-value { color: var(--owner-text); font-size: 25px; font-weight: 600; margin-top: 5px; }
    .gd-owner-dashboard .gd-pulse-note { color: var(--owner-muted); font-size: 12px; }
    .gd-owner-dashboard .gd-product-card { background: var(--owner-surface) !important; border: 1px solid var(--owner-line) !important; border-radius: 11px; color: var(--owner-text); height: 100%; overflow: hidden; }
    .gd-owner-dashboard .gd-product-head { align-items: center; display: flex; gap: 11px; padding: 17px 17px 12px; }
    .gd-owner-dashboard .gd-product-icon { align-items: center; border-radius: 9px; display: flex; flex: 0 0 35px; height: 35px; justify-content: center; }
    .gd-owner-dashboard .gd-product-icon svg { width: 17px; }
    .gd-owner-dashboard .gd-product-name { color: var(--owner-text); font-size: 15px; font-weight: 600; }
    .gd-owner-dashboard .gd-product-description { color: var(--owner-muted); font-size: 12px; margin-top: 2px; }
    .gd-owner-dashboard .gd-product-state { font-size: 10px; margin-left: auto; }
    .gd-owner-dashboard .gd-product-stats { border-top: 1px solid var(--owner-line); display: grid; grid-template-columns: 1fr 1fr; }
    .gd-owner-dashboard .gd-product-stat { border-bottom: 1px solid var(--owner-line); padding: 10px 13px; }
    .gd-owner-dashboard .gd-product-stat:nth-child(odd) { border-right: 1px solid var(--owner-line); }
    .gd-owner-dashboard .gd-product-stat:nth-last-child(-n+2) { border-bottom: 0; }
    .gd-owner-dashboard .gd-product-stat-label { color: var(--owner-muted); display: block; font-size: 12px; }
    .gd-owner-dashboard .gd-product-stat-value { color: var(--owner-text); font-size: 18px; font-weight: 600; }
    .gd-owner-dashboard .gd-product-finance { background: var(--owner-surface-2) !important; border-top: 1px solid var(--owner-line); padding: 12px 14px; }
    .gd-owner-dashboard .gd-product-finance-title { color: var(--owner-muted); font-size: 12px; margin-bottom: 8px; }
    .gd-owner-dashboard .gd-finance-line { align-items: center; color: var(--owner-text); display: flex; font-size: 12px; justify-content: space-between; margin-top: 5px; }
    .gd-owner-dashboard .gd-finance-line strong { color: var(--owner-text); font-size: 13px; }
    .gd-owner-dashboard .gd-product-footer { padding: 11px 14px; }
    .gd-owner-dashboard .gd-product-footer a { font-size: 12px; }
    .gd-owner-dashboard .gd-future-card { background: var(--owner-surface) !important; border: 1px dashed var(--owner-line); border-radius: 11px; color: var(--owner-text); height: 100%; padding: 22px 18px; }
    .gd-owner-dashboard .gd-future-card h4 { color: var(--owner-text); font-size: 16px; margin: 10px 0 5px; }
    .gd-owner-dashboard .gd-future-card p { color: var(--owner-muted); font-size: 13px; line-height: 1.5; }
    .gd-owner-dashboard .gd-panel { background: var(--owner-surface) !important; border: 1px solid var(--owner-line); border-radius: 10px; color: var(--owner-text); height: 100%; overflow: hidden; }
    .gd-owner-dashboard .gd-panel-head { align-items: center; border-bottom: 1px solid var(--owner-line); display: flex; justify-content: space-between; padding: 14px 16px; }
    .gd-owner-dashboard .gd-panel-head h3 { color: var(--owner-text); font-size: 16px; margin: 0; }
    .gd-owner-dashboard .gd-panel-head small { color: var(--owner-muted); font-size: 12px; }
    .gd-owner-dashboard .gd-panel-body { background: var(--owner-surface) !important; padding: 14px 16px; }
    .gd-owner-dashboard .gd-agenda-row { align-items: center; border-bottom: 1px solid var(--owner-line); display: flex; gap: 11px; padding: 10px 0; }
    .gd-owner-dashboard .gd-agenda-row:last-child { border-bottom: 0; }
    .gd-owner-dashboard .gd-agenda-time { color: var(--owner-text); flex: 0 0 82px; font-size: 13px; font-weight: 600; }
    .gd-owner-dashboard .gd-agenda-dot { border-radius: 50%; flex: 0 0 8px; height: 8px; }
    .gd-owner-dashboard .gd-agenda-dot.academy { background: #6c63ff; }
    .gd-owner-dashboard .gd-agenda-dot.court { background: #2d7dd2; }
    .gd-owner-dashboard .gd-agenda-dot.barbecue { background: #f0a202; }
    .gd-owner-dashboard .gd-agenda-title { color: var(--owner-text); font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gd-owner-dashboard .gd-agenda-meta { color: var(--owner-muted); font-size: 11px; margin-top: 2px; }
    .gd-owner-dashboard .gd-empty { color: var(--owner-muted); font-size: 13px; padding: 14px 0; text-align: center; }
    .gd-owner-dashboard .gd-alert { border-radius: 8px; font-size: 12px; margin-bottom: 9px; padding: 10px 12px; }
    .gd-owner-dashboard .gd-alert:last-child { margin-bottom: 0; }
    .gd-owner-dashboard .gd-alert strong { color: var(--owner-text); }
    .gd-owner-dashboard .gd-chart-row { align-items: center; display: flex; gap: 10px; margin: 13px 0; }
    .gd-owner-dashboard .gd-chart-label { color: var(--owner-muted); flex: 0 0 28px; font-size: 12px; }
    .gd-owner-dashboard .gd-chart-bars { flex: 1; }
    .gd-owner-dashboard .gd-bar { border-radius: 3px; height: 7px; margin: 2px 0; min-width: 2px; }
    .gd-owner-dashboard .gd-bar.received { background: #2d7dd2; }
    .gd-owner-dashboard .gd-bar.expenses { background: #f0a202; }
    .gd-owner-dashboard .gd-chart-total { color: var(--owner-muted); flex: 0 0 80px; font-size: 11px; text-align: right; }
    .gd-owner-dashboard .gd-legend { color: var(--owner-muted); font-size: 11px; margin-top: 15px; }
    .gd-owner-dashboard .gd-legend span { display: inline-block; margin-right: 12px; }
    .gd-owner-dashboard .gd-legend i { border-radius: 2px; display: inline-block; height: 7px; margin-right: 4px; width: 7px; }
    .gd-owner-dashboard .gd-table { margin-bottom: 0; }
    .gd-owner-dashboard .gd-table { --bs-table-bg: transparent; --bs-table-color: var(--owner-text); background: var(--owner-surface) !important; color: var(--owner-text) !important; }
    .gd-owner-dashboard .gd-table th { background: transparent !important; border-top: 0; color: var(--owner-muted) !important; font-size: 11px; font-weight: 500; text-transform: uppercase; white-space: nowrap; }
    .gd-owner-dashboard .gd-table td { background: transparent !important; color: var(--owner-text) !important; font-size: 13px; vertical-align: middle; }
    .gd-owner-dashboard .gd-table td:not(:first-child), .gd-owner-dashboard .gd-table th:not(:first-child) { text-align: right; }
    .gd-owner-dashboard .gd-table .gd-total-row td { border-top: 2px solid var(--owner-line); font-weight: 600; }
    .gd-owner-dashboard .gd-due-row { align-items: center; border-bottom: 1px solid var(--owner-line); display: flex; gap: 10px; padding: 10px 0; }
    .gd-owner-dashboard .gd-due-row:last-child { border-bottom: 0; }
    .gd-owner-dashboard .gd-due-date { color: var(--owner-text); flex: 0 0 60px; font-size: 12px; font-weight: 600; }
    .gd-owner-dashboard .gd-due-description { color: var(--owner-text); flex: 1; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gd-owner-dashboard .gd-due-description small { color: var(--owner-muted); display: block; font-size: 11px; margin-top: 2px; }
    .gd-owner-dashboard .gd-due-amount { color: var(--owner-text); font-size: 12px; font-weight: 600; }
    .gd-owner-dashboard .gd-technical summary { color: var(--owner-muted); cursor: pointer; font-size: 13px; }
    .gd-owner-dashboard .gd-technical .card-body { background: var(--owner-surface) !important; color: var(--owner-text); font-size: 12px; }
    .gd-owner-dashboard .text-muted { color: var(--owner-muted) !important; }
    .gd-owner-dashboard .text-success { color: var(--owner-success) !important; }
    .gd-owner-dashboard .text-warning { color: var(--owner-warning) !important; }
    .gd-owner-dashboard .text-danger { color: var(--owner-danger) !important; }
    .gd-owner-dashboard .gd-alert { color: var(--owner-text); }
    .gd-owner-dashboard .gd-alert strong { color: var(--owner-text); }
    .gd-owner-dashboard .gd-product-state.bg-light { background: var(--owner-surface-3, #0e3a6e) !important; color: var(--owner-text) !important; }
    .gd-owner-dashboard .gd-future-card .bg-light { background: var(--owner-surface-3, #0e3a6e) !important; color: var(--owner-text) !important; }
    @media (max-width: 767px) {
        .gd-owner-dashboard .gd-hero { padding: 20px; }
        .gd-owner-dashboard .gd-hero .d-flex { align-items: flex-start !important; flex-direction: column; gap: 13px; }
        .gd-owner-dashboard .gd-summary-value { font-size: 19px; }
        .gd-owner-dashboard .gd-agenda-time { flex-basis: 70px; }
        .gd-owner-dashboard .gd-table { min-width: 680px; }
    }
</style>

<div id="page-content" class="page-wrapper clearfix gd-owner-dashboard">
    <div class="gd-hero">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h1>Visão geral</h1>
                <p><?php echo $active_unit_name; ?> · visão executiva da operação</p>
            </div>
            <form method="get" action="<?php echo esc(get_uri("grupo_donato/dashboard")); ?>" class="d-flex align-items-center">
                <label for="gd-dashboard-period" class="me-2 mb-0 small" style="color:rgba(255,255,255,.78)">Período</label>
                <input id="gd-dashboard-period" type="month" name="period" value="<?php echo esc($period_key); ?>" class="form-control form-control-sm">
                <button type="submit" class="btn btn-light btn-sm ms-2">Atualizar</button>
            </form>
        </div>
    </div>

    <?php if ($schema_failed) { ?>
        <div class="alert alert-danger"><i data-feather="alert-triangle" class="icon-14"></i> Há uma falha de atualização do sistema. Consulte Configurações.</div>
    <?php } elseif ($schema_pending) { ?>
        <div class="alert alert-warning"><i data-feather="alert-circle" class="icon-14"></i> Há uma atualização técnica pendente. Consulte Configurações.</div>
    <?php } ?>

    <div class="gd-section-title">Visão consolidada <small>Período selecionado: <?php echo esc($period_label); ?></small></div>
    <div class="row">
        <?php
        $summary_cards = [
            ["label" => "Recebido no período", "value" => $money($summary["received"]), "meta" => $number($summary["payments"]) . " pagamento(s) confirmado(s)", "class" => "gd-positive"],
            ["label" => "Em aberto", "value" => $money($summary["open"]), "meta" => $number($summary["open_count"]) . " cobrança(s) pendente(s)", "class" => "gd-warning"],
            ["label" => "Vencido", "value" => $money($summary["overdue"]), "meta" => $number($summary["overdue_count"]) . " cobrança(s) vencida(s)", "class" => "gd-danger"],
            ["label" => "Saídas pagas", "value" => $money($summary["expenses"]), "meta" => "Saídas no período", "class" => "gd-neutral"],
            ["label" => "Resultado", "value" => $money($summary["result"]), "meta" => "Entradas menos despesas", "class" => $summary["result"] >= 0 ? "gd-positive" : "gd-danger"],
        ];
        foreach ($summary_cards as $summary_card) { ?>
            <div class="col-xl col-md-4 col-sm-6 mb-3">
                <div class="card gd-summary-card <?php echo esc($summary_card["class"]); ?> mb-0">
                    <div class="card-body">
                        <div class="gd-summary-label"><?php echo esc($summary_card["label"]); ?></div>
                        <div class="gd-summary-value"><?php echo esc($summary_card["value"]); ?></div>
                        <div class="gd-summary-meta"><?php echo esc($summary_card["meta"]); ?></div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="gd-section-title">Pulso da operação <small><?php echo esc($today_label); ?></small></div>
    <div class="row">
        <div class="col-md-3 col-sm-6 mb-3"><div class="gd-pulse"><div class="gd-pulse-label">Agenda de hoje</div><div class="gd-pulse-value"><?php echo $number($today_events); ?></div><div class="gd-pulse-note">eventos visíveis</div></div></div>
        <div class="col-md-3 col-sm-6 mb-3"><div class="gd-pulse"><div class="gd-pulse-label">Aulas hoje</div><div class="gd-pulse-value"><?php echo $number($today_classes); ?></div><div class="gd-pulse-note">turmas programadas</div></div></div>
        <div class="col-md-3 col-sm-6 mb-3"><div class="gd-pulse"><div class="gd-pulse-label">Reservas hoje</div><div class="gd-pulse-value"><?php echo $number($today_bookings); ?></div><div class="gd-pulse-note">quadras e churrasqueiras</div></div></div>
        <div class="col-md-3 col-sm-6 mb-3"><div class="gd-pulse"><div class="gd-pulse-label">Contratos ativos</div><div class="gd-pulse-value"><?php echo $number($active_contracts); ?></div><div class="gd-pulse-note"><?php echo $number($active_resources); ?> espaços disponíveis</div></div></div>
    </div>

    <div class="gd-section-title">Produtos <small>cada negócio com os mesmos quatro sinais: base, agenda e financeiro</small></div>
    <div class="row">
        <?php foreach ($product_cards as $product) {
            $product_finance = $product["finance"];
            $has_activity = array_sum(array_map("intval", array_column($product["stats"], "value"))) > 0 || (int) ($product_finance["charges"] ?? 0) > 0;
            ?>
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="gd-product-card <?php echo esc($product["class"]); ?>">
                    <div class="gd-product-head">
                        <div class="gd-product-icon" style="background:rgba(45,125,210,.12);color:#2d7dd2"><i data-feather="<?php echo esc($product["icon"]); ?>"></i></div>
                        <div><div class="gd-product-name"><?php echo esc($product["name"]); ?></div><div class="gd-product-description"><?php echo esc($product["description"]); ?></div></div>
                        <span class="badge <?php echo $has_activity ? "bg-success" : "bg-light text-muted"; ?> gd-product-state"><?php echo $has_activity ? "Ativo" : "Sem movimento"; ?></span>
                    </div>
                    <div class="gd-product-stats">
                        <?php foreach ($product["stats"] as $stat) { ?>
                            <div class="gd-product-stat"><span class="gd-product-stat-label"><?php echo esc($stat["label"]); ?></span><span class="gd-product-stat-value"><?php echo $number($stat["value"]); ?></span></div>
                        <?php } ?>
                    </div>
                    <div class="gd-product-finance">
                        <div class="gd-product-finance-title">Financeiro do período</div>
                        <div class="gd-finance-line"><span>Cobranças <?php echo $number($product_finance["charges"] ?? 0); ?></span><strong><?php echo esc($money($product_finance["billed_amount"] ?? 0)); ?></strong></div>
                        <div class="gd-finance-line"><span>Pagos <?php echo $number($product_finance["paid_count"] ?? 0); ?></span><strong class="text-success"><?php echo esc($money($product_finance["paid_amount"] ?? 0)); ?></strong></div>
                        <div class="gd-finance-line"><span>Em aberto <?php echo $number($product_finance["open_count"] ?? 0); ?></span><strong class="text-warning"><?php echo esc($money($product_finance["balance_amount"] ?? 0)); ?></strong></div>
                        <div class="gd-finance-line"><span>Vencidos <?php echo $number($product_finance["overdue_count"] ?? 0); ?></span><strong class="text-danger"><?php echo esc($money($product_finance["overdue_amount"] ?? 0)); ?></strong></div>
                    </div>
                    <?php if ($product["can_link"]) { ?><div class="gd-product-footer"><a href="<?php echo esc($product["href"]); ?>">Abrir produto <i data-feather="arrow-up-right" class="icon-12"></i></a></div><?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="gd-section-title">Acompanhamento do dia</div>
    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="gd-panel">
                <div class="gd-panel-head"><h3><i data-feather="calendar" class="icon-14"></i> Agenda compartilhada</h3><small>Hoje · <?php echo esc($today_label); ?></small></div>
                <div class="gd-panel-body">
                    <?php if (!$agenda) { ?><div class="gd-empty">Nenhum evento programado para hoje.</div><?php } ?>
                    <?php foreach ($agenda as $event) { ?>
                        <div class="gd-agenda-row">
                            <div class="gd-agenda-time"><?php echo esc($event["time"]); ?></div>
                            <div class="gd-agenda-dot <?php echo esc($event["kind"]); ?>"></div>
                            <div class="flex-grow-1 min-width-0"><div class="gd-agenda-title" title="<?php echo esc($event["title"]); ?>"><?php echo esc($event["title"]); ?></div><div class="gd-agenda-meta"><?php echo esc($event["product"]); ?> · <?php echo esc($event["meta"]); ?></div></div>
                            <span class="text-muted small d-none d-sm-inline"><?php echo esc($event["status"]); ?></span>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-3">
            <div class="gd-panel">
                <div class="gd-panel-head"><h3><i data-feather="alert-circle" class="icon-14"></i> Pontos de atenção</h3><small>ação do gestor</small></div>
                <div class="gd-panel-body">
                    <?php if ((int) $summary["overdue_count"] > 0) { ?><div class="gd-alert alert-danger"><strong><?php echo $number($summary["overdue_count"]); ?> cobrança(s) vencida(s).</strong><br><?php echo esc($money($summary["overdue"])); ?> precisam de acompanhamento.</div><?php } else { ?><div class="gd-alert alert-success"><strong>Financeiro em dia.</strong><br>Nenhuma cobrança vencida encontrada.</div><?php } ?>
                    <?php if ((int) $summary["open_count"] > 0) { ?><div class="gd-alert alert-warning"><strong><?php echo $number($summary["open_count"]); ?> cobrança(s) em aberto.</strong><br>Há <?php echo esc($money($summary["open"])); ?> para acompanhar.</div><?php } ?>
                    <?php if (!(int) $today_events) { ?><div class="gd-alert alert-info"><strong>Agenda livre hoje.</strong><br>Nenhum evento compartilhado foi encontrado.</div><?php } ?>
                    <?php if ((int) $summary["overdue_count"] === 0 && (int) $summary["open_count"] === 0 && (int) $today_events > 0) { ?><div class="gd-alert alert-info"><strong>Operação sem alertas críticos.</strong><br>A agenda está em movimento e não há pendências financeiras abertas.</div><?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($can_finance) { ?>
        <div class="gd-section-title">Financeiro por produto <small>cobranças da competência selecionada</small></div>
        <div class="card mb-3"><div class="table-responsive"><table class="table gd-table">
            <thead><tr><th>Produto</th><th>Cobranças</th><th>Cobrado</th><th>Pago</th><th>Em aberto</th><th>Vencidos</th></tr></thead>
            <tbody>
                <?php foreach ($product_rows as $product_row) { $row_finance = $product_row["finance"]; ?>
                    <tr><td><?php echo esc($product_row["label"]); ?></td><td><?php echo $number($row_finance["charges"] ?? 0); ?></td><td><?php echo esc($money($row_finance["billed_amount"] ?? 0)); ?></td><td class="text-success"><?php echo esc($money($row_finance["paid_amount"] ?? 0)); ?></td><td class="text-warning"><?php echo $number($row_finance["open_count"] ?? 0) . " · " . esc($money($row_finance["balance_amount"] ?? 0)); ?></td><td class="text-danger"><?php echo $number($row_finance["overdue_count"] ?? 0) . " · " . esc($money($row_finance["overdue_amount"] ?? 0)); ?></td></tr>
                <?php } ?>
                <tr class="gd-total-row"><td>Total da empresa</td><td><?php echo $number($finance_totals["charges"] ?? 0); ?></td><td><?php echo esc($money($finance_totals["billed_amount"] ?? 0)); ?></td><td class="text-success"><?php echo esc($money($finance_totals["paid_amount"] ?? 0)); ?></td><td class="text-warning"><?php echo $number($finance_totals["open_count"] ?? 0) . " · " . esc($money($finance_totals["balance_amount"] ?? 0)); ?></td><td class="text-danger"><?php echo $number($finance_totals["overdue_count"] ?? 0) . " · " . esc($money($finance_totals["overdue_amount"] ?? 0)); ?></td></tr>
            </tbody>
        </table></div></div>
    <?php } ?>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="gd-panel">
                <div class="gd-panel-head"><h3><i data-feather="trending-up" class="icon-14"></i> Entradas x despesas</h3><small>últimos 6 meses</small></div>
                <div class="gd-panel-body">
                    <?php if (!$trend) { ?><div class="gd-empty">Ainda não há movimentação financeira suficiente para montar a tendência.</div><?php } ?>
                    <?php foreach ($trend as $month) { ?>
                        <div class="gd-chart-row"><div class="gd-chart-label"><?php echo esc($month["label"]); ?></div><div class="gd-chart-bars"><div class="gd-bar received" style="width:<?php echo min(100, ((float) $month["received"] / $trend_max) * 100); ?>%" title="Recebido"></div><div class="gd-bar expenses" style="width:<?php echo min(100, ((float) $month["expenses"] / $trend_max) * 100); ?>%" title="Despesas"></div></div><div class="gd-chart-total"><?php echo esc($money($month["received"])); ?></div></div>
                    <?php } ?>
                    <?php if ($trend) { ?><div class="gd-legend"><span><i style="background:#2d7dd2"></i>Recebido</span><span><i style="background:#f0a202"></i>Despesas</span></div><?php } ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-3">
            <div class="gd-panel">
                <div class="gd-panel-head"><h3><i data-feather="clock" class="icon-14"></i> Próximos vencimentos</h3><small>contas em aberto do período</small></div>
                <div class="gd-panel-body">
                    <?php if (!$upcoming_receivables) { ?><div class="gd-empty">Nenhum vencimento futuro encontrado.</div><?php } ?>
                    <?php foreach ($upcoming_receivables as $due) { $source = $source_labels[(string) ($due->source_type ?? "other")] ?? "Outras cobranças"; ?>
                        <div class="gd-due-row"><div class="gd-due-date"><?php echo esc(date("d/m", strtotime((string) $due->due_date))); ?></div><div class="gd-due-description" title="<?php echo esc((string) $due->description); ?>"><?php echo esc((string) ($due->customer_name ?: $due->description)); ?><small><?php echo esc($source); ?></small></div><div class="gd-due-amount"><?php echo esc($money($due->balance_amount)); ?></div></div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($shortcuts) { ?><div class="gd-section-title">Acesso rápido</div><div class="card mb-3"><div class="card-body pb-1"><?php echo implode(" ", $shortcuts); ?></div></div><?php } ?>

    <?php if ($module_links) { ?>
        <div class="gd-section-title">Navegacao integrada <small>todas as telas desta unidade</small></div>
        <div class="card mb-3"><div class="card-body pb-1"><?php echo implode(" ", $module_links); ?></div></div>
    <?php } ?>

    <details class="gd-technical card mb-3">
        <summary class="card-body">Informações do sistema</summary>
        <div class="card-body border-top"><div class="row"><div class="col-md-3"><small class="text-muted d-block">Versão do módulo</small><?php echo esc($plugin_version); ?></div><div class="col-md-3"><small class="text-muted d-block">Schema</small><?php echo esc($schema_applied); ?> / <?php echo esc($schema_target); ?></div><div class="col-md-3"><small class="text-muted d-block">Unidade</small><?php echo $active_unit_name; ?></div><div class="col-md-3"><small class="text-muted d-block">Fuso horário</small><?php echo esc($timezone_name); ?></div></div>
            <?php if ($can_view_audit && $recent_audit) { ?><hr><div class="text-muted mb-2">Últimas ações do sistema</div><div class="table-responsive"><table class="table table-sm gd-table"><thead><tr><th>Quando</th><th>Ação</th><th>Registro</th><th>Usuário</th></tr></thead><tbody><?php foreach ($recent_audit as $log) { $actor = trim(($log->first_name ?? "") . " " . ($log->last_name ?? "")) ?: ($log->actor_type ?: "sistema"); ?><tr><td><?php echo $log->created_at ? esc(format_to_datetime($log->created_at)) : ""; ?></td><td><?php echo esc((string) $log->action); ?></td><td><?php echo esc((string) $log->entity_type); ?><?php echo $log->entity_id ? " #" . (int) $log->entity_id : ""; ?></td><td><?php echo esc($actor); ?></td></tr><?php } ?></tbody></table></div><?php } ?>
        </div>
    </details>
</div>
