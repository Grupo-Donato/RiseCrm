<?php
$all = static fn($label) => ["id" => "", "text" => "- " . app_lang($label) . " -"];
$type_filter = array_merge([$all("gd_all_types")], $type_options);
$category_filter = array_merge([$all("gd_all_categories")], array_values(array_filter($category_options, static fn($o) => $o["id"] !== "")));
$area_filter = array_merge([$all("gd_all_areas")], array_values(array_filter($area_options, static fn($o) => $o["id"] !== "")));
$status_filter = array_merge([$all("gd_all_statuses")], $status_options);
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_products"); ?></h4>
            <div class="title-button-group">
                <?php if (!empty($can_categories)) { ?>
                    <?php echo anchor(get_uri("grupo_donato/catalog/categories"), "<i data-feather='folder' class='icon-16'></i> " . app_lang("gd_product_categories"), ["class" => "btn btn-default"]); ?>
                <?php } ?>
                <?php if (!empty($can_manage)) { ?>
                    <?php echo modal_anchor(get_uri("grupo_donato/catalog/products/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_product"), ["class" => "btn btn-default", "title" => app_lang("gd_add_product")]); ?>
                <?php } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-products-table" class="display" width="100%"></table></div>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-products-table").appTable({
        source: '<?php echo_uri("grupo_donato/catalog/products/list_data"); ?>',
        serverSide: true,
        order: [[1, "asc"]],
        filterDropdown: [
            {name: "product_type", class: "w200", options: <?php echo json_encode($type_filter); ?>},
            {name: "category_id", class: "w200", options: <?php echo json_encode($category_filter); ?>},
            {name: "business_area_id", class: "w200", options: <?php echo json_encode($area_filter); ?>},
            {name: "status", class: "w200", options: <?php echo json_encode($status_filter); ?>}
        ],
        columns: [
            {title: '<?php echo app_lang("gd_code"); ?>', order_by: "code", "class": "w150"},
            {title: '<?php echo app_lang("gd_name"); ?>', order_by: "name", "class": "all"},
            {title: '<?php echo app_lang("gd_product_type"); ?>', order_by: "product_type"},
            {title: '<?php echo app_lang("gd_category"); ?>'},
            {title: '<?php echo app_lang("gd_business_area"); ?>'},
            {title: '<?php echo app_lang("gd_billing_mode"); ?>'},
            {title: '<?php echo app_lang("gd_unit_of_measure"); ?>'},
            {title: '<?php echo app_lang("gd_variants"); ?>', "class": "text-center"},
            {title: '<?php echo app_lang("gd_status"); ?>', order_by: "status", "class": "text-center"},
            {title: '<?php echo app_lang("gd_updated_at"); ?>', order_by: "updated_at"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>
