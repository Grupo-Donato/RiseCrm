<?php
$receivable = $receivable ?? null;
$rental = $rental ?? $receivable;
$methods = is_array($methods ?? null) ? $methods : [];
$reference = (string) ($reference_month ?? '');
$reference_label = preg_match('/^(\d{4})-(\d{2})$/', $reference)
    ? substr($reference, 5, 2) . '/' . substr($reference, 0, 4)
    : ($reference ?: '-');
$balance = (string) ($receivable->balance_amount ?? '0.00');
$reload_target = (string) ($reload_target ?? '');
?>
<?php echo form_open(get_uri('grupo_donato/finance/rental-payments/save'), ['id' => 'gd-rental-payment-form', 'class' => 'general-form']); ?>
<div class="modal-body">
    <input type="hidden" name="receivable_id" value="<?php echo (int) ($receivable->id ?? 0); ?>">
    <input type="hidden" name="competence" value="<?php echo esc($reference); ?>">
    <input type="hidden" name="reload_target" value="<?php echo esc($reload_target); ?>">

    <div class="alert alert-info">
        <strong><?php echo esc((string) ($rental->rental_number ?? 'Churrasqueira')); ?></strong>
        <?php if (!empty($rental->rental_title)) { ?>
            <br><small><?php echo esc((string) $rental->rental_title); ?></small>
        <?php } ?>
    </div>

    <div class="row">
        <div class="col-md-6 form-group">
            <label for="gd-rental-payment-customer">Pessoa que alugou</label>
            <input id="gd-rental-payment-customer" name="customer_name" class="form-control" value="<?php echo esc((string) ($renter_name ?? $customer_name ?? '')); ?>" readonly required>
        </div>
        <div class="col-md-6 form-group">
            <label>Competência</label>
            <div class="form-control-plaintext"><strong><?php echo esc($reference_label); ?></strong></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 form-group">
            <label>Total da cobrança</label>
            <div class="form-control-plaintext">R$ <?php echo esc(number_format((float) ($receivable->original_amount ?? 0), 2, ',', '.')); ?></div>
        </div>
        <div class="col-md-4 form-group">
            <label>Saldo em aberto</label>
            <div class="form-control-plaintext text-danger"><strong>R$ <?php echo esc(number_format((float) $balance, 2, ',', '.')); ?></strong></div>
        </div>
        <div class="col-md-4 form-group">
            <label for="gd-rental-payment-amount">Valor pago</label>
            <input id="gd-rental-payment-amount" name="amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo esc(number_format((float) $balance, 2, ',', '.')); ?>" required>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 form-group">
            <label for="gd-rental-payment-date">Data que foi paga</label>
            <input id="gd-rental-payment-date" type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        <div class="col-md-6 form-group">
            <label for="gd-rental-payment-method">Forma de pagamento</label>
            <select id="gd-rental-payment-method" name="payment_method" class="form-control" required>
                <option value="">Selecione a forma</option>
                <?php foreach ($methods as $method) { ?>
                    <option value="<?php echo esc($method); ?>"<?php echo $method === 'pix' ? ' selected' : ''; ?>><?php echo esc(app_lang('gd_finance_method_' . $method)); ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label for="gd-rental-payment-notes">Observação</label>
        <textarea id="gd-rental-payment-notes" name="notes" class="form-control" rows="3" placeholder="Opcional"></textarea>
    </div>

    <div class="text-muted">
        Informe o saldo total para quitar a cobrança. Um valor menor será registrado como pagamento parcial e manterá o saldo em aberto.
    </div>

    <?php if (!empty($payment_history)) { ?>
        <hr>
        <h5>Histórico de pagamentos</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Data</th><th>Forma</th><th class="text-right">Valor</th><th>Tipo</th></tr></thead>
                <tbody>
                <?php foreach ($payment_history as $payment) { ?>
                    <tr>
                        <td><?php echo esc(format_to_date($payment->payment_date, false)); ?></td>
                        <td><?php echo esc(app_lang('gd_finance_method_' . $payment->payment_method)); ?></td>
                        <td class="text-right">R$ <?php echo esc(number_format((float) $payment->allocated_amount, 2, ',', '.')); ?></td>
                        <td><?php echo esc((string) ($payment->payment_type ?? 'regular') === 'deposit' ? app_lang('gd_finance_deposit') : app_lang('gd_finance_regular_payment')); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> Baixar pagamento</button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    var form = $('#gd-rental-payment-form');
    form.appForm({
        onSuccess: function (result) {
            $('#ajaxModal').modal('hide');
            var target = form.find('[name="reload_target"]').val();
            if (target && $('#' + target).length && $.fn.appTable) {
                $('#' + target).appTable({reload: true});
            } else if (window.reloadBombeirosTable) {
                reloadBombeirosTable('#bombeiros-pagamentos-table');
            }
            if (window.reloadBombeirosPagamentosResumo) window.reloadBombeirosPagamentosResumo();
            appAlert.success(result.message || 'Pagamento baixado com sucesso.');
        }
    });
});
</script>
