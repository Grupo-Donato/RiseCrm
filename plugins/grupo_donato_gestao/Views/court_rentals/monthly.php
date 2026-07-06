<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

$resource_options = [["id" => "", "text" => "- " . app_lang("gd_resources") . " -"]];
foreach ($resources as $resource) {
    $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]];
}
$status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($statuses as $status) {
    $status_options[] = ["id" => $status, "text" => app_lang("gd_court_rental_status_" . $status)];
}
$weekday_options = [["id" => "", "text" => "- " . app_lang("gd_weekdays") . " -"]];
for ($day = 1; $day <= 7; $day++) {
    $weekday_options[] = ["id" => (string) $day, "text" => app_lang("gd_weekday_short_" . $day)];
}

$buttons = [];
if (!empty($can_calendar)) {
    $buttons[] = anchor(get_uri("grupo_donato/calendar"), '<i data-feather="calendar" class="icon-16"></i> ' . app_lang("gd_open_agenda"), ["class" => "btn btn-default"]);
}
$buttons[] = anchor(get_uri("grupo_donato/court-rentals"), '<i data-feather="clipboard" class="icon-16"></i> ' . app_lang("gd_view_reservations"), ["class" => "btn btn-default"]);
if (!empty($can_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/court-rentals/monthly-modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_new_court_rental_monthly"), ["class" => "btn btn-primary", "title" => app_lang("gd_new_court_rental_monthly")]);
}
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <?php echo view("grupo_donato_gestao\\Views\\components\\rentals_nav", [
        "active" => "monthly",
        "can_calendar" => $can_calendar ?? false,
        "can_court_rentals" => true,
        "can_bookings" => $can_bookings ?? false,
        "can_series" => $can_series ?? false,
    ]); ?>

    <div class="card">
        <div class="page-title clearfix">
            <div>
                <h4><?php echo app_lang("gd_monthly_renters"); ?></h4>
                <div class="text-muted gd-rentals-subtitle">
                    <?php echo app_lang("gd_monthly_renters_help"); ?>
                    <span class="ms-1"><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></span>
                </div>
            </div>
            <div class="title-button-group gd-toolbar"><?php echo implode(" ", $buttons); ?></div>
        </div>

        <div class="gd-table-note text-muted">
            <div class="gd-stat-line">
                <span><i data-feather="repeat" class="icon-16"></i> <?php echo app_lang("gd_monthly_contract_hint"); ?></span>
                <span><i data-feather="dollar-sign" class="icon-16"></i> <?php echo app_lang("gd_monthly_finance_hint"); ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table id="gd-court-renters-table" class="display" cellspacing="0" width="100%"></table>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    $("#gd-court-renters-table").appTable({
        source:'<?php echo_uri("grupo_donato/court-rentals/monthly-data"); ?>',
        serverSide:true,
        order:[[0,"asc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"weekday",class:"w180",options:<?php echo json_encode($weekday_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>}
        ],
        rangeDatepicker:[{startDate:{name:"date_from",value:""},endDate:{name:"date_to",value:""},showClearButton:true}],
        columns:[
            {title:'<?php echo app_lang("gd_customer"); ?>',class:"all"},
            {title:'<?php echo app_lang("gd_courts"); ?>'},
            {title:'<?php echo app_lang("gd_day_and_time"); ?>'},
            {title:'<?php echo app_lang("gd_contracted_amount"); ?>'},
            {title:'<?php echo app_lang("gd_due_day"); ?>'},
            {title:'<?php echo app_lang("gd_status"); ?>'},
            {title:'<?php echo app_lang("gd_next_occurrence"); ?>'},
            {title:'<?php echo app_lang("gd_finance_situation"); ?>'},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w120"}
        ]
    });

    $(document).on("click", ".gd-cr-act", function(event){
        event.preventDefault();
        var button = $(this),
            data = {lock_version: button.data("lock")};
        if (button.data("action") === "suspend") {
            data.future_policy = "keep";
        }
        $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/"); ?>' + button.data("id") + "/" + button.data("action"),
            type: "POST",
            data: data,
            dataType: "json"
        }).done(function(response){
                if (response.success) {
                    location.reload();
                } else {
                    appAlert.error(response.message);
                }
            })
            .fail(function(xhr){
                var body = xhr && xhr.responseJSON ? xhr.responseJSON : null;
                appAlert.error((body && body.message) || '<?php echo addslashes(app_lang("error_occurred")); ?>');
                if (xhr && xhr.status === 409) { setTimeout(function(){ location.reload(); }, 1200); }
            });
    });

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
