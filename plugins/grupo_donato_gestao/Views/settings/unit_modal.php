<?php echo form_open(get_uri("grupo_donato/settings/units/save"), ["id" => "gd-unit-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="name" class="col-md-3"><?php echo app_lang("gd_unit_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "name", "name" => "name", "value" => $model_info->name, "class" => "form-control", "placeholder" => app_lang("gd_unit_name"), "autofocus" => true, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="legal_name" class="col-md-3"><?php echo app_lang("gd_legal_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "legal_name", "name" => "legal_name", "value" => $model_info->legal_name, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="document" class="col-md-3"><?php echo app_lang("gd_document"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "document", "name" => "document", "value" => $model_info->document, "class" => "form-control"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="timezone" class="col-md-3"><?php echo app_lang("gd_timezone"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "timezone", "name" => "timezone", "value" => $model_info->timezone, "class" => "form-control", "placeholder" => "America/Sao_Paulo"]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="status" class="col-md-3"><?php echo app_lang("gd_status"); ?></label>
                <div class="col-md-9">
                    <select name="status" id="status" class="form-control">
                        <?php foreach ($status_dropdown as $opt) {
                            $selected = ($model_info->status === $opt["id"]) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars((string) $opt["id"], ENT_QUOTES) . "' $selected>" . htmlspecialchars((string) $opt["text"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</option>";
                        } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="is_default" class="col-md-3"><?php echo app_lang("gd_default_unit"); ?></label>
                <div class="col-md-9">
                    <?php echo form_checkbox("is_default", "1", (int) $model_info->is_default === 1, "id='is_default' class='form-check-input'"); ?>
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
        $("#gd-unit-form").appForm({
            onSuccess: function (result) {
                $("#gd-units-table").appTable({newData: result.data, dataId: result.id});
            }
        });
    });
</script>
