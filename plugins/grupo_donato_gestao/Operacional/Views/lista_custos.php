<div class="page-title clearfix">
    <div class="title-button-group skip-dropdown-migration">
        <?php echo modal_anchor(get_uri("grupo_donato/operacional/custo_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> Novo custo", ["class" => "btn btn-default", "title" => "Novo custo"]); ?>
    </div>
</div>

<div class="gd-mobile-filter-panel p15">
    <div class="row">
        <div class="col-sm-6">
            <label for="bombeiros-custos-mobile-status">Status</label>
            <?php
            echo form_dropdown("bombeiros_custos_mobile_status", [
                "" => "Todos os status",
                "Pago" => "Pago",
                "Previsto" => "Previsto",
                "Cancelado" => "Cancelado"
            ], "", ["id" => "bombeiros-custos-mobile-status", "class" => "form-control"]);
            ?>
        </div>
        <div class="col-sm-6">
            <label for="bombeiros-custos-mobile-categoria">Categoria</label>
            <?php
            echo form_dropdown("bombeiros_custos_mobile_categoria", [
                "" => "Todas as categorias",
                "Aluguel" => "Aluguel",
                "Equipe" => "Equipe",
                "Marketing" => "Marketing",
                "Materiais" => "Materiais",
                "Operacional" => "Operacional",
                "Impostos" => "Impostos",
                "Outros" => "Outros"
            ], "", ["id" => "bombeiros-custos-mobile-categoria", "class" => "form-control"]);
            ?>
        </div>
        <div class="col-sm-12 gd-mobile-filter-actions">
            <button type="button" id="bombeiros-custos-mobile-filtrar" class="btn btn-primary"><i data-feather="filter" class="icon-16"></i> Filtrar</button>
            <button type="button" id="bombeiros-custos-mobile-limpar" class="btn btn-default"><i data-feather="x" class="icon-16"></i> Limpar</button>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="bombeiros-custos-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    window.initBombeirosCustosTable = function () {
        if (!$("#bombeiros-custos-table").length || !$.fn.DataTable || !$.fn.appTable) {
            return;
        }

        if (!$.fn.DataTable.isDataTable("#bombeiros-custos-table")) {
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
            markTableLoading("#bombeiros-custos-table");

            $("#bombeiros-custos-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/custos_list_data"); ?>",
                order: [[2, "desc"]],
                tableRefreshButton: true,
                filterDropdown: [
                    {
                        name: "status",
                        class: "w160",
                        options: [
                            {id: "", text: "Todos os status"},
                            {id: "Pago", text: "Pago"},
                            {id: "Previsto", text: "Previsto"},
                            {id: "Cancelado", text: "Cancelado"}
                        ]
                    },
                    {
                        name: "categoria",
                        class: "w180",
                        options: [
                            {id: "", text: "Todas as categorias"},
                            {id: "Aluguel", text: "Aluguel"},
                            {id: "Equipe", text: "Equipe"},
                            {id: "Marketing", text: "Marketing"},
                            {id: "Materiais", text: "Materiais"},
                            {id: "Operacional", text: "Operacional"},
                            {id: "Impostos", text: "Impostos"},
                            {id: "Outros", text: "Outros"}
                        ]
                    }
                ],
                columns: [
                    {title: "Descrição", "class": "all"},
                    {title: "Categoria", "class": "w140"},
                    {title: "Data", "class": "w120"},
                    {title: "Competência", "class": "w120"},
                    {title: "Valor", "class": "all text-right w120"},
                    {title: "Status", "class": "text-center w100"},
                    {title: "Pagamento", "class": "w140"},
                    {title: "Observação"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6, 7],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6, 7]
            });
        } else {
            $("#bombeiros-custos-table").DataTable().columns.adjust();
        }
    };

    $(document).ready(function () {
        window.initBombeirosCustosTable();

        var applyBombeirosCustosMobileFilters = function () {
            var settings = window.InstanceCollection ? window.InstanceCollection["bombeiros-custos-table"] : null;
            if (settings) {
                settings.filterParams.status = $("#bombeiros-custos-mobile-status").val();
                settings.filterParams.categoria = $("#bombeiros-custos-mobile-categoria").val();
            }

            if (window.reloadBombeirosTable) {
                reloadBombeirosTable("#bombeiros-custos-table");
            } else {
                $("#bombeiros-custos-table").appTable({reload: true});
            }
        };

        $("body").off("click", "#bombeiros-custos-mobile-filtrar").on("click", "#bombeiros-custos-mobile-filtrar", function () {
            applyBombeirosCustosMobileFilters();
        });

        $("body").off("click", "#bombeiros-custos-mobile-limpar").on("click", "#bombeiros-custos-mobile-limpar", function () {
            $("#bombeiros-custos-mobile-status,#bombeiros-custos-mobile-categoria").val("");
            applyBombeirosCustosMobileFilters();
        });
    });
</script>
