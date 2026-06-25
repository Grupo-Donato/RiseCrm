<?php
$formas_pagamento = [
    "" => "-",
    "BOLETO" => "BOLETO",
    "CRÉDITO" => "CRÉDITO",
    "DÉBITO" => "DÉBITO",
    "PIX" => "PIX"
];
?>

<?php echo form_open(get_uri("grupo_donato/operacional/gerar_comprovante"), ["id" => "bombeiros-comprovante-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="cobranca_id" value="<?php echo (int) get_array_value($model_info, "cobranca_id"); ?>" />
        <input type="hidden" name="aluno_id" value="<?php echo (int) get_array_value($model_info, "aluno_id"); ?>" />

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-responsavel" class="col-md-3">Responsável</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-comprovante-responsavel", "name" => "responsavel_nome", "value" => get_array_value($model_info, "responsavel_nome"), "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-cpf" class="col-md-3">CPF</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-comprovante-cpf", "name" => "responsavel_cpf", "value" => get_array_value($model_info, "responsavel_cpf"), "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-comprovante-data-emissao" class="col-md-3">Data emissão</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-comprovante-data-emissao", "name" => "data_emissao", "type" => "date", "value" => get_array_value($model_info, "data_emissao"), "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-aluno" class="col-md-3">Aluno</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-comprovante-aluno", "name" => "aluno_nome", "value" => get_array_value($model_info, "aluno_nome"), "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-aluno-adicional" class="col-md-3">Aluno adicional</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-comprovante-aluno-adicional", "name" => "aluno_nome_adicional", "value" => "", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-mensalidade" class="col-md-3">Mensalidade</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-comprovante-mensalidade", "name" => "mensalidade_numero", "type" => "number", "min" => "1", "max" => "6", "value" => get_array_value($model_info, "mensalidade_numero"), "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-comprovante-valor" class="col-md-2">Valor</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-comprovante-valor", "name" => "valor", "value" => get_array_value($model_info, "valor"), "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-forma" class="col-md-3">Forma de pagamento</label>
                <div class="col-md-9">
                    <?php echo form_dropdown("forma_pagamento", $formas_pagamento, "", ["id" => "bombeiros-comprovante-forma", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-comprovante-conferido" class="col-md-3">Conferido por</label>
                <div class="col-md-5">
                    <?php echo form_input(["id" => "bombeiros-comprovante-conferido", "name" => "conferido_por", "value" => get_array_value($model_info, "conferido_por"), "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-comprovante-data-conferencia" class="col-md-2">Data</label>
                <div class="col-md-2">
                    <?php echo form_input(["id" => "bombeiros-comprovante-data-conferencia", "name" => "data_conferencia", "type" => "date", "value" => get_array_value($model_info, "data_conferencia"), "class" => "form-control"]); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="file-text" class="icon-16"></span> Gerar comprovante</button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-comprovante-form").appForm({
            onSuccess: function (result) {
                if (result.download_url) {
                    window.open(result.download_url, "_blank");
                }
                if (result.pdf_url) {
                    $(".modal-footer .bombeiros-baixar-pdf").remove();
                    $(".modal-footer").prepend("<a href='" + result.pdf_url + "' target='_blank' class='btn btn-default bombeiros-baixar-pdf'><span data-feather='download' class='icon-16'></span> Baixar PDF</a>");
                    feather.replace();
                }
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
