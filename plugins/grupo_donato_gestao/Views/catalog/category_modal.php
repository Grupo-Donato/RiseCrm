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
<?php echo form_open(get_uri("grupo_donato/catalog/categories/save"), ["id" => "gd-category-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="duplicate_override" value="0">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_code"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "code", "value" => $model_info->code ?? "", "class" => "form-control", "maxlength" => 40, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "name", "value" => $model_info->name ?? "", "class" => "form-control", "maxlength" => 150, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_parent_category"); ?></label><div class="col-md-9"><?php $render_select("parent_id", $parents, $model_info->parent_id ?? ""); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_sort_order"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "number", "name" => "sort_order", "value" => $model_info->sort_order ?? 0, "class" => "form-control", "min" => 0]); ?></div><label class="col-md-3"><?php echo app_lang("gd_status"); ?></label><div class="col-md-3"><?php $render_select("status", $statuses, $model_info->status ?? "active"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_description"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "description", "value" => $model_info->description ?? "", "class" => "form-control", "rows" => 2]); ?></div></div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-category-form");
    form.appForm({onSuccess: function (result) { if ($("#gd-categories-table").length) { $("#gd-categories-table").appTable({newData: result.data, dataId: result.id}); } }, onError: function (result) { if (result.duplicate_confirmation_required && window.confirm('<?php echo addslashes(app_lang("gd_confirm_duplicate_override")); ?>')) { form.find("[name=duplicate_override]").val("1"); setTimeout(function () { form.trigger("submit"); }, 100); } return true; }});
});
</script>
