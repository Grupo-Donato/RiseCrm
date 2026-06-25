<?php
$formas_pagamento = [
    "" => "-",
    "PIX" => "PIX",
    "DINHEIRO" => "Dinheiro",
    "CARTAO_CREDITO" => "Cartão de crédito",
    "CARTAO_DEBITO" => "Cartão de débito",
    "BOLETO" => "Boleto",
    "TRANSFERENCIA" => "Transferência",
    "OUTRO" => "Outro"
];

$data_pagamento = !empty($model_info->data_pagamento) ? date("Y-m-d", strtotime($model_info->data_pagamento)) : date("Y-m-d");
?>

<?php echo form_open(get_uri("grupo_donato/operacional/baixar_pagamento"), ["id" => "bombeiros-baixa-pagamento-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>" />

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Aluno</label>
                <div class="col-md-9">
                    <div class="form-control-plaintext"><?php echo esc($model_info->nome_aluno ?? "-"); ?></div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label class="col-md-3">Competência</label>
                <div class="col-md-9">
                    <div class="form-control-plaintext"><?php echo esc($model_info->competencia ?? "-"); ?></div>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-baixa-valor" class="col-md-3">Valor pago</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-baixa-valor", "name" => "valor", "value" => number_format((float) ($model_info->valor ?? 0), 2, ",", "."), "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-baixa-data" class="col-md-2">Data</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-baixa-data", "name" => "data_pagamento", "type" => "date", "value" => $data_pagamento, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-baixa-forma" class="col-md-3">Forma</label>
                <div class="col-md-9">
                    <?php echo form_dropdown("forma_pagamento", $formas_pagamento, $model_info->forma_pagamento ?? "", ["id" => "bombeiros-baixa-forma", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-baixa-observacao" class="col-md-3">Observação</label>
                <div class="col-md-9">
                    <?php echo form_textarea(["id" => "bombeiros-baixa-observacao", "name" => "observacao", "value" => $model_info->observacao ?? "", "class" => "form-control", "rows" => 3]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> Baixar pagamento</button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-baixa-pagamento-form").appForm({
            onSuccess: function () {
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
            }
        });
    });
</script>
