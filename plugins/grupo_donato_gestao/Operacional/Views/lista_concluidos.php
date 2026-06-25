<div class="table-responsive">
    <table id="bombeiros-concluidos-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-concluidos-table")) {
            $("#bombeiros-concluidos-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/concluidos_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all"},
                    {title: "Responsável"},
                    {title: "Turma", "class": "w120"},
                    {title: "Início", "class": "w120"},
                    {title: "Mensalidade", "class": "text-right w120"},
                    {title: "Status", "class": "text-center w100"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6]
            });
        }
    });
</script>
