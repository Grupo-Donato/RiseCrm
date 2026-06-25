<?php
$type_filter = array_merge([["id" => "", "text" => "- " . app_lang("gd_all_types") . " -"]], $type_options);
$active_filter = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"], ["id" => "1", "text" => app_lang("gd_status_active")], ["id" => "0", "text" => app_lang("gd_status_inactive")]];
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_resources"); ?></h4>
            <?php if (!empty($can_manage)) { ?>
                <div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/resources/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_resource"), ["class" => "btn btn-default", "title" => app_lang("gd_add_resource")]); ?></div>
            <?php } ?>
        </div>
        <div class="table-responsive"><table id="gd-resources-table" class="display" width="100%"></table></div>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-resources-table").appTable({
        source: '<?php echo_uri("grupo_donato/resources/list_data"); ?>',
        serverSide: true,
        order: [[0, "asc"]],
        filterDropdown: [
            {name: "resource_type", class: "w200", options: <?php echo json_encode($type_filter); ?>},
            {name: "is_active", class: "w200", options: <?php echo json_encode($active_filter); ?>}
        ],
        columns: [
            {title: '<?php echo app_lang("gd_code"); ?>', order_by: "code", "class": "w150"},
            {title: '<?php echo app_lang("gd_name"); ?>', order_by: "name", "class": "all"},
            {title: '<?php echo app_lang("gd_resource_type"); ?>', order_by: "resource_type"},
            {title: '<?php echo app_lang("gd_business_area"); ?>'},
            {title: '<?php echo app_lang("gd_cost_center"); ?>'},
            {title: '<?php echo app_lang("gd_capacity"); ?>', "class": "text-center"},
            {title: '<?php echo app_lang("gd_bookable"); ?>', "class": "text-center"},
            {title: '<?php echo app_lang("gd_status"); ?>', order_by: "is_active", "class": "text-center"},
            {title: '<?php echo app_lang("gd_updated_at"); ?>', order_by: "updated_at"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>
