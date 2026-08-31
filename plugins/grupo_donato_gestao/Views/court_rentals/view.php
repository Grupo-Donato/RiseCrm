<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$money = static fn($value) => $value === null ? "-" : $e(to_currency($value));
$status = (string) $rental->status;
$status_classes = [
    "draft" => "bg-secondary",
    "active" => "bg-success",
    "suspended" => "bg-warning",
    "cancelled" => "bg-danger",
    "completed" => "bg-info",
    "archived" => "bg-secondary",
];
$status_class = $status_classes[$status] ?? "bg-secondary";
$weekdays = [];
foreach (($rental->schedule["weekdays"] ?? []) as $day) {
    $weekdays[] = app_lang("gd_weekday_short_" . (int) $day);
}
$schedule_text = trim((string) ($rental->schedule_display ?? ""));
if ($schedule_text === "") {
    $schedule_text = trim(implode(", ", $weekdays) . (!empty($rental->schedule["local_time"]) ? " · " . $rental->schedule["local_time"] : ""), " ·");
}
$primary_amount = $rental->contracted_total ?? $rental->negotiated_amount ?? $rental->list_amount;
$extra_time_minutes = (int) ($rental->extra_time_minutes ?? 0);
$extra_time_amount = (string) ($rental->extra_time_amount ?? "0.00");
$rental_additions = [];
$additionLabel = static function (string $label, $amount): string {
    $amount = (string) ($amount ?? "0.00");
    return $label . ((float) $amount > 0 ? " · " . to_currency($amount) : "");
};
if (!empty($rental->has_vest)) { $rental_additions[] = $additionLabel(app_lang("gd_rental_addition_vest"), $rental->vest_amount ?? "0.00"); }
if (!empty($rental->has_ball)) { $rental_additions[] = $additionLabel(app_lang("gd_rental_addition_ball"), $rental->ball_amount ?? "0.00"); }
$edit_modal = ($rental->rental_type ?? "single") === "recurring" ? "monthly-modal" : "single-modal";
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<div id="page-content" class="page-wrapper clearfix gd-rentals-shell">
    <div class="page-title clearfix gd-page-header">
        <div>
            <h4>
                <?php echo $e($rental->rental_number . " — " . $rental->title); ?>
                <span class="badge <?php echo $status_class; ?>"><?php echo app_lang("gd_court_rental_status_" . $status); ?></span>
            </h4>
            <div class="text-muted"><?php echo app_lang("gd_court_rental_type_" . $rental->rental_type); ?></div>
        </div>
        <div class="title-button-group gd-toolbar">
            <?php if (!empty($can_manage) && !in_array($status, ["cancelled", "completed", "archived"], true)) { echo modal_anchor(get_uri("grupo_donato/court-rentals/" . $edit_modal), '<i data-feather="edit" class="icon-16"></i> ' . app_lang("edit"), ["class" => "btn btn-primary", "data-post-id" => (int) $rental->id, "title" => app_lang("edit")]); } ?>
            <?php if (!empty($can_reschedule)) { echo modal_anchor(get_uri("grupo_donato/court-rentals/reschedule-modal"), '<i data-feather="shuffle" class="icon-16"></i> ' . app_lang("gd_reschedule_rental"), ["class" => "btn btn-warning", "data-modal-lg" => 1, "data-post-rental_id" => (int) $rental->id, "title" => app_lang("gd_reschedule_rental")]); } ?>
            <?php if (!empty($can_calendar)) { echo anchor(get_uri("grupo_donato/calendar"), '<i data-feather="calendar" class="icon-16"></i> ' . app_lang("gd_open_agenda"), ["class" => "btn btn-default"]); } ?>
            <?php echo anchor(get_uri("grupo_donato/court-rentals"), '<i data-feather="arrow-left" class="icon-16"></i> ' . app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb15">
                <div class="card-body">
                    <div class="gd-detail-grid">
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_customer"); ?></small>
                            <strong><?php echo $e($rental->customer_name ?? "-"); ?></strong>
                            <?php if (!empty($rental->contact_name)) { ?><div class="text-muted"><?php echo $e($rental->contact_name); ?></div><?php } ?>
                        </div>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_courts"); ?></small>
                            <strong><?php echo $e($rental->schedule["resource_names"] ?? "-"); ?></strong>
                        </div>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_rental_additions"); ?></small>
                            <?php if ($rental_additions) { ?>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($rental_additions as $addition) { ?><span class="badge bg-primary"><?php echo $e($addition); ?></span><?php } ?>
                                </div>
                            <?php } else { ?>
                                <strong class="text-muted"><?php echo app_lang("gd_rental_no_additions"); ?></strong>
                            <?php } ?>
                        </div>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_day_and_time"); ?></small>
                            <strong><?php echo $e($schedule_text !== "" ? $schedule_text : "-"); ?></strong>
                        </div>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_contracted_amount"); ?></small>
                            <strong><?php echo $primary_amount !== null ? $money($primary_amount) : "-"; ?></strong>
                        </div>
                        <?php if ($extra_time_minutes > 0 || $extra_time_amount !== "0.00") { ?>
                            <div class="gd-detail-item">
                                <small class="text-muted"><?php echo app_lang("gd_extra_time_title"); ?></small>
                                <strong>+<?php echo $extra_time_minutes; ?> min · <?php echo $money($extra_time_amount); ?></strong>
                            </div>
                        <?php } ?>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_preferred_due_day"); ?></small>
                            <strong><?php echo $rental->preferred_due_day ? app_lang("gd_day_prefix") . " " . (int) $rental->preferred_due_day : "-"; ?></strong>
                        </div>
                        <div class="gd-detail-item">
                            <small class="text-muted"><?php echo app_lang("gd_validity"); ?></small>
                            <strong><?php echo $e(($rental->effective_from ? format_to_date($rental->effective_from, false) : "…") . " → " . ($rental->effective_until ? format_to_date($rental->effective_until, false) : "…")); ?></strong>
                        </div>
                    </div>
                    <?php if ($status === "cancelled" && !empty($rental->cancellation_reason)) { ?>
                        <div class="alert alert-danger mt15 mb0"><strong><?php echo app_lang("gd_cancellation_reason"); ?>:</strong> <?php echo $e($rental->cancellation_reason); ?></div>
                    <?php } ?>
                    <?php if (!empty($rental->commercial_notes)) { ?>
                        <hr>
                        <small class="text-muted d-block mb5"><?php echo app_lang("gd_commercial_notes"); ?></small>
                        <div><?php echo nl2br($e($rental->commercial_notes)); ?></div>
                    <?php } ?>
                </div>
            </div>

            <?php if (!empty($financial)) { include dirname(__DIR__) . "/finance/context_summary.php"; } ?>

            <?php if (!empty($can_manage) && in_array(($rental->rental_type ?? ""), ["single", "recurring"], true) && !in_array($status, ["cancelled", "completed", "archived"], true)) { ?>
                <div class="card mb15">
                    <div class="page-title"><h4><i data-feather="clock" class="icon-16"></i> <?php echo app_lang("gd_extra_time_title"); ?></h4></div>
                    <div class="card-body">
                        <p class="text-muted mb15"><?php echo app_lang("gd_extra_time_help"); ?></p>
<form id="gd-cr-extra-time">
    <?php echo csrf_field(); ?>
                            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
                            <input type="hidden" name="lock_version" value="<?php echo (int) $rental->lock_version; ?>">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label><?php echo app_lang("gd_extra_time_minutes"); ?></label>
                                        <input type="number" name="extra_time_minutes" class="form-control" min="0" max="1440" step="30" value="<?php echo $extra_time_minutes; ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label><?php echo app_lang("gd_extra_time_amount"); ?></label>
                                        <input type="text" name="extra_time_amount" class="form-control" inputmode="decimal" placeholder="0,00" value="<?php echo $extra_time_minutes > 0 || $extra_time_amount !== "0.00" ? $e($extra_time_amount) : ""; ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label><?php echo app_lang("gd_extra_time_notes"); ?></label>
                                        <input type="text" name="extra_time_notes" class="form-control" maxlength="2000" value="<?php echo $e($rental->extra_time_notes ?? ""); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn-warning btn-block"><?php echo app_lang("gd_extra_time_save"); ?></button></div>
                                </div>
                            </div>
                        </form>
                        <?php if ($extra_time_minutes > 0 || $extra_time_amount !== "0.00" || !empty($rental->extra_time_notes)) { ?>
                            <div class="alert alert-info mb0"><strong><?php echo app_lang("gd_extra_time_current"); ?>:</strong> <?php echo $extra_time_minutes; ?> min · <?php echo $money($extra_time_amount); ?><?php if (!empty($rental->extra_time_notes)) { ?> — <?php echo $e($rental->extra_time_notes); ?><?php } ?></div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($can_generate_receivable) && ($rental->rental_type ?? "") === "single" && $primary_amount !== null && empty($financial["receivables"])) { ?>
                <div class="card mb15">
                    <div class="page-title"><h4><i data-feather="file-text" class="icon-16"></i> <?php echo app_lang("gd_generate_receivable_for_rental"); ?></h4></div>
                    <div class="card-body">
                        <?php echo form_open(get_uri("grupo_donato/finance/generate-rental"), ["id" => "gd-cr-generate-receivable", "class" => "general-form"]); ?>
                            <input type="hidden" name="rental_id" value="<?php echo (int) $rental->id; ?>">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group"><label><?php echo app_lang("gd_finance_amount"); ?></label><input name="amount" class="form-control" value="<?php echo $e($primary_amount); ?>"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group"><label><?php echo app_lang("gd_finance_due"); ?></label><input type="date" name="due_date" class="form-control" value="<?php echo date("Y-m-d"); ?>"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block" type="submit"><?php echo app_lang("gd_finance_new_receivable"); ?></button></div>
                                </div>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb15">
                <div class="page-title clearfix">
                    <h4><i data-feather="link" class="icon-16"></i> <?php echo app_lang("gd_schedule_links"); ?></h4>
                    <?php if ($can_manage && !in_array($status, ["cancelled", "completed", "archived"], true)) { ?>
                        <div class="title-button-group">
                            <?php echo modal_anchor(get_uri("grupo_donato/court-rentals/link-modal"), '<i data-feather="link-2" class="icon-16"></i> ' . app_lang("gd_link_existing"), ["class" => "btn btn-default", "data-post-id" => $rental->id]); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb0">
                        <thead><tr><th><?php echo app_lang("gd_type"); ?></th><th><?php echo app_lang("gd_reference"); ?></th><th><?php echo app_lang("gd_status"); ?></th></tr></thead>
                        <tbody>
                            <?php if (empty($rental->links)) { ?>
                                <tr><td colspan="3" class="text-center text-muted p20"><?php echo app_lang("gd_no_schedule_links"); ?></td></tr>
                            <?php } ?>
                            <?php foreach ($rental->links as $link) { ?>
                                <tr>
                                    <td><?php echo app_lang("gd_court_rental_link_kind_" . $link->link_kind); ?></td>
                                    <?php if (!empty($link->booking)) { ?>
                                        <td><?php echo anchor(get_uri("grupo_donato/bookings/view/" . $link->booking->id), $e($link->booking->booking_number . " — " . $link->booking->title)); ?></td>
                                        <td><?php echo app_lang("gd_booking_status_" . $link->booking->status); ?></td>
                                    <?php } elseif (!empty($link->series)) { ?>
                                        <td><?php echo anchor(get_uri("grupo_donato/booking-series/view/" . $link->series->id), $e($link->series->series_number . " — " . $link->series->title)); ?></td>
                                        <td><?php echo app_lang("gd_booking_series_status_" . $link->series->status); ?></td>
                                    <?php } else { ?>
                                        <td>-</td><td>-</td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($can_reschedule)) { ?>
                <div class="card mb15">
                    <div class="page-title"><h4><i data-feather="shuffle" class="icon-16"></i> <?php echo app_lang("gd_reschedule_history"); ?></h4></div>
                    <div class="card-body">
                        <p class="text-muted"><?php echo app_lang("gd_reschedule_history_help"); ?></p>
                        <?php if (empty($reschedule_history)) { ?><div class="text-muted"><?php echo app_lang("gd_reschedule_history_empty"); ?></div><?php } else { ?>
                            <div class="table-responsive"><table class="table table-sm table-hover mb0">
                                <thead><tr><th><?php echo app_lang("gd_date"); ?></th><th><?php echo app_lang("gd_reschedule_original"); ?></th><th><?php echo app_lang("gd_reschedule_new"); ?></th><th><?php echo app_lang("gd_status"); ?></th><th></th></tr></thead>
                                <tbody>
                                <?php foreach ($reschedule_history as $item) { ?>
                                    <tr>
                                        <td><?php echo $e($item["occurrence_date"]); ?><br><small class="text-muted">#<?php echo (int) $item["revision"]; ?></small></td>
                                        <td><?php echo $e($item["original_resource"]); ?><br><?php echo $e($item["original_start"] . " – " . $item["original_end"]); ?></td>
                                        <td><?php echo $e($item["new_resource"]); ?><br><?php echo $e($item["new_start"] . " – " . $item["new_end"]); ?></td>
                                        <td><span class="badge <?php echo $item["status"] === "active" ? "bg-warning" : "bg-secondary"; ?>"><?php echo app_lang("gd_reschedule_status_" . $item["status"]); ?></span><br><small class="text-muted"><?php echo $e($item["reason"]); ?></small><br><small class="text-muted"><?php echo $e($item["created_by_name"] ?: ("#" . $item["created_by"])); ?></small><?php if (!empty($item["reverted_by"])) { ?><br><small class="text-muted"><?php echo $e($item["reverted_by_name"] ?: ("#" . $item["reverted_by"])); ?></small><?php } ?></td>
                                        <td class="text-end"><?php if ($item["status"] === "active" && (int) $item["booking_id"] > 0) { echo modal_anchor(get_uri("grupo_donato/court-rentals/reschedule-modal"), '<i data-feather="edit-2" class="icon-14"></i>', ["class" => "btn btn-default btn-sm", "data-modal-lg" => 1, "data-post-booking_id" => (int) $item["booking_id"], "title" => app_lang("gd_reschedule_point")]); } ?></td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table></div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb15">
                <div class="page-title"><h4><i data-feather="dollar-sign" class="icon-16"></i> <?php echo app_lang("gd_commercial_terms"); ?></h4></div>
                <div class="card-body">
                    <div class="gd-detail-grid">
                        <div class="gd-detail-item"><small class="text-muted"><?php echo app_lang("gd_list_amount"); ?></small><strong><?php echo $money($rental->list_amount); ?></strong></div>
                        <div class="gd-detail-item"><small class="text-muted"><?php echo app_lang("gd_negotiated_amount"); ?></small><strong><?php echo $money($rental->negotiated_amount); ?></strong></div>
                        <div class="gd-detail-item"><small class="text-muted"><?php echo app_lang("gd_discount_amount"); ?></small><strong><?php echo $money($rental->discount_amount); ?></strong></div>
                        <div class="gd-detail-item"><small class="text-muted"><?php echo app_lang("gd_price_difference"); ?></small><strong><?php echo $rental->price_difference !== null ? $money($rental->price_difference) : "-"; ?></strong></div>
                    </div>
                    <?php if (!empty($rental->discount_reason)) { ?><hr><strong><?php echo app_lang("gd_discount_reason"); ?>:</strong> <?php echo $e($rental->discount_reason); ?><?php } ?>

                    <?php if (($can_manage || $can_override) && !in_array($status, ["cancelled", "completed", "archived"], true)) { ?>
                        <hr>
                        <button class="btn btn-default btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gd-cr-reprice-box">
                            <i data-feather="edit-3" class="icon-14"></i> <?php echo app_lang("gd_adjust_commercial_value"); ?>
                        </button>
                        <div class="collapse mt15" id="gd-cr-reprice-box">
                            <form id="gd-cr-reprice">
                                <input type="hidden" name="id" value="<?php echo (int) $rental->id; ?>">
                                <input type="hidden" name="lock_version" value="<?php echo (int) $rental->lock_version; ?>">
                                <input type="hidden" name="rental_type" value="<?php echo $e($rental->rental_type); ?>">
                                <input type="hidden" name="title" value="<?php echo $e($rental->title); ?>">
                                <input type="hidden" name="customer_account_id" value="<?php echo (int) $rental->customer_account_id; ?>">
                                <div class="row">
                                    <div class="col-md-3"><div class="form-group"><label><?php echo app_lang("gd_negotiated_amount"); ?></label><input name="negotiated_amount" class="form-control" value="<?php echo $e($rental->negotiated_amount); ?>"></div></div>
                                    <div class="col-md-3"><div class="form-group"><label><?php echo app_lang("gd_discount_amount"); ?></label><input name="discount_amount" class="form-control" value="<?php echo $e($rental->discount_amount); ?>"></div></div>
                                    <div class="col-md-4"><div class="form-group"><label><?php echo app_lang("gd_discount_reason"); ?></label><input name="discount_reason" class="form-control"></div></div>
                                    <div class="col-md-2"><div class="form-group"><label>&nbsp;</label><button type="submit" class="btn btn-warning btn-block"><?php echo app_lang("gd_reprice"); ?></button></div></div>
                                </div>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php if ($can_status) { ?>
                <div class="card mb15">
                    <div class="page-title"><h4><i data-feather="zap" class="icon-16"></i> <?php echo app_lang("gd_actions"); ?></h4></div>
                    <div class="card-body">
                        <div id="gd-cr-actions" class="gd-actions-stack">
                            <?php if ($status === "draft") { ?>
                                <button data-action="activate" class="btn btn-success"><?php echo app_lang("gd_activate"); ?></button>
                                <button data-action="cancel" data-reason="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button>
                            <?php } ?>
                            <?php if ($status === "active") { ?>
                                <button data-action="suspend" class="btn btn-warning"><?php echo app_lang("gd_suspend"); ?></button>
                                <button data-action="complete" class="btn btn-default"><?php echo app_lang("gd_complete"); ?></button>
                                <button data-action="cancel" data-reason="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button>
                            <?php } ?>
                            <?php if ($status === "suspended") { ?>
                                <button data-action="resume" class="btn btn-success"><?php echo app_lang("gd_resume"); ?></button>
                                <button data-action="cancel" data-reason="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="card mb15">
                <div class="page-title"><h4><i data-feather="activity" class="icon-16"></i> <?php echo app_lang("gd_events"); ?></h4></div>
                <div class="card-body">
                    <?php if (empty($rental->events)) { ?><div class="text-muted"><?php echo app_lang("gd_no_events"); ?></div><?php } ?>
                    <?php foreach ($rental->events as $event) { ?>
                        <div class="mb15">
                            <strong><?php echo app_lang("gd_court_rental_event_" . $event->event_type); ?></strong>
                            <div class="text-muted"><small><?php echo $e($event->created_at . " UTC"); ?></small></div>
                            <?php if (!empty($event->reason)) { ?><div><?php echo $e($event->reason); ?></div><?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card mb15">
                <div class="page-title"><h4><i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_technical_info"); ?></h4></div>
                <div class="card-body">
                    <div><small class="text-muted"><?php echo app_lang("gd_lock_version"); ?></small><br><?php echo (int) $rental->lock_version; ?></div>
                    <div class="mt10"><small class="text-muted"><?php echo app_lang("gd_unit_timezone"); ?></small><br><?php echo $e($timezone); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    function postAction(url, data) {
        $.ajax({url: url, type: "POST", data: data, dataType: "json"}).done(function(response){
            if (response.success) { location.reload(); }
            else { appAlert.error(response.message); }
        }).fail(function(xhr){
            var body = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            appAlert.error((body && body.message) || '<?php echo addslashes(app_lang("error_occurred")); ?>');
            // Conflito de versão (409): recarrega para trazer o lock_version atual.
            if (xhr && xhr.status === 409) { setTimeout(function(){ location.reload(); }, 1200); }
        });
    }


    $("#gd-cr-generate-receivable").appForm({
        onSuccess: function(){ location.reload(); }
    });

    $("#gd-cr-actions button").on("click", function(){
        var button = $(this), data = {lock_version: <?php echo (int) $rental->lock_version; ?>};
        if (button.data("action") === "cancel" && !window.confirm('<?php echo addslashes(app_lang("gd_cancel_rental_confirm")); ?>')) { return; }
        if (button.data("reason")) {
            var reason = window.prompt('<?php echo addslashes(app_lang("gd_reason")); ?>', "");
            if (!reason) { return; }
            data.reason = reason;
        }
        postAction('<?php echo_uri("grupo_donato/court-rentals/" . (int) $rental->id . "/"); ?>' + button.data("action"), data);
    });

    $("#gd-cr-reprice").on("submit", function(event){
        event.preventDefault();
        postAction('<?php echo_uri("grupo_donato/court-rentals/reprice"); ?>', $(this).serialize());
    });

    $("#gd-cr-extra-time").on("submit", function(event){
        event.preventDefault();
        postAction('<?php echo_uri("grupo_donato/court-rentals/extra-time"); ?>', $(this).serialize());
    });

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
