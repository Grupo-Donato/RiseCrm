<?php
/**
 * Visão geral operacional — apenas dados reais + atalhos do dia a dia.
 */
$active_unit_name = $active_unit && isset($active_unit->name) && $active_unit->name
    ? htmlspecialchars((string) $active_unit->name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8")
    : "<span class='text-muted'>" . app_lang("gd_no_unit") . "</span>";

/** Atalhos: usa modal quando há modal pronto; senão link para a tela. */
$shortcuts = [];
if ($can_students) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/school/students/modal"), '<i data-feather="user-plus" class="icon-16"></i> ' . app_lang("gd_shortcut_new_student"), ["class" => "btn btn-primary mb-2 me-2", "title" => app_lang("gd_shortcut_new_student")]); }
if ($can_classes) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/school/classes/modal"), '<i data-feather="layers" class="icon-16"></i> ' . app_lang("gd_shortcut_new_class"), ["class" => "btn btn-default mb-2 me-2", "title" => app_lang("gd_shortcut_new_class")]); }
if ($can_attendance) { $shortcuts[] = anchor(get_uri("grupo_donato/school/attendance"), '<i data-feather="check-square" class="icon-16"></i> ' . app_lang("gd_shortcut_attendance"), ["class" => "btn btn-default mb-2 me-2"]); }
if ($can_bookings) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="calendar" class="icon-16"></i> ' . app_lang("gd_shortcut_new_booking"), ["class" => "btn btn-default mb-2 me-2", "title" => app_lang("gd_shortcut_new_booking")]); }
if ($can_court_rentals) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/court-rentals/monthly-modal"), '<i data-feather="dollar-sign" class="icon-16"></i> ' . app_lang("gd_shortcut_new_monthly"), ["class" => "btn btn-default mb-2 me-2", "title" => app_lang("gd_shortcut_new_monthly")]); }
if ($can_receivables) { $shortcuts[] = anchor(get_uri("grupo_donato/finance/generate"), '<i data-feather="file-text" class="icon-16"></i> ' . app_lang("gd_shortcut_generate_charges"), ["class" => "btn btn-default mb-2 me-2"]); }
if ($can_payments) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/finance/payment-modal"), '<i data-feather="check-circle" class="icon-16"></i> ' . app_lang("gd_shortcut_register_payment"), ["class" => "btn btn-default mb-2 me-2", "title" => app_lang("gd_shortcut_register_payment")]); }
if ($can_expenses) { $shortcuts[] = modal_anchor(get_uri("grupo_donato/finance/expense-modal"), '<i data-feather="arrow-down-circle" class="icon-16"></i> ' . app_lang("gd_shortcut_register_expense"), ["class" => "btn btn-default mb-2 me-2", "title" => app_lang("gd_shortcut_register_expense")]); }

$kpi_cards = [
    ["label" => "gd_kpi_active_students", "value" => (int) $kpi["students"], "icon" => "users"],
    ["label" => "gd_kpi_active_classes", "value" => (int) $kpi["classes"], "icon" => "layers"],
    ["label" => "gd_kpi_classes_today", "value" => (int) $kpi["classes_today"], "icon" => "calendar"],
    ["label" => "gd_kpi_bookings_today", "value" => (int) $kpi["bookings_today"], "icon" => "clock"],
    ["label" => "gd_kpi_active_monthly", "value" => (int) $kpi["monthly_renters"], "icon" => "repeat"],
];

$finance_cards = [
    ["label" => "gd_finance_total_receivable", "value" => $finance["open"]],
    ["label" => "gd_finance_overdue", "value" => $finance["overdue"]],
    ["label" => "gd_finance_received_month", "value" => $finance["received"]],
    ["label" => "gd_finance_period_balance", "value" => $finance["balance"]],
];
?>
<div id="page-content" class="page-wrapper clearfix">

    <?php echo view("grupo_donato_gestao\\Views\\components\\page_header", ["title" => app_lang("gd_menu_overview")]); ?>

    <?php if ($schema_failed) { ?>
        <div class="alert alert-danger"><i data-feather="alert-triangle" class="icon-16"></i>
            <?php echo app_lang("gd_schema_failed_warning"); ?>
        </div>
    <?php } else if ($schema_pending) { ?>
        <div class="alert alert-warning"><i data-feather="alert-circle" class="icon-16"></i>
            <?php echo app_lang("gd_schema_pending_warning"); ?>
        </div>
    <?php } ?>

    <!-- Atalhos -->
    <?php if (count($shortcuts)) { ?>
        <div class="card mb-3"><div class="card-body">
            <div class="widget-title mb-2"><?php echo app_lang("gd_shortcuts"); ?></div>
            <?php echo implode(" ", $shortcuts); ?>
        </div></div>
    <?php } ?>

    <!-- KPIs operacionais -->
    <div class="row">
        <?php foreach ($kpi_cards as $c) { ?>
            <div class="col-md col-sm-6">
                <div class="card dashboard-icon-widget mb-3"><div class="card-body">
                    <div class="widget-title"><i data-feather="<?php echo $c["icon"]; ?>" class="icon-16"></i> <?php echo app_lang($c["label"]); ?></div>
                    <h3 class="mt-2"><?php echo (int) $c["value"]; ?></h3>
                </div></div>
            </div>
        <?php } ?>
    </div>

    <!-- KPIs financeiros -->
    <?php if ($can_finance) { ?>
        <div class="row">
            <?php foreach ($finance_cards as $c) { ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card mb-3"><div class="card-body">
                        <div class="widget-title"><?php echo app_lang($c["label"]); ?></div>
                        <h4 class="mt-2">R$ <?php echo esc($c["value"]); ?></h4>
                    </div></div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>

    <!-- Seção técnica discreta -->
    <div class="card">
        <div class="page-title"><h4 class="text-muted"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_system_info"); ?></h4></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang("gd_plugin_version"); ?></small><?php echo htmlspecialchars((string) $plugin_version); ?></div>
                <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang("gd_schema_version"); ?></small><?php echo htmlspecialchars((string) $schema_applied); ?> / <?php echo htmlspecialchars((string) $schema_target); ?>
                    <span class="badge <?php echo $schema_pending ? "bg-warning" : "bg-success"; ?>"><?php echo $schema_pending ? app_lang("gd_schema_pending") : app_lang("gd_schema_up_to_date"); ?></span>
                </div>
                <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang("gd_active_unit"); ?></small><?php echo $active_unit_name; ?></div>
                <div class="col-md-3"><a href="<?php echo get_uri("grupo_donato/settings/general"); ?>" class="btn btn-link p-0"><?php echo app_lang("gd_menu_settings"); ?> →</a></div>
            </div>

            <?php if ($can_view_audit && count($recent_audit)) { ?>
                <hr>
                <div class="widget-title mb-2"><?php echo app_lang("gd_recent_audit"); ?></div>
                <div class="table-responsive"><table class="table table-sm">
                    <thead><tr>
                        <th><?php echo app_lang("gd_audit_when"); ?></th>
                        <th><?php echo app_lang("gd_audit_action"); ?></th>
                        <th><?php echo app_lang("gd_audit_entity"); ?></th>
                        <th><?php echo app_lang("gd_audit_actor"); ?></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($recent_audit as $log) {
                            $actor = trim(($log->first_name ?? "") . " " . ($log->last_name ?? ""));
                            if (!$actor) { $actor = $log->actor_type ?: "system"; }
                            ?>
                            <tr>
                                <td><?php echo $log->created_at ? format_to_datetime($log->created_at) : ""; ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars((string) $log->action); ?></span></td>
                                <td><?php echo htmlspecialchars((string) $log->entity_type) . ($log->entity_id ? " #" . $log->entity_id : ""); ?></td>
                                <td><?php echo htmlspecialchars($actor); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table></div>
            <?php } ?>
        </div>
    </div>

</div>
