<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$id = (int) ($model_info->id ?? 0);
$render_select = static function (string $name, array $options, $current, string $id_attr = "") use ($e) {
    echo "<select name='" . $e($name) . "'" . ($id_attr ? " id='" . $e($id_attr) . "'" : "") . " class='form-control'>";
    foreach ($options as $o) {
        $sel = ((string) $current === (string) $o["id"]) ? " selected" : "";
        echo "<option value='" . $e($o["id"]) . "'$sel>" . $e($o["text"]) . "</option>";
    }
    echo "</select>";
};
?>
<?php echo form_open(get_uri("grupo_donato/pricing/prices/save"), ["id" => "gd-price-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="price_list_id" value="<?php echo (int) $price_list->id; ?>">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_product"); ?></label><div class="col-md-9"><?php $render_select("product_id", $product_options, $model_info->product_id ?? "", "gd-price-product"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_variant"); ?></label><div class="col-md-4"><?php $render_select("variant_id", $variant_options, $model_info->variant_id ?? "", "gd-price-variant"); ?></div><label class="col-md-2"><?php echo app_lang("gd_resource"); ?></label><div class="col-md-3"><?php $render_select("resource_id", $resource_options, $model_info->resource_id ?? ""); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_amount"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "amount", "value" => $model_info->amount ?? "", "class" => "form-control", "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div><label class="col-md-3"><?php echo app_lang("gd_reference_cost"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "reference_cost", "value" => $model_info->reference_cost ?? "", "class" => "form-control"]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_min_quantity"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "minimum_quantity", "value" => $model_info->minimum_quantity ?? "1", "class" => "form-control"]); ?></div><label class="col-md-3"><?php echo app_lang("gd_status"); ?></label><div class="col-md-3"><?php $render_select("status", $status_options, $model_info->status ?? "active"); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_valid_from"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "date", "name" => "valid_from", "value" => $model_info->valid_from ?? "", "class" => "form-control"]); ?></div><label class="col-md-3"><?php echo app_lang("gd_valid_until"); ?></label><div class="col-md-3"><?php echo form_input(["type" => "date", "name" => "valid_until", "value" => $model_info->valid_until ?? "", "class" => "form-control"]); ?></div></div></div>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-price-form");
    function loadVariants(productId, selected) {
        var $v = $("#gd-price-variant");
        $v.html("<option value=''>-</option>");
        if (!productId) { return; }
        $.post('<?php echo_uri("grupo_donato/pricing/prices/variants"); ?>', {product_id: productId}, function (r) {
            if (r && r.variants) { r.variants.forEach(function (it) { $v.append($("<option>").val(it.id).text(it.text)); }); if (selected) { $v.val(selected); } }
        }, "json");
    }
    $("#gd-price-product").on("change", function () { loadVariants($(this).val(), null); });
    form.appForm({onSuccess: function (result) { if ($("#gd-prices-table").length) { $("#gd-prices-table").appTable({newData: result.data, dataId: result.id}); } }});
});
</script>
