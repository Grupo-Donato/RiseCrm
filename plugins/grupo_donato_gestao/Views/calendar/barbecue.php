<?php
load_css(["assets/js/fullcalendar/fullcalendar.min.css"]);
load_js(["assets/js/fullcalendar/fullcalendar.min.js", "assets/js/fullcalendar/locales-all.min.js"]);
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");

$status_labels = [];
foreach (($booking_statuses ?? []) as $status) {
    $status_labels[$status] = app_lang("gd_booking_status_" . $status);
}

$buttons = [];
if (!empty($can_barbecue_rentals_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/barbecue-rentals/single-modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_new_barbecue_rental"), ["class" => "btn btn-primary", "title" => app_lang("gd_new_barbecue_rental")]);
} elseif (!empty($can_bookings_manage)) {
    $buttons[] = modal_anchor(get_uri("grupo_donato/bookings/modal"), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang("gd_add_booking"), ["class" => "btn btn-primary", "title" => app_lang("gd_add_booking")]);
}
if (!empty($can_barbecue_rentals_view)) {
    $buttons[] = anchor(get_uri("grupo_donato/barbecue-rentals"), '<i data-feather="clipboard" class="icon-16"></i> ' . app_lang("gd_menu_barbecue_bookings"), ["class" => "btn btn-default"]);
}
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <div class="card gd-calendar-card">
        <div class="page-title clearfix gd-page-header">
            <div>
                <h4><?php echo app_lang("gd_agenda_title"); ?></h4>
            </div>
            <?php if ($buttons) { ?><div class="title-button-group gd-toolbar"><?php echo implode(" ", $buttons); ?></div><?php } ?>
        </div>

        <div class="card-body">
            <div class="card mb15">
                <div class="card-body">
                    <div class="gd-calendar-court-filter">
                        <div class="gd-calendar-court-filter-heading">
                            <div>
                                <label><?php echo app_lang("gd_barbecues"); ?></label>
                                <div class="text-muted"><?php echo app_lang("gd_calendar_barbecue_filter_help"); ?></div>
                            </div>
                            <button type="button" id="gd-calendar-select-all-courts" class="btn btn-default btn-sm"><?php echo app_lang("gd_all_barbecues"); ?></button>
                        </div>
                        <div class="gd-calendar-court-options" role="group" aria-label="<?php echo $e(app_lang("gd_barbecues")); ?>">
                            <?php foreach ($resources as $resource) { ?>
                                <label class="gd-calendar-court-option">
                                    <input type="checkbox" class="gd-calendar-resource" value="<?php echo (int) $resource["id"]; ?>">
                                    <span title="<?php echo $e($resource["code"] . " — " . $resource["name"]); ?>"><?php echo $e($resource["code"]); ?></span>
                                </label>
                            <?php } ?>
                        </div>
                    </div>

                    <div class="gd-filter-grid gd-calendar-secondary-filters">
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
                                <option value="booking,block,closed_exception" selected>Conteúdo principal</option>
                                <option value="booking,block,closed_exception,open_exception,weekly_rule"><?php echo app_lang("all"); ?></option>
                                <option value="booking"><?php echo app_lang("gd_calendar_content_bookings"); ?></option>
                                <option value="block"><?php echo app_lang("gd_calendar_content_blocks"); ?></option>
                                <option value="closed_exception"><?php echo app_lang("gd_calendar_content_closures"); ?></option>
                                <option value="open_exception"><?php echo app_lang("gd_calendar_content_openings"); ?></option>
                                <option value="weekly_rule"><?php echo app_lang("gd_calendar_content_availability"); ?></option>
                            </select>
                        </div>
                        <div class="form-group" id="gd-calendar-free-duration-group">
                            <label for="gd-calendar-free-duration"><?php echo app_lang("gd_free_slot_duration"); ?></label>
                            <input type="text" id="gd-calendar-free-duration" class="form-control" value="90" inputmode="text" autocomplete="off" placeholder="Ex.: 1h30 ou 150 min">
                            <small class="text-muted">Informe em minutos ou use formatos como 1h30.</small>
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

            <div id="gd-calendar-free-warning" class="alert alert-info mb15" style="display:none">
                <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_calendar_select_barbecue_for_free_slots"); ?>
            </div>

            <div class="gd-legend mb15">
                <span class="gd-legend-item text-warning"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_pending_confirmation"); ?></span>
                <span class="gd-legend-item text-primary"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_confirmed"); ?></span>
                <span class="gd-legend-item text-success"><span class="gd-legend-dot"></span><?php echo app_lang("gd_booking_status_in_progress"); ?></span>
                <span class="gd-legend-item text-danger"><span class="gd-legend-dot"></span><?php echo app_lang("gd_calendar_content_closures"); ?></span>
                <span class="gd-legend-item text-success"><span class="gd-legend-dot"></span><?php echo app_lang("gd_calendar_content_free_slots"); ?></span>
                <span class="gd-legend-item text-muted"><i data-feather="repeat" class="icon-14"></i><?php echo app_lang("gd_recurring_indicator"); ?></span>
            </div>

            <div id="gd-calendar"></div>
            <?php if (!empty($can_barbecue_rentals_manage)) { ?>
                <a id="gd-calendar-free-slot-book" class="hide" href="javascript:;" data-act="ajax-modal" data-modal-lg="1" data-title="<?php echo $e(app_lang("gd_new_barbecue_rental")); ?>" data-action-url="<?php echo get_uri("grupo_donato/barbecue-rentals/single-modal"); ?>"></a>
            <?php } ?>
        </div>
    </div>
</div>

<script>
var gdCalendarTimeZonePlugin = (function(){
    function IntlNamedTimeZone(timeZoneName) {
        FullCalendar.NamedTimeZoneImpl.call(this, timeZoneName);
        this.formatter = new Intl.DateTimeFormat("en-US", {
            timeZone: timeZoneName,
            calendar: "gregory",
            numberingSystem: "latn",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
            hourCycle: "h23"
        });
    }

    IntlNamedTimeZone.prototype = Object.create(FullCalendar.NamedTimeZoneImpl.prototype);
    IntlNamedTimeZone.prototype.constructor = IntlNamedTimeZone;
    IntlNamedTimeZone.prototype.timestampToArray = function(ms) {
        var values = {}, date = new Date(ms);
        this.formatter.formatToParts(date).forEach(function(part){
            if (part.type !== "literal") { values[part.type] = parseInt(part.value, 10); }
        });
        return [values.year, values.month - 1, values.day, values.hour, values.minute, values.second, date.getUTCMilliseconds()];
    };
    IntlNamedTimeZone.prototype.offsetAtTimestamp = function(ms) {
        var parts = this.timestampToArray(ms);
        return (Date.UTC(parts[0], parts[1], parts[2], parts[3], parts[4], parts[5], parts[6]) - ms) / 60000;
    };
    IntlNamedTimeZone.prototype.offsetForArray = function(parts) {
        var wallTime = Date.UTC(parts[0], parts[1], parts[2], parts[3] || 0, parts[4] || 0, parts[5] || 0, parts[6] || 0),
            offset = this.offsetAtTimestamp(wallTime);
        return this.offsetAtTimestamp(wallTime - offset * 60000);
    };

    return FullCalendar.createPlugin({namedTimeZonedImpl: IntlNamedTimeZone});
})();

$(document).ready(function(){
    var calendarElement = document.getElementById("gd-calendar"),
        resourceFilters = $(".gd-calendar-resource"),
        statusFilter = $("#gd-calendar-statuses"),
        typeFilter = $("#gd-calendar-types"),
        freeDuration = $("#gd-calendar-free-duration"),
        freeWarning = $("#gd-calendar-free-warning"),
        freeSlotBook = $("#gd-calendar-free-slot-book"),
        isMobile = window.matchMedia("(max-width: 767px)").matches;

    function values(field) {
        var value = field.val();
        if ($.isArray(value)) { return value.join(","); }
        return value || "";
    }

    function parseDuration(value) {
        var raw = String(value || "").trim().toLowerCase().replace(/\s+/g, " "), match, hours, minutes;
        if (!raw) { return 0; }
        if (/^\d+$/.test(raw)) { return parseInt(raw, 10); }
        match = /^(\d+)\s*h\s*(?:(\d{1,2})\s*(?:m|min|minutos?)?)?$/i.exec(raw);
        if (match) {
            hours = parseInt(match[1], 10);
            minutes = match[2] ? parseInt(match[2], 10) : 0;
            return minutes < 60 ? (hours * 60) + minutes : 0;
        }
        match = /^(\d+)\s*(?:m|min|minutos?)$/i.exec(raw);
        if (match) { return parseInt(match[1], 10); }
        match = /^(\d+):(\d{2})$/.exec(raw);
        if (match) {
            minutes = parseInt(match[2], 10);
            return minutes < 60 ? (parseInt(match[1], 10) * 60) + minutes : 0;
        }
        return 0;
    }

    function resourceValues() {
        return resourceFilters.filter(":checked").map(function(){ return this.value; }).get().join(",");
    }

    function calendarTypes() {
        var types = values(typeFilter).split(",").filter(Boolean);
        if (types.indexOf("free_slot") === -1) { types.push("free_slot"); }
        return types.join(",");
    }

    function refresh() {
        if (calendar) { calendar.refetchEvents(); }
    }

    function syncCourtSelection(changeView) {
        var hasCourt = !!resourceValues();
        freeWarning.toggle(!hasCourt);
        if (hasCourt && changeView && calendar && calendar.view.type === "dayGridMonth") {
            calendar.changeView(isMobile ? "timeGridDay" : "timeGridWeek");
        }
        if (typeof feather !== "undefined") { feather.replace(); }
    }

    var filterFields = statusFilter.add(typeFilter);
    filterFields.select2({width: "100%"}).on("change", refresh);
    freeDuration.on("input change", refresh);
    resourceFilters.on("change", function(){ syncCourtSelection(true); refresh(); });

    var calendar = new FullCalendar.Calendar(calendarElement, {
        plugins: [gdCalendarTimeZonePlugin],
        locale: AppLanugage.locale,
        timeZone: '<?php echo addslashes($timezone); ?>',
        initialView: isMobile ? "timeGridDay" : "timeGridWeek",
        height: isMobile ? "auto" : Math.max(620, $(window).height() - 300),
        firstDay: AppHelper.settings.firstDayOfWeek,
        nowIndicator: true,
        allDaySlot: false,
        slotMinTime: "04:00:00",
        slotMaxTime: "24:00:00",
        slotDuration: "00:30:00",
        slotLabelInterval: "01:00:00",
        slotEventOverlap: false,
        eventOrder: "resource_order,start,title",
        expandRows: true,
        stickyHeaderDates: true,
        headerToolbar: isMobile ? {
            left: "prev,next",
            center: "title",
            right: "today,listWeek,timeGridDay"
        } : {
            left: "prev,next today",
            center: "title",
            right: "timeGridWeek,timeGridDay,listWeek"
        },
        buttonText: {
            prev: '\u2039',
            next: '\u203a',
            today: '<?php echo addslashes(app_lang("today")); ?>',
            month: '<?php echo addslashes(app_lang("month")); ?>',
            week: '<?php echo addslashes(app_lang("week")); ?>',
            day: '<?php echo addslashes(app_lang("day")); ?>',
            list: '<?php echo addslashes(app_lang("gd_list")); ?>'
        },
        events: function(info, success, failure) {
            if (!resourceValues()) { success([]); return; }
            $.ajax({
                url: '<?php echo_uri("grupo_donato/barbecue-calendar/events"); ?>',
                data: {
                    start: info.startStr,
                    end: info.endStr,
                    resources: resourceValues(),
                    statuses: values(statusFilter),
                    types: calendarTypes(),
                    duration_minutes: parseDuration(values(freeDuration)) || 90
                },
                dataType: "json"
            }).done(success).fail(failure);
        },
        eventClick: function(info) {
            var props = info.event.extendedProps || {};
            if (props.event_type === "free_slot" && freeSlotBook.length) {
                freeSlotBook.attr("data-post-prefill_date", props.local_date || "")
                    .attr("data-post-prefill_start_time", props.local_start_time || "")
                    .attr("data-post-prefill_duration_minutes", props.duration_minutes || 90)
                    .attr("data-post-prefill_resource_id", props.resource_id || "")
                    .trigger("click");
                return;
            }
            if (props.barbecue_rental_id) {
                window.location.href = '<?php echo_uri("grupo_donato/barbecue-rentals/view/"); ?>' + props.barbecue_rental_id;
                return;
            }
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
            if (props.event_type === "booking" || (props.event_type === "free_slot" && freeSlotBook.length)) {
                info.el.style.cursor = "pointer";
            }
        },
        loading: function(loading) {
            if (loading) { appLoader.show(); } else { appLoader.hide(); }
            if (typeof feather !== "undefined") { feather.replace(); }
        }
    });

    calendar.render();
    syncCourtSelection(false);

    $("#gd-calendar-today").on("click", function(){ calendar.today(); });
    $("#gd-calendar-select-all-courts").on("click", function(){
        resourceFilters.prop("checked", true);
        syncCourtSelection(false);
        refresh();
    });
    $("#gd-calendar-clear").on("click", function(){
        resourceFilters.prop("checked", false);
        statusFilter.val("hold,pending_confirmation,confirmed,in_progress").trigger("change.select2");
        typeFilter.val("booking,block,closed_exception").trigger("change.select2");
        freeDuration.val("90").trigger("change.select2");
        syncCourtSelection(false);
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
