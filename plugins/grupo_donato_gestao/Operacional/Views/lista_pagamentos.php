<?php
$meses_pagamento = [
    ["id" => "1", "text" => "Janeiro"],
    ["id" => "2", "text" => "Fevereiro"],
    ["id" => "3", "text" => "Março"],
    ["id" => "4", "text" => "Abril"],
    ["id" => "5", "text" => "Maio"],
    ["id" => "6", "text" => "Junho"],
    ["id" => "7", "text" => "Julho"],
    ["id" => "8", "text" => "Agosto"],
    ["id" => "9", "text" => "Setembro"],
    ["id" => "10", "text" => "Outubro"],
    ["id" => "11", "text" => "Novembro"],
    ["id" => "12", "text" => "Dezembro"]
];

$ano_atual = (int) date("Y");
$anos_pagamento = [];
for ($ano = $ano_atual - 3; $ano <= $ano_atual + 2; $ano++) {
    $anos_pagamento[] = ["id" => (string) $ano, "text" => (string) $ano];
}

$status_pagamento = [
    ["id" => "", "text" => "Todos"],
    ["id" => "pago", "text" => "Pago"],
    ["id" => "aberto", "text" => "Em aberto"],
    ["id" => "vencido", "text" => "Vencido"]
];

$turmas_pagamento = [
    ["id" => "", "text" => "Todas as turmas"],
    ["id" => "08:30-11:00", "text" => "08:30-11:00"],
    ["id" => "13:30-16:00", "text" => "13:30-16:00"]
];
$pagamento_select_options = function ($items) {
    $options = [];
    foreach ($items as $item) {
        $options[$item["id"]] = $item["text"];
    }

    return $options;
};
?>

<div class="p20">
    <div class="page-title clearfix">
        <h4>Controle mensal de pagamentos</h4>
        <div class="title-button-group skip-dropdown-migration">
            <button type="button" id="bombeiros-gerar-cobrancas-mes" class="btn btn-default">
                <i data-feather="plus-circle" class="icon-16"></i> Gerar cobranças do mês
            </button>
        </div>
    </div>

    <div class="text-off mb15">Pagamentos Mensais</div>

    <div class="gd-mobile-filter-panel p15 mb15">
        <div class="row">
            <div class="col-sm-6">
                <label for="bombeiros-pagamentos-mobile-mes">Mês</label>
                <?php echo form_dropdown("bombeiros_pagamentos_mobile_mes", $pagamento_select_options($meses_pagamento), (string) (int) date("m"), ["id" => "bombeiros-pagamentos-mobile-mes", "class" => "form-control"]); ?>
            </div>
            <div class="col-sm-6">
                <label for="bombeiros-pagamentos-mobile-ano">Ano</label>
                <?php echo form_dropdown("bombeiros_pagamentos_mobile_ano", $pagamento_select_options($anos_pagamento), (string) $ano_atual, ["id" => "bombeiros-pagamentos-mobile-ano", "class" => "form-control"]); ?>
            </div>
            <div class="col-sm-6">
                <label for="bombeiros-pagamentos-mobile-status">Status</label>
                <?php echo form_dropdown("bombeiros_pagamentos_mobile_status", $pagamento_select_options($status_pagamento), "", ["id" => "bombeiros-pagamentos-mobile-status", "class" => "form-control"]); ?>
            </div>
            <div class="col-sm-6">
                <label for="bombeiros-pagamentos-mobile-turma">Turma</label>
                <?php echo form_dropdown("bombeiros_pagamentos_mobile_turma", $pagamento_select_options($turmas_pagamento), "", ["id" => "bombeiros-pagamentos-mobile-turma", "class" => "form-control"]); ?>
            </div>
            <div class="col-sm-12 gd-mobile-filter-actions">
                <button type="button" id="bombeiros-pagamentos-mobile-filtrar" class="btn btn-primary"><i data-feather="filter" class="icon-16"></i> Filtrar</button>
                <button type="button" id="bombeiros-pagamentos-mobile-limpar" class="btn btn-default"><i data-feather="x" class="icon-16"></i> Limpar</button>
            </div>
        </div>
    </div>

    <div class="row" id="bombeiros-pagamentos-resumo">
        <div class="col-md-3 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-info"><i data-feather="users" class="icon"></i></div>
                    <div class="widget-details">
                        <h1 data-resumo="total_alunos">0</h1>
                        <span>Com cobrança</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-success"><i data-feather="check-circle" class="icon"></i></div>
                    <div class="widget-details">
                        <h1 data-resumo="total_pagos">0</h1>
                        <span>Pagos</span>
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
                        <h1 data-resumo="total_vencidos">0</h1>
                        <span>Vencidos</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-success"><i data-feather="dollar-sign" class="icon"></i></div>
                    <div class="widget-details">
                        <h1 data-resumo="total_recebido_formatado">R$ 0,00</h1>
                        <span>Total recebido</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-warning"><i data-feather="trending-up" class="icon"></i></div>
                    <div class="widget-details">
                        <h1 data-resumo="total_a_receber_formatado">R$ 0,00</h1>
                        <span>Total a receber</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="card dashboard-icon-widget">
                <div class="card-body">
                    <div class="widget-icon bg-primary"><i data-feather="bar-chart-2" class="icon"></i></div>
                    <div class="widget-details">
                        <h1 data-resumo="valor_previsto_formatado">R$ 0,00</h1>
                        <span>Valor lançado do mês</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table id="bombeiros-pagamentos-table" class="display" cellspacing="0" width="100%"></table>
    </div>
</div>

<script type="text/javascript">
    window.initBombeirosPagamentosTable = function () {
        if (!$("#bombeiros-pagamentos-table").length || !$.fn.DataTable || !$.fn.appTable) {
            return;
        }

        var dedupePagamentoRows = function () {
            var $table = $("#bombeiros-pagamentos-table");
            if (!$table.length || !$.fn.DataTable.isDataTable("#bombeiros-pagamentos-table") || $table.data("gdDedupingRows")) {
                return;
            }

            var table = $table.DataTable();
            var seen = {};
            var duplicateIndexes = [];

            table.rows().every(function (rowIndex) {
                var data = this.data() || [];
                var $actions = $("<div>").html(data[13] || "");
                var cobrancaId = $actions.find("[data-id]").first().attr("data-id") ||
                    $actions.find("[data-post-id]").first().attr("data-post-id") ||
                    $actions.find("[data-post-cobranca_id]").first().attr("data-post-cobranca_id");
                var alunoId = $actions.find("[data-aluno-id]").first().attr("data-aluno-id");
                var rowKey = cobrancaId ? "cobranca:" + cobrancaId : (alunoId ? "aluno:" + alunoId + ":" + data[5] : data.join("|"));

                if (seen[rowKey]) {
                    duplicateIndexes.push(rowIndex);
                } else {
                    seen[rowKey] = true;
                }
            });

            if (duplicateIndexes.length) {
                $table.data("gdDedupingRows", true);
                table.rows(duplicateIndexes).remove().draw(false);
                $table.data("gdDedupingRows", false);
            }
        };

        $("#bombeiros-pagamentos-table")
            .off("draw.dt.gdPagamentosDedupe")
            .on("draw.dt.gdPagamentosDedupe", function () {
                setTimeout(dedupePagamentoRows, 0);
            });

        if (!$.fn.DataTable.isDataTable("#bombeiros-pagamentos-table")) {
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
            markTableLoading("#bombeiros-pagamentos-table");

            $("#bombeiros-pagamentos-table").appTable({
                source: "<?php echo_uri("grupo_donato/operacional/pagamentos_list_data"); ?>",
                order: [[1, "asc"]],
                stateSave: false,
                tableRefreshButton: true,
                onRelaodCallback: function () {
                    if (window.reloadBombeirosPagamentosResumo) {
                        reloadBombeirosPagamentosResumo();
                    }
                },
                filterDropdown: [
                    {
                        name: "mes_referencia",
                        class: "w150",
                        value: "<?php echo (int) date("m"); ?>",
                        options: <?php echo json_encode($meses_pagamento); ?>
                    },
                    {
                        name: "ano_referencia",
                        class: "w120",
                        value: "<?php echo $ano_atual; ?>",
                        options: <?php echo json_encode($anos_pagamento); ?>
                    },
                    {
                        name: "status_pagamento",
                        class: "w150",
                        options: <?php echo json_encode($status_pagamento); ?>
                    },
                    {
                        name: "turma",
                        class: "w160",
                        options: <?php echo json_encode($turmas_pagamento); ?>
                    }
                ],
                columns: [
                    {title: "Matrícula", "class": "w90"},
                    {title: "Aluno", "class": "all w180"},
                    {title: "Responsável", "class": "w180"},
                    {title: "WhatsApp", "class": "w130"},
                    {title: "Turma/Pelotão", "class": "w140"},
                    {title: "Competência", "class": "w120"},
                    {title: "Parcela/Descrição", "class": "w170"},
                    {title: "Vencimento", "class": "w110"},
                    {title: "Valor", "class": "text-right w120"},
                    {title: "Status", "class": "text-center w120"},
                    {title: "Data pagamento", "class": "w130"},
                    {title: "Forma", "class": "w130"},
                    {title: "Observação", "class": "w180"},
                    {title: "Ações", "class": "all text-center option w220"}
                ],
                printColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12],
                xlsColumns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
            });
        } else {
            $("#bombeiros-pagamentos-table").DataTable().columns.adjust();
        }
    };

    $(document).ready(function () {
        window.initBombeirosPagamentosTable();

        var getPagamentoFilterValue = function (name, fallback) {
            var settings = window.InstanceCollection ? window.InstanceCollection["bombeiros-pagamentos-table"] : null;
            if (settings && settings.filterParams && typeof settings.filterParams[name] !== "undefined") {
                return settings.filterParams[name];
            }

            var $field = $("#bombeiros-pagamentos-table_wrapper").find("[name='" + name + "']");
            return $field.length ? $field.val() : fallback;
        };

        var reloadPagamentos = function () {
            if (window.reloadBombeirosTable) {
                reloadBombeirosTable("#bombeiros-pagamentos-table");
                reloadBombeirosTable("#bombeiros-inadimplencia-table");
            }
            if (window.reloadBombeirosPagamentosResumo) {
                reloadBombeirosPagamentosResumo();
            }
            if (window.reloadBombeirosFinanceiro) {
                reloadBombeirosFinanceiro();
            }
        };

        window.reloadBombeirosPagamentosResumo = function () {
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/pagamentos_mensais_resumo"); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    mes_referencia: getPagamentoFilterValue("mes_referencia", "<?php echo (int) date("m"); ?>"),
                    ano_referencia: getPagamentoFilterValue("ano_referencia", "<?php echo $ano_atual; ?>"),
                    turma: getPagamentoFilterValue("turma", "")
                },
                success: function (result) {
                    if (!result.success) {
                        return;
                    }

                    $.each(result.data, function (key, value) {
                        $("#bombeiros-pagamentos-resumo").find("[data-resumo='" + key + "']").text(value);
                    });
                }
            });
        };

        window.reloadBombeirosPagamentosResumo();

        var applyBombeirosPagamentosMobileFilters = function () {
            var settings = window.InstanceCollection ? window.InstanceCollection["bombeiros-pagamentos-table"] : null;
            if (settings) {
                settings.filterParams.mes_referencia = $("#bombeiros-pagamentos-mobile-mes").val();
                settings.filterParams.ano_referencia = $("#bombeiros-pagamentos-mobile-ano").val();
                settings.filterParams.status_pagamento = $("#bombeiros-pagamentos-mobile-status").val();
                settings.filterParams.turma = $("#bombeiros-pagamentos-mobile-turma").val();
            }

            reloadPagamentos();
        };

        $("body").off("click", "#bombeiros-pagamentos-mobile-filtrar").on("click", "#bombeiros-pagamentos-mobile-filtrar", function () {
            applyBombeirosPagamentosMobileFilters();
        });

        $("body").off("click", "#bombeiros-pagamentos-mobile-limpar").on("click", "#bombeiros-pagamentos-mobile-limpar", function () {
            $("#bombeiros-pagamentos-mobile-mes").val("<?php echo (int) date("m"); ?>");
            $("#bombeiros-pagamentos-mobile-ano").val("<?php echo $ano_atual; ?>");
            $("#bombeiros-pagamentos-mobile-status,#bombeiros-pagamentos-mobile-turma").val("");
            applyBombeirosPagamentosMobileFilters();
        });

        $("body").off("click", "#bombeiros-gerar-cobrancas-mes").on("click", "#bombeiros-gerar-cobrancas-mes", function () {
            var $button = $(this);
            $button.attr("disabled", "disabled");
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/gerar_mensalidades_periodo"); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    mes_referencia: getPagamentoFilterValue("mes_referencia", "<?php echo (int) date("m"); ?>"),
                    ano_referencia: getPagamentoFilterValue("ano_referencia", "<?php echo $ano_atual; ?>")
                },
                success: function (result) {
                    $button.removeAttr("disabled");
                    if (result.success) {
                        appAlert.success(result.message);
                        reloadPagamentos();
                    } else {
                        appAlert.error(result.message);
                    }
                },
                error: function () {
                    $button.removeAttr("disabled");
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });
        });

        $("body").off("click", ".bombeiros-criar-cobranca-mensal").on("click", ".bombeiros-criar-cobranca-mensal", function () {
            var $link = $(this);
            $link.addClass("disabled");
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/criar_cobranca_mensal_aluno"); ?>",
                type: "POST",
                dataType: "json",
                data: {
                    aluno_id: $link.data("aluno-id"),
                    mes_referencia: getPagamentoFilterValue("mes_referencia", $link.data("mes")),
                    ano_referencia: getPagamentoFilterValue("ano_referencia", $link.data("ano"))
                },
                success: function (result) {
                    if (result.success) {
                        appAlert.success(result.message);
                        reloadPagamentos();
                    } else {
                        appAlert.error(result.message);
                        $link.removeClass("disabled");
                    }
                },
                error: function () {
                    $link.removeClass("disabled");
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });

            return false;
        });

        $("body").off("click", ".bombeiros-marcar-pendente").on("click", ".bombeiros-marcar-pendente", function () {
            var $link = $(this);
            $link.addClass("disabled");
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/marcar_pagamento_pendente"); ?>",
                type: "POST",
                dataType: "json",
                data: {id: $link.data("id")},
                success: function (result) {
                    if (result.success) {
                        appAlert.success(result.message);
                        reloadPagamentos();
                    } else {
                        appAlert.error(result.message);
                        $link.removeClass("disabled");
                    }
                },
                error: function () {
                    $link.removeClass("disabled");
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });

            return false;
        });
    });
</script>
