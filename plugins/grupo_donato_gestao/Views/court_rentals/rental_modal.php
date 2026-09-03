<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$initial_mode = in_array(($initial_mode ?? "single"), ["single", "recurring"], true) ? $initial_mode : "single";
$edit_data = is_array($edit_data ?? null) ? $edit_data : null;
$is_edit = !empty($edit_data["id"]);
$edit_type = (string) ($edit_data["rental_type"] ?? $initial_mode);
$edit_endpoint = $edit_type === "single" ? "grupo_donato/court-rentals/update-single" : "grupo_donato/court-rentals/update-monthly";
$edit_duration = (int) ($edit_data["duration_minutes"] ?? 0);
$status_options = is_array($status_options ?? null) ? $status_options : [];
$can_status = !empty($can_status);
$finance_accounts = is_array($finance_accounts ?? null) ? $finance_accounts : [];
$payment_methods = is_array($payment_methods ?? null) ? $payment_methods : [];
$messages = [
    "resource_required" => app_lang("gd_select_at_least_one_court"),
    "date_required" => app_lang("gd_rental_date_required"),
    "time_required" => app_lang("gd_rental_time_required"),
    "duration_required" => app_lang("gd_invalid_rental_duration"),
    "due_day_required" => app_lang("gd_due_day_required"),
    "amount_required" => app_lang("gd_rental_amount_required"),
    "deposit_invalid" => app_lang("gd_deposit_invalid"),
    "deposit_method_required" => app_lang("gd_deposit_payment_method_required"),
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
    "available_label" => app_lang("gd_available"),
    "unavailable_label" => app_lang("gd_unavailable"),
    "available_courts_help" => app_lang("gd_available_courts_help"),
    "no_courts" => app_lang("gd_no_courts_available_for_time"),
    "combo_amount_required" => app_lang("gd_combo_amount_required"),
    "barbecue_required" => app_lang("gd_select_at_least_one_barbecue"),
    "combo_discount_invalid" => app_lang("gd_combo_discount_invalid"),
    "combo_discount_reason_required" => app_lang("gd_combo_discount_reason_required"),
    "addition_amount_required" => app_lang("gd_rental_addition_amount_required"),
    "available_barbecues_help" => app_lang("gd_available_barbecues_help"),
    "no_barbecues" => app_lang("gd_no_barbecues_available_for_time"),
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
<?php echo form_open(get_uri($is_edit ? $edit_endpoint : "grupo_donato/court-rentals/save-rental"), ["id" => "gd-rental-form", "class" => "general-form", "role" => "form"]); ?>
<?php if ($is_edit) { ?>
<input type="hidden" name="id" value="<?php echo (int) $edit_data["id"]; ?>">
<input type="hidden" name="lock_version" value="<?php echo (int) $edit_data["lock_version"]; ?>">
<?php if ($edit_type === "single") { ?>
<input type="hidden" name="booking_lock_version" value="<?php echo (int) ($edit_data["booking_lock_version"] ?? 0); ?>">
<?php } else { ?>
<input type="hidden" name="series_lock_version" value="<?php echo (int) ($edit_data["series_lock_version"] ?? 0); ?>">
<input type="hidden" name="series_id" value="<?php echo (int) ($edit_data["series_id"] ?? 0); ?>">
<?php } ?>
<?php } ?>
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
<input type="hidden" name="discount_amount" id="gd-rental-discount-amount">
<input type="hidden" name="discount_reason" id="gd-rental-discount-reason">
<input type="hidden" name="financial_status" id="gd-rental-financial-status" value="chargeable">
<input type="hidden" name="currency" value="BRL">
<input type="hidden" name="metadata" id="gd-rental-metadata">

<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="mb20"<?php echo $is_edit ? ' style="display:none"' : ""; ?>>
            <h5 class="mb15"><?php echo app_lang("gd_rental_type_choice"); ?></h5>
            <div class="form-group mb0">
                <select name="rental_mode_choice" id="gd-rental-mode-choice" class="form-control"<?php echo $is_edit ? " disabled" : ""; ?>>
                    <option value="single"<?php echo $initial_mode === "single" ? " selected" : ""; ?>><?php echo app_lang("gd_rental_mode_single"); ?></option>
                    <option value="recurring"<?php echo $initial_mode === "recurring" ? " selected" : ""; ?>><?php echo app_lang("gd_rental_mode_recurring"); ?></option>
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
                        <input type="hidden" name="customer_account_id" id="gd-rental-customer-id" value="<?php echo $is_edit ? (int) $edit_data["customer_account_id"] : ""; ?>">
                        <input type="text" name="customer_name" id="gd-rental-customer" class="form-control" maxlength="190" autocomplete="off" required data-rule-required="true" value="<?php echo $e($edit_data["customer_name"] ?? ""); ?>" placeholder="<?php echo $e(app_lang("gd_customer_name_placeholder")); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gd-rental-contact"><?php echo app_lang("gd_contact"); ?></label>
                        <input type="hidden" name="contact_person_id" id="gd-rental-contact-id" value="<?php echo $is_edit ? (int) $edit_data["contact_person_id"] : ""; ?>">
                        <input type="text" name="contact_name" id="gd-rental-contact" class="form-control" maxlength="190" autocomplete="off" value="<?php echo $e($edit_data["contact_name"] ?? ""); ?>" placeholder="<?php echo $e(app_lang("gd_contact_name_placeholder")); ?>">
                    </div>
                    <div class="form-group mb0">
                        <label for="gd-rental-phone"><?php echo app_lang("phone"); ?></label>
                        <input id="gd-rental-phone" name="contact_phone" class="form-control" inputmode="tel" maxlength="15" autocomplete="off" value="<?php echo $e($edit_data["contact_phone"] ?? ""); ?>" placeholder="(00) 00000-0000">
                        <small class="text-muted"><?php echo app_lang("gd_contact_optional_help"); ?></small>
                    </div>
                    <?php if ($is_edit && $edit_type === "recurring" && $status_options) { ?>
                    <div class="form-group mt15 mb0 gd-rental-status-field">
                        <label for="gd-rental-status"><?php echo app_lang("gd_status"); ?></label>
                        <select name="status" id="gd-rental-status" class="form-control"<?php echo $can_status ? "" : " disabled"; ?>>
                            <?php foreach ($status_options as $status_option) { ?>
                                <option value="<?php echo $e($status_option["id"]); ?>"<?php echo (string) ($edit_data["status"] ?? "") === (string) $status_option["id"] ? " selected" : ""; ?>><?php echo $e($status_option["text"]); ?></option>
                            <?php } ?>
                        </select>
                        <?php if (!$can_status) { ?><small class="text-muted"><?php echo app_lang("gd_perm_court_rentals_status_manage"); ?></small><?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_schedule"); ?></h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gd-rental-date"><span class="gd-date-label"><?php echo app_lang("gd_rental_date"); ?></span> <span class="text-danger">*</span></label>
                                <input type="date" id="gd-rental-date" class="form-control" value="<?php echo $e($edit_data["starts_on"] ?? ""); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gd-rental-start-time"><?php echo app_lang("gd_local_start_time"); ?> <span class="text-danger">*</span></label>
                                <select id="gd-rental-start-time" class="form-control" required>
                                    <option value=""></option>
                                    <?php foreach ($time_options as $time_option) { ?>
                                        <option value="<?php echo $e($time_option["value"]); ?>"<?php echo ($edit_data["local_start_time"] ?? "") === $time_option["value"] ? " selected" : ""; ?>><?php echo $e($time_option["label"]); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="gd-regular-duration">
                        <div class="form-group">
                            <label for="gd-rental-duration"><?php echo app_lang("gd_rental_duration"); ?> <span class="text-danger">*</span></label>
                            <select name="duration_minutes" id="gd-rental-duration" class="form-control">
                                <?php if ($is_edit && $edit_duration > 0 && !in_array($edit_duration, [90, 120], true)) { ?>
                                    <option value="<?php echo $edit_duration; ?>" selected><?php echo $edit_duration . " min"; ?></option>
                                <?php } ?>
                                <option value="90"<?php echo $edit_duration === 90 ? " selected" : ""; ?>>1h30</option>
                                <option value="120"<?php echo $edit_duration === 120 ? " selected" : ""; ?>>2h</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="gd-rental-amount" id="gd-rental-amount-label"><span id="gd-rental-amount-label-text"><?php echo app_lang("gd_rental_value"); ?></span> <span class="text-danger">*</span></label>
                        <input type="text" id="gd-rental-amount" name="court_amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo $e($edit_data["court_amount"] ?? ($edit_data["amount"] ?? "")); ?>" placeholder="0,00" required>
                        <small class="text-muted"><?php echo app_lang("gd_rental_amount_help"); ?></small>
                        <div class="form-check mt10">
                            <input type="checkbox" class="form-check-input" id="gd-rental-exempt">
                            <label class="form-check-label" for="gd-rental-exempt"><?php echo app_lang("gd_finance_mark_exempt"); ?></label>
                        </div>
                        <small class="text-muted"><?php echo app_lang("gd_finance_exempt_help"); ?></small>
                    </div>

                    <div class="gd-recurring-fields" style="display:none">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="gd-rental-due-day"><?php echo app_lang("gd_preferred_due_day"); ?> <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="31" name="preferred_due_day" id="gd-rental-due-day" class="form-control" value="<?php echo $e($edit_data["preferred_due_day"] ?? ""); ?>" placeholder="1 a 31">
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

                    <div class="gd-single-payment-fields" style="display:none">
                        <?php if ($is_edit) { ?>
                        <div class="alert alert-info mb0">
                            <i data-feather="info" class="icon-16"></i>
                            <?php echo app_lang("gd_rental_edit_payments_help"); ?>
                        </div>
                        <?php } else { ?>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="gd-rental-deposit"><?php echo app_lang("gd_deposit_amount"); ?></label>
                                    <input type="text" id="gd-rental-deposit" name="deposit_amount" class="form-control" inputmode="decimal" autocomplete="off" value="0,00" placeholder="0,00">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="gd-rental-deposit-method"><?php echo app_lang("gd_deposit_payment_method"); ?></label>
                                    <select id="gd-rental-deposit-method" name="deposit_payment_method" class="form-control">
                                        <option value=""><?php echo app_lang("gd_deposit_payment_method_select"); ?></option>
                                        <?php foreach ($payment_methods as $method) { ?>
                                            <option value="<?php echo $e($method); ?>"<?php echo $method === "pix" ? " selected" : ""; ?>><?php echo $e(app_lang("gd_finance_method_" . $method)); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="gd-rental-financial-account"><?php echo app_lang("gd_finance_account"); ?></label>
                                    <select id="gd-rental-financial-account" name="financial_account_id" class="form-control">
                                        <option value=""><?php echo app_lang("gd_deposit_account_select"); ?></option>
                                        <?php foreach ($finance_accounts as $account) { ?>
                                            <option value="<?php echo (int) $account["id"]; ?>"<?php echo count($finance_accounts) === 1 ? " selected" : ""; ?>><?php echo $e($account["name"]); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="gd-rental-balance-preview" class="text-muted"></div>
                        <?php } ?>
                    </div>

                    <div class="text-muted mb0">
                        <div id="gd-rental-time-preview"><?php echo app_lang("gd_choose_date_and_time"); ?></div>
                        <div><?php echo app_lang("gd_rental_value") . ": "; ?><strong id="gd-rental-price-hint">-</strong></div>
                        <div><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($is_edit && $edit_type === "recurring") { ?>
            <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb20">
                <span><i data-feather="shuffle" class="icon-16"></i> <?php echo app_lang("gd_reschedule_help"); ?></span>
                <?php echo modal_anchor(get_uri("grupo_donato/court-rentals/reschedule-modal"), '<i data-feather="shuffle" class="icon-14"></i> ' . app_lang("gd_reschedule_rental"), ["class" => "btn btn-warning btn-sm", "data-modal-lg" => 1, "data-post-rental_id" => (int) $edit_data["id"], "title" => app_lang("gd_reschedule_rental")]); ?>
            </div>
        <?php } ?>

        <?php if (!$is_edit) { ?>
        <div id="gd-rental-available-courts" class="card bg-light mb20" style="display:none">
            <div class="card-body">
                <h5 class="mb5"><?php echo app_lang("gd_available_times"); ?></h5>
                <div id="gd-rental-available-period" class="fw-bold mb5"></div>
                <div class="text-muted mb10"><small><?php echo app_lang("gd_available_courts_help"); ?></small></div>
                <div id="gd-rental-available-courts-results" class="gd-available-courts-grid"></div>
            </div>
        </div>
        <?php } ?>

        <div class="mb20">
            <h5 class="mb15"><?php echo app_lang("gd_select_court"); ?></h5>
            <?php if ($resources) { ?>
                <div class="form-group mb0">
                    <select name="selected_resource_id" id="gd-rental-court" class="form-control">
                        <option value=""></option>
                        <?php foreach ($resources as $resource) { ?>
                            <option value="<?php echo (int) $resource["id"]; ?>" data-court-code="<?php echo $e($resource["code"]); ?>"<?php echo (int) ($edit_data["resource_id"] ?? 0) === (int) $resource["id"] ? " selected" : ""; ?>>
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

        <div class="mb20" id="gd-rental-combo-section">
            <div class="form-check mb10">
                <input type="checkbox" class="form-check-input" id="gd-rental-with-barbecue" name="combo_enabled" value="1"<?php echo !empty($edit_data["combo_enabled"]) ? " checked" : ""; ?><?php echo $initial_mode !== "single" ? " disabled" : ""; ?>>
                <label class="form-check-label" for="gd-rental-with-barbecue">
                    <strong><?php echo app_lang("gd_combo_add_barbecue"); ?></strong>
                    <span class="text-muted d-block"><small><?php echo app_lang("gd_combo_same_schedule_help"); ?></small></span>
                </label>
            </div>

            <div id="gd-rental-combo-fields" class="card bg-light" style="display:none">
                <div class="card-body">
                    <div id="gd-rental-available-barbecues" class="mb15" style="display:none">
                        <h5 class="mb5"><?php echo app_lang("gd_available_barbecues_for_time"); ?></h5>
                        <div id="gd-rental-available-barbecue-period" class="fw-bold mb5"></div>
                        <div class="text-muted mb10"><small><?php echo app_lang("gd_available_barbecues_help"); ?></small></div>
                        <div id="gd-rental-available-barbecues-results" class="gd-available-courts-grid"></div>
                    </div>

                    <div class="form-group">
                        <label for="gd-rental-barbecue"><?php echo app_lang("gd_select_barbecue"); ?> <span class="text-danger">*</span></label>
                        <select name="barbecue_resource_id" id="gd-rental-barbecue" class="form-control">
                            <option value=""></option>
                            <?php foreach (($barbecue_resources ?? []) as $resource) { ?>
                                <option value="<?php echo (int) $resource["id"]; ?>" data-barbecue-code="<?php echo $e($resource["code"]); ?>"<?php echo (int) ($edit_data["barbecue_resource_id"] ?? 0) === (int) $resource["id"] ? " selected" : ""; ?>><?php echo $e($resource["code"] . " — " . $resource["name"]); ?></option>
                            <?php } ?>
                        </select>
                        <?php if (empty($barbecue_resources)) { ?><div class="alert alert-warning mt10 mb0"><?php echo app_lang("gd_no_barbecues_registered"); ?></div><?php } ?>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="gd-rental-barbecue-amount"><?php echo app_lang("gd_combo_barbecue_amount"); ?> <span class="text-danger">*</span></label>
                                <input type="text" id="gd-rental-barbecue-amount" name="barbecue_amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo $e($edit_data["barbecue_amount"] ?? ""); ?>" placeholder="0,00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="gd-rental-combo-discount"><?php echo app_lang("gd_combo_discount"); ?></label>
                                <input type="text" id="gd-rental-combo-discount" name="combo_discount_amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo $e($edit_data["combo_discount_amount"] ?? ""); ?>" placeholder="0,00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="gd-rental-combo-discount-reason"><?php echo app_lang("gd_combo_discount_reason"); ?></label>
                                <input type="text" id="gd-rental-combo-discount-reason" name="combo_discount_reason" class="form-control" maxlength="255" value="<?php echo $e($edit_data["combo_discount_reason"] ?? ""); ?>" placeholder="<?php echo $e(app_lang("gd_combo_discount_reason_placeholder")); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-success mb0" id="gd-rental-combo-total">
                        <strong><?php echo app_lang("gd_combo_total"); ?>:</strong> <span>-</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb20" id="gd-rental-additions-section">
            <h5 class="mb15"><?php echo app_lang("gd_rental_additions"); ?></h5>
            <div class="row">
                <div class="col-md-6">
                    <label class="d-flex align-items-start mb0">
                        <input type="checkbox" name="has_vest" value="1" id="gd-rental-addition-vest" class="mt5 me-2"<?php echo !empty($edit_data["has_vest"]) ? " checked" : ""; ?>>
                        <span><?php echo app_lang("gd_rental_addition_vest"); ?></span>
                    </label>
                    <div id="gd-rental-addition-vest-wrap" class="form-group mt10 mb0"<?php echo empty($edit_data["has_vest"]) ? ' style="display:none"' : ""; ?>>
                        <label for="gd-rental-addition-vest-amount"><?php echo app_lang("gd_rental_addition_vest_amount"); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="vest_amount" id="gd-rental-addition-vest-amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo $e($edit_data["vest_amount"] ?? ""); ?>" placeholder="0,00">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="d-flex align-items-start mb0">
                        <input type="checkbox" name="has_ball" value="1" id="gd-rental-addition-ball" class="mt5 me-2"<?php echo !empty($edit_data["has_ball"]) ? " checked" : ""; ?>>
                        <span><?php echo app_lang("gd_rental_addition_ball"); ?></span>
                    </label>
                    <div id="gd-rental-addition-ball-wrap" class="form-group mt10 mb0"<?php echo empty($edit_data["has_ball"]) ? ' style="display:none"' : ""; ?>>
                        <label for="gd-rental-addition-ball-amount"><?php echo app_lang("gd_rental_addition_ball_amount"); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="ball_amount" id="gd-rental-addition-ball-amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo $e($edit_data["ball_amount"] ?? ""); ?>" placeholder="0,00">
                    </div>
                </div>
            </div>
            <div id="gd-rental-additions-total" class="alert alert-info mt15 mb0" style="display:none"></div>
            <small class="text-muted"><?php echo app_lang("gd_rental_addition_amount_help"); ?></small>
        </div>

        <div class="mb0">
            <h5 class="mb15"><?php echo app_lang("gd_commercial_notes"); ?></h5>
            <div class="form-group">
                <textarea name="commercial_notes" class="form-control" rows="3" maxlength="5000" placeholder="<?php echo $e(app_lang("gd_simple_rental_notes_placeholder")); ?>"><?php echo $e($edit_data["commercial_notes"] ?? ""); ?></textarea>
            </div>
            <?php if (!$is_edit) { ?>
            <div class="form-group mb0">
                <label class="d-flex align-items-start mb0">
                    <input type="checkbox" name="activate" value="1" checked class="mt5 me-2">
                    <span>
                        <strong><?php echo app_lang("gd_confirm_and_activate"); ?></strong>
                        <span class="text-muted d-block"><small><?php echo app_lang("gd_confirm_and_activate_help"); ?></small></span>
                    </span>
                </label>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="modal-footer">
    <?php if (!$is_edit) { ?><button type="button" id="gd-rental-check" class="btn btn-info">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_check_availability"); ?>
    </button><?php } ?>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary" id="gd-rental-submit">
        <i data-feather="save" class="icon-16"></i> <span><?php echo $is_edit ? app_lang("save") : app_lang("gd_create_single_rental"); ?></span>
    </button>
</div>
<?php echo form_close(); ?>

<style>
.gd-available-courts-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.gd-available-court{display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;padding:10px 12px;text-align:left;border:1px solid #d7dde4;border-radius:7px;background:#fff}
.gd-available-court:not(:disabled):hover,.gd-available-court.is-selected{border-color:#198754;box-shadow:0 0 0 1px #198754}
.gd-available-court:disabled{cursor:not-allowed;opacity:.62;background:#f3f4f6}
</style>

<script>
$(document).ready(function(){
    var form = $("#gd-rental-form"),
        modeInput = $("#gd-rental-mode"),
        modeChoice = $("#gd-rental-mode-choice"),
        dateInput = $("#gd-rental-date"),
        startTime = $("#gd-rental-start-time"),
        durationInput = $("#gd-rental-duration"),
        amountInput = $("#gd-rental-amount"),
        exemptInput = $("#gd-rental-exempt"),
        financialStatusInput = $("#gd-rental-financial-status"),
        depositInput = $("#gd-rental-deposit"),
        depositMethod = $("#gd-rental-deposit-method"),
        courtInput = $("#gd-rental-court"),
        barbecueToggle = $("#gd-rental-with-barbecue"),
        barbecueSection = $("#gd-rental-combo-fields"),
        barbecueInput = $("#gd-rental-barbecue"),
        barbecueAmountInput = $("#gd-rental-barbecue-amount"),
        comboDiscountInput = $("#gd-rental-combo-discount"),
        comboDiscountReasonInput = $("#gd-rental-combo-discount-reason"),
        comboTotal = $("#gd-rental-combo-total span"),
        vestToggle = $("#gd-rental-addition-vest"),
        vestAmountInput = $("#gd-rental-addition-vest-amount"),
        vestAmountWrap = $("#gd-rental-addition-vest-wrap"),
        ballToggle = $("#gd-rental-addition-ball"),
        ballAmountInput = $("#gd-rental-addition-ball-amount"),
        ballAmountWrap = $("#gd-rental-addition-ball-wrap"),
        additionsTotal = $("#gd-rental-additions-total"),
        dueDay = $("#gd-rental-due-day"),
        customer = $("#gd-rental-customer"),
        contact = $("#gd-rental-contact"),
        phone = $("#gd-rental-phone"),
        availability = $("#gd-rental-availability"),
        availableCourts = $("#gd-rental-available-courts"),
        availableCourtsResults = $("#gd-rental-available-courts-results"),
        availablePeriod = $("#gd-rental-available-period"),
        availableBarbecues = $("#gd-rental-available-barbecues"),
        availableBarbecuesResults = $("#gd-rental-available-barbecues-results"),
        availableBarbecuePeriod = $("#gd-rental-available-barbecue-period"),
        checkButton = $("#gd-rental-check"),
        submitButton = $("#gd-rental-submit"),
        activateInput = form.find("input[name='activate']"),
        messages = <?php echo json_encode($messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        initialMode = <?php echo json_encode($initial_mode); ?>,
        editData = <?php echo json_encode($edit_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        isEdit = !!(<?php echo $is_edit ? "true" : "false"; ?>),
        modeHelp = {
            single: <?php echo json_encode(app_lang("gd_rental_mode_single_help")); ?>,
            recurring: <?php echo json_encode(app_lang("gd_rental_mode_recurring_help")); ?>
        },
        checkTimer = null,
        availableCourtsTimer = null,
        availableCourtsXhr = null,
        availableBarbecuesTimer = null,
        availableBarbecuesXhr = null,
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
    function moneyCents(value) {
        var normalized = normalizeMoney(value), parts;
        if (!/^\d+(\.\d{1,2})?$/.test(normalized)) { return null; }
        parts = normalized.split(".");
        return parseInt(parts[0], 10) * 100 + parseInt((parts[1] || "").padEnd(2, "0"), 10);
    }
    function centsMoney(cents) {
        cents = parseInt(cents, 10);
        if (isNaN(cents) || cents < 0) { return ""; }
        return (Math.floor(cents / 100) + "." + String(cents % 100).padStart(2, "0"));
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
    function selectedBarbecue() {
        var selected = barbecueInput.find("option:selected");
        return {id: barbecueInput.val() || "", code: selected.data("barbecue-code") || ""};
    }
    function comboEnabled() { return mode() === "single" && barbecueToggle.is(":checked"); }
    function comboValues() {
        if (!comboEnabled()) { return null; }
        var court = moneyCents(amountInput.val()), barbecue = moneyCents(barbecueAmountInput.val()), discount = moneyCents(comboDiscountInput.val());
        if (discount === null) { discount = 0; }
        if (court === null || barbecue === null) { return {court: court, barbecue: barbecue, discount: discount, base: null, total: null}; }
        return {court: court, barbecue: barbecue, discount: discount, base: court + barbecue, total: court + barbecue - discount};
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
    function additionValues() {
        var vest = vestToggle.is(":checked") ? moneyCents(vestAmountInput.val()) : 0,
            ball = ballToggle.is(":checked") ? moneyCents(ballAmountInput.val()) : 0;
        if (vest === null || ball === null) { return {vest: vest, ball: ball, total: null}; }
        return {vest: vest, ball: ball, total: vest + ball};
    }
    function syncAdditions() {
        var exempt = exemptInput.is(":checked"), vestChecked = vestToggle.is(":checked"), ballChecked = ballToggle.is(":checked");
        vestAmountWrap.toggle(vestChecked);
        ballAmountWrap.toggle(ballChecked);
        vestAmountInput.prop("required", vestChecked && !exempt).prop("disabled", !vestChecked || exempt);
        ballAmountInput.prop("required", ballChecked && !exempt).prop("disabled", !ballChecked || exempt);
        var additions = additionValues();
        if (additions.total !== null && additions.total > 0) {
            additionsTotal.text(<?php echo json_encode(app_lang("gd_rental_additions_total")); ?> + ": " + brl(centsMoney(additions.total))).show();
        } else {
            additionsTotal.empty().hide();
        }
    }
    function currentAmount() {
        if (exemptInput.is(":checked")) { return ""; }
        var additions = additionValues();
        if (!additions || additions.total === null) { return ""; }
        var combo = comboValues();
        if (comboEnabled()) { return combo && combo.total !== null && combo.total >= 0 ? centsMoney(combo.total + additions.total) : ""; }
        var amount = moneyCents(amountInput.val());
        return amount === null ? "" : centsMoney(amount + additions.total);
    }
    function currentListAmount() {
        if (exemptInput.is(":checked")) { return ""; }
        var additions = additionValues();
        if (!additions || additions.total === null) { return ""; }
        var combo = comboValues();
        if (comboEnabled()) { return combo && combo.base !== null ? centsMoney(combo.base + additions.total) : ""; }
        var amount = moneyCents(amountInput.val());
        return amount === null ? "" : centsMoney(amount + additions.total);
    }
    function currentDiscount() {
        if (exemptInput.is(":checked") || !comboEnabled()) { return ""; }
        var combo = comboValues();
        return combo && combo.discount > 0 ? centsMoney(combo.discount) : "";
    }
    function updateComboSummary() {
        var combo = comboValues();
        if (!comboEnabled()) { comboTotal.text("-"); return; }
        var additions = additionValues();
        if (!combo || combo.base === null || combo.total < 0 || !additions || additions.total === null) { comboTotal.text("-"); return; }
        comboTotal.text(brl(centsMoney(combo.total + additions.total)));
    }
    function scheduleValues() {
        var date = dateInput.val(), start = startTime.val(), currentMode = mode(), duration = selectedDuration(), end = null;
        if (!date || !start) { return null; }
        if (!isHalfHour(start)) { return null; }
        end = addMinutes(date, start, duration);
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
        var currentMode = mode(), schedule = scheduleValues(), amount = currentAmount(), listAmount = currentListAmount(), discount = currentDiscount(), court = selectedCourt(), barbecue = selectedBarbecue(), duration = selectedDuration(), additions = additionValues();
        $("#gd-rental-type").val(currentMode === "recurring" ? "recurring" : "single");
        var activeStatus = activateInput.is(":checked") ? "confirmed" : "pending_confirmation";
        $("#gd-rental-booking-status").val(activeStatus);
        $("#gd-rental-default-booking-status").val(activeStatus);
        if (isEdit) {
            $("#gd-rental-customer-id").val($.trim(customer.val()) ? String(editData.customer_account_id || "") : "");
            $("#gd-rental-contact-id").val($.trim(contact.val()) ? String(editData.contact_person_id || "") : "");
        } else {
            $("#gd-rental-customer-id").val($.trim(customer.val()) ? "new:" + $.trim(customer.val()) : "");
            $("#gd-rental-contact-id").val($.trim(contact.val()) ? "new:" + $.trim(contact.val()) : "");
        }
        $("#gd-rental-list-amount").val(listAmount);
        $("#gd-rental-negotiated-amount").val(comboEnabled() ? listAmount : amount);
        $("#gd-rental-discount-amount").val(discount);
        $("#gd-rental-discount-reason").val(comboEnabled() ? comboDiscountReasonInput.val() : "");
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

        var metadata = {rental_mode: currentMode, duration_minutes: duration, amount_source: "manual", vest_amount: additions.vest === null ? "" : centsMoney(additions.vest), ball_amount: additions.ball === null ? "" : centsMoney(additions.ball), addition_total: additions.total === null ? "" : centsMoney(additions.total), total_amount: amount || ""};
        if (comboEnabled()) {
            var combo = comboValues();
            metadata.combo_enabled = true;
            metadata.barbecue_resource_id = barbecue.id || "";
            metadata.court_amount = normalizeMoney(amountInput.val());
            metadata.barbecue_amount = normalizeMoney(barbecueAmountInput.val());
            metadata.combo_discount_amount = combo && combo.discount > 0 ? centsMoney(combo.discount) : "0.00";
            metadata.combo_discount_reason = comboDiscountReasonInput.val() || "";
            metadata.total_amount = amount || "";
        }
        financialStatusInput.val(exemptInput.is(":checked") ? "exempt" : "chargeable");
        if (exemptInput.is(":checked")) { metadata.financial_status = "exempt"; }
        $("#gd-rental-metadata").val(JSON.stringify(metadata));

        var titleBits = [$.trim(customer.val()), court.code];
        if (comboEnabled() && barbecue.code) { titleBits.push(barbecue.code); }
        if (currentMode === "recurring") { titleBits.push("mensalista"); }
        else { titleBits.push("avulso"); }
        if (dateInput.val()) { titleBits.push(formatDate(dateInput.val())); }
        if (startTime.val()) { titleBits.push(startTime.val()); }
        $("#gd-rental-title").val(titleBits.filter(Boolean).join(" — ").substring(0, 180));
    }
    function syncMode() {
        var currentMode = modeChoice.val() || initialMode;
        modeInput.val(currentMode);
        $("#gd-rental-mode-help").text(modeHelp[currentMode] || "");
        $(".gd-regular-duration").show();
        $(".gd-recurring-fields").toggle(currentMode === "recurring");
        $(".gd-single-payment-fields").toggle(currentMode === "single");
        $("#gd-rental-amount-label-text").text(currentMode === "recurring" ? <?php echo json_encode(app_lang("gd_monthly_value")); ?> : <?php echo json_encode(app_lang("gd_rental_value")); ?>);
        amountInput.prop("required", !exemptInput.is(":checked")).prop("disabled", exemptInput.is(":checked"));
        $("#gd-rental-amount-label .text-danger").toggle(!exemptInput.is(":checked"));
        depositInput.prop("disabled", exemptInput.is(":checked"));
        depositMethod.prop("disabled", exemptInput.is(":checked"));
        dueDay.prop("required", currentMode === "recurring");
        $(".gd-date-label").text(currentMode === "recurring" ? <?php echo json_encode(app_lang("gd_first_date")); ?> : <?php echo json_encode(app_lang("gd_rental_date")); ?>);
        submitButton.find("span").text(isEdit ? <?php echo json_encode(app_lang("save")); ?> : (currentMode === "recurring" ? <?php echo json_encode(app_lang("gd_create_recurring_rental")); ?> : <?php echo json_encode(app_lang("gd_create_single_rental")); ?>));

        if (currentMode !== "single") { barbecueToggle.prop("checked", false); }
        syncCombo();
        syncAdditions();
        clearAvailability();
        updateSummary();
        scheduleAvailableCourts();
    }
    function syncCombo() {
        var enabled = comboEnabled();
        $("#gd-rental-amount-label-text").text(enabled ? <?php echo json_encode(app_lang("gd_combo_court_amount")); ?> : (mode() === "recurring" ? <?php echo json_encode(app_lang("gd_monthly_value")); ?> : <?php echo json_encode(app_lang("gd_rental_value")); ?>));
        barbecueSection.toggle(enabled);
        barbecueInput.prop("required", enabled && !exemptInput.is(":checked"));
        barbecueAmountInput.prop("required", enabled && !exemptInput.is(":checked")).prop("disabled", !enabled || exemptInput.is(":checked"));
        comboDiscountInput.prop("disabled", !enabled || exemptInput.is(":checked"));
        comboDiscountReasonInput.prop("disabled", !enabled || exemptInput.is(":checked"));
        if (!enabled) { hideAvailableBarbecues(); }
        updateComboSummary();
        scheduleAvailableBarbecues();
    }
    function updateBalancePreview() {
        if (mode() !== "single") {
            $("#gd-rental-balance-preview").empty();
            return;
        }
        var totalCents = moneyCents(currentAmount()), depositCents = moneyCents(depositInput.val());
        $("#gd-rental-balance-preview").text(totalCents !== null && depositCents !== null && depositCents <= totalCents ? <?php echo json_encode(app_lang("gd_finance_balance")); ?> + ": " + brl((totalCents - depositCents) / 100) : "");
    }
    function updateSummary() {
        syncDerivedFields();
        updateBalancePreview();
        var currentMode = mode(), schedule = scheduleValues(), amount = currentAmount();
        updateComboSummary();
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
    function hideAvailableCourts() {
        clearTimeout(availableCourtsTimer);
        if (availableCourtsXhr) { availableCourtsXhr.abort(); availableCourtsXhr = null; }
        availableCourts.hide();
        availablePeriod.empty();
        availableCourtsResults.empty();
    }
    function hideAvailableBarbecues() {
        clearTimeout(availableBarbecuesTimer);
        if (availableBarbecuesXhr) { availableBarbecuesXhr.abort(); availableBarbecuesXhr = null; }
        availableBarbecues.hide();
        availableBarbecuePeriod.empty();
        availableBarbecuesResults.empty();
    }
    function renderAvailableCourts(rows) {
        var html = "", availableCount = 0, selectedId = String(selectedCourt().id || "");
        $.each(rows || [], function(_, row){
            var isAvailable = row && row.available === true,
                id = String((row && row.id) || ""),
                label = escapeHtml(((row && row.code) || "") + " — " + ((row && row.name) || ""));
            if (isAvailable) { availableCount++; }
            html += '<button type="button" class="gd-available-court' + (id === selectedId ? ' is-selected' : '') + '" data-resource-id="' + escapeHtml(id) + '"' + (isAvailable ? '' : ' disabled') + '>' +
                '<span>' + label + '</span><span class="badge bg-' + (isAvailable ? 'success' : 'secondary') + '">' +
                escapeHtml(isAvailable ? messages.available_label : messages.unavailable_label) + '</span></button>';
        });
        if (!availableCount) {
            html = '<div class="alert alert-warning mb0">' + escapeHtml(messages.no_courts) + '</div>' + html;
        }
        availableCourtsResults.html(html);
        if (typeof feather !== "undefined") { feather.replace(); }
    }
    function loadAvailableCourts() {
        var schedule = scheduleValues();
        if (isEdit || !dateInput.val() || !startTime.val() || !schedule) { hideAvailableCourts(); return; }
        availableCourts.show();
        availablePeriod.text(formatDate(schedule.startDate) + " · " + schedule.startTime + " às " + schedule.endTime);
        availableCourtsResults.html('<div class="text-muted"><i data-feather="loader" class="icon-16"></i> ' + escapeHtml(messages.checking) + '</div>');
        if (typeof feather !== "undefined") { feather.replace(); }
        var data = form.serializeArray();
        setPostValue(data, "starts_at_local", schedule.startsAt);
        setPostValue(data, "ends_at_local", schedule.endsAt);
        if (availableCourtsXhr) { availableCourtsXhr.abort(); }
        availableCourtsXhr = $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/availability-options"); ?>',
            type: "POST",
            data: $.param(data),
            dataType: "json"
        }).done(function(response){
            if (response && response.success) { renderAvailableCourts(response.data || []); }
            else { availableCourtsResults.html('<div class="alert alert-danger mb0">' + escapeHtml((response && response.message) || messages.error) + '</div>'); }
        }).fail(function(xhr, status){
            if (status === "abort") { return; }
            var body = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            availableCourtsResults.html('<div class="alert alert-danger mb0">' + escapeHtml((body && body.message) || messages.error) + '</div>');
        }).always(function(){ availableCourtsXhr = null; });
    }
    function scheduleAvailableCourts() {
        clearTimeout(availableCourtsTimer);
        if (isEdit || !dateInput.val() || !startTime.val() || !scheduleValues()) { hideAvailableCourts(); return; }
        availableCourtsTimer = setTimeout(loadAvailableCourts, 250);
    }
    function renderAvailableBarbecues(rows) {
        var html = "", availableCount = 0, selectedId = String(selectedBarbecue().id || "");
        $.each(rows || [], function(_, row){
            var isAvailable = row && row.available === true,
                id = String((row && row.id) || ""),
                label = escapeHtml(((row && row.code) || "") + " — " + ((row && row.name) || ""));
            if (isAvailable) { availableCount++; }
            html += '<button type="button" class="gd-available-court' + (id === selectedId ? ' is-selected' : '') + '" data-resource-id="' + escapeHtml(id) + '"' + (isAvailable ? '' : ' disabled') + '>' +
                '<span>' + label + '</span><span class="badge bg-' + (isAvailable ? 'success' : 'secondary') + '">' +
                escapeHtml(isAvailable ? messages.available_label : messages.unavailable_label) + '</span></button>';
        });
        if (!availableCount) {
            html = '<div class="alert alert-warning mb0">' + escapeHtml(messages.no_barbecues) + '</div>' + html;
        }
        availableBarbecuesResults.html(html);
        if (typeof feather !== "undefined") { feather.replace(); }
    }
    function loadAvailableBarbecues() {
        var schedule = scheduleValues();
        if (isEdit || !comboEnabled() || !dateInput.val() || !startTime.val() || !schedule) { hideAvailableBarbecues(); return; }
        availableBarbecues.show();
        availableBarbecuePeriod.text(formatDate(schedule.startDate) + " · " + schedule.startTime + " às " + schedule.endTime);
        availableBarbecuesResults.html('<div class="text-muted"><i data-feather="loader" class="icon-16"></i> ' + escapeHtml(messages.checking) + '</div>');
        if (typeof feather !== "undefined") { feather.replace(); }
        var data = form.serializeArray();
        setPostValue(data, "starts_at_local", schedule.startsAt);
        setPostValue(data, "ends_at_local", schedule.endsAt);
        if (availableBarbecuesXhr) { availableBarbecuesXhr.abort(); }
        availableBarbecuesXhr = $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/barbecue-availability-options"); ?>',
            type: "POST",
            data: $.param(data),
            dataType: "json"
        }).done(function(response){
            if (response && response.success) { renderAvailableBarbecues(response.data || []); }
            else { availableBarbecuesResults.html('<div class="alert alert-danger mb0">' + escapeHtml((response && response.message) || messages.error) + '</div>'); }
        }).fail(function(xhr, status){
            if (status === "abort") { return; }
            var body = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            availableBarbecuesResults.html('<div class="alert alert-danger mb0">' + escapeHtml((body && body.message) || messages.error) + '</div>');
        }).always(function(){ availableBarbecuesXhr = null; });
    }
    function scheduleAvailableBarbecues() {
        clearTimeout(availableBarbecuesTimer);
        if (isEdit || !comboEnabled() || !dateInput.val() || !startTime.val() || !scheduleValues()) { hideAvailableBarbecues(); return; }
        availableBarbecuesTimer = setTimeout(loadAvailableBarbecues, 250);
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
        if (comboEnabled() && !selectedBarbecue().id) { return messages.barbecue_required; }
        var exempt = exemptInput.is(":checked"), amountCents = moneyCents(currentAmount());
        var additions = additionValues();
        if (!exempt && ((vestToggle.is(":checked") && (additions.vest === null || additions.vest <= 0)) || (ballToggle.is(":checked") && (additions.ball === null || additions.ball <= 0)))) {
            return messages.addition_amount_required;
        }
        if (comboEnabled() && !exempt) {
            var combo = comboValues();
            if (!combo || combo.court === null || combo.barbecue === null) { return messages.combo_amount_required; }
            if (combo.total < 0) { return messages.combo_discount_invalid; }
            if (combo.discount > 0 && !$.trim(comboDiscountReasonInput.val())) { return messages.combo_discount_reason_required; }
        }
        if (!exempt && amountCents === null) { return messages.amount_required; }
        if (mode() === "recurring" && (!dueDay.val() || parseInt(dueDay.val(), 10) < 1 || parseInt(dueDay.val(), 10) > 31)) { return messages.due_day_required; }
        if ([90, 120].indexOf(selectedDuration()) === -1 && !(isEdit && selectedDuration() === parseInt(editData.duration_minutes || "0", 10))) { return messages.duration_required; }
        if (!exempt && mode() === "single") {
            var depositCents = moneyCents(depositInput.val());
            if (depositCents === null || depositCents > amountCents) { return messages.deposit_invalid; }
            if (depositCents > 0 && !depositMethod.val()) { return messages.deposit_method_required; }
        }
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
        setPostValue(data, "financial_status", exemptInput.is(":checked") ? "exempt" : "chargeable");
        setPostValue(data, "negotiated_amount", comboEnabled() ? currentListAmount() : currentAmount());
        setPostValue(data, "list_amount", currentListAmount());
        setPostValue(data, "discount_amount", currentDiscount());
        setPostValue(data, "discount_reason", comboEnabled() ? $.trim(comboDiscountReasonInput.val()) : "");
        setPostValue(data, "deposit_amount", !exemptInput.is(":checked") && mode() === "single" ? normalizeMoney(depositInput.val()) : "0.00");
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
            url: currentMode === "recurring" ? (isEdit ? '<?php echo_uri("grupo_donato/court-rentals/recurring-availability"); ?>' : '<?php echo_uri("grupo_donato/court-rentals/preview"); ?>') : '<?php echo_uri("grupo_donato/court-rentals/check-availability"); ?>',
            type: "POST",
            data: $.param(data),
            dataType: "json"
        }).done(function(response){
            if (currentMode === "recurring") {
                if (response && response.success) {
                    var rows = isEdit ? ((response.data && response.data.occurrences) || []) : (response.data || []), count = rows.length,
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
        if (isEdit) { return; }
        if (validationMessage()) { return; }
        checkTimer = setTimeout(checkAvailability, 550);
    }

    form.on("input", "#gd-rental-phone", function(){ this.value = maskPhone(this.value); });
    form.on("input", "#gd-rental-amount, #gd-rental-barbecue-amount, #gd-rental-combo-discount, #gd-rental-deposit, #gd-rental-addition-vest-amount, #gd-rental-addition-ball-amount", function(){ this.value = maskMoney(this.value); updateSummary(); });
    form.on("change", "#gd-rental-exempt", function(){ syncMode(); updateSummary(); });
    form.on("change", "#gd-rental-with-barbecue", function(){ syncCombo(); updateSummary(); scheduleAutoCheck(); });
    form.on("change", "#gd-rental-addition-vest, #gd-rental-addition-ball", function(){ syncAdditions(); updateSummary(); scheduleAutoCheck(); });
    form.on("change", "#gd-rental-mode-choice", syncMode);
    form.on("change", "#gd-rental-duration", function(){ scheduleAvailableCourts(); scheduleAvailableBarbecues(); scheduleAutoCheck(); });
    form.on("change", "#gd-rental-court", scheduleAutoCheck);
    form.on("change", "#gd-rental-barbecue", scheduleAutoCheck);
    form.on("change input", "#gd-rental-date, #gd-rental-start-time", function(){ scheduleAvailableCourts(); scheduleAvailableBarbecues(); scheduleAutoCheck(); });
    form.on("input change", "#gd-rental-amount, #gd-rental-barbecue-amount, #gd-rental-combo-discount, #gd-rental-combo-discount-reason", function(){ syncCombo(); updateSummary(); scheduleAutoCheck(); });
    form.on("change input", "#gd-rental-due-day", scheduleAutoCheck);
    form.on("input change", "#gd-rental-customer, #gd-rental-contact, #gd-rental-phone, #gd-rental-deposit-method, #gd-rental-financial-account", updateSummary);
    activateInput.on("change", function(){ syncDerivedFields(); });
    customer.on("blur", scheduleAutoCheck);
    checkButton.on("click", checkAvailability);
    availableCourtsResults.on("click", ".gd-available-court:not(:disabled)", function(){
        courtInput.val(String($(this).data("resource-id"))).trigger("change");
        availableCourtsResults.find(".gd-available-court").removeClass("is-selected");
        $(this).addClass("is-selected");
    });
    availableBarbecuesResults.on("click", ".gd-available-court:not(:disabled)", function(){
        barbecueInput.val(String($(this).data("resource-id"))).trigger("change");
        availableBarbecuesResults.find(".gd-available-court").removeClass("is-selected");
        $(this).addClass("is-selected");
    });

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
            if (isEdit) { location.reload(); }
            else { location.href = '<?php echo_uri("grupo_donato/court-rentals/view/"); ?>' + response.id; }
        }
    });

    modeChoice.val(initialMode);
    if (isEdit) {
        customer.val(editData.customer_name || "");
        contact.val(editData.contact_name || "");
        phone.val(maskPhone(editData.contact_phone || ""));
        dateInput.val(editData.starts_on || "");
        startTime.val(editData.local_start_time || "");
        durationInput.val(String(editData.duration_minutes || ""));
        amountInput.val(editData.court_amount || editData.amount || "");
        courtInput.val(String(editData.resource_id || ""));
        dueDay.val(editData.preferred_due_day || "");
    }
    syncMode();
    updateSummary();
    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
