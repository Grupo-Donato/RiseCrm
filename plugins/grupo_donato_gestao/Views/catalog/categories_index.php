<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_product_categories"); ?></h4>
            <div class="title-button-group">
                <?php echo anchor(get_uri("grupo_donato/catalog/products"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang("gd_menu_products"), ["class" => "btn btn-default"]); ?>
                <?php if (!empty($can_manage)) { ?>
                    <?php echo modal_anchor(get_uri("grupo_donato/catalog/categories/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_category"), ["class" => "btn btn-default", "title" => app_lang("gd_add_category")]); ?>
                <?php } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-categories-table" class="display" width="100%"></table></div>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-categories-table").appTable({
        source: '<?php echo_uri("grupo_donato/catalog/categories/list_data"); ?>',
        columns: [
            {title: '<?php echo app_lang("gd_code"); ?>', "class": "w150"},
            {title: '<?php echo app_lang("gd_name"); ?>'},
            {title: '<?php echo app_lang("gd_parent_category"); ?>'},
            {title: '<?php echo app_lang("gd_sort_order"); ?>', "class": "text-center w100"},
            {title: '<?php echo app_lang("gd_status"); ?>', "class": "text-center w100"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>
