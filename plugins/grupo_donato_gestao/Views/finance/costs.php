<?php
use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\DataNormalizationService;

$money = static function ($value): string {
    $raw = trim((string) $value);
    $negative = str_starts_with($raw, "-");
    $raw = ltrim($raw, "+-");
    if (strpos($raw, ",") !== false && strpos($raw, ".") === false) $raw = str_replace(",", ".", $raw);
    [$integer, $fraction] = array_pad(explode(".", $raw, 2), 2, "00");
    $integer = ltrim($integer, "0") ?: "0";
    $integer = preg_replace('/\B(?=(\d{3})+(?!\d))/', ".", $integer) ?: $integer;
    return ($negative ? "-" : "") . "R$ " . $integer . "," . str_pad(substr($fraction, 0, 2), 2, "0");
};

$percent = static function ($value, $max): int {
    if (DataNormalizationService::decimalCompare((string) $max, "0.00") <= 0) return 0;
    $value = (float) str_replace(",", ".", (string) $value);
    $max = (float) str_replace(",", ".", (string) $max);
    return max(0, min(100, (int) round(($value / $max) * 100)));
};

$current_month = (string) ($metrics["reference_month"] ?? date("Y-m"));
$status_options = [["id" => "", "text" => app_lang("all")]];
foreach (Constants::EXPENSE_STATUSES as $status) $status_options[] = ["id" => $status, "text" => app_lang("gd_cost_status_" . $status)];
$status_options[] = ["id" => "overdue", "text" => app_lang("gd_cost_status_overdue")];

$nature_options = [["id" => "", "text" => app_lang("all")]];
foreach ($natures as $nature) $nature_options[] = ["id" => $nature, "text" => app_lang("gd_cost_nature_" . $nature)];

$behavior_options = [["id" => "", "text" => app_lang("all")]];
foreach ($behaviors as $behavior) $behavior_options[] = ["id" => $behavior, "text" => app_lang("gd_cost_behavior_" . $behavior)];

$category_options = [["id" => "", "text" => app_lang("all")]];
foreach ($categories as $category) {
    $category_options[] = [
        "id" => (int) $category->id,
        "text" => ($category->parent_id ? "— " : "") . $category->name,
    ];
}

$center_options = [["id" => "", "text" => app_lang("all")]];
foreach ($centers as $center) $center_options[] = ["id" => (int) $center["id"], "text" => $center["text"]];

$reference_month_options = [["id" => "", "text" => app_lang("all")]];
$month_cursor = new DateTimeImmutable($current_month . "-01");
for ($month_index = 0; $month_index < 12; $month_index++) {
    $reference_month_options[] = ["id" => $month_cursor->format("Y-m"), "text" => $month_cursor->format("m/Y")];
    $month_cursor = $month_cursor->modify("-1 month");
}
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\cash_nav", ["active" => "costs"]); ?>

<div id="page-content" class="page-wrapper clearfix grid-button">
    <div class="card mb15">
        <div class="page-title clearfix">
            <h1><?php echo app_lang("gd_costs_title"); ?></h1>
            <div class="title-button-group">
                <?php if ($can_categories) echo modal_anchor(get_uri("grupo_donato/finance/costs/categories/modal"), '<i data-feather="tag" class="icon-16"></i> ' . app_lang("gd_costs_category"), ["class" => "btn btn-default", "title" => app_lang("gd_costs_category")]); ?>
                <?php if ($can_budget) echo modal_anchor(get_uri("grupo_donato/finance/costs/budgets/modal"), '<i data-feather="target" class="icon-16"></i> ' . app_lang("gd_costs_budget"), ["class" => "btn btn-default", "title" => app_lang("gd_costs_budget")]); ?>
                <?php if ($can_manage) echo modal_anchor(get_uri("grupo_donato/finance/costs/recurrences/modal"), '<i data-feather="repeat" class="icon-16"></i> ' . app_lang("gd_costs_recurring"), ["class" => "btn btn-default", "title" => app_lang("gd_costs_recurring")]); ?>
                <?php if ($can_manage) echo modal_anchor(get_uri("grupo_donato/finance/costs/modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_costs_new"), ["class" => "btn btn-primary", "title" => app_lang("gd_costs_new")]); ?>
            </div>
        </div>
        <div class="card-body pt0">
            <span class="text-muted">Controle de compromissos, pagamentos e orçamento da unidade.</span>
        </div>
    </div>

    <div class="row mb15">
        <?php foreach ([
            ["total", "gd_costs_final", "file-text", "info"],
            ["paid", "gd_costs_paid", "check-circle", "success"],
            ["balance", "gd_costs_balance", "trending-down", "warning"],
            ["overdue", "gd_finance_overdue", "alert-triangle", "danger"],
        ] as [$key, $label, $icon, $color]): ?>
            <div class="col-md-3 col-sm-6">
                <div class="card dashboard-icon-widget">
                    <div class="card-body">
                        <div class="widget-icon bg-<?php echo $color; ?>"><i data-feather="<?php echo $icon; ?>" class="icon"></i></div>
                        <div class="widget-details">
                            <h1><?php echo $money($metrics[$key] ?? "0.00"); ?></h1>
                            <span><?php echo app_lang($label); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row mb15">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="mb0"><?php echo app_lang("gd_costs_final"); ?> — <?php echo esc($current_month); ?></h4>
                </div>
                <div class="card-body">
                    <?php
                    $month_max = "0.00";
                    foreach (($metrics["months"] ?? []) as $item) if (DataNormalizationService::decimalCompare((string) $item->amount, $month_max) > 0) $month_max = (string) $item->amount;
                    ?>
                    <?php if (!empty($metrics["months"])): foreach ($metrics["months"] as $item): $item_percent = $percent($item->amount, $month_max); ?>
                        <div class="mb15">
                            <div class="d-flex justify-content-between mb5">
                                <span><?php echo esc($item->reference_month); ?></span>
                                <strong><?php echo $money($item->amount); ?></strong>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?php echo $item_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary" style="width: <?php echo $item_percent; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; else: ?><span class="text-muted">-</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="mb0"><?php echo app_lang("gd_costs_category"); ?></h4>
                </div>
                <div class="card-body">
                    <?php
                    $category_max = "0.00";
                    foreach (($metrics["by_category"] ?? []) as $item) if (DataNormalizationService::decimalCompare((string) $item->amount, $category_max) > 0) $category_max = (string) $item->amount;
                    ?>
                    <?php if (!empty($metrics["by_category"])): foreach ($metrics["by_category"] as $item): $item_percent = $percent($item->amount, $category_max); ?>
                        <div class="mb15">
                            <div class="d-flex justify-content-between mb5">
                                <span class="text-truncate" title="<?php echo esc($item->name); ?>"><?php echo esc($item->name); ?></span>
                                <strong><?php echo $money($item->amount); ?></strong>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?php echo $item_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-info" style="width: <?php echo $item_percent; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; else: ?><span class="text-muted">-</span><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="mb0"><?php echo app_lang("gd_costs_budget"); ?></h4>
                </div>
                <div class="card-body">
                    <div class="text-muted mb5"><?php echo app_lang("gd_costs_budget_amount"); ?></div>
                    <h3 class="mt0 mb15"><?php echo $money($metrics["budget"]["budget"] ?? "0.00"); ?></h3>
                    <div class="text-muted mb5"><?php echo app_lang("gd_costs_realized"); ?></div>
                    <h3 class="mt0 mb15"><?php echo $money($metrics["budget"]["actual"] ?? "0.00"); ?></h3>
                    <div class="<?php echo !empty($metrics["budget"]["over_budget"]) ? "text-danger" : "text-success"; ?>">
                        <?php echo app_lang("gd_costs_variance"); ?>: <?php echo $money($metrics["budget"]["variance"] ?? "0.00"); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb15">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb0"><?php echo app_lang("gd_costs_center"); ?></h4>
                </div>
                <div class="card-body">
                    <?php
                    $center_max = "0.00";
                    foreach (($metrics["by_center"] ?? []) as $item) if (DataNormalizationService::decimalCompare((string) $item->amount, $center_max) > 0) $center_max = (string) $item->amount;
                    ?>
                    <?php if (!empty($metrics["by_center"])): foreach ($metrics["by_center"] as $item): $item_percent = $percent($item->amount, $center_max); ?>
                        <div class="mb15">
                            <div class="d-flex justify-content-between mb5">
                                <span class="text-truncate" title="<?php echo esc($item->name); ?>"><?php echo esc($item->name); ?></span>
                                <strong><?php echo $money($item->amount); ?></strong>
                            </div>
                            <div class="progress" role="progressbar" aria-valuenow="<?php echo $item_percent; ?>" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-success" style="width: <?php echo $item_percent; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; else: ?><span class="text-muted">-</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table id="gd-costs-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function () {
    "use strict";

    $("#gd-costs-table").appTable({
        source: "<?php echo_uri("grupo_donato/finance/costs/data"); ?>",
        order: [[3, "desc"]],
        smartFilterIdentity: "gd_costs",
        filterParams: {reference_month: "<?php echo esc($current_month); ?>"},
        filterDropdown: [
            {name: "status", class: "w150", options: <?php echo json_encode($status_options, JSON_UNESCAPED_UNICODE); ?>},
            {name: "nature", class: "w200", options: <?php echo json_encode($nature_options, JSON_UNESCAPED_UNICODE); ?>},
            {name: "cost_behavior", class: "w180", options: <?php echo json_encode($behavior_options, JSON_UNESCAPED_UNICODE); ?>},
            {name: "category_id", class: "w200", options: <?php echo json_encode($category_options, JSON_UNESCAPED_UNICODE); ?>},
            {name: "cost_center_id", class: "w200", options: <?php echo json_encode($center_options, JSON_UNESCAPED_UNICODE); ?>},
            {name: "reference_month", class: "w150", options: <?php echo json_encode($reference_month_options, JSON_UNESCAPED_UNICODE); ?>}
        ],
        rangeDatepicker: [{
            startDate: {name: "date_from", value: ""},
            endDate: {name: "date_to", value: ""},
            showClearButton: true
        }],
        columns: [
            {title: "<?php echo app_lang("gd_finance_number"); ?>", data: "number"},
            {title: "<?php echo app_lang("gd_finance_description"); ?>", data: "description"},
            {title: "<?php echo app_lang("gd_finance_payee"); ?>", data: "payee"},
            {title: "<?php echo app_lang("gd_costs_competence"); ?>", data: "competence"},
            {title: "<?php echo app_lang("gd_finance_due"); ?>", data: "due"},
            {title: "<?php echo app_lang("gd_costs_category"); ?>", data: "category"},
            {title: "<?php echo app_lang("gd_costs_center"); ?>", data: "center"},
            {title: "<?php echo app_lang("gd_costs_final"); ?>", data: "amount", class: "text-right"},
            {title: "<?php echo app_lang("gd_costs_paid"); ?>", data: "paid", class: "text-right"},
            {title: "<?php echo app_lang("gd_costs_balance"); ?>", data: "balance", class: "text-right"},
            {title: "<?php echo app_lang("gd_status"); ?>", data: "status"},
            {title: '<i data-feather="menu" class="icon-16"></i>', data: "options", class: "text-center option w100"}
        ],
        printColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        xlsColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
        summation: [
            {column: 7, dataType: "currency"},
            {column: 8, dataType: "currency"},
            {column: 9, dataType: "currency"}
        ]
    });
});
</script>
