<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$id = (int) ($model_info->id ?? 0);
$render_select = static function (string $name, array $options, $current) use ($e) {
    echo "<select name='" . $e($name) . "' class='form-control'>";
    foreach ($options as $o) {
        $sel = ((string) $current === (string) $o["id"]) ? " selected" : "";
        echo "<option value='" . $e($o["id"]) . "'$sel>" . $e($o["text"]) . "</option>";
    }
    echo "</select>";
};
?>
<?php echo form_open(get_uri("grupo_donato/resources/save"), ["id" => "gd-resource-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="duplicate_override" value="0">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_code"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "code", "value" => $model_info->code ?? "", "class" => "form-control", "maxlength" => 40, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div><label class="col-md-3"><?php echo app_lang("gd_resource_type"); ?></label><div class="col-md-3"><?php $render_select("resource_type", $type_options, $model_info->resource_type ?? "court"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "name", "value" => $model_info->name ?? "", "class" => "form-control", "maxlength" => 150, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_business_area"); ?></label><div class="col-md-3"><?php $render_select("business_area_id", $areas, $model_info->business_area_id ?? ""); ?></div><label class="col-md-3"><?php echo app_lang("gd_cost_center"); ?></label><div class="col-md-3"><?php $render_select("cost_center_id", $cost_centers, $model_info->cost_center_id ?? ""); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_capacity"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "number", "name" => "capacity", "value" => $model_info->capacity ?? "", "class" => "form-control", "min" => 0]); ?></div><label class="col-md-3"><?php echo app_lang("gd_sort_order"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "number", "name" => "sort_order", "value" => $model_info->sort_order ?? 0, "class" => "form-control", "min" => 0]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_options"); ?></label><div class="col-md-9 mt5"><label class="mr15"><input type="checkbox" name="is_bookable" value="1"<?php echo (!isset($model_info->is_bookable) || $model_info->is_bookable) ? " checked" : ""; ?>> <?php echo app_lang("gd_bookable"); ?></label><label><input type="checkbox" name="is_active" value="1"<?php echo (!isset($model_info->is_active) || $model_info->is_active) ? " checked" : ""; ?>> <?php echo app_lang("gd_status_active"); ?></label></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_description"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "description", "value" => $model_info->description ?? "", "class" => "form-control", "rows" => 2]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_metadata_json"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "metadata", "value" => $model_info->metadata ?? "", "class" => "form-control", "rows" => 2, "placeholder" => '{"key":"value"}']); ?></div></div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-resource-form");
    form.appForm({onSuccess: function (result) { if ($("#gd-resources-table").length) { $("#gd-resources-table").appTable({newData: result.data, dataId: result.id}); } else { location.reload(); } }, onError: function (result) { if (result.duplicate_confirmation_required && window.confirm('<?php echo addslashes(app_lang("gd_confirm_duplicate_override")); ?>')) { form.find("[name=duplicate_override]").val("1"); setTimeout(function () { form.trigger("submit"); }, 100); } return true; }});
});
</script>
