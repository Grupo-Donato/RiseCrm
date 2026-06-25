<?php
$preview = $preview ?? [];
$quality = $preview["qualidade_dados"] ?? [];
?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="row">
            <?php
            $cards = [
                "Responsáveis" => $preview["responsaveis"] ?? 0,
                "Alunos ativos" => $preview["alunos_ativos"] ?? 0,
                "Cancelados" => $preview["alunos_cancelados"] ?? 0,
                "Concluídos" => $preview["alunos_concluidos"] ?? 0,
                "Pagos" => $preview["pagamentos_pagos"] ?? 0,
                "Pendentes" => $preview["pagamentos_pendentes"] ?? 0,
                "Ignorados/sem registro" => $preview["pagamentos_ignorados"] ?? 0,
                "Materiais" => $preview["materiais"] ?? 0,
                "Presenças" => $preview["presencas"] ?? 0,
                "Leads/palestra" => $preview["presencas_palestra"] ?? 0,
                "Alunos novos" => $preview["alunos_criados"] ?? 0,
                "Alunos atualizados" => $preview["alunos_atualizados"] ?? 0
            ];
            ?>
            <?php foreach ($cards as $label => $value): ?>
                <div class="col-md-4 col-sm-6">
                    <div class="card dashboard-icon-widget">
                        <div class="card-body">
                            <div class="widget-details">
                                <h1><?php echo (int) $value; ?></h1>
                                <span><?php echo esc($label); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($preview["duplicidades"])): ?>
            <div class="alert alert-warning">
                <strong>Duplicidades</strong>
                <ul class="mb0">
                    <?php foreach ($preview["duplicidades"] as $item): ?>
                        <li><?php echo esc($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($preview["invalidos"])): ?>
            <div class="alert alert-danger">
                <strong>Registros inválidos</strong>
                <ul class="mb0">
                    <?php foreach ($preview["invalidos"] as $item): ?>
                        <li><?php echo esc($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($quality)): ?>
            <div class="alert alert-info">
                <strong>Alertas de qualidade do arquivo</strong>
                <pre class="mb0"><?php echo esc(json_encode($quality, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> Cancelar</button>
    <button type="button" id="bombeiros-confirmar-importacao" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> Confirmar importação</button>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-confirmar-importacao").on("click", function () {
            appLoader.show({container: ".modal-dialog"});
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/confirmar_importacao"); ?>",
                type: "POST",
                dataType: "json",
                success: function (result) {
                    appLoader.hide();
                    if (result.report_html) {
                        $(".modal-content").html(result.report_html);
                        feather.replace();
                    }
                    if (result.success) {
                        appAlert.success(result.message);
                        if (window.reloadGdOperationalTables) {
                            reloadGdOperationalTables();
                        }
                    } else {
                        appAlert.error(result.message);
                    }
                },
                error: function () {
                    appLoader.hide();
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });
        });
    });
</script>
