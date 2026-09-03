<?php
$event = $event ?? (object) [];
$metrics = is_array($metrics ?? null) ? $metrics : [];
$participants = is_array($participants ?? null) ? $participants : [];
$money = static fn($value): string => "R$ " . number_format((float) ($value ?? 0), 2, ",", ".");
$eventId = (int) ($event->id ?? 0);
$categories = [];
foreach ($participants as $participant) {
    if ((int) ($participant->category_id ?? 0) > 0) $categories[(int) $participant->category_id] = (string) ($participant->category_name ?? "Categoria");
}
$statusOptions = [
    ["id" => "", "text" => "Todos"],
    ["id" => "pending_generation", "text" => "Pendente de cobrança"],
    ["id" => "open", "text" => "Em aberto"],
    ["id" => "partial", "text" => "Parcial"],
    ["id" => "paid", "text" => "Pago"],
    ["id" => "overdue", "text" => "Vencido"],
    ["id" => "exempt", "text" => "Isento"],
    ["id" => "courtesy", "text" => "Cortesia"],
    ["id" => "cancelled", "text" => "Cancelado"],
];
$categoryOptions = [["id" => "", "text" => "Todas as categorias"]];
foreach ($categories as $categoryId => $categoryName) $categoryOptions[] = ["id" => (string) $categoryId, "text" => $categoryName];
?>

<style>
    .gd-academy-event-finance .gd-mobile-filter-panel { display: none; }
    .gd-academy-event-finance .gd-academy-table { min-width: 980px; }
    .gd-academy-event-finance .dataTables_wrapper { color: var(--academy-text); }
    .gd-academy-event-finance .dataTables_wrapper .dataTables_filter input,
    .gd-academy-event-finance .dataTables_wrapper .dataTables_length select,
    .gd-academy-event-finance .dataTables_wrapper .filter-section-container select { background: var(--academy-surface-2); border-color: var(--academy-line); color: var(--academy-text); }
    .gd-academy-event-finance .dataTables_wrapper .dataTables_info,
    .gd-academy-event-finance .dataTables_wrapper .dataTables_filter label,
    .gd-academy-event-finance .dataTables_wrapper .dataTables_length label { color: var(--academy-muted); }
    .gd-academy-event-finance .dataTables_wrapper .dataTables_paginate .paginate_button { color: var(--academy-muted) !important; }
    .gd-academy-event-finance .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .gd-academy-event-finance .dataTables_wrapper .dataTables_paginate .paginate_button:hover { background: var(--academy-surface-2) !important; border-color: var(--academy-line) !important; color: var(--academy-text) !important; }
    .gd-academy-event-finance .gd-finance-help { color: var(--academy-muted); font-size: 13px; margin: -5px 0 15px; }
    @media (max-width: 767.98px) {
        .gd-academy-event-finance .gd-mobile-filter-panel { background: var(--academy-surface); border: 1px solid var(--academy-line); border-radius: 10px; display: block; margin-bottom: 15px; padding: 14px; }
        .gd-academy-event-finance .gd-mobile-filter-panel .form-control,
        .gd-academy-event-finance .gd-mobile-filter-panel .btn { width: 100%; }
        .gd-academy-event-finance .gd-mobile-filter-actions { display: grid; gap: 8px; grid-template-columns: 1fr 1fr; margin-top: 4px; }
        .gd-academy-event-finance .filter-section-container,
        .gd-academy-event-finance .dataTables_filter,
        .gd-academy-event-finance .dataTables_length { display: none; }
        .gd-academy-event-finance .gd-academy-table { min-width: 0; }
        .gd-academy-event-finance .gd-academy-table td:last-child .btn { margin: 3px 4px 0 0; }
    }
</style>

<div class="gd-academy-event-finance">
    <div class="gd-academy-section-title">
        <div>
            <h2>Pagamentos do evento</h2>
            <p class="gd-academy-muted mb0">Use a mesma rotina financeira das demais áreas: gerar cobrança, baixar parcial ou total, consultar comprovante e desfazer baixa.</p>
        </div>
    </div>

    <div class="row">
        <?php foreach ([["Previsto", $metrics["expected_amount"] ?? 0], ["Recebido", $metrics["received_amount"] ?? 0], ["Em aberto", $metrics["open_amount"] ?? 0], ["Vencido", $metrics["overdue_amount"] ?? 0]] as $kpi): ?>
            <div class="col-6 col-md-3 mb10"><div class="gd-academy-kpi"><span><?php echo esc($kpi[0]); ?></span><strong><?php echo $money($kpi[1]); ?></strong></div></div>
        <?php endforeach; ?>
    </div>

    <p class="gd-finance-help">A cobrança continua sendo um lançamento próprio do evento dentro da conta familiar do responsável.</p>

    <div class="gd-mobile-filter-panel">
        <div class="row">
            <div class="col-sm-6 mb10"><label for="gd-event-finance-mobile-status">Status</label><select id="gd-event-finance-mobile-status" class="form-control"><option value="">Todos</option><?php foreach (array_slice($statusOptions, 1) as $option): ?><option value="<?php echo esc($option["id"]); ?>"><?php echo esc($option["text"]); ?></option><?php endforeach; ?></select></div>
            <div class="col-sm-6 mb10"><label for="gd-event-finance-mobile-category">Categoria</label><select id="gd-event-finance-mobile-category" class="form-control"><?php foreach ($categoryOptions as $option): ?><option value="<?php echo esc($option["id"]); ?>"><?php echo esc($option["text"]); ?></option><?php endforeach; ?></select></div>
            <div class="col-12 gd-mobile-filter-actions"><button type="button" id="gd-event-finance-mobile-filter" class="btn btn-primary"><i data-feather="filter" class="icon-16"></i> Filtrar</button><button type="button" id="gd-event-finance-mobile-clear" class="btn btn-default"><i data-feather="x" class="icon-16"></i> Limpar</button></div>
        </div>
    </div>

    <div class="gd-academy-table-wrap">
        <table id="gd-academy-event-finance-table" class="display gd-academy-table" cellspacing="0" width="100%"></table>
    </div>
</div>

<script>
$(function () {
    var selector = "#gd-academy-event-finance-table";
    var tableUrl = "<?php echo get_uri("grupo_donato/operacional/event_finance_list_data"); ?>?event_id=<?php echo $eventId; ?>";
    var statusOptions = <?php echo json_encode($statusOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var categoryOptions = <?php echo json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var labels = ["Atleta", "Responsável", "Categoria", "Descrição", "Valor", "Vencimento", "Status", "Data pagamento", "Forma", "Ações"];

    window.reloadGdAcademyEventFinanceTable = function () {
        if (window.reloadBombeirosTable) {
            window.reloadBombeirosTable(selector);
        } else if ($.fn.appTable && $(selector).length) {
            $(selector).appTable({ reload: true });
        }
    };

    if (!$(selector).length || !$.fn.appTable) return;
    $(selector).appTable({
        source: tableUrl,
        order: [[0, "asc"]],
        stateSave: false,
        tableRefreshButton: true,
        filterDropdown: [
            { name: "status_pagamento", class: "w170", options: statusOptions },
            { name: "category_id", class: "w170", options: categoryOptions }
        ],
        columns: [
            { title: "Atleta", class: "all w170" },
            { title: "Responsável", class: "w160" },
            { title: "Categoria", class: "w120" },
            { title: "Descrição", class: "w220" },
            { title: "Valor", class: "w120" },
            { title: "Vencimento", class: "w110" },
            { title: "Status", class: "text-center w140" },
            { title: "Data pagamento", class: "w130" },
            { title: "Forma", class: "w130" },
            { title: "Ações", class: "all text-center option w250" }
        ],
        createdRow: function (row) {
            $(row).children("td").each(function (index) {
                $(this).attr("data-label", labels[index] || "");
            });
        }
    });

    var filterValue = function (name, fallback) {
        var settings = window.InstanceCollection ? window.InstanceCollection["gd-academy-event-finance-table"] : null;
        if (settings && settings.filterParams && typeof settings.filterParams[name] !== "undefined") return settings.filterParams[name];
        return fallback;
    };
    var applyMobileFilters = function () {
        var settings = window.InstanceCollection ? window.InstanceCollection["gd-academy-event-finance-table"] : null;
        if (settings) {
            settings.filterParams.status_pagamento = $("#gd-event-finance-mobile-status").val();
            settings.filterParams.category_id = $("#gd-event-finance-mobile-category").val();
        }
        window.reloadGdAcademyEventFinanceTable();
    };

    $(document).off("click.gdAcademyEventFinance", "#gd-event-finance-mobile-filter").on("click.gdAcademyEventFinance", "#gd-event-finance-mobile-filter", applyMobileFilters);
    $(document).off("click.gdAcademyEventFinance", "#gd-event-finance-mobile-clear").on("click.gdAcademyEventFinance", "#gd-event-finance-mobile-clear", function () {
        $("#gd-event-finance-mobile-status,#gd-event-finance-mobile-category").val("");
        applyMobileFilters();
    });
});
</script>
