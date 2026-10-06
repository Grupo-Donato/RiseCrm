<?php
$aluno = $aluno ?? (object) [];
$data_padrao = $data_padrao ?? date("Y-m-d");
$itens = [
    "Camiseta" => "Uniforme / camiseta",
    "Caneleira" => "Caneleira",
    "Meião" => "Meião",
    "Passeio" => "Passeio",
    "Outro" => "Outro"
];
$status_options = [
    "Pendente" => "Pendente — baixar depois",
    "Pago" => "Pago agora"
];
$formas_pagamento = [
    "" => "Não informado",
    "PIX" => "PIX",
    "DINHEIRO" => "Dinheiro",
    "CARTAO_CREDITO" => "Cartão de crédito",
    "CARTAO_DEBITO" => "Cartão de débito",
    "BOLETO" => "Boleto",
    "TRANSFERENCIA" => "Transferência",
    "OUTRO" => "Outro"
];
?>

<style>
    #bombeiros-nova-cobranca-form .gd-cobranca-intro { margin-bottom: 17px; }
    #bombeiros-nova-cobranca-form .gd-cobranca-intro strong { display: block; color: #172554; font-size: 17px; }
    #bombeiros-nova-cobranca-form .gd-cobranca-intro small { color: #64748b; }
    #bombeiros-nova-cobranca-form .gd-cobranca-section { margin-top: 17px; padding-top: 15px; border-top: 1px solid #e5eaf0; }
    #bombeiros-nova-cobranca-form .gd-cobranca-section-title { margin-bottom: 12px; color: #334155; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    #bombeiros-nova-cobranca-form .gd-cobranca-paid-fields { display: none; }
</style>

<?php echo form_open(get_uri("grupo_donato/operacional/salvar_cobranca_avulsa"), ["id" => "bombeiros-nova-cobranca-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="gd-cobranca-intro">
            <strong><?php echo esc($aluno->nome_aluno ?? "Aluno"); ?></strong>
            <small>Crie uma cobrança independente. Lançamentos repetidos, como dois uniformes, permanecem no histórico.</small>
        </div>

        <?php echo form_hidden("aluno_id", (string) ((int) ($aluno->id ?? 0))); ?>

        <div class="row">
            <div class="form-group col-md-6">
                <label for="bombeiros-cobranca-item">Tipo</label>
                <?php echo form_dropdown("item", $itens, "Camiseta", ["id" => "bombeiros-cobranca-item", "class" => "form-control", "required" => true]); ?>
            </div>
            <div class="form-group col-md-6">
                <label for="bombeiros-cobranca-valor">Valor</label>
                <?php echo form_input(["id" => "bombeiros-cobranca-valor", "name" => "valor", "type" => "number", "step" => "0.01", "min" => "0.01", "class" => "form-control", "required" => true, "placeholder" => "0,00"]); ?>
            </div>
        </div>

        <div class="form-group">
            <label for="bombeiros-cobranca-descricao">Descrição</label>
            <?php echo form_input(["id" => "bombeiros-cobranca-descricao", "name" => "descricao", "class" => "form-control", "placeholder" => "Ex.: Uniforme tamanho M"]); ?>
        </div>

        <div class="row">
            <div class="form-group col-md-6">
                <label for="bombeiros-cobranca-vencimento">Data de vencimento</label>
                <?php echo form_input(["id" => "bombeiros-cobranca-vencimento", "name" => "vencimento", "type" => "date", "value" => $data_padrao, "class" => "form-control", "required" => true]); ?>
            </div>
            <div class="form-group col-md-6">
                <label for="bombeiros-cobranca-status">Status inicial</label>
                <?php echo form_dropdown("status", $status_options, "Pendente", ["id" => "bombeiros-cobranca-status", "class" => "form-control", "required" => true]); ?>
            </div>
        </div>

        <div id="bombeiros-cobranca-paid-fields" class="gd-cobranca-section gd-cobranca-paid-fields">
            <div class="gd-cobranca-section-title">Dados do pagamento</div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="bombeiros-cobranca-data-pagamento">Data do pagamento</label>
                    <?php echo form_input(["id" => "bombeiros-cobranca-data-pagamento", "name" => "data_pagamento", "type" => "date", "value" => $data_padrao, "class" => "form-control"]); ?>
                </div>
                <div class="form-group col-md-6">
                    <label for="bombeiros-cobranca-forma">Forma de pagamento</label>
                    <?php echo form_dropdown("forma_pagamento", $formas_pagamento, "", ["id" => "bombeiros-cobranca-forma", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group gd-cobranca-section">
            <label for="bombeiros-cobranca-observacao">Observação</label>
            <?php echo form_textarea(["id" => "bombeiros-cobranca-observacao", "name" => "observacao", "class" => "form-control", "rows" => 3, "placeholder" => "Opcional"]); ?>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="plus-circle" class="icon-16"></span> Lançar cobrança</button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        var $form = $("#bombeiros-nova-cobranca-form");
        var $status = $("#bombeiros-cobranca-status");
        var $paidFields = $("#bombeiros-cobranca-paid-fields");

        var updatePaidFields = function () {
            var paid = $status.val() === "Pago";
            $paidFields.toggle(paid);
            $("#bombeiros-cobranca-data-pagamento, #bombeiros-cobranca-forma").prop("disabled", !paid);
        };

        $status.on("change", updatePaidFields);
        updatePaidFields();

        $form.appForm({
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
                $("#ajaxModal").modal("hide");
            }
        });
        if (window.feather) {
            feather.replace();
        }
    });
</script>
