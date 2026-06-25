<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$render_select = static function (string $name, array $options, string $id_attr = "") use ($e) {
    echo "<select name='" . $e($name) . "'" . ($id_attr ? " id='" . $e($id_attr) . "'" : "") . " class='form-control'>";
    foreach ($options as $o) { echo "<option value='" . $e($o["id"]) . "'>" . $e($o["text"]) . "</option>"; }
    echo "</select>";
};
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo app_lang("gd_price_resolver"); ?></h4>
        <div class="title-button-group"><?php echo anchor(get_uri("grupo_donato/pricing/lists"), app_lang("back"), ["class" => "btn btn-default"]); ?></div>
    </div>
    <div class="row"><div class="col-md-6"><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_resolver_params"); ?></h4></div><div class="card-body">
        <form id="gd-resolver-form">
            <div class="form-group"><label><?php echo app_lang("gd_product"); ?></label><?php $render_select("product_id", $product_options, "gd-resolver-product"); ?></div>
            <div class="form-group"><label><?php echo app_lang("gd_variant"); ?></label><select name="variant_id" id="gd-resolver-variant" class="form-control"><option value="">-</option></select></div>
            <div class="form-group"><label><?php echo app_lang("gd_resource"); ?></label><?php $render_select("resource_id", $resource_options); ?></div>
            <div class="form-group"><label><?php echo app_lang("gd_quantity"); ?></label><input type="text" name="quantity" value="1" class="form-control"></div>
            <div class="form-group"><label><?php echo app_lang("gd_reference_date"); ?></label><input type="date" name="reference_date" class="form-control"></div>
            <div class="form-group"><label><?php echo app_lang("gd_price_list"); ?></label><?php $render_select("price_list_id", $list_options); ?></div>
            <button type="submit" class="btn btn-primary"><i data-feather="search" class="icon-16"></i> <?php echo app_lang("gd_resolve"); ?></button>
        </form>
    </div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_resolver_result"); ?></h4></div><div class="card-body"><div id="gd-resolver-result"><span class="text-muted"><?php echo app_lang("gd_resolver_hint"); ?></span></div></div></div></div></div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    var L = {
        no_price: '<?php echo addslashes(app_lang("gd_resolver_no_price")); ?>',
        amount: '<?php echo addslashes(app_lang("gd_amount")); ?>',
        scope: '<?php echo addslashes(app_lang("gd_matched_scope")); ?>',
        list: '<?php echo addslashes(app_lang("gd_price_list")); ?>',
        validity: '<?php echo addslashes(app_lang("gd_validity")); ?>',
        ref_cost: '<?php echo addslashes(app_lang("gd_reference_cost")); ?>'
    };
    $("#gd-resolver-product").on("change", function () {
        var $v = $("#gd-resolver-variant").html("<option value=''>-</option>");
        var pid = $(this).val();
        if (!pid) { return; }
        $.post('<?php echo_uri("grupo_donato/pricing/prices/variants"); ?>', {product_id: pid}, function (r) {
            if (r && r.variants) { r.variants.forEach(function (it) { $v.append($("<option>").val(it.id).text(it.text)); }); }
        }, "json");
    });
    $("#gd-resolver-form").on("submit", function (ev) {
        ev.preventDefault();
        $.post('<?php echo_uri("grupo_donato/pricing/resolve"); ?>', $(this).serialize(), function (r) {
            var box = $("#gd-resolver-result");
            if (!r.success || !r.result) { box.html('<span class="text-danger">' + (r.message || L.no_price) + '</span>'); return; }
            var res = r.result;
            if (!res.found) { box.html('<div class="alert alert-warning mb0">' + L.no_price + ' <code>' + res.reason + '</code></div>'); return; }
            var html = '<table class="table table-sm mb0">';
            html += '<tr><th>' + L.amount + '</th><td><strong>' + res.amount + ' ' + res.currency + '</strong></td></tr>';
            html += '<tr><th>' + L.scope + '</th><td>' + res.matched_scope + '</td></tr>';
            html += '<tr><th>' + L.ref_cost + '</th><td>' + (res.reference_cost || '-') + '</td></tr>';
            html += '<tr><th>' + L.list + '</th><td>#' + res.price_list_id + '</td></tr>';
            html += '<tr><th>' + L.validity + '</th><td>' + (res.valid_from || '∞') + ' — ' + (res.valid_until || '∞') + '</td></tr>';
            html += '</table>';
            box.html(html);
        }, "json");
    });
});
</script>
