<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$occurrence = is_array($occurrence ?? null) ? $occurrence : null;
$occurrence_options = is_array($occurrence_options ?? null) ? $occurrence_options : [];
$selected_occurrence_date = (string) ($selected_occurrence_date ?? "");
$current = (array) ($occurrence["current"] ?? []);
$active = !empty($occurrence["has_reschedule"]);
$has_occurrence = $occurrence !== null;
$has_options = !empty($occurrence_options);
$date = (string) ($occurrence["occurrence_date"] ?? "");
$current_time = (string) ($current["local_start_time"] ?? "");
$current_end_time = (string) ($current["local_end_time"] ?? "");
$current_resource_id = (int) ($current["resource_id"] ?? 0);
$current_resource = (string) ($current["resource"] ?? "-");
$duration_minutes = 0;
if (!empty($current["starts_at_utc"]) && !empty($current["ends_at_utc"])) {
    try {
        $duration_minutes = (int) ((new DateTimeImmutable((string) $current["ends_at_utc"], new DateTimeZone("UTC")))->getTimestamp() - (new DateTimeImmutable((string) $current["starts_at_utc"], new DateTimeZone("UTC")))->getTimestamp()) / 60;
    } catch (Throwable $ignore) {
        $duration_minutes = 0;
    }
}
$initial = [
    "booking_id" => (int) ($occurrence["booking_id"] ?? 0),
    "date" => $date,
    "start" => $current_time,
    "end" => $current_end_time,
    "resource_id" => $current_resource_id,
    "resource" => $current_resource,
    "duration" => $duration_minutes,
];
?>
<?php echo form_open(get_uri("grupo_donato/court-rentals/reschedule"), ["id" => "gd-reschedule-form", "class" => "general-form gd-reschedule-modal", "role" => "form"]); ?>
<div class="modal-body clearfix gd-reschedule-modal-body">
    <div class="container-fluid">
        <input type="hidden" name="booking_id" id="gd-reschedule-booking-id" value="<?php echo (int) ($occurrence["booking_id"] ?? 0); ?>">
        <input type="hidden" name="occurrence_date" id="gd-reschedule-date" value="<?php echo $e($date); ?>">
        <input type="hidden" name="from_time" id="gd-reschedule-from" value="<?php echo $e($current_time); ?>">
        <input type="hidden" name="until_time" id="gd-reschedule-until" value="<?php echo $e($current_end_time); ?>">
        <input type="hidden" name="resource_id" value="0">
        <input type="hidden" name="new_start_time" id="gd-reschedule-start" value="<?php echo $e($current_time); ?>">
        <input type="hidden" name="new_resource_id" id="gd-reschedule-resource" value="">
        <input type="hidden" id="gd-reschedule-current-resource-id" value="<?php echo $current_resource_id; ?>">

        <?php if ($has_options) { ?>
            <div class="gd-reschedule-step mb20">
                <label for="gd-reschedule-occurrence" class="gd-reschedule-step-title">1. <?php echo app_lang("gd_reschedule_occurrence"); ?></label>
                <div class="text-muted mb10"><small><?php echo app_lang("gd_reschedule_choose_occurrence"); ?></small></div>
                <select id="gd-reschedule-occurrence" class="form-control">
                    <option value=""><?php echo $e("Selecione uma ocorrência futura"); ?></option>
                    <?php foreach ($occurrence_options as $item) {
                        $item_date = (string) ($item["occurrence_date"] ?? "");
                        $item_start = (string) ($item["local_start_time"] ?? "");
                        $item_end = (string) ($item["local_end_time"] ?? "");
                        $item_resource = (string) ($item["resource"] ?? "-");
                        $is_selected = $selected_occurrence_date !== "" && $selected_occurrence_date === $item_date;
                    ?>
                        <option value="<?php echo (int) ($item["booking_id"] ?? 0); ?>"
                            data-date="<?php echo $e($item_date); ?>"
                            data-start="<?php echo $e($item_start); ?>"
                            data-end="<?php echo $e($item_end); ?>"
                            data-resource-id="<?php echo (int) ($item["resource_id"] ?? 0); ?>"
                            data-resource="<?php echo $e($item_resource); ?>"<?php echo $is_selected ? " selected" : ""; ?>>
                            <?php echo $e($item_date . " · " . $item_start . " às " . $item_end . " · " . $item_resource); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        <?php } ?>

        <?php if (!$has_occurrence && !$has_options) { ?>
            <div class="alert alert-warning mb0"><i data-feather="calendar" class="icon-16"></i> <?php echo app_lang("gd_reschedule_no_future_occurrences"); ?></div>
        <?php } elseif (!$has_occurrence && $has_options) { ?>
            <div id="gd-reschedule-selection-help" class="alert alert-info mb20"><i data-feather="calendar" class="icon-16"></i> Escolha uma ocorrência acima para iniciar a alteração.</div>
        <?php } ?>

        <div id="gd-reschedule-editor" style="<?php echo $has_occurrence ? "" : "display:none;"; ?>">
            <div class="gd-reschedule-current mb20">
                <div>
                    <small><?php echo app_lang("gd_reschedule_current"); ?></small>
                    <strong id="gd-reschedule-current-period"><?php echo $e($date !== "" ? $date . " · " . $current_time . " às " . $current_end_time : "-"); ?></strong>
                </div>
                <div>
                    <small><?php echo app_lang("gd_courts"); ?></small>
                    <strong id="gd-reschedule-current-resource"><?php echo $e($current_resource); ?></strong>
                </div>
            </div>

            <?php if ($active) { ?>
                <div class="alert alert-warning">
                    <strong><?php echo app_lang("gd_reschedule_active"); ?></strong>
                    <?php if (!empty($occurrence["exception"]["reason"])) { ?><br><?php echo $e($occurrence["exception"]["reason"]); ?><?php } ?>
                </div>
                <button type="button" id="gd-reschedule-revert" class="btn btn-outline-secondary"><i data-feather="rotate-ccw" class="icon-16"></i> <?php echo app_lang("gd_reschedule_revert"); ?></button>
            <?php } else { ?>
                <div class="gd-reschedule-step mb20">
                    <div class="gd-reschedule-step-title">2. <?php echo app_lang("gd_reschedule_new_schedule"); ?></div>
                    <div class="row">
                        <div class="col-md-4 form-group mb10">
                            <label for="gd-reschedule-new-start"><?php echo app_lang("gd_reschedule_start_time"); ?></label>
                            <select id="gd-reschedule-new-start" class="form-control" aria-describedby="gd-reschedule-duration-help"></select>
                        </div>
                        <div class="col-md-4 form-group mb10">
                            <label for="gd-reschedule-duration"><?php echo app_lang("gd_reschedule_duration"); ?></label>
                            <input type="text" id="gd-reschedule-duration" class="form-control" readonly value="">
                        </div>
                        <div class="col-md-4 form-group mb10">
                            <label for="gd-reschedule-new-end"><?php echo app_lang("gd_reschedule_end_time"); ?></label>
                            <input type="text" id="gd-reschedule-new-end" class="form-control" readonly value="">
                        </div>
                    </div>
                    <small id="gd-reschedule-duration-help" class="text-muted"><?php echo app_lang("gd_reschedule_duration_preserved"); ?></small>
                </div>

                <div class="gd-reschedule-available-courts mb20">
                    <div class="gd-reschedule-availability-heading">
                        <div>
                            <div class="gd-reschedule-step-title">3. <?php echo app_lang("gd_reschedule_choose_court"); ?></div>
                            <div id="gd-reschedule-period" class="fw-bold"></div>
                        </div>
                    </div>
                    <div class="mb10"><small><?php echo app_lang("gd_reschedule_choose_court_help"); ?></small></div>
                    <div id="gd-reschedule-status" class="mb10"></div>
                    <div id="gd-reschedule-resources" class="gd-available-courts-grid"></div>
                </div>

                <div id="gd-reschedule-confirm" class="card" style="display:none">
                    <div class="card-body">
                        <div class="gd-reschedule-step-title mb10">4. <?php echo app_lang("gd_reschedule_summary"); ?></div>
                        <div class="gd-reschedule-summary mb15">
                            <div><small><?php echo app_lang("gd_reschedule_original_schedule"); ?></small><strong id="gd-reschedule-summary-original"></strong></div>
                            <div><small><?php echo app_lang("gd_reschedule_new_schedule_summary"); ?></small><strong id="gd-reschedule-summary-new"></strong></div>
                        </div>
                        <div class="form-group mb10">
                            <label for="gd-reschedule-reason"><?php echo app_lang("gd_reschedule_reason"); ?> <span class="text-danger">*</span></label>
                            <textarea name="reason" id="gd-reschedule-reason" class="form-control" rows="2" maxlength="255" required></textarea>
                        </div>
                        <div class="form-group mb0">
                            <label for="gd-reschedule-notes"><?php echo app_lang("gd_reschedule_notes"); ?></label>
                            <textarea name="notes" id="gd-reschedule-notes" class="form-control" rows="2" maxlength="5000"></textarea>
                        </div>
                        <div class="mt15">
                            <button type="submit" id="gd-reschedule-submit" class="btn btn-primary" disabled><i data-feather="check" class="icon-16"></i> <?php echo app_lang("gd_reschedule_confirm"); ?></button>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
</div>
<?php echo form_close(); ?>

<style>
#gd-reschedule-form.gd-reschedule-modal,
#gd-reschedule-form.gd-reschedule-modal .gd-reschedule-modal-body {
    background: #154579;
    color: #fff;
}

#gd-reschedule-form.gd-reschedule-modal .gd-reschedule-modal-body {
    padding: 28px 30px 24px;
}

#gd-reschedule-form.gd-reschedule-modal .container-fluid {
    padding: 0;
}

#gd-reschedule-form.gd-reschedule-modal label,
#gd-reschedule-form.gd-reschedule-modal .gd-reschedule-step-title {
    color: #fff;
    font-weight: 600;
}

#gd-reschedule-form.gd-reschedule-modal .text-muted {
    color: rgba(255, 255, 255, .82) !important;
}

#gd-reschedule-form.gd-reschedule-modal .form-group {
    margin-bottom: 16px;
}

#gd-reschedule-form.gd-reschedule-modal .form-control {
    min-height: 44px;
    border: 1px solid transparent !important;
    border-radius: 7px;
    background: #0c376b !important;
    color: #fff !important;
    box-shadow: none !important;
}

#gd-reschedule-form.gd-reschedule-modal .form-control::placeholder {
    color: #b9cee6;
    opacity: 1;
}

#gd-reschedule-form.gd-reschedule-modal .form-control:focus {
    border-color: #5e90c4 !important;
    background: #0e3e77 !important;
    color: #fff !important;
    box-shadow: 0 0 0 .15rem rgba(115, 171, 224, .18) !important;
}

#gd-reschedule-form.gd-reschedule-modal select.form-control {
    color: #fff !important;
}

#gd-reschedule-form.gd-reschedule-modal .modal-footer {
    padding: 20px 30px 24px;
    border-top: 1px solid rgba(255, 255, 255, .14);
    background: #154579;
    color: #fff;
}

#gd-reschedule-form.gd-reschedule-modal .modal-footer .btn-default {
    border-color: #2d5d8f;
    background: #2d5d8f;
    color: #fff;
}

#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-submit {
    border-color: #dfad2e;
    background: #dfad2e;
    color: #16283c;
    font-weight: 600;
}

#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-submit:hover,
#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-submit:focus {
    border-color: #edbd3d;
    background: #edbd3d;
    color: #16283c;
}

#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-revert {
    border-color: rgba(255, 255, 255, .45);
    color: #fff;
}

.gd-reschedule-step {
    margin-bottom: 22px;
    padding: 0 0 22px;
    border: 0;
    border-bottom: 1px solid rgba(255, 255, 255, .12);
    border-radius: 0;
    background: transparent;
    color: #fff;
    box-shadow: none;
}

.gd-reschedule-step-title {
    color: #fff;
    font-weight: 600;
}

.gd-reschedule-current {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid rgba(255, 255, 255, .14);
    border-radius: 7px;
    background: #103d72;
    color: #fff;
    box-shadow: none;
}

.gd-reschedule-current small,
.gd-reschedule-summary small {
    display: block;
    margin-bottom: 3px;
    color: #b9cee6;
}

.gd-reschedule-current strong,
.gd-reschedule-summary strong {
    display: block;
    color: #fff;
}

.gd-reschedule-available-courts {
    padding: 18px;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 8px;
    background: #103d72;
    color: #fff;
}

.gd-reschedule-available-courts .gd-reschedule-step-title {
    color: #fff;
}

.gd-reschedule-available-courts small {
    color: rgba(255, 255, 255, .82);
}

.gd-reschedule-availability-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
}

.gd-available-courts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 10px;
}

.gd-available-court {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    min-height: 54px;
    padding: 10px 12px;
    border: 1px solid #d7e0ea;
    border-radius: 7px;
    background: #f8fafc;
    color: #1c2d3f;
    text-align: left;
}

.gd-available-court:not(:disabled):hover,
.gd-available-court.is-selected {
    border-color: #25a55a;
    box-shadow: 0 0 0 2px rgba(37, 165, 90, .35);
}

.gd-available-court:disabled {
    cursor: not-allowed;
    opacity: .78;
    background: #e9edf2;
    color: #6c757d;
}

.gd-available-court small {
    display: block;
    margin-top: 2px;
    color: #6c757d !important;
}

#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-confirm {
    border: 1px solid rgba(255, 255, 255, .08);
    background: #103d72 !important;
    color: #fff !important;
    box-shadow: none;
}

#gd-reschedule-form.gd-reschedule-modal #gd-reschedule-confirm .card-body {
    border-radius: 8px;
    background: transparent;
    color: #fff;
}

.gd-reschedule-summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    padding: 12px;
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: 7px;
    background: #0c376b;
    color: #fff;
}

.gd-reschedule-summary > div {
    min-width: 0;
}

.gd-reschedule-summary strong {
    word-break: break-word;
}

#gd-reschedule-form.gd-reschedule-modal .alert-info,
#gd-reschedule-form.gd-reschedule-modal .alert-success,
#gd-reschedule-form.gd-reschedule-modal .alert-warning,
#gd-reschedule-form.gd-reschedule-modal .alert-danger {
    border: 0;
    color: #173b64;
}

#gd-reschedule-form.gd-reschedule-modal .alert-info {
    background: #d8edf8;
}

#gd-reschedule-form.gd-reschedule-modal .alert-success {
    background: #cfe9da;
}

#gd-reschedule-form.gd-reschedule-modal .alert-warning {
    background: #fff0c2;
}

#gd-reschedule-form.gd-reschedule-modal .alert-danger {
    background: #f8d7da;
}

@media (max-width: 575px) {
    #gd-reschedule-form.gd-reschedule-modal .gd-reschedule-modal-body {
        padding: 22px 16px 18px;
    }

    #gd-reschedule-form.gd-reschedule-modal .modal-footer {
        padding: 16px;
    }

    .gd-reschedule-current,
    .gd-reschedule-summary {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
$(function () {
    var form = $("#gd-reschedule-form"), occurrenceSelect = $("#gd-reschedule-occurrence"), startSelect = $("#gd-reschedule-new-start"), resourceRequest = null, saving = false;
    var initial = <?php echo json_encode($initial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var current = {bookingId: 0, date: "", start: "", end: "", resourceId: "", resource: "", duration: 0};
    var selectedResource = null;

    function escapeHtml(value) { return $("<div>").text(value == null ? "" : String(value)).html(); }
    function formatDate(value) { if (!/^\d{4}-\d{2}-\d{2}$/.test(value || "")) { return value || "-"; } var p = value.split("-"); return p[2] + "/" + p[1] + "/" + p[0]; }
    function formatDuration(minutes) { minutes = parseInt(minutes || 0, 10); if (!minutes) { return "-"; } var hours = Math.floor(minutes / 60), rest = minutes % 60; return hours ? hours + "h" + (rest ? String(rest).padStart(2, "0") : "") : rest + " min"; }
    function status(message, type) { $("#gd-reschedule-status").html(message ? '<div class="alert alert-' + (type || "info") + ' mb0">' + escapeHtml(message) + "</div>" : ""); }
    function fail(xhr) { var body = xhr && xhr.responseJSON ? xhr.responseJSON : {}; status(body.message || <?php echo json_encode(app_lang("gd_reschedule_availability_error")); ?>, "danger"); }
    function hmMinutes(value) { var match = /^(\d{2}):(\d{2})$/.exec(value || ""); return match ? parseInt(match[1], 10) * 60 + parseInt(match[2], 10) : -1; }
    function hm(value) { var hours = Math.floor(value / 60), minutes = value % 60; return String(hours).padStart(2, "0") + ":" + String(minutes).padStart(2, "0"); }
    function endFor(start, duration) { var startMinutes = hmMinutes(start), total = startMinutes + parseInt(duration || 0, 10); return startMinutes >= 0 && total > startMinutes && total <= 1440 ? hm(total) : ""; }
    function currentScheduleLabel() { return formatDate(current.date) + " · " + current.start + " às " + current.end + " · " + (current.resource || "-"); }
    function newScheduleLabel() { return formatDate(current.date) + " · " + startSelect.val() + " às " + $("#gd-reschedule-new-end").val() + " · " + ((selectedResource && selectedResource.label) || "-"); }
    function reasonLabel(code) { return {active_block: "Bloqueada", closed_exception: "Indisponível", booking_conflict: "Ocupada", outside_availability: "Fora do horário", resource_inactive: "Inativa", resource_not_bookable: "Não reservável"}[code] || "Indisponível"; }

    function clearSelection() {
        selectedResource = null;
        $("#gd-reschedule-resource").val("");
        $("#gd-reschedule-confirm").hide();
        $("#gd-reschedule-submit").prop("disabled", true);
    }

    function populateStartTimes() {
        var duration = parseInt(current.duration || 0, 10), options = [], seen = {}, currentStart = current.start || "";
        startSelect.empty().append($('<option value=""></option>').text("Selecione"));
        if (!duration) { $("#gd-reschedule-duration").val(""); $("#gd-reschedule-new-end").val(""); return; }
        for (var minutes = 0; minutes + duration <= 1440; minutes += 30) { seen[hm(minutes)] = true; }
        if (currentStart && !seen[currentStart] && endFor(currentStart, duration)) { seen[currentStart] = true; }
        Object.keys(seen).sort(function (a, b) { return hmMinutes(a) - hmMinutes(b); }).forEach(function (time) { options.push(time); });
        $.each(options, function (_, time) { startSelect.append($('<option></option>').val(time).text(time)); });
        if (currentStart && seen[currentStart]) { startSelect.val(currentStart); }
        $("#gd-reschedule-duration").val(formatDuration(duration));
        updateSchedule(false);
    }

    function renderResources(rows) {
        var html = "", availableCount = 0, alternativeCount = 0;
        $.each(rows || [], function (_, row) {
            var id = String((row && row.id) || ""), available = row && row.available === true, isCurrent = row && (row.is_current === true || row.is_current === 1), selectable = available && !isCurrent;
            var code = String((row && row.code) || ""), name = String((row && row.name) || ""), label = code + " — " + name;
            if (available) { availableCount++; }
            if (selectable) { alternativeCount++; }
            var badge = isCurrent ? <?php echo json_encode(app_lang("gd_reschedule_current_badge")); ?> : (available ? <?php echo json_encode(app_lang("gd_available")); ?> : <?php echo json_encode(app_lang("gd_reschedule_unavailable")); ?>);
            var badgeClass = isCurrent ? "secondary" : (available ? "success" : "secondary");
            var disabled = selectable ? "" : " disabled";
            var title = available ? (isCurrent ? <?php echo json_encode(app_lang("gd_reschedule_current_schedule")); ?> : "") : reasonLabel(String((row && row.reason_code) || ""));
            html += '<button type="button" class="gd-available-court gd-reschedule-resource" data-resource-id="' + escapeHtml(id) + '" data-label="' + escapeHtml(label) + '" title="' + escapeHtml(title) + '"' + disabled + '>' +
                '<span><strong>' + escapeHtml(code) + '</strong><small>' + escapeHtml(name) + '</small></span>' +
                '<span class="badge bg-' + badgeClass + '">' + escapeHtml(badge) + '</span></button>';
        });
        $("#gd-reschedule-resources").html(html);
        if (!rows || !rows.length) { status(<?php echo json_encode(app_lang("gd_reschedule_no_courts")); ?>, "warning"); }
        else if (!alternativeCount) { status(<?php echo json_encode(app_lang("gd_reschedule_no_courts")); ?>, "warning"); }
        else { status(<?php echo json_encode(app_lang("gd_reschedule_select_court")); ?>, "success"); }
        if (typeof feather !== "undefined") { feather.replace(); }
    }

    function loadResources() {
        var bookingId = parseInt($("#gd-reschedule-booking-id").val() || "0", 10), date = $("#gd-reschedule-date").val(), start = startSelect.val();
        if (!bookingId || !date || !start || !$("#gd-reschedule-new-end").val()) { $("#gd-reschedule-resources").empty(); status(""); return; }
        if (resourceRequest) { resourceRequest.abort(); }
        clearSelection();
        status(<?php echo json_encode(app_lang("gd_reschedule_loading")); ?>);
        $("#gd-reschedule-resources").empty();
        resourceRequest = $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/reschedule-resource-options"); ?>', type: "POST", dataType: "json",
            data: form.serialize()
        }).done(function (response) {
            if (!response || !response.success) { status((response && response.message) || <?php echo json_encode(app_lang("gd_reschedule_availability_error")); ?>, "danger"); return; }
            var data = response.data || {};
            if (data.schedule && data.schedule.end_time) { $("#gd-reschedule-new-end").val(data.schedule.end_time); }
            renderResources(data.resources || []);
        }).fail(function (xhr, requestStatus) { if (requestStatus !== "abort") { fail(xhr); } }).always(function () { resourceRequest = null; });
    }

    function updateSchedule(shouldLoad) {
        var start = startSelect.val(), end = endFor(start, current.duration);
        $("#gd-reschedule-start").val(start || ""); $("#gd-reschedule-until").val(end || ""); $("#gd-reschedule-new-end").val(end || "");
        $("#gd-reschedule-period").text(start && end ? formatDate(current.date) + " · " + start + " às " + end : "");
        clearSelection();
        if (shouldLoad) { loadResources(); }
    }

    function setOccurrence(data) {
        current = {
            bookingId: String(data.bookingId || data.booking_id || ""), date: String(data.date || ""), start: String(data.start || ""), end: String(data.end || ""),
            resourceId: String(data.resourceId || data.resource_id || ""), resource: String(data.resource || "-"), duration: parseInt(data.duration || 0, 10)
        };
        if (!current.duration && current.start && current.end) { current.duration = hmMinutes(current.end) - hmMinutes(current.start); }
        $("#gd-reschedule-booking-id").val(current.bookingId); $("#gd-reschedule-date").val(current.date); $("#gd-reschedule-from").val(current.start); $("#gd-reschedule-until").val(current.end); $("#gd-reschedule-current-resource-id").val(current.resourceId);
        $("#gd-reschedule-current-period").text(formatDate(current.date) + " · " + current.start + " às " + current.end); $("#gd-reschedule-current-resource").text(current.resource || "-");
        $("#gd-reschedule-editor").show(); $("#gd-reschedule-selection-help").hide();
        populateStartTimes();
        <?php if (!$active) { ?>loadResources();<?php } ?>
    }

    occurrenceSelect.on("change", function () {
        var option = occurrenceSelect.find("option:selected");
        if (!option.val()) { $("#gd-reschedule-editor").hide(); $("#gd-reschedule-selection-help").show(); return; }
        setOccurrence({bookingId: option.val(), date: option.attr("data-date"), start: option.attr("data-start"), end: option.attr("data-end"), resourceId: option.attr("data-resource-id"), resource: option.attr("data-resource")});
    });
    startSelect.on("change", function () { updateSchedule(true); });
    $(document).on("click", "#gd-reschedule-resources .gd-reschedule-resource:not(:disabled)", function () {
        var card = $(this);
        selectedResource = {id: String(card.attr("data-resource-id") || ""), label: String(card.attr("data-label") || "-")};
        $("#gd-reschedule-resource").val(selectedResource.id); $("#gd-reschedule-summary-original").text(currentScheduleLabel()); $("#gd-reschedule-summary-new").text(newScheduleLabel());
        $("#gd-reschedule-resources .gd-reschedule-resource").removeClass("is-selected"); card.addClass("is-selected"); $("#gd-reschedule-confirm").show(); $("#gd-reschedule-submit").prop("disabled", false);
    });
    form.on("submit", function (event) {
        event.preventDefault();
        if (saving) { return; }
        if (!$("#gd-reschedule-start").val() || !$("#gd-reschedule-resource").val() || !$("#gd-reschedule-reason").val().trim()) { status(<?php echo json_encode(app_lang("gd_reschedule_select_court")); ?>, "warning"); return; }
        if (!window.confirm(<?php echo json_encode(app_lang("gd_reschedule_confirm_question")); ?>)) { return; }
        saving = true; $("#gd-reschedule-submit").prop("disabled", true);
        $.ajax({url: form.attr("action"), type: "POST", dataType: "json", data: form.serialize()})
            .done(function (response) { if (response.success) { location.reload(); } else { status(response.message || <?php echo json_encode(app_lang("gd_reschedule_availability_error")); ?>, "danger"); saving = false; $("#gd-reschedule-submit").prop("disabled", false); } })
            .fail(function (xhr) { fail(xhr); saving = false; $("#gd-reschedule-submit").prop("disabled", false); });
    });
    $("#gd-reschedule-revert").on("click", function () {
        if (!window.confirm(<?php echo json_encode(app_lang("gd_reschedule_revert_confirm")); ?>)) { return; }
        var button = $(this).prop("disabled", true);
        $.ajax({url: '<?php echo_uri("grupo_donato/court-rentals/reschedule/revert"); ?>', type: "POST", dataType: "json", data: form.serialize()})
            .done(function (response) { if (response.success) { location.reload(); } else { alert(response.message || <?php echo json_encode(app_lang("error_occurred")); ?>); button.prop("disabled", false); } })
            .fail(function (xhr) { fail(xhr); button.prop("disabled", false); });
    });

    if (initial.booking_id) { setOccurrence({bookingId: initial.booking_id, date: initial.date, start: initial.start, end: initial.end, resourceId: initial.resource_id, resource: initial.resource, duration: initial.duration}); }
    else if (occurrenceSelect.val()) { occurrenceSelect.trigger("change"); }
    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
