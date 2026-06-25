<?php echo form_open(get_uri("grupo_donato/operacional/importar_preview"), ["id" => "bombeiros-import-form", "class" => "general-form", "role" => "form", "enctype" => "multipart/form-data"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="form-group">
            <div class="row">
                <label for="bombeiros-import-file" class="col-md-3">Arquivo</label>
                <div class="col-md-9">
                    <input type="file" id="bombeiros-import-file" name="file" class="form-control" accept=".json,application/json" data-rule-required="true" data-msg-required="<?php echo esc(app_lang("field_required"), "attr"); ?>" />
                    <span class="help-block">Aceita o JSON compatível antigo e o JSON original normalizado da planilha. A importação só grava após a confirmação da prévia.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><span data-feather="search" class="icon-16"></span> Gerar prévia</button>
</div>
<?php echo form_close(); ?>

<script type="text/javascript">
    $(document).ready(function () {
        $("#bombeiros-import-form").appForm({
            onSuccess: function (result) {
                if (result.preview_html) {
                    $(".modal-content").html(result.preview_html);
                    feather.replace();
                }
            }
        });
    });
</script>
