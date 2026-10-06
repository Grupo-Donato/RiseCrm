<?php
$model_info = $model_info ?? (object) [];
$open_charges = is_array($open_charges ?? null) ? $open_charges : [];
$selected_charge_id = (int) ($selected_charge_id ?? 0);
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

<style>
    #bombeiros-baixa-pagamento-form .gd-baixa-intro { margin-bottom: 16px; }
    #bombeiros-baixa-pagamento-form .gd-baixa-intro strong { display: block; font-size: 17px; color: #172554; }
    #bombeiros-baixa-pagamento-form .gd-baixa-intro small { color: #64748b; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charges { display: grid; gap: 8px; max-height: 285px; overflow-y: auto; margin-bottom: 16px; padding: 2px; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge { display: flex; align-items: flex-start; gap: 10px; padding: 11px 12px; border: 1px solid #e5eaf0; border-radius: 9px; cursor: pointer; background: #fff; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge:has(input:checked) { border-color: #3b82f6; background: #eff6ff; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge input { flex: 0 0 auto; margin-top: 3px; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge-main { min-width: 0; flex: 1; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge-title { display: flex; justify-content: space-between; gap: 10px; color: #334155; font-weight: 600; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge-title strong { white-space: nowrap; color: #172554; }
    #bombeiros-baixa-pagamento-form .gd-baixa-charge-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; margin-top: 3px; color: #64748b; font-size: 11px; }
    #bombeiros-baixa-pagamento-form .gd-baixa-total { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 0 0 16px; padding: 11px 12px; border-radius: 9px; background: #f1f5f9; color: #475569; }
    #bombeiros-baixa-pagamento-form .gd-baixa-total strong { color: #172554; font-size: 17px; }
    @media (max-width: 575px) {
        #bombeiros-baixa-pagamento-form .gd-baixa-charge-title { display: block; }
        #bombeiros-baixa-pagamento-form .gd-baixa-charge-title strong { display: block; margin-top: 4px; }
        #bombeiros-baixa-pagamento-form .gd-baixa-fields .col-md-3,
        #bombeiros-baixa-pagamento-form .gd-baixa-fields .col-md-4,
        #bombeiros-baixa-pagamento-form .gd-baixa-fields .col-md-9 { width: 100%; }
        #bombeiros-baixa-pagamento-form .gd-baixa-fields .form-group { margin-bottom: 12px; }
    }
</style>

<?php echo form_open(get_uri("grupo_donato/operacional/baixar_pagamento"), ["id" => "bombeiros-baixa-pagamento-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="gd-baixa-intro">
            <strong><?php echo esc($model_info->nome_aluno ?? "Aluno"); ?></strong>
            <small><?php echo count($open_charges); ?> cobrança(s) em aberto. Selecione as que estão sendo pagas agora.</small>
        </div>

        <div class="gd-baixa-charges" role="group" aria-label="Cobranças em aberto">
            <?php foreach ($open_charges as $charge): ?>
                <?php
                $charge_id = (int) ($charge->id ?? 0);
                $charge_value = (float) ($charge->valor ?? 0);
                $charge_description = trim((string) ($charge->descricao ?? "")) ?: trim((string) ($charge->tipo ?? "Cobrança"));
                $charge_status = (string) ($charge->status ?? "Pendente");
                ?>
                <label class="gd-baixa-charge">
                    <input type="checkbox" name="ids[]" value="<?php echo $charge_id; ?>" data-charge-value="<?php echo number_format($charge_value, 2, ".", ""); ?>" <?php echo $charge_id === $selected_charge_id ? "checked" : ""; ?> />
                    <span class="gd-baixa-charge-main">
                        <span class="gd-baixa-charge-title">
                            <span><?php echo esc($charge_description); ?></span>
                            <strong>R$ <?php echo number_format($charge_value, 2, ",", "."); ?></strong>
                        </span>
                        <span class="gd-baixa-charge-meta">
                            <span>Competência: <?php echo esc($charge->competencia ?? "-"); ?></span>
                            <span>Vencimento: <?php echo !empty($charge->vencimento) ? esc(date("d/m/Y", strtotime($charge->vencimento))) : "-"; ?></span>
                            <span><?php echo esc($charge_status); ?></span>
                        </span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="gd-baixa-total">
            <span>Total selecionado</span>
            <strong id="bombeiros-baixa-total">R$ 0,00</strong>
        </div>

        <div class="gd-baixa-fields">
            <div class="form-group">
                <div class="row">
                    <label for="bombeiros-baixa-data" class="col-md-3">Data</label>
                    <div class="col-md-9">
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
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" id="bombeiros-baixa-submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> Baixar selecionados</button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        var $form = $("#bombeiros-baixa-pagamento-form");
        var formatMoney = function (value) {
            return value.toLocaleString("pt-BR", {style: "currency", currency: "BRL"});
        };
        var updateSelectedTotal = function () {
            var total = 0;
            var selected = 0;
            $form.find("input[name='ids[]']:checked").each(function () {
                total += parseFloat($(this).data("charge-value")) || 0;
                selected++;
            });
            $("#bombeiros-baixa-total").text(formatMoney(total));
            $("#bombeiros-baixa-submit").prop("disabled", selected === 0);
        };

        $form.on("change", "input[name='ids[]']", updateSelectedTotal);
        updateSelectedTotal();
        if (window.feather) {
            feather.replace();
        }

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
            }
        });
    });
</script>
