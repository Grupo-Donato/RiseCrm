<?php
$resource_options = [["id" => "", "text" => "-"]];
foreach ($resources as $resource) { $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]]; }
$status_options = [["id" => "", "text" => "-"]];
foreach ($statuses as $status) { $status_options[] = ["id" => $status, "text" => app_lang("gd_booking_series_status_" . $status)]; }
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_booking_series"); ?></h4>
            <div class="title-button-group">
                <?php if ($can_manage) { echo modal_anchor(get_uri("grupo_donato/booking-series/modal"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_new_booking_series"), ["class" => "btn btn-default", "title" => app_lang("gd_new_booking_series")]); } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-booking-series-table" class="display" cellspacing="0" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-booking-series-table").appTable({
        source:'<?php echo_uri("grupo_donato/booking-series/list-data"); ?>', serverSide:true, order:[[5,"desc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>}
        ],
        rangeDatepicker:[{startDate:{name:"date_from",value:""},endDate:{name:"date_to",value:""},showClearButton:true}],
        columns:[
            {title:'<?php echo app_lang("gd_series_number"); ?>',order_by:"series_number"},
            {title:'<?php echo app_lang("gd_title"); ?>',order_by:"title",class:"all"},
            {title:'<?php echo app_lang("gd_frequency"); ?>',order_by:"frequency"},
            {title:'<?php echo app_lang("gd_resources"); ?>'},
            {title:'<?php echo app_lang("gd_customer"); ?>'},
            {title:'<?php echo app_lang("gd_starts_on"); ?>',order_by:"starts_on"},
            {title:'<?php echo app_lang("gd_status"); ?>',order_by:"status"},
            {title:'<?php echo app_lang("gd_updated_at"); ?>',order_by:"updated_at"},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w100"}
        ]
    });
});
</script>
