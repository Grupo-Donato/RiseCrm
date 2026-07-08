<?php
load_css(["assets/js/fullcalendar/fullcalendar.min.css"]);
load_js(["assets/js/fullcalendar/fullcalendar.min.js", "assets/js/fullcalendar/locales-all.min.js"]);
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

$status_labels = [];
foreach (($booking_statuses ?? []) as $status) {
    $status_labels[$status] = app_lang("gd_booking_status_" . $status);
}

$buttons = [];
if (!empty($can_court_rentals_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/court-rentals/single-modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_new_rental"), ["class" => "btn btn-primary", "title" => app_lang("gd_new_rental")]);
} elseif (!empty($can_bookings_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_add_booking"), ["class" => "btn btn-primary", "title" => app_lang("gd_add_booking")]);
}
if (!empty($can_court_rentals_view)) {
    $buttons[] = anchor(get_uri("grupo_donato/court-rentals"), '<i data-feather="clipboard" class="icon-16"></i> ' . app_lang("gd_view_reservations"), ["class" => "btn btn-default"]);
} elseif (!empty($can_bookings_view)) {
    $buttons[] = anchor(get_uri("grupo_donato/bookings"), '<i data-feather="clipboard" class="icon-16"></i> ' . app_lang("gd_view_reservations"), ["class" => "btn btn-default"]);
}
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <?php echo view("grupo_donato_gestao\\Views\\components\\rentals_nav", [
        "active" => "agenda",
        "can_calendar" => true,
        "can_court_rentals" => $can_court_rentals_view ?? false,
        "can_bookings" => $can_bookings_view ?? false,
        "can_series" => $can_series_view ?? false,
        "can_finance" => $can_finance ?? false,
    ]); ?>

    <div class="card gd-calendar-card">
        <div class="page-title clearfix">
            <div>
                <h4><?php echo app_lang("gd_agenda_title"); ?></h4>
                <div class="text-muted gd-rentals-subtitle">
                    <?php echo app_lang("gd_agenda_help"); ?>
                    <span class="ms-1"><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></span>
                </div>
            </div>
            <?php if ($buttons) { ?><div class="title-button-group gd-toolbar"><?php echo implode(" ", $buttons); ?></div><?php } ?>
        </div>

        <div class="card-body">
            <div class="card mb15">
                <div class="card-body">
                    <div class="gd-filter-grid">
                        <div class="form-group">
                            <label for="gd-calendar-resources"><?php echo app_lang("gd_courts"); ?></label>
                            <select id="gd-calendar-resources" class="select2 form-control">
                                <option value=""><?php echo app_lang("all"); ?></option>
                                <?php foreach ($resources as $resource) { ?>
                                    <option value="<?php echo (int) $resource["id"]; ?>"><?php echo $e($resource["code"] . " — " . $resource["name"]); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gd-calendar-statuses"><?php echo app_lang("gd_booking_status_filter"); ?></label>
                            <select id="gd-calendar-statuses" class="select2 form-control">
                                <option value="hold,pending_confirmation,confirmed,in_progress" selected>Status ativos</option>
                                <option value=""><?php echo app_lang("all"); ?></option>
                                <?php foreach (($booking_statuses ?? []) as $status) { ?>
                                    <option value="<?php echo $e($status); ?>"><?php echo $e($status_labels[$status] ?? $status); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gd-calendar-types"><?php echo app_lang("gd_calendar_content"); ?></label>
                            <select id="gd-calendar-types" class="select2 form-control">
                                <option value="booking,block,closed_exception" selected>Conteudo principal</option>
                                <option value="booking,block,closed_exception,open_exception,weekly_rule"><?php echo app_lang("all"); ?></option>
                                <option value="booking"><?php echo app_lang("gd_calendar_content_bookings"); ?></option>
                                <option value="block"><?php echo app_lang("gd_calendar_content_blocks"); ?></option>
                                <option value="closed_exception"><?php echo app_lang("gd_calendar_content_closures"); ?></option>
                                <option value="open_exception"><?php echo app_lang("gd_calendar_content_openings"); ?></option>
                                <option value="weekly_rule"><?php echo app_lang("gd_calendar_content_availability"); ?></option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="gd-filter-actions">
                                <button type="button" id="gd-calendar-today" class="btn btn-default">
                                    <i data-feather="crosshair" class="icon-16"></i> <?php echo app_lang("today"); ?>
                                </button>
                                <button type="button" id="gd-calendar-clear" class="btn btn-default">
                                    <i data-feather="rotate-ccw" class="icon-16"></i> <?php echo app_lang("gd_clear_filters"); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="gd-legend mb15">
                <span class="gd-legend-item text-warning"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_pending_confirmation"); ?></span>
                <span class="gd-legend-item text-primary"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_confirmed"); ?></span>
                <span class="gd-legend-item text-success"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_in_progress"); ?></span>
                <span class="gd-legend-item text-danger"><span class="gd-legend-dot"></span><?php echo app_lang("gd_calendar_content_closures"); ?></span>
                <span class="gd-legend-item text-muted"><i data-feather="repeat" class="icon-14"></i><?php echo app_lang("gd_recurring_indicator"); ?></span>
            </div>

            <div id="gd-calendar"></div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    var calendarElement = document.getElementById("gd-calendar"),
        resourceFilter = $("#gd-calendar-resources"),
        statusFilter = $("#gd-calendar-statuses"),
        typeFilter = $("#gd-calendar-types"),
        isMobile = window.matchMedia("(max-width: 767px)").matches;

    function values(field) {
        var value = field.val();
        if ($.isArray(value)) { return value.join(","); }
        return value || "";
    }

    function refresh() {
        calendar.refetchEvents();
    }

    var filterFields = resourceFilter.add(statusFilter).add(typeFilter);
    filterFields.select2({width: "100%"}).on("change", refresh);

    var calendar = new FullCalendar.Calendar(calendarElement, {
        locale: AppLanugage.locale,
        timeZone: '<?php echo addslashes($timezone); ?>',
        initialView: isMobile ? "listWeek" : "timeGridWeek",
        height: isMobile ? "auto" : Math.max(620, $(window).height() - 300),
        firstDay: AppHelper.settings.firstDayOfWeek,
        nowIndicator: true,
        allDaySlot: false,
        slotDuration: "00:30:00",
        slotLabelInterval: "01:00:00",
        expandRows: true,
        stickyHeaderDates: true,
        headerToolbar: isMobile ? {
            left: "prev,next",
            center: "title",
            right: "today,listWeek,timeGridDay"
        } : {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek,timeGridDay,listWeek"
        },
        buttonText: {
            today: '<?php echo addslashes(app_lang("today")); ?>',
            month: '<?php echo addslashes(app_lang("month")); ?>',
            week: '<?php echo addslashes(app_lang("week")); ?>',
            day: '<?php echo addslashes(app_lang("day")); ?>',
            list: '<?php echo addslashes(app_lang("gd_list")); ?>'
        },
        events: function(info, success, failure) {
            $.ajax({
                url: '<?php echo_uri("grupo_donato/calendar/events"); ?>',
                data: {
                    start: info.startStr,
                    end: info.endStr,
                    resources: values(resourceFilter),
                    statuses: values(statusFilter),
                    types: values(typeFilter)
                },
                dataType: "json"
            }).done(success).fail(failure);
        },
        eventClick: function(info) {
            var props = info.event.extendedProps || {};
            if (props.event_type === "booking" && props.booking_id) {
                window.location.href = '<?php echo_uri("grupo_donato/bookings/view/"); ?>' + props.booking_id;
            }
        },
        eventDidMount: function(info) {
            var props = info.event.extendedProps || {},
                title = info.event.title || "";
            if (props.status && <?php echo json_encode($status_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>[props.status]) {
                title += " — " + <?php echo json_encode($status_labels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>[props.status];
            }
            info.el.setAttribute("title", title);
            if (props.event_type === "booking") {
                info.el.style.cursor = "pointer";
            }
        },
        loading: function(loading) {
            if (loading) { appLoader.show(); } else { appLoader.hide(); }
            if (typeof feather !== "undefined") { feather.replace(); }
        }
    });

    calendar.render();

    $("#gd-calendar-today").on("click", function(){ calendar.today(); });
    $("#gd-calendar-clear").on("click", function(){
        resourceFilter.val("").trigger("change.select2");
        statusFilter.val("hold,pending_confirmation,confirmed,in_progress").trigger("change.select2");
        typeFilter.val("booking,block,closed_exception").trigger("change.select2");
        refresh();
    });

    $(window).on("resize.gdCalendar", function(){
        var mobileNow = window.matchMedia("(max-width: 767px)").matches;
        calendar.setOption("height", mobileNow ? "auto" : Math.max(620, $(window).height() - 300));
        calendar.updateSize();
    });

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
