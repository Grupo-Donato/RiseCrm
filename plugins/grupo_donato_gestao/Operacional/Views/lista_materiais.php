<div class="table-responsive">
    <table id="bombeiros-materiais-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-materiais-table")) {
            $("#bombeiros-materiais-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/materiais_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all"},
                    {title: "Turma", "class": "w120"},
                    {title: "Uniforme", "class": "text-center w110"},
                    {title: "Material 01", "class": "text-center w110"},
                    {title: "Material 02", "class": "text-center w110"},
                    {title: "Observação"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6]
            });
        }

        $("body").off("click", ".bombeiros-atualizar-material").on("click", ".bombeiros-atualizar-material", function () {
            var id = $(this).data("id");
            var item = $(this).data("item");
            var status = $(this).data("status");

            $(this).appConfirmation({
                title: "Atualizar material?",
                btnConfirmLabel: "Confirmar",
                btnCancelLabel: "Cancelar",
                onConfirm: function () {
                    appAjaxRequest({
                        url: "<?php echo_uri("grupo_donato/operacional/atualizar_material"); ?>",
                        type: "POST",
                        dataType: "json",
                        data: {id: id, item: item, status: status},
                        success: function (result) {
                            if (result.success) {
                                appAlert.success(result.message);
                                if (window.reloadBombeirosTable) {
                                    reloadBombeirosTable("#bombeiros-materiais-table");
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
