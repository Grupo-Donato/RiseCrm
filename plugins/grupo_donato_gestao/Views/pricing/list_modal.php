<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$id = (int) ($model_info->id ?? 0);
?>
<?php echo form_open(get_uri("grupo_donato/pricing/lists/save"), ["id" => "gd-price-list-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="duplicate_override" value="0">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_code"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "code", "value" => $model_info->code ?? "", "class" => "form-control", "maxlength" => 40, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div><label class="col-md-3"><?php echo app_lang("gd_currency"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "currency", "value" => $model_info->currency ?? "BRL", "class" => "form-control text-uppercase", "maxlength" => 3]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "name", "value" => $model_info->name ?? "", "class" => "form-control", "maxlength" => 150, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_valid_from"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "date", "name" => "valid_from", "value" => $model_info->valid_from ?? "", "class" => "form-control"]); ?></div><label class="col-md-3"><?php echo app_lang("gd_valid_until"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "date", "name" => "valid_until", "value" => $model_info->valid_until ?? "", "class" => "form-control"]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_priority"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "number", "name" => "priority", "value" => $model_info->priority ?? 0, "class" => "form-control"]); ?></div><label class="col-md-3"><?php echo app_lang("gd_status"); ?></label><div class="col-md-3"><select name="status" class="form-control"><?php foreach ($statuses as $o) { ?><option value="<?php echo $e($o["id"]); ?>"<?php echo ($model_info->status ?? "active") === $o["id"] ? " selected" : ""; ?>><?php echo $e($o["text"]); ?></option><?php } ?></select></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_default"); ?></label><div class="col-md-9 mt5"><label><input type="checkbox" name="is_default" value="1"<?php echo !empty($model_info->is_default) ? " checked" : ""; ?>> <?php echo app_lang("gd_is_default_price_list"); ?></label></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_description"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "description", "value" => $model_info->description ?? "", "class" => "form-control", "rows" => 2]); ?></div></div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-price-list-form");
    form.appForm({onSuccess: function (result) { if ($("#gd-price-lists-table").length) { $("#gd-price-lists-table").appTable({newData: result.data, dataId: result.id}); } else { location.reload(); } }, onError: function (result) { if (result.duplicate_confirmation_required && window.confirm('<?php echo addslashes(app_lang("gd_confirm_duplicate_override")); ?>')) { form.find("[name=duplicate_override]").val("1"); setTimeout(function () { form.trigger("submit"); }, 100); } return true; }});
});
</script>
