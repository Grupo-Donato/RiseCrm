<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$initial_mode = in_array(($initial_mode ?? "single"), ["single", "recurring", "special"], true) ? $initial_mode : "single";
$messages = [
    "resource_required" => app_lang("gd_select_at_least_one_court"),
    "date_required" => app_lang("gd_rental_date_required"),
    "time_required" => app_lang("gd_rental_time_required"),
    "duration_required" => app_lang("gd_invalid_rental_duration"),
    "due_day_required" => app_lang("gd_due_day_required"),
    "special_amount_required" => app_lang("gd_special_amount_required"),
    "special_end_required" => app_lang("gd_special_end_required"),
    "checking" => app_lang("gd_booking_form_checking"),
    "available" => app_lang("gd_booking_availability_ok"),
    "unavailable" => app_lang("gd_booking_availability_problem"),
    "error" => app_lang("gd_booking_availability_error"),
    "preview_error" => app_lang("gd_occurrence_preview_error"),
    "conflict" => app_lang("gd_booking_conflict_friendly"),
    "blocked" => app_lang("gd_booking_blocked_friendly"),
    "closed" => app_lang("gd_booking_closed_friendly"),
    "outside_hours" => app_lang("gd_booking_outside_hours_friendly"),
    "resource_problem" => app_lang("gd_booking_resource_problem_friendly"),
];
$time_options = [];
for ($minutes = 0; $minutes < 24 * 60; $minutes += 30) {
    $hour = intdiv($minutes, 60);
    $minute = $minutes % 60;
    $time_options[] = [
        "value" => sprintf("%02d:%02d", $hour, $minute),
        "label" => sprintf("%02dh%02d", $hour, $minute),
    ];
}
?>
<?php echo form_open(get_uri("grupo_donato/court-rentals/save-rental"), ["id" => "gd-rental-form", "class" => "general-form", "role" => "form"]); ?>
<input type="hidden" name="rental_mode" id="gd-rental-mode" value="<?php echo $e($initial_mode); ?>">
<input type="hidden" name="rental_type" id="gd-rental-type" value="single">
<input type="hidden" name="title" id="gd-rental-title" value="">
<input type="hidden" name="booking_status" id="gd-rental-booking-status" value="pending_confirmation">
<input type="hidden" name="default_booking_status" id="gd-rental-default-booking-status" value="pending_confirmation">
<input type="hidden" name="frequency" value="weekly">
<input type="hidden" name="interval_value" value="1">
<input type="hidden" name="ends_mode" value="open_ended">
<input type="hidden" name="conflict_policy" value="reject_series">
<input type="hidden" name="generation_horizon_days" value="90">
<input type="hidden" name="starts_at_local" id="gd-rental-starts-at">
<input type="hidden" name="ends_at_local" id="gd-rental-ends-at">
<input type="hidden" name="starts_on" id="gd-rental-starts-on">
<input type="hidden" name="local_start_time" id="gd-rental-local-start">
<input type="hidden" name="local_end_time" id="gd-rental-local-end">
<input type="hidden" name="effective_from" id="gd-rental-effective-from">
<input type="hidden" name="effective_until" id="gd-rental-effective-until">
<input type="hidden" name="list_amount" id="gd-rental-list-amount">
<input type="hidden" name="negotiated_amount" id="gd-rental-negotiated-amount">
<input type="hidden" name="currency" value="BRL">
<input type="hidden" name="metadata" id="gd-rental-metadata">

<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="mb20">
            <h5 class="mb15"><?php echo app_lang("gd_rental_type_choice"); ?></h5>
            <div class="form-group mb0">
                <select name="rental_mode_choice" id="gd-rental-mode-choice" class="form-control">
                    <option value="single"<?php echo $initial_mode === "single" ? " selected" : ""; ?>><?php echo app_lang("gd_rental_mode_single"); ?></option>
                    <option value="recurring"<?php echo $initial_mode === "recurring" ? " selected" : ""; ?>><?php echo app_lang("gd_rental_mode_recurring"); ?></option>
                    <option value="special"<?php echo $initial_mode === "special" ? " selected" : ""; ?>><?php echo app_lang("gd_rental_mode_special"); ?></option>
                </select>
                <div class="text-muted mt5"><small id="gd-rental-mode-help"></small></div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_customer"); ?></h5>
                    <div class="form-group">
                        <label for="gd-rental-customer"><?php echo app_lang("gd_customer"); ?> <span class="text-danger">*</span></label>
                        <input type="hidden" name="customer_account_id" id="gd-rental-customer-id">
                        <input type="text" id="gd-rental-customer" class="form-control" maxlength="190" autocomplete="off" required data-rule-required="true" placeholder="<?php echo $e(app_lang("gd_customer_name_placeholder")); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gd-rental-contact"><?php echo app_lang("gd_contact"); ?></label>
                        <input type="hidden" name="contact_person_id" id="gd-rental-contact-id">
                        <input type="text" id="gd-rental-contact" class="form-control" maxlength="190" autocomplete="off" placeholder="<?php echo $e(app_lang("gd_contact_name_placeholder")); ?>">
                    </div>
                    <div class="form-group mb0">
                        <label for="gd-rental-phone"><?php echo app_lang("phone"); ?></label>
                        <input id="gd-rental-phone" name="contact_phone" class="form-control" inputmode="tel" maxlength="15" autocomplete="off" placeholder="(00) 00000-0000">
                        <small class="text-muted"><?php echo app_lang("gd_contact_optional_help"); ?></small>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_schedule"); ?></h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gd-rental-date"><span class="gd-date-label"><?php echo app_lang("gd_rental_date"); ?></span> <span class="text-danger">*</span></label>
                                <input type="date" id="gd-rental-date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gd-rental-start-time"><?php echo app_lang("gd_local_start_time"); ?> <span class="text-danger">*</span></label>
                                <select id="gd-rental-start-time" class="form-control" required>
                                    <option value=""></option>
                                    <?php foreach ($time_options as $time_option) { ?>
                                        <option value="<?php echo $e($time_option["value"]); ?>"><?php echo $e($time_option["label"]); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="gd-regular-duration">
                        <div class="form-group">
                            <label for="gd-rental-duration"><?php echo app_lang("gd_rental_duration"); ?> <span class="text-danger">*</span></label>
                            <select name="duration_minutes" id="gd-rental-duration" class="form-control">
                                <option value="90">1h30</option>
                                <option value="120">2h</option>
                            </select>
                        </div>
                    </div>

                    <div class="gd-special-duration" style="display:none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gd-rental-special-end"><?php echo app_lang("gd_special_end_time"); ?> <span class="text-danger">*</span></label>
                                    <select id="gd-rental-special-end" class="form-control">
                                        <option value=""></option>
                                        <?php foreach ($time_options as $time_option) { ?>
                                            <option value="<?php echo $e($time_option["value"]); ?>"><?php echo $e($time_option["label"]); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gd-rental-special-amount"><?php echo app_lang("gd_special_amount"); ?> <span class="text-danger">*</span></label>
                                    <input type="text" id="gd-rental-special-amount" class="form-control" inputmode="decimal" autocomplete="off" placeholder="0,00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="gd-recurring-fields" style="display:none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gd-rental-due-day"><?php echo app_lang("gd_preferred_due_day"); ?> <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="31" name="preferred_due_day" id="gd-rental-due-day" class="form-control" placeholder="1 a 31">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label><?php echo app_lang("gd_recurrence"); ?></label>
                                    <input type="text" id="gd-rental-weekday-preview" class="form-control" value="-" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-muted mb0">
                        <div id="gd-rental-time-preview"><?php echo app_lang("gd_choose_date_and_time"); ?></div>
                        <div><?php echo app_lang("gd_rental_value") . ": "; ?><strong id="gd-rental-price-hint">-</strong></div>
                        <div><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb20">
            <h5 class="mb15"><?php echo app_lang("gd_select_court"); ?></h5>
            <?php if ($resources) { ?>
                <div class="form-group mb0">
                    <select name="selected_resource_id" id="gd-rental-court" class="form-control">
                        <option value=""></option>
                        <?php foreach ($resources as $resource) { ?>
                            <option value="<?php echo (int) $resource["id"]; ?>" data-court-code="<?php echo $e($resource["code"]); ?>">
                                <?php echo $e($resource["code"] . " — " . $resource["name"]); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            <?php } else { ?>
                <div class="alert alert-warning mb0"><?php echo app_lang("gd_booking_no_bookable_resources"); ?></div>
            <?php } ?>
            <div id="gd-rental-availability" class="mt15"></div>
        </div>

        <div class="mb0">
            <h5 class="mb15"><?php echo app_lang("gd_commercial_notes"); ?></h5>
            <div class="form-group">
                <textarea name="commercial_notes" class="form-control" rows="3" maxlength="5000" placeholder="<?php echo $e(app_lang("gd_simple_rental_notes_placeholder")); ?>"></textarea>
            </div>
            <div class="form-group mb0">
                <label class="d-flex align-items-start mb0">
                    <input type="checkbox" name="activate" value="1" checked class="mt5 me-2">
                    <span>
                        <strong><?php echo app_lang("gd_confirm_and_activate"); ?></strong>
                        <span class="text-muted d-block"><small><?php echo app_lang("gd_confirm_and_activate_help"); ?></small></span>
                    </span>
                </label>
            </div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" id="gd-rental-check" class="btn btn-info">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_check_availability"); ?>
    </button>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary" id="gd-rental-submit">
        <i data-feather="save" class="icon-16"></i> <span><?php echo app_lang("gd_create_single_rental"); ?></span>
    </button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function(){
    var form = $("#gd-rental-form"),
        modeInput = $("#gd-rental-mode"),
        modeChoice = $("#gd-rental-mode-choice"),
        dateInput = $("#gd-rental-date"),
        startTime = $("#gd-rental-start-time"),
        durationInput = $("#gd-rental-duration"),
        courtInput = $("#gd-rental-court"),
        specialEnd = $("#gd-rental-special-end"),
        specialAmount = $("#gd-rental-special-amount"),
        dueDay = $("#gd-rental-due-day"),
        customer = $("#gd-rental-customer"),
        contact = $("#gd-rental-contact"),
        phone = $("#gd-rental-phone"),
        availability = $("#gd-rental-availability"),
        checkButton = $("#gd-rental-check"),
        submitButton = $("#gd-rental-submit"),
        activateInput = form.find("input[name='activate']"),
        messages = <?php echo json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        initialMode = <?php echo json_encode($initial_mode); ?>,
        prices = <?php echo json_encode($pricing_presets ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        modeHelp = {
            single: <?php echo json_encode(app_lang("gd_rental_mode_single_help")); ?>,
            recurring: <?php echo json_encode(app_lang("gd_rental_mode_recurring_help")); ?>,
            special: <?php echo json_encode(app_lang("gd_rental_mode_special_help")); ?>
        },
        checkTimer = null,
        availabilityOk = false;

    form.closest(".modal-dialog").addClass("modal-lg");

    function escapeHtml(value) { return $("<div>").text(value || "").html(); }
    function digitsOnly(value) { return String(value || "").replace(/\D+/g, ""); }
    function maskPhone(value) {
        var digits = digitsOnly(value);
        if (digits.length > 11 && digits.indexOf("55") === 0) { digits = digits.substring(2); }
        digits = digits.substring(0, 11);
        if (digits.length <= 2) { return digits; }
        if (digits.length <= 6) { return "(" + digits.substring(0, 2) + ") " + digits.substring(2); }
        if (digits.length <= 10) { return "(" + digits.substring(0, 2) + ") " + digits.substring(2, 6) + "-" + digits.substring(6); }
        return "(" + digits.substring(0, 2) + ") " + digits.substring(2, 7) + "-" + digits.substring(7);
    }
    function maskMoney(value) {
        var digits = digitsOnly(value);
        if (!digits) { return ""; }
        digits = digits.replace(/^0+(?=\d{3})/, "");
        while (digits.length < 3) { digits = "0" + digits; }
        var cents = digits.slice(-2), integer = digits.slice(0, -2);
        integer = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        return integer + "," + cents;
    }
    function normalizeMoney(value) {
        value = String(value || "").trim().replace(/[^\d,.-]/g, "");
        if (!value) { return ""; }
        if (value.indexOf(",") !== -1) { return value.replace(/\./g, "").replace(",", "."); }
        return value;
    }
    function brl(value) {
        return new Intl.NumberFormat("pt-BR", {style: "currency", currency: "BRL"}).format(Number(value || 0));
    }
    function selectedDuration() { return parseInt(durationInput.val() || "0", 10); }
    function isHalfHour(value) { return /^\d{2}:(00|30)$/.test(value || ""); }
    function selectedCourt() {
        var selected = courtInput.find("option:selected");
        return {id: courtInput.val() || "", code: selected.data("court-code") || ""};
    }
    function mode() { return modeInput.val(); }
    function pad(value) { return String(value).padStart(2, "0"); }
    function addMinutes(date, time, minutes) {
        if (!date || !time) { return null; }
        var parts = time.split(":"), base = new Date(date + "T" + time + ":00");
        if (isNaN(base.getTime()) || parts.length < 2) { return null; }
        base.setMinutes(base.getMinutes() + minutes);
        return {
            date: base.getFullYear() + "-" + pad(base.getMonth() + 1) + "-" + pad(base.getDate()),
            time: pad(base.getHours()) + ":" + pad(base.getMinutes())
        };
    }
    function formatDate(value) {
        if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) { return "-"; }
        var parts = value.split("-");
        return parts[2] + "/" + parts[1] + "/" + parts[0];
    }
    function recurrenceLabel(value) {
        if (!value) { return "-"; }
        var date = new Date(value + "T12:00:00");
        if (isNaN(date.getTime())) { return "-"; }
        var labels = ["Todo domingo", "Toda segunda-feira", "Toda terça-feira", "Toda quarta-feira", "Toda quinta-feira", "Toda sexta-feira", "Todo sábado"];
        return labels[date.getDay()];
    }
    function isoWeekday(value) {
        if (!value) { return ""; }
        var date = new Date(value + "T12:00:00"), day = date.getDay();
        return day === 0 ? 7 : day;
    }
    function setPostValue(data, name, value) {
        var found = false;
        $.each(data, function(_, field){
            if (field && field.name === name) { field.value = value; found = true; return false; }
        });
        if (!found) { data.push({name: name, value: value}); }
    }
    function setResult(type, icon, text) {
        availability.html('<div class="alert alert-' + type + ' mb0"><i data-feather="' + icon + '" class="icon-16"></i> ' + escapeHtml(text) + '</div>');
        availabilityOk = type === "success";
        if (typeof feather !== "undefined") { feather.replace(); }
    }
    function currentAmount() {
        if (mode() === "special") { return normalizeMoney(specialAmount.val()); }
        var duration = selectedDuration();
        return prices[mode()] ? String(prices[mode()][duration] || "") : "";
    }
    function scheduleValues() {
        var date = dateInput.val(), start = startTime.val(), currentMode = mode(), duration = selectedDuration(), end = null;
        if (!date || !start) { return null; }
        if (!isHalfHour(start)) { return null; }
        if (currentMode === "special") {
            if (!specialEnd.val()) { return null; }
            if (!isHalfHour(specialEnd.val())) { return null; }
            end = {date: date, time: specialEnd.val()};
            if (specialEnd.val() <= start) {
                var next = addMinutes(date, specialEnd.val(), 24 * 60);
                end.date = next ? next.date : date;
            }
        } else {
            end = addMinutes(date, start, duration);
        }
        if (!end) { return null; }
        return {
            startDate: date,
            startTime: start,
            endDate: end.date,
            endTime: end.time,
            startsAt: date + "T" + start,
            endsAt: end.date + "T" + end.time
        };
    }
    function syncDerivedFields() {
        var currentMode = mode(), schedule = scheduleValues(), amount = currentAmount(), court = selectedCourt(), duration = selectedDuration();
        $("#gd-rental-type").val(currentMode === "recurring" ? "recurring" : "single");
        var activeStatus = activateInput.is(":checked") ? "confirmed" : "pending_confirmation";
        $("#gd-rental-booking-status").val(activeStatus);
        $("#gd-rental-default-booking-status").val(activeStatus);
        $("#gd-rental-customer-id").val($.trim(customer.val()) ? "new:" + $.trim(customer.val()) : "");
        $("#gd-rental-contact-id").val($.trim(contact.val()) ? "new:" + $.trim(contact.val()) : "");
        $("#gd-rental-list-amount, #gd-rental-negotiated-amount").val(amount);
        $("#gd-rental-effective-from").val(dateInput.val() || "");
        $("#gd-rental-effective-until").val(currentMode === "recurring" ? "" : (schedule ? schedule.endDate : ""));

        if (schedule) {
            $("#gd-rental-starts-at").val(schedule.startsAt);
            $("#gd-rental-ends-at").val(schedule.endsAt);
            $("#gd-rental-starts-on").val(schedule.startDate);
            $("#gd-rental-local-start").val(schedule.startTime);
            $("#gd-rental-local-end").val(schedule.endTime);
        } else {
            $("#gd-rental-starts-at, #gd-rental-ends-at, #gd-rental-starts-on, #gd-rental-local-start, #gd-rental-local-end").val("");
        }

        var metadata = {rental_mode: currentMode};
        if (currentMode === "special") { metadata.package = "court_and_barbecue"; }
        else { metadata.duration_minutes = duration; metadata.pricing_preset = currentMode + "_" + duration; }
        $("#gd-rental-metadata").val(JSON.stringify(metadata));

        var titleBits = [$.trim(customer.val()), court.code];
        if (currentMode === "recurring") { titleBits.push("mensalista"); }
        else if (currentMode === "special") { titleBits.push("quadra + churrasqueira"); }
        else { titleBits.push("avulso"); }
        if (dateInput.val()) { titleBits.push(formatDate(dateInput.val())); }
        if (startTime.val()) { titleBits.push(startTime.val()); }
        $("#gd-rental-title").val(titleBits.filter(Boolean).join(" — ").substring(0, 180));
    }
    function syncMode() {
        var currentMode = modeChoice.val() || initialMode;
        modeInput.val(currentMode);
        $("#gd-rental-mode-help").text(modeHelp[currentMode] || "");
        $(".gd-regular-duration").toggle(currentMode !== "special");
        $(".gd-special-duration").toggle(currentMode === "special");
        $(".gd-recurring-fields").toggle(currentMode === "recurring");
        specialEnd.prop("required", currentMode === "special");
        specialAmount.prop("required", currentMode === "special");
        dueDay.prop("required", currentMode === "recurring");
        $(".gd-date-label").text(currentMode === "recurring" ? <?php echo json_encode(app_lang("gd_first_date")); ?> : <?php echo json_encode(app_lang("gd_rental_date")); ?>);
        submitButton.find("span").text(currentMode === "recurring" ? <?php echo json_encode(app_lang("gd_create_recurring_rental")); ?> : (currentMode === "special" ? <?php echo json_encode(app_lang("gd_create_special_rental")); ?> : <?php echo json_encode(app_lang("gd_create_single_rental")); ?>));

        durationInput.find("option[value='90']").text(currentMode === "recurring" ? "1h30 — R$ 900,00/mês" : "1h30 — R$ 380,00");
        durationInput.find("option[value='120']").text(currentMode === "recurring" ? "2h — R$ 1.050,00/mês" : "2h — R$ 460,00");
        clearAvailability();
        updateSummary();
    }
    function updateSummary() {
        syncDerivedFields();
        var currentMode = mode(), schedule = scheduleValues(), amount = currentAmount();
        $("#gd-rental-weekday-preview").val(dateInput.val() ? recurrenceLabel(dateInput.val()) : "-");
        if (schedule) {
            var timeText = schedule.startTime + " às " + schedule.endTime;
            if (currentMode === "recurring") { timeText = recurrenceLabel(schedule.startDate) + ", " + timeText; }
            $("#gd-rental-time-preview").text(timeText);
        } else {
            $("#gd-rental-time-preview").text(<?php echo json_encode(app_lang("gd_choose_date_and_time")); ?>);
        }
        $("#gd-rental-price-hint").text(amount ? brl(amount) + (currentMode === "recurring" ? "/mês" : "") : "-");
    }
    function clearAvailability() {
        availabilityOk = false;
        availability.empty();
    }
    function unavailableMessage(response) {
        var data = response && response.data ? response.data : {};
        if ($.isArray(data.conflicts) && data.conflicts.length) {
            return messages.conflict;
        }
        var resources = data.resources || {}, reason = "";
        $.each(resources, function(_, item){
            if (item && item.available !== true && item.reason_code) {
                reason = item.reason_code;
                return false;
            }
        });
        if (reason === "active_block") { return messages.blocked; }
        if (reason === "closed_exception") { return messages.closed; }
        if (reason === "outside_availability") { return messages.outside_hours; }
        if (["resource_not_found", "resource_inactive", "resource_not_bookable"].indexOf(reason) !== -1) {
            return messages.resource_problem;
        }
        return (response && response.message) || messages.unavailable;
    }
    function validationMessage() {
        if (!$.trim(customer.val())) { return <?php echo json_encode(app_lang("gd_court_rental_customer_required")); ?>; }
        if (!dateInput.val()) { return messages.date_required; }
        if (!startTime.val()) { return messages.time_required; }
        if (!selectedCourt().id) { return messages.resource_required; }
        if (mode() === "recurring" && (!dueDay.val() || parseInt(dueDay.val(), 10) < 1 || parseInt(dueDay.val(), 10) > 31)) { return messages.due_day_required; }
        if (mode() === "special") {
            if (!specialEnd.val()) { return messages.special_end_required; }
            if (!currentAmount() || Number(currentAmount()) <= 0) { return messages.special_amount_required; }
        } else if ([90, 120].indexOf(selectedDuration()) === -1) { return messages.duration_required; }
        if (!scheduleValues()) { return messages.time_required; }
        return "";
    }
    function preparedData() {
        syncDerivedFields();
        var data = form.serializeArray(), error = validationMessage();
        if (error) { setResult("danger", "alert-triangle", error); return false; }
        setPostValue(data, "customer_account_id", $("#gd-rental-customer-id").val());
        setPostValue(data, "contact_person_id", $("#gd-rental-contact-id").val());
        setPostValue(data, "contact_phone", digitsOnly(phone.val()).substring(0, 11));
        setPostValue(data, "negotiated_amount", currentAmount());
        setPostValue(data, "list_amount", currentAmount());
        if (mode() === "recurring") {
            setPostValue(data, "weekdays[]", isoWeekday(dateInput.val()));
        }
        return data;
    }
    function checkAvailability() {
        var data = preparedData();
        if (data === false) { return; }
        var currentMode = mode(), original = checkButton.html();
        checkButton.prop("disabled", true).text(messages.checking);
        setResult("warning", "loader", messages.checking);
        $.ajax({
            url: currentMode === "recurring" ? '<?php echo_uri("grupo_donato/court-rentals/preview"); ?>' : '<?php echo_uri("grupo_donato/court-rentals/check-availability"); ?>',
            type: "POST",
            data: $.param(data),
            dataType: "json"
        }).done(function(response){
            if (currentMode === "recurring") {
                if (response && response.success) {
                    var rows = response.data || [], count = rows.length,
                        unavailableCount = rows.filter(function(item){ return !item || item.available !== true; }).length;
                    if (unavailableCount > 0) {
                        setResult("danger", "alert-triangle", unavailableCount + " de " + count + " ocorrências possuem conflito ou indisponibilidade.");
                    } else {
                        setResult("success", "check-circle", count + " " + <?php echo json_encode(app_lang("gd_occurrences_previewed")); ?> + ". " + messages.available);
                    }
                } else {
                    setResult("danger", "alert-triangle", (response && response.message) || messages.preview_error);
                }
            } else if (response && response.success && response.data && response.data.available) {
                setResult("success", "check-circle", messages.available);
            } else {
                setResult("danger", "alert-triangle", unavailableMessage(response));
            }
        }).fail(function(xhr){
            var body = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            setResult("danger", "alert-triangle", (body && body.message) || messages.error);
        }).always(function(){
            checkButton.prop("disabled", false).html(original);
            if (typeof feather !== "undefined") { feather.replace(); }
        });
    }
    function scheduleAutoCheck() {
        clearTimeout(checkTimer);
        clearAvailability();
        updateSummary();
        if (validationMessage()) { return; }
        checkTimer = setTimeout(checkAvailability, 550);
    }

    form.on("input", "#gd-rental-phone", function(){ this.value = maskPhone(this.value); });
    form.on("input", "#gd-rental-special-amount", function(){ this.value = maskMoney(this.value); });
    form.on("change", "#gd-rental-mode-choice", syncMode);
    form.on("change", "#gd-rental-duration, #gd-rental-court", scheduleAutoCheck);
    form.on("change input", "#gd-rental-date, #gd-rental-start-time, #gd-rental-special-end, #gd-rental-due-day", scheduleAutoCheck);
    form.on("input", "#gd-rental-customer, #gd-rental-contact, #gd-rental-phone, #gd-rental-special-amount", updateSummary);
    activateInput.on("change", function(){ syncDerivedFields(); });
    customer.on("blur", scheduleAutoCheck);
    checkButton.on("click", checkAvailability);

    form.on("submit.gdSimpleRental", function(event){
        var error = validationMessage();
        syncDerivedFields();
        if (error) {
            event.preventDefault();
            event.stopImmediatePropagation();
            setResult("danger", "alert-triangle", error);
            return false;
        }
    });

    form.appForm({
        beforeAjaxSubmit: function(data) {
            var prepared = preparedData();
            if (prepared === false) { return false; }
            data.length = 0;
            $.each(prepared, function(_, field){ data.push(field); });
            return true;
        },
        onSuccess: function(response){
            location.href = '<?php echo_uri("grupo_donato/court-rentals/view/"); ?>' + response.id;
        }
    });

    modeChoice.val(initialMode);
    syncMode();
    updateSummary();
    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
