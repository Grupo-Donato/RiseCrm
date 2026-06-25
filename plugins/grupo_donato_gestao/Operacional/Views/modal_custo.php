<?php
$categorias_dropdown = [
    "Aluguel" => "Aluguel",
    "Equipe" => "Equipe",
    "Marketing" => "Marketing",
    "Materiais" => "Materiais",
    "Operacional" => "Operacional",
    "Impostos" => "Impostos",
    "Outros" => "Outros"
];
$status_dropdown = [
    "Pago" => "Pago",
    "Previsto" => "Previsto",
    "Cancelado" => "Cancelado"
];
$mes_dropdown = [
    1 => "Janeiro",
    2 => "Fevereiro",
    3 => "Março",
    4 => "Abril",
    5 => "Maio",
    6 => "Junho",
    7 => "Julho",
    8 => "Agosto",
    9 => "Setembro",
    10 => "Outubro",
    11 => "Novembro",
    12 => "Dezembro"
];
$ano_selecionado = (int) ($model_info->ano_referencia ?? date("Y"));
$ano_dropdown = [];
for ($ano = (int) date("Y") - 3; $ano <= (int) date("Y") + 2; $ano++) {
    $ano_dropdown[$ano] = $ano;
}
$ano_dropdown[$ano_selecionado] = $ano_selecionado;
ksort($ano_dropdown);
?>

<?php echo form_open(get_uri("grupo_donato/operacional/save_custo"), ["id" => "bombeiros-custo-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>" />

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-descricao" class="col-md-3">Descrição</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-custo-descricao", "name" => "descricao", "value" => $model_info->descricao ?? "", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-categoria" class="col-md-3">Categoria</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("categoria", $categorias_dropdown, $model_info->categoria ?? "Operacional", ["id" => "bombeiros-custo-categoria", "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-custo-status" class="col-md-2">Status</label>
                <div class="col-md-3">
                    <?php echo form_dropdown("status", $status_dropdown, $model_info->status ?? "Pago", ["id" => "bombeiros-custo-status", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-valor" class="col-md-3">Valor</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-custo-valor", "name" => "valor", "value" => isset($model_info->valor) && $model_info->valor !== "" ? number_format((float) $model_info->valor, 2, ",", ".") : "", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
                <label for="bombeiros-custo-data" class="col-md-2">Data</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-custo-data", "name" => "data_custo", "type" => "date", "value" => $model_info->data_custo ?? date("Y-m-d"), "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-mes" class="col-md-3">Competência</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("mes_referencia", $mes_dropdown, (int) ($model_info->mes_referencia ?? date("m")), ["id" => "bombeiros-custo-mes", "class" => "form-control"]); ?>
                </div>
                <div class="col-md-3">
                    <?php echo form_dropdown("ano_referencia", $ano_dropdown, $ano_selecionado, ["id" => "bombeiros-custo-ano", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-pagamento" class="col-md-3">Pagamento</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-custo-pagamento", "name" => "forma_pagamento", "value" => $model_info->forma_pagamento ?? "", "class" => "form-control", "placeholder" => "PIX, cartão, dinheiro, boleto..."]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-custo-observacao" class="col-md-3">Observação</label>
                <div class="col-md-9">
                    <?php echo form_textarea(["id" => "bombeiros-custo-observacao", "name" => "observacao", "value" => $model_info->observacao ?? "", "class" => "form-control", "rows" => 3]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-custo-form").appForm({
            onSuccess: function (result) {
                if (result.success) {
                    $("#bombeiros-custos-table").appTable({newData: result.data, dataId: result.id});
                    if (window.reloadGdOperationalTables) {
                        reloadGdOperationalTables();
                    }
                }
            }
        });
    });
</script>
