<?php
$model_info = $model_info ?? (object) [];
$types = [
    "championship" => "Campeonato", "cup" => "Copa", "tournament" => "Torneio",
    "friendly" => "Amistoso", "festival" => "Festival", "single_game" => "Jogo isolado",
    "official" => "Competição oficial", "unofficial" => "Competição não oficial", "other" => "Outro",
];
$statuses = ["draft" => "Rascunho", "registrations_open" => "Inscrições abertas", "confirmed" => "Confirmado", "in_progress" => "Em andamento", "completed" => "Finalizado", "cancelled" => "Cancelado"];
?>
<?php echo form_open(get_uri("grupo_donato/operacional/save_event"), ["id" => "gd-academy-event-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>">
    <input type="hidden" name="lock_version" value="<?php echo (int) ($model_info->lock_version ?? 1); ?>">
    <div class="form-group"><label>Nome</label><input class="form-control" name="name" value="<?php echo esc($model_info->name ?? ""); ?>" required></div>
    <div class="row">
        <div class="col-md-6 form-group"><label>Tipo</label><?php echo form_dropdown("event_type", $types, $model_info->event_type ?? "other", ["class" => "form-control"]); ?></div>
        <div class="col-md-6 form-group"><label>Status</label><?php echo form_dropdown("status", $statuses, $model_info->status ?? "draft", ["class" => "form-control"]); ?></div>
    </div>
    <div class="form-group"><label>Descrição</label><textarea class="form-control" name="description" rows="2"><?php echo esc($model_info->description ?? ""); ?></textarea></div>
    <div class="row">
        <div class="col-md-6 form-group"><label>Data inicial</label><input type="date" class="form-control" name="starts_on" value="<?php echo esc($model_info->starts_on ?? date("Y-m-d")); ?>" required></div>
        <div class="col-md-6 form-group"><label>Data final</label><input type="date" class="form-control" name="ends_on" value="<?php echo esc($model_info->ends_on ?? ""); ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-6 form-group"><label>Horário</label><input type="time" class="form-control" name="event_time" value="<?php echo esc(substr((string) ($model_info->event_time ?? ""), 0, 5)); ?>"></div>
        <div class="col-md-6 form-group"><label>Apresentação</label><input type="time" class="form-control" name="presentation_time" value="<?php echo esc(substr((string) ($model_info->presentation_time ?? ""), 0, 5)); ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-6 form-group"><label>Local</label><input class="form-control" name="location" value="<?php echo esc($model_info->location ?? ""); ?>"></div>
        <div class="col-md-6 form-group"><label>Organização / promotor</label><input class="form-control" name="organizer" value="<?php echo esc($model_info->organizer ?? ""); ?>"></div>
    </div>
    <div class="form-group"><label>Endereço</label><input class="form-control" name="address" value="<?php echo esc($model_info->address ?? ""); ?>"></div>
    <div class="row">
        <div class="col-md-6 form-group"><label>Valor padrão por atleta (R$)</label><input class="form-control" name="default_participation_amount" inputmode="decimal" value="<?php echo esc($model_info->default_participation_amount ?? "0.00"); ?>"></div>
        <div class="col-md-6 form-group"><label>Regulamento / anexo</label><input class="form-control" name="regulation_path" value="<?php echo esc($model_info->regulation_path ?? ""); ?>" placeholder="Caminho do arquivo, se houver"></div>
    </div>
    <div class="form-group"><label>Observações</label><textarea class="form-control" name="notes" rows="2"><?php echo esc($model_info->notes ?? ""); ?></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal">Fechar</button><button type="submit" class="btn btn-primary">Salvar evento</button></div>
<?php echo form_close(); ?>
<script>
$(function(){ $("#gd-academy-event-form").appForm({onSuccess:function(result){ if(result.success){ window.hardReloadGdOperational ? window.hardReloadGdOperational(0) : window.location.reload(); } }}); });
</script>
