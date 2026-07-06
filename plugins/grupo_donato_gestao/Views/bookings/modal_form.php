<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$id = (int) ($model_info->id ?? 0);
$selected = [];
foreach (($model_info->resources ?? []) as $resource) {
    $selected[(int) $resource->resource_id] = $resource;
}

$series_scope = $series_scope ?? "";
$booking_save_uri = $series_scope === "single" ? "grupo_donato/booking-series/update-occurrence" : "grupo_donato/bookings/save";
$customer_options_uri = $customer_options_uri ?? "grupo_donato/bookings/customer-options";
$contact_options_uri = $contact_options_uri ?? "grupo_donato/bookings/contact-options";
$booking_check_uri = $booking_check_uri ?? "grupo_donato/bookings/check-availability";
$current_type = (string) ($model_info->booking_type ?? "customer_rental");
$current_status = (string) ($model_info->status ?? "pending_confirmation");
$availability_messages = [
    "checking" => app_lang("gd_booking_form_checking"),
    "available" => app_lang("gd_booking_availability_ok"),
    "unavailable" => app_lang("gd_booking_availability_problem"),
    "error" => app_lang("gd_booking_availability_error"),
    "gd_invalid_datetime_range" => app_lang("gd_invalid_datetime_range"),
    "gd_invalid_local_datetime" => app_lang("gd_invalid_local_datetime"),
    "gd_invalid_booking_resources" => app_lang("gd_invalid_booking_resources"),
    "gd_invalid_booking_resource" => app_lang("gd_invalid_booking_resource"),
    "gd_booking_resource_unavailable" => app_lang("gd_booking_resource_unavailable"),
    "gd_booking_conflict" => app_lang("gd_booking_conflict"),
    "gd_booking_duplicate" => app_lang("gd_booking_duplicate"),
    "gd_hold_expiry_required" => app_lang("gd_hold_expiry_required"),
    "gd_invalid_hold_expiry" => app_lang("gd_invalid_hold_expiry"),
];
?>

<?php echo form_open(get_uri($booking_save_uri), ["id" => "gd-booking-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <input type="hidden" name="lock_version" value="<?php echo (int) ($model_info->lock_version ?? 0); ?>">

        <div class="row">
            <div class="col-md-6">
                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_details"); ?></h5>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_type"); ?></label>
                        <select name="booking_type" id="gd-booking-type" class="form-control">
                            <?php foreach ($types as $value) { ?>
                                <option value="<?php echo $e($value); ?>"<?php echo $current_type === $value ? " selected" : ""; ?>>
                                    <?php echo app_lang("gd_booking_type_" . $value); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_title"); ?></label>
                        <input name="title" class="form-control" maxlength="180" required value="<?php echo $e($model_info->title ?? ""); ?>" placeholder="<?php echo $e(app_lang("gd_booking_title_placeholder")); ?>">
                    </div>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_status"); ?></label>
                        <?php if ($id) { ?>
                            <input class="form-control" value="<?php echo app_lang("gd_booking_status_" . $model_info->status); ?>" disabled>
                        <?php } else { ?>
                            <select name="status" id="gd-booking-status" class="form-control">
                                <?php foreach ($initial_statuses as $value) { ?>
                                    <option value="<?php echo $e($value); ?>"<?php echo $current_status === $value ? " selected" : ""; ?>>
                                        <?php echo app_lang("gd_booking_status_" . $value); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                    </div>
                </div>

                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_customer"); ?></h5>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_customer"); ?></label>
                        <select name="customer_account_id" id="gd-booking-customer" class="form-control">
                            <option value=""></option>
                            <?php foreach ($accounts as $account) { ?>
                                <option value="<?php echo (int) $account["id"]; ?>"<?php echo (int) ($model_info->customer_account_id ?? 0) === (int) $account["id"] ? " selected" : ""; ?>>
                                    <?php echo $e($account["display_name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_contact"); ?></label>
                        <select name="contact_person_id" id="gd-booking-contact" class="form-control">
                            <option value=""></option>
                            <?php foreach ($contacts as $person) { ?>
                                <option value="<?php echo (int) $person["id"]; ?>"<?php echo (int) ($model_info->contact_person_id ?? 0) === (int) $person["id"] ? " selected" : ""; ?>>
                                    <?php echo $e($person["full_name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_schedule"); ?></h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo app_lang("gd_starts_at"); ?></label>
                                <input type="datetime-local" name="starts_at_local" class="form-control" required value="<?php echo $e($starts_local); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo app_lang("gd_ends_at"); ?></label>
                                <input type="datetime-local" name="ends_at_local" class="form-control" required value="<?php echo $e($ends_local); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="text-muted mb10">
                        <?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?>
                    </div>
                    <div class="form-group gd-hold-row">
                        <label><?php echo app_lang("gd_hold_until"); ?></label>
                        <input type="datetime-local" name="hold_expires_at_local" class="form-control" value="<?php echo $e($hold_local); ?>">
                    </div>
                </div>

                <div class="mb20">
                    <h5 class="mb15"><?php echo app_lang("gd_booking_form_notes"); ?></h5>
                    <div class="form-group">
                        <label><?php echo app_lang("gd_notes"); ?></label>
                        <textarea name="notes" class="form-control" rows="4"><?php echo $e($model_info->notes ?? ""); ?></textarea>
                    </div>
                    <button class="btn btn-default btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gd-booking-advanced">
                        <i data-feather="settings" class="icon-14"></i> <?php echo app_lang("gd_booking_form_advanced"); ?>
                    </button>
                    <div class="collapse mt10" id="gd-booking-advanced">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_metadata_json"); ?></label>
                            <textarea name="metadata" class="form-control" rows="3"><?php echo $e($model_info->metadata ?? ""); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb20">
            <h5 class="mb15"><?php echo app_lang("gd_booking_form_courts"); ?></h5>
            <?php if ($resources) { ?>
                <div class="row">
                    <?php foreach ($resources as $resource) {
                        $resource_id = (int) $resource["id"];
                        $current = $selected[$resource_id] ?? null;
                    ?>
                        <div class="col-md-6">
                            <div class="border rounded p10 mb10 gd-booking-resource-box">
                                <label class="d-flex align-items-start mb10">
                                    <input type="checkbox" class="form-check-input gd-booking-resource-toggle mt5 me-2" name="resources[<?php echo $resource_id; ?>][selected]" value="1"<?php echo $current ? " checked" : ""; ?>>
                                    <span>
                                        <strong><?php echo $e($resource["code"]); ?></strong>
                                        <span class="text-muted">— <?php echo $e($resource["name"]); ?></span>
                                    </span>
                                </label>
                                <div class="row gd-booking-buffer-row">
                                    <div class="col-xs-6 col-md-6">
                                        <label><?php echo app_lang("gd_buffer_before"); ?></label>
                                        <input type="number" min="0" name="resources[<?php echo $resource_id; ?>][buffer_before_minutes]" class="form-control" value="<?php echo (int) ($current->buffer_before_minutes ?? 0); ?>">
                                    </div>
                                    <div class="col-xs-6 col-md-6">
                                        <label><?php echo app_lang("gd_buffer_after"); ?></label>
                                        <input type="number" min="0" name="resources[<?php echo $resource_id; ?>][buffer_after_minutes]" class="form-control" value="<?php echo (int) ($current->buffer_after_minutes ?? 0); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="alert alert-warning"><?php echo app_lang("gd_booking_no_bookable_resources"); ?></div>
            <?php } ?>
        </div>

        <div id="gd-booking-check-result" class="mb10"></div>
    </div>
</div>
<div class="modal-footer">
    <button type="button" id="gd-booking-check" class="btn btn-info">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_check_availability"); ?>
    </button>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary">
        <i data-feather="save" class="icon-16"></i> <?php echo app_lang("save"); ?>
    </button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function() {
    var form = $("#gd-booking-form"),
        customer = $("#gd-booking-customer"),
        contact = $("#gd-booking-contact"),
        checkButton = $("#gd-booking-check"),
        checkResult = $("#gd-booking-check-result"),
        messages = <?php echo json_encode($availability_messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function escapeHtml(value) {
        return $("<div>").text(value || "").html();
    }

    function operationalMessage(message) {
        var raw = $.trim(message || "");
        return messages[raw] || raw || messages.unavailable;
    }

    function resultAlert(type, icon, text) {
        checkResult.html(
            '<div class="alert alert-' + type + ' mb0">' +
                '<i data-feather="' + icon + '" class="icon-16"></i> ' + escapeHtml(text) +
            '</div>'
        );
        if (typeof feather !== "undefined") {
            feather.replace();
        }
    }

    function syncHold() {
        $(".gd-hold-row").toggle(!<?php echo $id ? "true" : "false"; ?> && $("#gd-booking-status").val() === "hold");
    }

    function syncResources() {
        $(".gd-booking-resource-box").each(function() {
            var box = $(this),
                checked = box.find(".gd-booking-resource-toggle").is(":checked");
            box.toggleClass("border-primary", checked);
            box.find(".gd-booking-buffer-row input").prop("disabled", !checked);
        });
    }

    form.appForm({
        onSuccess: function(response) {
            location.href = '<?php echo_uri("grupo_donato/bookings/view/"); ?>' + response.id;
        }
    });

    customer.select2({
        placeholder: "-",
        allowClear: true,
        ajax: {
            url: '<?php echo_uri($customer_options_uri); ?>',
            type: "POST",
            dataType: "json",
            delay: 250,
            data: function(params) {
                return {q: params.term || ""};
            },
            processResults: function(data) {
                return data;
            }
        }
    }).on("change", function() {
        contact.val(null).trigger("change");
    });

    contact.select2({
        placeholder: "-",
        allowClear: true,
        ajax: {
            url: '<?php echo_uri($contact_options_uri); ?>',
            type: "POST",
            dataType: "json",
            delay: 250,
            data: function(params) {
                return {q: params.term || "", customer_account_id: customer.val()};
            },
            processResults: function(data) {
                return data;
            }
        }
    });

    $("#gd-booking-status").on("change", syncHold);
    $(".gd-booking-resource-toggle").on("change", syncResources);
    syncHold();
    syncResources();

    checkButton.on("click", function() {
        var originalHtml = checkButton.html();
        checkButton.prop("disabled", true).text(messages.checking);
        checkResult.empty();

        $.post('<?php echo_uri($booking_check_uri); ?>', form.serialize())
            .done(function(response) {
                if (response && response.success && response.data && response.data.available) {
                    resultAlert("success", "check-circle", messages.available);
                    return;
                }
                resultAlert("danger", "alert-triangle", operationalMessage(response ? response.message : ""));
            })
            .fail(function() {
                resultAlert("danger", "alert-triangle", messages.error);
            })
            .always(function() {
                checkButton.prop("disabled", false).html(originalHtml);
                if (typeof feather !== "undefined") {
                    feather.replace();
                }
            });
    });

    if (typeof feather !== "undefined") {
        feather.replace();
    }
});
</script>
