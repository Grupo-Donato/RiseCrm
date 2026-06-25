<div class="p20">
    <div class="row">
        <div class="col-md-4 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-success">
                        <i data-feather="check-circle" class="icon"></i>
                    </div>
                    <div class="widget-details">
                        <h1><?php echo to_currency($total_pago, "R$"); ?></h1>
                        <span>Total recebido</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-warning">
                        <i data-feather="clock" class="icon"></i>
                    </div>
                    <div class="widget-details">
                        <h1><?php echo to_currency($total_pendente, "R$"); ?></h1>
                        <span>Total pendente</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-danger">
                        <i data-feather="alert-triangle" class="icon"></i>
                    </div>
                    <div class="widget-details">
                        <h1><?php echo to_currency($total_inadimplencia, "R$"); ?></h1>
                        <span><?php echo (int) $total_parcelas_atraso; ?> parcelas em atraso</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="page-title clearfix">
            <h4>Inadimplência</h4>
        </div>
        <div class="table-responsive">
            <table id="bombeiros-inadimplencia-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        if (!$.fn.DataTable.isDataTable("#bombeiros-inadimplencia-table")) {
            $("#bombeiros-inadimplencia-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/inadimplencia_list_data"); ?>",
                order: [[2, "asc"]],
                tableRefreshButton: true,
                columns: [
                    {title: "Aluno", "class": "all"},
                    {title: "Responsável"},
                    {title: "Vencimento", "class": "w120"},
                    {title: "Competência", "class": "w120"},
                    {title: "Valor", "class": "all text-right w120"},
                    {title: "Cobrança", "class": "all text-center option w100"}
                ],
                printColumns: [0, 1, 2, 3, 4],
                xlsColumns: [0, 1, 2, 3, 4]
            });
        }
    });
</script>
