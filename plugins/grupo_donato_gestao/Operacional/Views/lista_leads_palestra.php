<div class="page-title clearfix">
    <div class="title-button-group skip-dropdown-migration">
        <?php echo modal_anchor(get_uri("grupo_donato/operacional/lead_palestra_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> Novo lead", ["class" => "btn btn-default", "title" => "Novo lead"]); ?>
    </div>
</div>

<div class="gd-mobile-filter-panel p15">
    <div class="row">
        <div class="col-sm-8">
            <label for="bombeiros-leads-palestra-mobile-status">Status</label>
            <?php
            echo form_dropdown("bombeiros_leads_palestra_mobile_status", [
                "" => "Todos os status",
                "compareceu_palestra" => "Compareceu",
                "matriculado" => "Matriculado",
                "nao_matriculado" => "Não matriculado",
                "em_negociacao" => "Em negociação",
                "perdido" => "Perdido",
                "sem_status" => "Sem status"
            ], "", ["id" => "bombeiros-leads-palestra-mobile-status", "class" => "form-control"]);
            ?>
        </div>
        <div class="col-sm-4 gd-mobile-filter-actions">
            <button type="button" id="bombeiros-leads-palestra-mobile-filtrar" class="btn btn-primary"><i data-feather="filter" class="icon-16"></i> Filtrar</button>
            <button type="button" id="bombeiros-leads-palestra-mobile-limpar" class="btn btn-default"><i data-feather="x" class="icon-16"></i> Limpar</button>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table id="bombeiros-leads-palestra-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-leads-palestra-table")) {
            $("#bombeiros-leads-palestra-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/leads_palestra_list_data"); ?>",
                order: [[5, "desc"]],
                tableRefreshButton: true,
                filterDropdown: [
                    {
                        name: "status",
                        class: "w180",
                        options: [
                            {id: "", text: "Todos os status"},
                            {id: "compareceu_palestra", text: "Compareceu"},
                            {id: "matriculado", text: "Matriculado"},
                            {id: "nao_matriculado", text: "Não matriculado"},
                            {id: "em_negociacao", text: "Em negociação"},
                            {id: "perdido", text: "Perdido"},
                            {id: "sem_status", text: "Sem status"}
                        ]
                    }
                ],
                columns: [
                    {title: "Responsável", "class": "all"},
                    {title: "Aluno"},
                    {title: "Telefone", "class": "all w140"},
                    {title: "Status", "class": "text-center w140"},
                    {title: "Matrícula", "class": "text-center w110"},
                    {title: "Evento", "class": "w120"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5],
                xlsColumns: [0, 1, 2, 3, 4, 5]
            });
        }

        var applyBombeirosLeadsPalestraMobileFilters = function () {
            var settings = window.InstanceCollection ? window.InstanceCollection["bombeiros-leads-palestra-table"] : null;
            if (settings) {
                settings.filterParams.status = $("#bombeiros-leads-palestra-mobile-status").val();
            }

            if (window.reloadBombeirosTable) {
                reloadBombeirosTable("#bombeiros-leads-palestra-table");
            } else {
                $("#bombeiros-leads-palestra-table").appTable({reload: true});
            }
        };

        $("body").off("click", "#bombeiros-leads-palestra-mobile-filtrar").on("click", "#bombeiros-leads-palestra-mobile-filtrar", function () {
            applyBombeirosLeadsPalestraMobileFilters();
        });

        $("body").off("click", "#bombeiros-leads-palestra-mobile-limpar").on("click", "#bombeiros-leads-palestra-mobile-limpar", function () {
            $("#bombeiros-leads-palestra-mobile-status").val("");
            applyBombeirosLeadsPalestraMobileFilters();
        });

        $("body").off("click", ".bombeiros-converter-lead").on("click", ".bombeiros-converter-lead", function () {
            var id = $(this).data("id");

            $(this).appConfirmation({
                title: "Converter lead em aluno?",
                btnConfirmLabel: "Converter",
                btnCancelLabel: "Cancelar",
                onConfirm: function () {
                    appAjaxRequest({
                        url: "<?php echo_uri("grupo_donato/operacional/converter_lead_em_aluno"); ?>",
                        type: "POST",
                        dataType: "json",
                        data: {id: id},
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
    });
</script>
