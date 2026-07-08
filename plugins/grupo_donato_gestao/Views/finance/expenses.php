<?php echo view("grupo_donato_gestao\\Views\\components\\cash_nav", ["active" => "expenses"]); ?>
<div class="page-title clearfix">
    <h1><?php echo app_lang('gd_finance_expenses'); ?></h1>
    <div class="title-button-group"><?php if($can_manage)echo modal_anchor(get_uri('grupo_donato/finance/expense-modal'),'<i data-feather="plus-circle" class="icon-16"></i> '.app_lang('add'),['class'=>'btn btn-default','title'=>app_lang('gd_finance_expense')]); ?></div>
</div>
<div class="row" id="gd-expenses-resumo">
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-info"><i data-feather="file-text" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_lancadas">0</h1>
                    <span>Lançadas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-success"><i data-feather="check-circle" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_pagas">0</h1>
                    <span>Pagas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-warning"><i data-feather="clock" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_em_aberto">0</h1>
                    <span>Em aberto</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-danger"><i data-feather="alert-triangle" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_vencidas">0</h1>
                    <span>Vencidas</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-success"><i data-feather="dollar-sign" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_pago_formatado">R$ 0,00</h1>
                    <span>Total pago</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-warning"><i data-feather="trending-down" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_a_pagar_formatado">R$ 0,00</h1>
                    <span>Total a pagar</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card dashboard-icon-widget">
            <div class="card-body">
                <div class="widget-icon bg-primary"><i data-feather="bar-chart-2" class="icon"></i></div>
                <div class="widget-details">
                    <h1 data-resumo="total_lancado_formatado">R$ 0,00</h1>
                    <span>Total lançado</span>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="card"><div class="table-responsive"><table id="expenses-table" class="display" width="100%"></table></div></div>
<script>
$(document).ready(function(){"use strict";
    $("#expenses-table").appTable({source:'<?php echo_uri('grupo_donato/finance/expenses/data'); ?>',columns:[{title:'<?php echo app_lang('gd_finance_number'); ?>',data:'number'},{title:'<?php echo app_lang('gd_finance_description'); ?>',data:'description'},{title:'<?php echo app_lang('gd_finance_payee'); ?>',data:'payee'},{title:'<?php echo app_lang('gd_date'); ?>',data:'date'},{title:'<?php echo app_lang('gd_finance_due'); ?>',data:'due'},{title:'<?php echo app_lang('gd_finance_amount'); ?>',data:'amount'},{title:'<?php echo app_lang('gd_status'); ?>',data:'status'},{title:'<?php echo app_lang('gd_finance_account'); ?>',data:'account'},{title:'',data:'options',class:'option w75'}]});
    window.reloadExpensesResumo=function(){appAjaxRequest({url:'<?php echo_uri('grupo_donato/finance/expenses/summary'); ?>',type:'POST',dataType:'json',success:function(result){if(!result.success)return;$.each(result.data,function(key,value){$("#gd-expenses-resumo").find("[data-resumo='"+key+"']").text(value);});}});};
    reloadExpensesResumo();
    $("#expenses-table").on("draw.dt",function(){reloadExpensesResumo();});
});
</script>
