<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$form_messages = [
    "resource_required" => app_lang("gd_select_at_least_one_court"),
    "weekday_required" => app_lang("gd_select_at_least_one_weekday"),
    "preview_error" => app_lang("gd_occurrence_preview_error"),
];
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<?php echo form_open(get_uri("grupo_donato/court-rentals/save-monthly"), ["id" => "gd-court-monthly-form", "class" => "general-form gd-rentals-shell", "role" => "form"]); ?>
<input type="hidden" name="rental_type" value="recurring">
<input type="hidden" name="generation_horizon_days" value="90">

<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="alert alert-info mb20">
            <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_monthly_rental_form_help"); ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="gd-section-title"><i data-feather="user" class="icon-18"></i><h5><?php echo app_lang("gd_booking_form_customer"); ?></h5></div>
                <div class="form-group">
                    <label for="gd-crm-customer"><?php echo app_lang("gd_customer"); ?> <span class="text-danger">*</span></label>
                    <select name="customer_account_id" id="gd-crm-customer" class="form-control" required>
                        <option value=""></option>
                        <?php foreach ($accounts as $account) { ?>
                            <option value="<?php echo (int) $account["id"]; ?>"><?php echo $e($account["display_name"]); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="gd-crm-contact"><?php echo app_lang("gd_contact"); ?></label>
                    <select name="contact_person_id" id="gd-crm-contact" class="form-control"><option value=""></option></select>
                </div>
                <div class="form-group">
                    <label for="gd-crm-title"><?php echo app_lang("gd_title"); ?> <span class="text-danger">*</span></label>
                    <input id="gd-crm-title" name="title" class="form-control" maxlength="180" required placeholder="<?php echo $e(app_lang("gd_monthly_title_placeholder")); ?>">
                </div>
            </div>

            <div class="col-md-6">
                <div class="gd-section-title"><i data-feather="dollar-sign" class="icon-18"></i><h5><?php echo app_lang("gd_commercial_terms"); ?></h5></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_negotiated_amount"); ?> <span class="text-danger">*</span></label>
                            <input name="negotiated_amount" class="form-control" inputmode="decimal" placeholder="0,00" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_preferred_due_day"); ?> <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="31" name="preferred_due_day" class="form-control" required placeholder="<?php echo $e(app_lang("gd_due_day_placeholder")); ?>">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo app_lang("gd_commercial_notes"); ?></label>
                    <textarea name="commercial_notes" class="form-control" rows="4" placeholder="<?php echo $e(app_lang("gd_monthly_notes_placeholder")); ?>"></textarea>
                </div>
            </div>
        </div>

        <hr>
        <div class="gd-section-title"><i data-feather="calendar" class="icon-18"></i><h5><?php echo app_lang("gd_fixed_schedule"); ?></h5></div>
        <input type="hidden" name="frequency" id="gd-crm-frequency" value="weekly">
        <input type="hidden" name="interval_value" value="1">
        <div class="form-group">
            <label><?php echo app_lang("gd_weekdays"); ?> <span class="text-danger">*</span></label>
            <div class="gd-weekday-list">
                <?php for ($day = 1; $day <= 7; $day++) { ?>
                    <label class="gd-weekday-option">
                        <input type="checkbox" name="weekdays[]" value="<?php echo $day; ?>"> <?php echo app_lang("gd_weekday_short_" . $day); ?>
                    </label>
                <?php } ?>
            </div>
        </div>
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label><?php echo app_lang("gd_local_start_time"); ?> <span class="text-danger">*</span></label>
                    <input type="time" name="local_start_time" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label><?php echo app_lang("gd_local_end_time"); ?> <span class="text-danger">*</span></label>
                    <input type="time" name="local_end_time" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label><?php echo app_lang("gd_starts_on"); ?> <span class="text-danger">*</span></label>
                    <input type="date" name="starts_on" class="form-control" required>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label><?php echo app_lang("gd_ends_mode"); ?></label>
                    <select name="ends_mode" id="gd-crm-ends-mode" class="form-control">
                        <?php foreach ($ends_modes as $mode) { ?>
                            <option value="<?php echo $e($mode); ?>"<?php echo $mode === "open_ended" ? " selected" : ""; ?>><?php echo app_lang("gd_booking_series_ends_mode_" . $mode); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="row gd-crm-until-date" style="display:none">
            <div class="col-md-3">
                <div class="form-group">
                    <label><?php echo app_lang("gd_ends_on"); ?></label>
                    <input type="date" name="ends_on" class="form-control">
                </div>
            </div>
        </div>
        <div class="text-muted gd-form-help mb15"><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></div>

        <div class="gd-section-title"><i data-feather="grid" class="icon-18"></i><h5><?php echo app_lang("gd_select_courts"); ?></h5></div>
        <?php if ($resources) { ?>
            <div class="row">
                <?php foreach ($resources as $resource) {
                    $resource_id = (int) $resource["id"];
                ?>
                    <div class="col-md-6 mb10">
                        <div class="border rounded p15 gd-resource-card" data-resource-card>
                            <label class="d-flex align-items-start mb0">
                                <input type="checkbox" class="form-check-input gd-resource-toggle mt5 me-2" name="resources[<?php echo $resource_id; ?>][selected]" value="1">
                                <span><strong><?php echo $e($resource["code"]); ?></strong> <span class="text-muted">— <?php echo $e($resource["name"]); ?></span></span>
                            </label>
                            <div class="row gd-resource-buffer mt10">
                                <div class="col-xs-6 col-md-6">
                                    <label><?php echo app_lang("gd_buffer_before"); ?></label>
                                    <input type="number" min="0" name="resources[<?php echo $resource_id; ?>][buffer_before_minutes]" class="form-control" value="0" disabled>
                                </div>
                                <div class="col-xs-6 col-md-6">
                                    <label><?php echo app_lang("gd_buffer_after"); ?></label>
                                    <input type="number" min="0" name="resources[<?php echo $resource_id; ?>][buffer_after_minutes]" class="form-control" value="0" disabled>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="alert alert-warning"><?php echo app_lang("gd_booking_no_bookable_resources"); ?></div>
        <?php } ?>

        <div class="d-flex flex-wrap align-items-center gap-2 mt10 mb10">
            <button type="button" id="gd-crm-preview" class="btn btn-info btn-sm">
                <i data-feather="eye" class="icon-14"></i> <?php echo app_lang("gd_preview_occurrences"); ?>
            </button>
            <span id="gd-crm-preview-result"></span>
        </div>
        <div id="gd-crm-validation-result" class="mb10"></div>

        <button class="btn btn-default btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gd-crm-advanced" aria-expanded="false">
            <i data-feather="settings" class="icon-14"></i> <?php echo app_lang("gd_booking_form_advanced"); ?>
        </button>
        <div class="collapse mt15" id="gd-crm-advanced">
            <div class="card mb0"><div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_conflict_policy"); ?></label>
                            <select name="conflict_policy" class="form-control">
                                <?php foreach ($conflict_policies as $policy) { ?>
                                    <option value="<?php echo $e($policy); ?>"><?php echo app_lang("gd_booking_series_conflict_" . $policy); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_default_occurrence_status"); ?></label>
                            <select name="default_booking_status" class="form-control">
                                <?php foreach ($default_statuses as $status) { ?>
                                    <option value="<?php echo $e($status); ?>"<?php echo $status === "confirmed" ? " selected" : ""; ?>><?php echo app_lang("gd_booking_status_" . $status); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 gd-crm-count" style="display:none">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_max_occurrences"); ?></label>
                            <input type="number" min="1" name="max_occurrences" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_list_amount"); ?></label>
                            <input name="list_amount" id="gd-crm-list" class="form-control" inputmode="decimal" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_discount_amount"); ?></label>
                            <input name="discount_amount" class="form-control" inputmode="decimal" placeholder="0,00">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo app_lang("gd_discount_reason"); ?></label>
                    <input name="discount_reason" class="form-control" maxlength="255">
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_product"); ?></label>
                            <select name="product_id" id="gd-crm-product" class="form-control" style="width:100%"></select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_price_list"); ?></label>
                            <select name="price_list_id" id="gd-crm-price-list" class="form-control" style="width:100%"></select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" id="gd-crm-resolve" class="btn btn-default btn-block"><?php echo app_lang("gd_resolve_price"); ?></button>
                        </div>
                    </div>
                </div>
                <div id="gd-crm-price-info" class="mb15"></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_effective_from"); ?></label>
                            <input type="date" name="effective_from" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_effective_until"); ?></label>
                            <input type="date" name="effective_until" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><input type="checkbox" name="activate" value="1"> <?php echo app_lang("gd_activate_on_create"); ?></label>
                            <div class="text-muted gd-form-help"><?php echo app_lang("gd_activate_on_create_help"); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_justification"); ?></label>
                            <input name="justification" class="form-control">
                        </div>
                    </div>
                </div>
            </div></div>
        </div>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><i data-feather="save" class="icon-16"></i> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function(){
    var form = $("#gd-court-monthly-form"),
        customer = $("#gd-crm-customer"),
        contact = $("#gd-crm-contact"),
        validationResult = $("#gd-crm-validation-result"),
        previewResult = $("#gd-crm-preview-result"),
        messages = <?php echo json_encode($form_messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function escapeHtml(value) { return $("<div>").text(value || "").html(); }
    function firstResourceId() {
        var selected = $(".gd-resource-toggle:checked").first();
        return selected.length ? selected.attr("name").replace(/\D+/g, "") : "";
    }
    function showValidation(message) {
        validationResult.html('<div class="alert alert-danger mb0"><i data-feather="alert-triangle" class="icon-16"></i> ' + escapeHtml(message) + '</div>');
        if (typeof feather !== "undefined") { feather.replace(); }
    }
    function validateRequiredChoices() {
        if (!$(".gd-resource-toggle:checked").length) { showValidation(messages.resource_required); return false; }
        if (!$("input[name='weekdays[]']:checked").length) { showValidation(messages.weekday_required); return false; }
        validationResult.empty();
        return true;
    }
    function syncResources() {
        $("[data-resource-card]").each(function(){
            var card = $(this), checked = card.find(".gd-resource-toggle").is(":checked");
            card.toggleClass("is-selected border-primary", checked);
            card.find(".gd-resource-buffer input").prop("disabled", !checked);
        });
    }
    function syncEndsMode() {
        var mode = $("#gd-crm-ends-mode").val();
        $(".gd-crm-until-date").toggle(mode === "until_date");
        $(".gd-crm-count").toggle(mode === "count");
    }

    form.on("submit.gdMonthlyValidate", function(event){
        if (!validateRequiredChoices()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return false;
        }
    });

    form.appForm({onSuccess:function(response){
        location.href = '<?php echo_uri("grupo_donato/court-rentals/view/"); ?>' + response.id;
    }});

    customer.select2({
        placeholder: "-", allowClear: true, width: "100%",
        ajax: {
            url: '<?php echo_uri("grupo_donato/court-rentals/customer-options"); ?>', type: "POST", dataType: "json", delay: 250,
            data: function(params){ return {q: params.term || ""}; }, processResults: function(data){ return data; }
        }
    }).on("change", function(){ contact.val(null).trigger("change"); });

    contact.select2({
        placeholder: "-", allowClear: true, width: "100%",
        ajax: {
            url: '<?php echo_uri("grupo_donato/court-rentals/contact-options"); ?>', type: "POST", dataType: "json", delay: 250,
            data: function(params){ return {q: params.term || "", customer_account_id: customer.val()}; }, processResults: function(data){ return data; }
        }
    });

    // Produto e tabela de preço: Select2 com busca remota (IDs internos preservados).
    function gdRentalSelect(el, url){
        el.select2({
            placeholder: "-", allowClear: true, width: "100%",
            ajax: {
                url: url, type: "POST", dataType: "json", delay: 250,
                data: function(params){ return {q: params.term || "", page: params.page || 1}; },
                processResults: function(data, params){ params.page = params.page || 1; return {results: (data && data.results) || [], pagination: {more: !!(data && data.pagination && data.pagination.more)}}; }
            }
        });
    }
    gdRentalSelect($("#gd-crm-product"), '<?php echo_uri("grupo_donato/court-rentals/product-options"); ?>');
    gdRentalSelect($("#gd-crm-price-list"), '<?php echo_uri("grupo_donato/court-rentals/price-list-options"); ?>');

    $(document).off("change.gdMonthlyResource").on("change.gdMonthlyResource", ".gd-resource-toggle", syncResources);
    $("#gd-crm-ends-mode").on("change", syncEndsMode);
    syncResources();
    syncEndsMode();

    $("#gd-crm-preview").on("click", function(){
        if (!validateRequiredChoices()) { return; }
        var button = $(this), original = button.html();
        button.prop("disabled", true);
        previewResult.html('<span class="text-muted"><?php echo addslashes(app_lang("gd_booking_form_checking")); ?></span>');
        $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/preview"); ?>',
            type: "POST",
            data: form.serialize(),
            dataType: "json"
        }).done(function(response){
                if (response.success) {
                    var count = response.data ? response.data.length : 0;
                    previewResult.html('<span class="text-success"><i data-feather="check-circle" class="icon-14"></i> ' + count + ' <?php echo addslashes(app_lang("gd_occurrences_previewed")); ?></span>');
                } else {
                    previewResult.html('<span class="text-danger">' + escapeHtml(response.message || messages.preview_error) + '</span>');
                }
            })
            .fail(function(){ previewResult.html('<span class="text-danger">' + escapeHtml(messages.preview_error) + '</span>'); })
            .always(function(){ button.prop("disabled", false).html(original); if (typeof feather !== "undefined") { feather.replace(); } });
    });

    $("#gd-crm-resolve").on("click", function(){
        $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/resolve-price"); ?>',
            type: "POST",
            dataType: "json",
            data: {
                product_id: $("#gd-crm-product").val(),
                price_list_id: $("#gd-crm-price-list").val(),
                resource_id: firstResourceId(),
                quantity: "1"
            }
        }).done(function(response){
            if (response.success && response.data && response.data.found) {
                $("#gd-crm-list").val(response.data.amount);
                $("#gd-crm-price-info").html('<span class="text-success"><i data-feather="check-circle" class="icon-14"></i> <?php echo addslashes(app_lang("gd_suggested_price")); ?>: ' + escapeHtml(response.data.currency + " " + response.data.amount) + '</span>');
            } else {
                $("#gd-crm-price-info").html('<span class="text-warning"><?php echo addslashes(app_lang("gd_no_price_suggestion")); ?></span>');
            }
            if (typeof feather !== "undefined") { feather.replace(); }
        }).fail(function(){
            $("#gd-crm-price-info").html('<span class="text-danger"><?php echo addslashes(app_lang("error_occurred")); ?></span>');
        });
    });

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
