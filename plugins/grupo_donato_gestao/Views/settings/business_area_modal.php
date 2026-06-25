<?php echo form_open(get_uri("grupo_donato/settings/business-areas/save"), ["id" => "gd-area-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo (int) $model_info->id; ?>" />

        <div class="form-group">
            <div class="row">
                <label for="code" class="col-md-3"><?php echo app_lang("gd_code"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "code", "name" => "code", "value" => $model_info->code, "class" => "form-control", "autofocus" => true, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="name" class="col-md-3"><?php echo app_lang("gd_name"); ?></label>
                <div class="col-md-9">
                    <?php echo form_input(["id" => "name", "name" => "name", "value" => $model_info->name, "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="unit_id" class="col-md-3"><?php echo app_lang("gd_unit"); ?></label>
                <div class="col-md-9">
                    <select name="unit_id" id="unit_id" class="form-control">
                        <?php foreach ($units_dropdown as $opt) {
                            $selected = ((string) $model_info->unit_id === (string) $opt["id"]) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars((string) $opt["id"], ENT_QUOTES) . "' $selected>" . htmlspecialchars((string) $opt["text"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</option>";
                        } ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-group">
            <div class="row">
                <label for="status" class="col-md-3"><?php echo app_lang("gd_status"); ?></label>
                <div class="col-md-9">
                    <select name="status" id="status" class="form-control">
                        <option value="active" <?php echo ($model_info->status === "active" || !$model_info->id) ? "selected" : ""; ?>><?php echo app_lang("gd_status_active"); ?></option>
                        <option value="inactive" <?php echo $model_info->status === "inactive" ? "selected" : ""; ?>><?php echo app_lang("gd_status_inactive"); ?></option>
                    </select>
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
        $("#gd-area-form").appForm({
            onSuccess: function (result) {
                $("#gd-areas-table").appTable({newData: result.data, dataId: result.id});
            }
        });
    });
</script>
