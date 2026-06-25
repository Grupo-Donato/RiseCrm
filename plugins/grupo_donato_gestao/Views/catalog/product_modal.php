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
$checkbox = static function (string $name, $checked) use ($e) {
    echo "<label class='mr15'><input type='checkbox' name='" . $e($name) . "' value='1'" . ($checked ? " checked" : "") . "> " . app_lang("gd_flag_" . $name) . "</label>";
};
?>
<?php echo form_open(get_uri("grupo_donato/catalog/products/save"), ["id" => "gd-product-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="duplicate_override" value="0">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_code"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "code", "value" => $model_info->code ?? "", "class" => "form-control", "maxlength" => 40, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div><label class="col-md-3"><?php echo app_lang("gd_status"); ?></label><div class="col-md-3"><?php $render_select("status", $status_options, $model_info->status ?? "draft"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "name", "value" => $model_info->name ?? "", "class" => "form-control", "maxlength" => 190, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_product_type"); ?></label><div class="col-md-3"><?php $render_select("product_type", $type_options, $model_info->product_type ?? "service"); ?></div><label class="col-md-3"><?php echo app_lang("gd_category"); ?></label><div class="col-md-3"><?php $render_select("category_id", $category_options, $model_info->category_id ?? ""); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_billing_mode"); ?></label><div class="col-md-3"><?php $render_select("billing_mode", $billing_options, $model_info->billing_mode ?? "one_time"); ?></div><label class="col-md-3"><?php echo app_lang("gd_unit_of_measure"); ?></label><div class="col-md-3"><?php $render_select("unit_of_measure", $uom_options, $model_info->unit_of_measure ?? "unit"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_business_area"); ?></label><div class="col-md-3"><?php $render_select("business_area_id", $area_options, $model_info->business_area_id ?? ""); ?></div><label class="col-md-3"><?php echo app_lang("gd_default_cost_center"); ?></label><div class="col-md-3"><?php $render_select("default_cost_center_id", $cost_center_options, $model_info->default_cost_center_id ?? ""); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_flags"); ?></label><div class="col-md-9 mt5"><?php $checkbox("allows_variants", $model_info->allows_variants ?? 0); $checkbox("track_stock", $model_info->track_stock ?? 0); $checkbox("allows_discount", $model_info->allows_discount ?? 0); $checkbox("requires_resource", $model_info->requires_resource ?? 0); ?><div class="text-muted mt5"><small><?php echo app_lang("gd_track_stock_hint"); ?></small></div></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_rise_item_id"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "number", "name" => "rise_item_id", "value" => $model_info->rise_item_id ?? "", "class" => "form-control", "min" => 1]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_description"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "description", "value" => $model_info->description ?? "", "class" => "form-control", "rows" => 2]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_metadata_json"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "metadata", "value" => $model_info->metadata ?? "", "class" => "form-control", "rows" => 2, "placeholder" => '{"key":"value"}']); ?></div></div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-product-form");
    form.appForm({onSuccess: function (result) { if ($("#gd-products-table").length) { $("#gd-products-table").appTable({newData: result.data, dataId: result.id}); } else { location.reload(); } }, onError: function (result) { if (result.duplicate_confirmation_required && window.confirm('<?php echo addslashes(app_lang("gd_confirm_duplicate_override")); ?>')) { form.find("[name=duplicate_override]").val("1"); setTimeout(function () { form.trigger("submit"); }, 100); } return true; }});
});
</script>
