<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$status_filter = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach (\grupo_donato_gestao\Config\Constants::PRICE_STATUSES as $s) { $status_filter[] = ["id" => $s, "text" => app_lang("gd_status_" . $s)]; }
$product_filter = $product_options;
$resource_filter = $resource_options;
$category_filter = $category_options;
$type_filter = $type_options;
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($list->code . " — " . $list->name); ?> <small class="text-muted">(<?php echo $e($list->currency); ?><?php echo (int) $list->is_default ? " · " . app_lang("gd_default") : ""; ?> · <?php echo app_lang("gd_status_" . $list->status); ?>)</small></h4>
        <div class="title-button-group">
            <?php if ($can_manage_prices) echo modal_anchor(get_uri("grupo_donato/pricing/prices/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_price"), ["class" => "btn btn-default", "data-post-price_list_id" => $list->id]); ?>
            <?php echo anchor(get_uri("grupo_donato/pricing/lists"), app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>
    <div class="card"><div class="table-responsive"><table id="gd-prices-table" class="display" width="100%"></table></div></div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-prices-table").appTable({
        source: '<?php echo_uri("grupo_donato/pricing/prices/list_data"); ?>',
        serverSide: true,
        filterParams: {price_list_id: "<?php echo (int) $list->id; ?>"},
        filterDropdown: [
            {name: "product_id", class: "w200", options: <?php echo json_encode($product_filter); ?>},
            {name: "category_id", class: "w200", options: <?php echo json_encode($category_filter); ?>},
            {name: "product_type", class: "w200", options: <?php echo json_encode($type_filter); ?>},
            {name: "resource_id", class: "w200", options: <?php echo json_encode($resource_filter); ?>},
            {name: "status", class: "w200", options: <?php echo json_encode($status_filter); ?>}
        ],
        columns: [
            {title: '<?php echo app_lang("gd_product"); ?>', order_by: "product_name", "class": "all"},
            {title: '<?php echo app_lang("gd_variant"); ?>'},
            {title: '<?php echo app_lang("gd_resource"); ?>'},
            {title: '<?php echo app_lang("gd_min_quantity"); ?>', order_by: "minimum_quantity", "class": "text-center"},
            {title: '<?php echo app_lang("gd_amount"); ?>', order_by: "amount", "class": "text-end"},
            {title: '<?php echo app_lang("gd_reference_cost"); ?>', "class": "text-end"},
            {title: '<?php echo app_lang("gd_validity"); ?>', order_by: "valid_from"},
            {title: '<?php echo app_lang("gd_status"); ?>', order_by: "status", "class": "text-center"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w80"}
        ]
    });
});
</script>
