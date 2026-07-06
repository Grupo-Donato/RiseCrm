<?php
$resource_options = [["id" => "", "text" => "- " . app_lang("gd_resources") . " -"]];
foreach ($resources as $resource) { $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]]; }
$status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($statuses as $status) { $status_options[] = ["id" => $status, "text" => app_lang("gd_booking_series_status_" . $status)]; }
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <?php echo view("grupo_donato_gestao\\Views\\components\\rentals_nav", [
        "active" => "reservations",
        "can_calendar" => $can_calendar ?? false,
        "can_court_rentals" => $can_court_rentals ?? false,
        "can_bookings" => $can_bookings ?? false,
        "can_series" => true,
    ]); ?>
    <div class="card">
        <div class="page-title clearfix">
            <div><h4><?php echo app_lang("gd_menu_booking_series"); ?></h4><div class="text-muted"><?php echo app_lang("gd_recurrences_help"); ?></div></div>
            <?php if ($can_manage) { ?><div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/booking-series/modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_new_booking_series"), ["class" => "btn btn-primary", "title" => app_lang("gd_new_booking_series")]); ?></div><?php } ?>
        </div>
        <div class="table-responsive"><table id="gd-booking-series-table" class="display" cellspacing="0" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-booking-series-table").appTable({
        source:'<?php echo_uri("grupo_donato/booking-series/list-data"); ?>',serverSide:true,order:[[5,"desc"]],
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
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w80"}
        ]
    });
});
</script>
