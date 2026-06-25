<?php
$resource_options = [["id" => "", "text" => "-"]];
foreach ($resources as $resource) { $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]]; }
$status_options = [["id" => "", "text" => "-"]];
foreach ($statuses as $status) { $status_options[] = ["id" => $status, "text" => app_lang("gd_court_rental_status_" . $status)]; }
$type_options = [["id" => "", "text" => "-"]];
foreach ($types as $type) { $type_options[] = ["id" => $type, "text" => app_lang("gd_court_rental_type_" . $type)]; }
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_court_rentals"); ?></h4>
            <div class="title-button-group">
                <?php echo anchor(get_uri("grupo_donato/court-rentals/monthly"), "<i data-feather='users' class='icon-16'></i> " . app_lang("gd_monthly_renters"), ["class" => "btn btn-default", "title" => app_lang("gd_monthly_renters")]); ?>
                <?php if ($can_manage) { echo modal_anchor(get_uri("grupo_donato/court-rentals/single-modal"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_new_court_rental_single"), ["class" => "btn btn-default", "title" => app_lang("gd_new_court_rental_single")]); } ?>
                <?php if ($can_manage) { echo modal_anchor(get_uri("grupo_donato/court-rentals/monthly-modal"), "<i data-feather='repeat' class='icon-16'></i> " . app_lang("gd_new_court_rental_monthly"), ["class" => "btn btn-default", "title" => app_lang("gd_new_court_rental_monthly")]); } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-court-rentals-table" class="display" cellspacing="0" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-court-rentals-table").appTable({
        source:'<?php echo_uri("grupo_donato/court-rentals/list-data"); ?>', serverSide:true, order:[[8,"desc"]],
        filterDropdown:[
            {name:"rental_type",class:"w180",options:<?php echo json_encode($type_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>},
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>}
        ],
        rangeDatepicker:[{startDate:{name:"date_from",value:""},endDate:{name:"date_to",value:""},showClearButton:true}],
        columns:[
            {title:'<?php echo app_lang("gd_court_rental_number"); ?>',order_by:"rental_number"},
            {title:'<?php echo app_lang("gd_title"); ?>',order_by:"title",class:"all"},
            {title:'<?php echo app_lang("gd_customer"); ?>'},
            {title:'<?php echo app_lang("gd_rental_type"); ?>'},
            {title:'<?php echo app_lang("gd_resources"); ?>'},
            {title:'<?php echo app_lang("gd_validity"); ?>'},
            {title:'<?php echo app_lang("gd_contracted_amount"); ?>'},
            {title:'<?php echo app_lang("gd_status"); ?>',order_by:"status"},
            {title:'<?php echo app_lang("gd_updated_at"); ?>',order_by:"updated_at"},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w100"}
        ]
    });
});
</script>
