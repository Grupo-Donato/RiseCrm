<div class="table-responsive">
    <table id="bombeiros-unidades-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    window.initBombeirosUnidadesTable = function () {
        if (!$("#bombeiros-unidades-table").length || !$.fn.DataTable || !$.fn.appTable) {
            return;
        }

        if (!$.fn.DataTable.isDataTable("#bombeiros-unidades-table")) {
            var markTableLoading = window.markBombeirosTableLoading || function (selector) {
                var $table = $(selector);
                if (!$table.length) {
                    return;
                }

                $table.data("gdTableLoading", true);
                $table.off(".gdTableLoading").on("draw.dt.gdTableLoading error.dt.gdTableLoading", function () {
                    $table.off(".gdTableLoading");
                    $table.data("gdTableLoading", false);
                    $table.data("gdTableLoaded", true);
                });
            };
            markTableLoading("#bombeiros-unidades-table");

            $("#bombeiros-unidades-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/unidades_list_data"); ?>",
                order: [[0, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Unidade", "class": "all"},
                    {title: "Slug", "class": "w180"},
                    {title: "Cidade", "class": "w180"},
                    {title: "Endereço"},
                    {title: "Contexto", "class": "text-center w100"},
                    {title: "Status", "class": "all text-center w100"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5],
                xlsColumns: [0, 1, 2, 3, 4, 5]
            });
        } else {
            $("#bombeiros-unidades-table").DataTable().columns.adjust();
        }
    };

    $(document).ready(function () {
        window.initBombeirosUnidadesTable();
    });
</script>
