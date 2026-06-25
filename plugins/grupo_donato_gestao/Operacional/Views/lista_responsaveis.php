<div class="table-responsive">
    <table id="bombeiros-responsaveis-table" class="display" cellspacing="0" width="100%"></table>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-responsaveis-table")) {
            $("#bombeiros-responsaveis-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/responsaveis_list_data"); ?>",
                order: [[1, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "ID", "class": "w80"},
                    {title: "Nome", "class": "all"},
                    {title: "CPF", "class": "w130"},
                    {title: "WhatsApp", "class": "all w140"},
                    {title: "Celular", "class": "w140"},
                    {title: "E-mail"},
                    {title: "Endereço"},
                    {title: "<i data-feather='menu' class='icon-16'></i>", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6]
            });
        }
    });
</script>
