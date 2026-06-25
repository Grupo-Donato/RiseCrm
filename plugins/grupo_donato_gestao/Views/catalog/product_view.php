<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$flag = static fn($v) => $v ? app_lang("yes") : app_lang("no");
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($product->code . " — " . $product->name); ?></h4>
        <div class="title-button-group">
            <?php if ($can_manage) echo modal_anchor(get_uri("grupo_donato/catalog/products/modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang("edit"), ["class" => "btn btn-default", "data-post-id" => $product->id]); ?>
            <?php echo anchor(get_uri("grupo_donato/catalog/products"), app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>
    <div class="row"><div class="col-md-7"><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_main_data"); ?></h4></div><div class="card-body">
        <div class="row">
            <div class="col-md-4"><strong><?php echo app_lang("gd_product_type"); ?></strong><br><?php echo app_lang("gd_product_type_" . $product->product_type); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_billing_mode"); ?></strong><br><?php echo app_lang("gd_billing_mode_" . $product->billing_mode); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_unit_of_measure"); ?></strong><br><?php echo app_lang("gd_uom_" . $product->unit_of_measure); ?></div>
        </div><hr>
        <div class="row">
            <div class="col-md-4"><strong><?php echo app_lang("gd_category"); ?></strong><br><?php echo $e($product->category_name ?? "-"); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_business_area"); ?></strong><br><?php echo $e($product->business_area_name ?? "-"); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_status"); ?></strong><br><?php echo app_lang("gd_status_" . $product->status); ?></div>
        </div><hr>
        <div class="row">
            <div class="col-md-3"><strong><?php echo app_lang("gd_flag_allows_variants"); ?></strong><br><?php echo $flag($product->allows_variants); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_flag_track_stock"); ?></strong><br><?php echo $flag($product->track_stock); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_flag_allows_discount"); ?></strong><br><?php echo $flag($product->allows_discount); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_flag_requires_resource"); ?></strong><br><?php echo $flag($product->requires_resource); ?></div>
        </div>
        <?php if (!empty($product->description)) { ?><hr><strong><?php echo app_lang("gd_description"); ?></strong><br><?php echo nl2br($e($product->description)); ?><?php } ?>
    </div></div></div><div class="col-md-5">
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_current_prices"); ?></h4></div><div class="card-body">
            <?php if (!$prices) { ?><span class="text-muted"><?php echo app_lang("gd_no_prices"); ?></span><?php } else { foreach ($prices as $price) { ?>
                <div class="mb10"><strong><?php echo $e(to_currency((float) $price->amount)); ?></strong> <?php echo $e($price->currency); ?> — <?php echo $e($price->price_list_name); ?><br><small><?php echo app_lang("gd_min_quantity"); ?>: <?php echo $e(rtrim(rtrim((string) $price->minimum_quantity, "0"), ".")); ?><?php echo $price->variant_id ? " · var #" . (int) $price->variant_id : ""; ?><?php echo $price->resource_id ? " · rec #" . (int) $price->resource_id : ""; ?></small></div>
            <?php }} ?>
        </div></div>
        <?php if ($can_audit) { ?><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_recent_audit"); ?></h4></div><div class="card-body"><?php if (!$audits) echo '<span class="text-muted">' . app_lang("gd_no_audit_events") . '</span>'; foreach ($audits as $event) { ?><div class="mb10"><strong><?php echo $e($event->action); ?></strong><br><small><?php echo $event->created_at ? format_to_datetime($event->created_at) : ""; ?></small></div><?php } ?></div></div><?php } ?>
    </div></div>
    <?php if ($product->allows_variants) { ?>
    <div class="card"><div class="page-title clearfix"><h4><?php echo app_lang("gd_variants"); ?></h4><?php if ($can_manage) { ?><div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/catalog/variants/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_variant"), ["class" => "btn btn-default", "data-post-product_id" => $product->id]); ?></div><?php } ?></div><div class="table-responsive"><table id="gd-variants-table" class="display" width="100%"></table></div></div>
    <?php } ?>
</div>
<script type="text/javascript">
$(document).ready(function () {
    <?php if ($product->allows_variants) { ?>
    $("#gd-variants-table").appTable({source: '<?php echo_uri("grupo_donato/catalog/variants/list_data"); ?>', serverSide: true, filterParams: {product_id: "<?php echo (int) $product->id; ?>"}, columns: [
        {title: '<?php echo app_lang("gd_code"); ?>'}, {title: '<?php echo app_lang("gd_name"); ?>'}, {title: '<?php echo app_lang("gd_barcode"); ?>'}, {title: '<?php echo app_lang("gd_attributes"); ?>'}, {title: '<?php echo app_lang("gd_default"); ?>', "class": "text-center"}, {title: '<?php echo app_lang("gd_status"); ?>', "class": "text-center"}, {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w80"}
    ]});
    <?php } ?>
});
</script>
