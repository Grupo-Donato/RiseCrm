<?php
$participant = $participant ?? (object) [];
$event = $event ?? (object) [];
$amount = (float) ($participant->amount ?? 0);
?>
<?php echo form_open(get_uri("grupo_donato/operacional/event_charge"), ["id" => "gd-academy-event-charge-form", "class" => "general-form"]); ?>
<div class="modal-body">
    <input type="hidden" name="participant_id" value="<?php echo (int) ($participant->id ?? 0); ?>">
    <div class="alert alert-info">
        <strong><?php echo esc((string) ($participant->athlete_name ?? "Atleta")); ?></strong>
        <br><small><?php echo esc((string) ($event->name ?? "Evento")); ?> · <?php echo esc((string) ($participant->category_name ?? "Categoria")); ?></small>
    </div>
    <div class="row">
        <div class="col-md-6 form-group">
            <label>Valor da participação</label>
            <input class="form-control" name="amount" inputmode="decimal" value="<?php echo esc(number_format($amount, 2, ",", ".")); ?>" required>
        </div>
        <div class="col-md-6 form-group">
            <label>Estratégia de cobrança</label>
            <select class="form-control" name="charge_strategy" required>
                <option value="open">Deixar em aberto</option>
                <option value="immediate">Vencimento hoje</option>
                <option value="next_closing">Próximo fechamento</option>
            </select>
        </div>
    </div>
    <div class="form-group">
        <label for="gd-academy-event-charge-due-date">Vencimento</label>
        <input id="gd-academy-event-charge-due-date" class="form-control" type="date" name="due_date" value="<?php echo esc((string) ($participant->due_date ?? "")); ?>">
        <small class="text-muted">Se ficar em branco, o sistema calcula conforme a estratégia escolhida.</small>
    </div>
    <div class="form-group">
        <label>Observação</label>
        <textarea class="form-control" name="notes" rows="3" placeholder="Opcional"></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><i data-feather="file-plus" class="icon-16"></i> Gerar cobrança</button>
</div>
<?php echo form_close(); ?>
<script>
$(function () {
    var form = $("#gd-academy-event-charge-form");
    form.appForm({
        onSuccess: function () {
            $("#ajaxModal").modal("hide");
            window.location.reload();
        }
    });
});
</script>
