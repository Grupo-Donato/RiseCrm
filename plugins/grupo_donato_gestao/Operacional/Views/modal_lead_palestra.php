<?php
$status_dropdown = [
    "compareceu_palestra" => "Compareceu",
    "matriculado" => "Matriculado",
    "nao_matriculado" => "Não matriculado",
    "em_negociacao" => "Em negociação",
    "perdido" => "Perdido",
    "sem_status" => "Sem status"
];
?>

<?php echo form_open(get_uri("grupo_donato/operacional/save_lead_palestra"), ["id" => "bombeiros-lead-palestra-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) ($model_info->id ?? 0); ?>" />

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-lead-responsavel" class="col-md-3">Responsável</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-lead-responsavel", "name" => "responsavel_nome", "value" => $model_info->responsavel_nome ?? "", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-lead-aluno" class="col-md-3">Aluno</label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "bombeiros-lead-aluno", "name" => "aluno_nome", "value" => $model_info->aluno_nome ?? "", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-lead-telefone" class="col-md-3">Telefone</label>
                <div class="col-md-4">
                    <?php echo form_input(["id" => "bombeiros-lead-telefone", "name" => "telefone", "value" => $model_info->telefone ?? "", "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-lead-evento" class="col-md-2">Evento</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-lead-evento", "name" => "data_evento", "type" => "date", "value" => $model_info->data_evento ?? date("Y-m-d"), "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-lead-status" class="col-md-3">Status</label>
                <div class="col-md-4">
                    <?php echo form_dropdown("status", $status_dropdown, $model_info->status ?? "compareceu_palestra", ["id" => "bombeiros-lead-status", "class" => "form-control"]); ?>
                </div>
                <label for="bombeiros-lead-origem" class="col-md-2">Origem</label>
                <div class="col-md-3">
                    <?php echo form_input(["id" => "bombeiros-lead-origem", "name" => "origem", "value" => $model_info->origem ?? "manual", "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="bombeiros-lead-observacao" class="col-md-3">Observação</label>
                <div class="col-md-9">
                    <?php echo form_textarea(["id" => "bombeiros-lead-observacao", "name" => "observacao", "value" => $model_info->observacao ?? "", "class" => "form-control", "rows" => 3]); ?>
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
        $("#bombeiros-lead-palestra-form").appForm({
            onSuccess: function (result) {
                if (result.success) {
                    $("#bombeiros-leads-palestra-table").appTable({newData: result.data, dataId: result.id});
                    if (window.reloadGdOperationalTables) {
                        reloadGdOperationalTables();
                    }
                }
            }
        });
    });
</script>
