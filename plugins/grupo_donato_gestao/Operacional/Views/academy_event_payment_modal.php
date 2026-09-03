<?php
$participant = $participant ?? (object) [];
$event = $event ?? (object) [];
$receivable = $receivable ?? (object) [];
$paymentHistory = is_array($payment_history ?? null) ? $payment_history : [];
$balance = (float) ($receivable->balance_amount ?? 0);
$methods = \grupo_donato_gestao\Config\Constants::PAYMENT_METHODS;
?>
<?php echo form_open(get_uri("grupo_donato/operacional/event_payment"), ["id" => "gd-academy-event-payment-form", "class" => "general-form"]); ?>
<div class="modal-body">
    <input type="hidden" name="participant_id" value="<?php echo (int) ($participant->id ?? 0); ?>">
    <input type="hidden" name="reload_target" value="<?php echo esc((string) ($reload_target ?? "")); ?>">
    <div class="alert alert-info">
        <strong><?php echo esc((string) ($participant->athlete_name ?? "Atleta")); ?></strong>
        <br><small><?php echo esc((string) ($event->name ?? "Evento")); ?> · <?php echo esc((string) ($participant->category_name ?? "Categoria")); ?></small>
    </div>
    <div class="row">
        <div class="col-md-4 form-group">
            <label>Total da cobrança</label>
            <div class="form-control-plaintext">R$ <?php echo esc(number_format((float) ($receivable->original_amount ?? $participant->amount ?? 0), 2, ",", ".")); ?></div>
        </div>
        <div class="col-md-4 form-group">
            <label>Saldo em aberto</label>
            <div class="form-control-plaintext text-danger"><strong>R$ <?php echo esc(number_format($balance, 2, ",", ".")); ?></strong></div>
        </div>
        <div class="col-md-4 form-group">
            <label for="gd-academy-event-payment-amount">Valor pago</label>
            <input id="gd-academy-event-payment-amount" class="form-control" name="payment_amount" inputmode="decimal" autocomplete="off" value="<?php echo esc(number_format($balance, 2, ",", ".")); ?>" required>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 form-group">
            <label for="gd-academy-event-payment-date">Data que foi paga</label>
            <input id="gd-academy-event-payment-date" class="form-control" type="date" name="payment_date" value="<?php echo date("Y-m-d"); ?>" required>
        </div>
        <div class="col-md-6 form-group">
            <label for="gd-academy-event-payment-method">Forma de pagamento</label>
            <select id="gd-academy-event-payment-method" class="form-control" name="payment_method" required>
                <option value="">Selecione a forma</option>
                <?php foreach ($methods as $method): ?><option value="<?php echo esc($method); ?>"<?php echo $method === "pix" ? " selected" : ""; ?>><?php echo esc(app_lang("gd_finance_method_" . $method)); ?></option><?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="gd-academy-event-payment-notes">Observação</label>
        <textarea id="gd-academy-event-payment-notes" class="form-control" name="payment_notes" rows="3" placeholder="Opcional"></textarea>
    </div>
    <div class="text-muted">Um valor menor será registrado como pagamento parcial e manterá o saldo em aberto.</div>
    <?php if ($paymentHistory): ?>
        <hr>
        <h5>Histórico de pagamentos</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Data</th><th>Forma</th><th class="text-right">Valor</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($paymentHistory as $payment): ?>
                        <tr>
                            <td><?php echo esc(format_to_date($payment->payment_date, false)); ?></td>
                            <td><?php echo esc(app_lang("gd_finance_method_" . $payment->payment_method)); ?></td>
                            <td class="text-right">R$ <?php echo esc(number_format((float) ($payment->allocated_amount ?? 0), 2, ",", ".")); ?></td>
                            <td><?php echo esc((string) ($payment->payment_status ?? $payment->status ?? "")); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><i data-feather="check-circle" class="icon-16"></i> Baixar pagamento</button>
</div>
<?php echo form_close(); ?>
<script>
$(function () {
    var form = $("#gd-academy-event-payment-form");
    form.appForm({
        onSuccess: function () {
            $("#ajaxModal").modal("hide");
            window.location.reload();
        }
    });
});
</script>
