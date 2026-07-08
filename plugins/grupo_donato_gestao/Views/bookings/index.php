<?php
$type_options = [["id" => "", "text" => "- " . app_lang("gd_all_types") . " -"]];
foreach ($types as $value) { $type_options[] = ["id" => $value, "text" => app_lang("gd_booking_type_" . $value)]; }
$status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($statuses as $value) { $status_options[] = ["id" => $value, "text" => app_lang("gd_booking_status_" . $value)]; }
$resource_options = [["id" => "", "text" => "- " . app_lang("gd_resources") . " -"]];
foreach ($resources as $value) { $resource_options[] = ["id" => $value["id"], "text" => $value["code"] . " — " . $value["name"]]; }
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <?php echo view("grupo_donato_gestao\\Views\\components\\rentals_nav", [
        "active" => "reservations",
        "can_calendar" => $can_calendar ?? false,
        "can_court_rentals" => $can_court_rentals ?? false,
        "can_bookings" => true,
        "can_series" => $can_series ?? false,
        "can_finance" => $can_finance ?? false,
    ]); ?>
    <div class="card">
        <div class="page-title clearfix">
            <div><h4><?php echo app_lang("gd_menu_bookings"); ?></h4><div class="text-muted"><?php echo app_lang("gd_schedule_occupancies_help"); ?></div></div>
            <?php if ($can_manage) { ?><div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_add_booking"), ["class" => "btn btn-primary", "title" => app_lang("gd_add_booking")]); ?></div><?php } ?>
        </div>
        <div class="table-responsive"><table id="gd-bookings-table" class="display" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-bookings-table").appTable({
        source:'<?php echo_uri("grupo_donato/bookings/list-data"); ?>',serverSide:true,order:[[5,"desc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"booking_type",class:"w180",options:<?php echo json_encode($type_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>}
        ],
        rangeDatepicker:[{startDate:{name:"date_from",value:""},endDate:{name:"date_to",value:""},showClearButton:true}],
        columns:[
            {title:'<?php echo app_lang("gd_booking_number"); ?>',order_by:"booking_number"},
            {title:'<?php echo app_lang("gd_title"); ?>',order_by:"title",class:"all"},
            {title:'<?php echo app_lang("gd_type"); ?>',order_by:"booking_type"},
            {title:'<?php echo app_lang("gd_customer"); ?>'},
            {title:'<?php echo app_lang("gd_resources"); ?>'},
            {title:'<?php echo app_lang("gd_starts_at"); ?>',order_by:"starts_at_utc"},
            {title:'<?php echo app_lang("gd_ends_at"); ?>',order_by:"ends_at_utc"},
            {title:'<?php echo app_lang("gd_status"); ?>',order_by:"status"},
            {title:'<?php echo app_lang("gd_hold_until"); ?>',order_by:"hold_expires_at_utc"},
            {title:'<?php echo app_lang("gd_updated_at"); ?>',order_by:"updated_at"},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w80"}
        ]
    });
});
</script>
