<div class="table-responsive">
    <table id="bombeiros-cancelados-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-cancelados-table")) {
            $("#bombeiros-cancelados-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/cancelados_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all"},
                    {title: "Responsável"},
                    {title: "Cancelamento", "class": "w120"},
                    {title: "Motivo"},
                    {title: "Observação"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5],
                xlsColumns: [0, 1, 2, 3, 4, 5]
            });
        }

        $("body").off("click", ".bombeiros-reativar-aluno").on("click", ".bombeiros-reativar-aluno", function () {
            var id = $(this).data("id");

            $(this).appConfirmation({
                title: "Reativar este aluno?",
                btnConfirmLabel: "Reativar",
                btnCancelLabel: "Cancelar",
                onConfirm: function () {
                    appAjaxRequest({
                        url: "<?php echo_uri("grupo_donato/operacional/reativar_aluno"); ?>",
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
