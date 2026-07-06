<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$active_tab = in_array(($active_tab ?? "rentals"), ["rentals", "bookings", "series"], true) ? $active_tab : "rentals";

$resource_options = [["id" => "", "text" => "- " . app_lang("gd_resources") . " -"]];
foreach ($resources as $resource) {
    $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]];
}

$rental_status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($statuses as $status) {
    $rental_status_options[] = ["id" => $status, "text" => app_lang("gd_court_rental_status_" . $status)];
}
$rental_type_options = [["id" => "", "text" => "- " . app_lang("gd_all_types") . " -"]];
foreach ($types as $type) {
    $rental_type_options[] = ["id" => $type, "text" => app_lang("gd_court_rental_type_" . $type)];
}

$booking_status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($booking_statuses as $status) {
    $booking_status_options[] = ["id" => $status, "text" => app_lang("gd_booking_status_" . $status)];
}
$booking_type_options = [["id" => "", "text" => "- " . app_lang("gd_all_types") . " -"]];
foreach ($booking_types as $type) {
    $booking_type_options[] = ["id" => $type, "text" => app_lang("gd_booking_type_" . $type)];
}

$series_status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($series_statuses as $status) {
    $series_status_options[] = ["id" => $status, "text" => app_lang("gd_booking_series_status_" . $status)];
}

$buttons = [];
if (!empty($can_calendar)) {
    $buttons[] = anchor(get_uri("grupo_donato/calendar"), '<i data-feather="calendar" class="icon-16"></i> ' . app_lang("gd_open_agenda"), ["class" => "btn btn-default"]);
}
if (!empty($can_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/court-rentals/single-modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_new_rental"), ["class" => "btn btn-primary", "title" => app_lang("gd_new_rental")]);
}
if ($active_tab === "bookings" && !empty($can_bookings_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="tool" class="icon-16"></i> ' . app_lang("gd_new_internal_occupancy"), ["class" => "btn btn-default", "title" => app_lang("gd_new_internal_occupancy")]);
}
if ($active_tab === "series" && !empty($can_series_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/booking-series/modal"), '<i data-feather="refresh-cw" class="icon-16"></i> ' . app_lang("gd_new_internal_recurrence"), ["class" => "btn btn-default", "title" => app_lang("gd_new_internal_recurrence")]);
}
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <?php echo view("grupo_donato_gestao\\Views\\components\\rentals_nav", [
        "active" => "reservations",
        "can_calendar" => $can_calendar ?? false,
        "can_court_rentals" => true,
        "can_bookings" => $can_bookings ?? false,
        "can_series" => $can_series ?? false,
    ]); ?>

    <div class="card">
        <div class="page-title clearfix">
            <div>
                <h4><?php echo app_lang("gd_rentals_workspace_title"); ?></h4>
                <div class="text-muted gd-rentals-subtitle"><?php echo app_lang("gd_rentals_workspace_help"); ?></div>
            </div>
            <?php if ($buttons) { ?><div class="title-button-group gd-toolbar"><?php echo implode(" ", $buttons); ?></div><?php } ?>
        </div>

        <ul class="nav nav-tabs bg-white title scrollable-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link<?php echo $active_tab === "rentals" ? " active" : ""; ?>" href="<?php echo get_uri("grupo_donato/court-rentals"); ?>?tab=rentals">
                    <i data-feather="briefcase" class="icon-16"></i> <?php echo app_lang("gd_tab_commercial_rentals"); ?>
                </a>
            </li>
            <?php if (!empty($can_bookings)) { ?>
                <li class="nav-item">
                    <a class="nav-link<?php echo $active_tab === "bookings" ? " active" : ""; ?>" href="<?php echo get_uri("grupo_donato/court-rentals"); ?>?tab=bookings">
                        <i data-feather="clock" class="icon-16"></i> <?php echo app_lang("gd_tab_schedule_occupancies"); ?>
                    </a>
                </li>
            <?php } ?>
            <?php if (!empty($can_series)) { ?>
                <li class="nav-item">
                    <a class="nav-link<?php echo $active_tab === "series" ? " active" : ""; ?>" href="<?php echo get_uri("grupo_donato/court-rentals"); ?>?tab=series">
                        <i data-feather="refresh-cw" class="icon-16"></i> <?php echo app_lang("gd_tab_recurrences"); ?>
                    </a>
                </li>
            <?php } ?>
        </ul>

        <?php if ($active_tab === "rentals") { ?>
            <div class="gd-table-note text-muted">
                <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_commercial_rentals_help"); ?>
            </div>
            <div class="table-responsive"><table id="gd-court-rentals-table" class="display" cellspacing="0" width="100%"></table></div>
        <?php } elseif ($active_tab === "bookings") { ?>
            <div class="gd-table-note text-muted">
                <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_schedule_occupancies_help"); ?>
            </div>
            <div class="table-responsive"><table id="gd-bookings-table" class="display" cellspacing="0" width="100%"></table></div>
        <?php } else { ?>
            <div class="gd-table-note text-muted">
                <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_recurrences_help"); ?>
            </div>
            <div class="table-responsive"><table id="gd-booking-series-table" class="display" cellspacing="0" width="100%"></table></div>
        <?php } ?>
    </div>
</div>

<script>
$(document).ready(function(){
    <?php if ($active_tab === "rentals") { ?>
    $("#gd-court-rentals-table").appTable({
        source:'<?php echo_uri("grupo_donato/court-rentals/list-data"); ?>',
        serverSide:true,
        order:[[8,"desc"]],
        filterDropdown:[
            {name:"rental_type",class:"w180",options:<?php echo json_encode($rental_type_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($rental_status_options); ?>},
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
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w80"}
        ]
    });
    <?php } elseif ($active_tab === "bookings") { ?>
    $("#gd-bookings-table").appTable({
        source:'<?php echo_uri("grupo_donato/bookings/list-data"); ?>',
        serverSide:true,
        order:[[5,"desc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"booking_type",class:"w180",options:<?php echo json_encode($booking_type_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($booking_status_options); ?>}
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
    <?php } else { ?>
    $("#gd-booking-series-table").appTable({
        source:'<?php echo_uri("grupo_donato/booking-series/list-data"); ?>',
        serverSide:true,
        order:[[5,"desc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($series_status_options); ?>}
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
    <?php } ?>

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
