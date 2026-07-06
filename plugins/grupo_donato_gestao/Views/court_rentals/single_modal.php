<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$availability_messages = [
    "checking" => app_lang("gd_booking_form_checking"),
    "available" => app_lang("gd_booking_availability_ok"),
    "unavailable" => app_lang("gd_booking_availability_problem"),
    "error" => app_lang("gd_booking_availability_error"),
    "resource_required" => app_lang("gd_select_at_least_one_court"),
];
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\rentals_styles"); ?>
<?php echo form_open(get_uri("grupo_donato/court-rentals/save-single"), ["id" => "gd-court-single-form", "class" => "general-form gd-rentals-shell", "role" => "form"]); ?>
<input type="hidden" name="rental_type" value="single">
<input type="hidden" name="booking_status" value="pending_confirmation">

<div class="modal-body clearfix">
    <div class="container-fluid">
        <div class="alert alert-info mb20">
            <i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_single_rental_form_help"); ?>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="gd-section-title"><i data-feather="user" class="icon-18"></i><h5><?php echo app_lang("gd_booking_form_customer"); ?></h5></div>
                <div class="form-group">
                    <label for="gd-cr-customer"><?php echo app_lang("gd_customer"); ?> <span class="text-danger">*</span></label>
                    <input type="hidden" name="customer_account_id" id="gd-cr-customer-id">
                    <input type="text" name="customer_account_text" id="gd-cr-customer" class="form-control" maxlength="190" autocomplete="off" required data-rule-required="true" placeholder="Digite o cliente">
                </div>
                <div class="form-group">
                    <label for="gd-cr-contact"><?php echo app_lang("gd_contact"); ?></label>
                    <input type="hidden" name="contact_person_id" id="gd-cr-contact-id">
                    <input type="text" name="contact_person_text" id="gd-cr-contact" class="form-control" maxlength="190" autocomplete="off" placeholder="Digite o contato">
                    <div class="text-muted gd-form-help mt5"><?php echo app_lang("gd_contact_optional_help"); ?></div>
                </div>
                <div class="form-group">
                    <label for="gd-cr-phone"><?php echo app_lang("phone"); ?></label>
                    <input id="gd-cr-phone" name="contact_phone" class="form-control" inputmode="tel" maxlength="15" autocomplete="off" placeholder="(00) 00000-0000" data-gd-mask="phone-br">
                </div>
                <div class="form-group">
                    <label for="gd-cr-title"><?php echo app_lang("gd_title"); ?> <span class="text-danger">*</span></label>
                    <input id="gd-cr-title" name="title" class="form-control" maxlength="180" required placeholder="<?php echo $e(app_lang("gd_rental_title_placeholder")); ?>">
                </div>
            </div>

            <div class="col-md-6">
                <div class="gd-section-title"><i data-feather="clock" class="icon-18"></i><h5><?php echo app_lang("gd_booking_form_schedule"); ?></h5></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_starts_at"); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="starts_at_local" class="form-control" inputmode="numeric" maxlength="16" autocomplete="off" required placeholder="dd/mm/aaaa hh:mm" data-gd-mask="datetime-local">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_ends_at"); ?> <span class="text-danger">*</span></label>
                            <input type="text" name="ends_at_local" class="form-control" inputmode="numeric" maxlength="16" autocomplete="off" required placeholder="dd/mm/aaaa hh:mm" data-gd-mask="datetime-local">
                        </div>
                    </div>
                </div>
                <div class="text-muted gd-form-help mb15"><?php echo app_lang("gd_unit_timezone") . ": " . $e($timezone); ?></div>

                <div class="gd-section-title"><i data-feather="dollar-sign" class="icon-18"></i><h5><?php echo app_lang("gd_commercial_terms"); ?></h5></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_negotiated_amount"); ?></label>
                            <input name="negotiated_amount" class="form-control" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_list_amount"); ?></label>
                            <input name="list_amount" id="gd-cr-list" class="form-control" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label><?php echo app_lang("gd_commercial_notes"); ?></label>
                    <textarea name="commercial_notes" class="form-control" rows="3" placeholder="<?php echo $e(app_lang("gd_commercial_notes_placeholder")); ?>"></textarea>
                </div>
            </div>
        </div>

        <hr>
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
                                <span>
                                    <strong><?php echo $e($resource["code"]); ?></strong>
                                    <span class="text-muted">— <?php echo $e($resource["name"]); ?></span>
                                </span>
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

        <div id="gd-cr-check-result" class="mt10 mb10"></div>

        <button class="btn btn-default btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#gd-cr-advanced" aria-expanded="false">
            <i data-feather="settings" class="icon-14"></i> <?php echo app_lang("gd_booking_form_advanced"); ?>
        </button>
        <div class="collapse mt15" id="gd-cr-advanced">
            <div class="card mb0"><div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_discount_amount"); ?></label>
                            <input name="discount_amount" class="form-control" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_discount_reason"); ?></label>
                            <input name="discount_reason" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_effective_from"); ?></label>
                            <input type="text" name="effective_from" class="form-control" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="dd/mm/aaaa" data-gd-mask="date">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_effective_until"); ?></label>
                            <input type="text" name="effective_until" class="form-control" inputmode="numeric" maxlength="10" autocomplete="off" placeholder="dd/mm/aaaa" data-gd-mask="date">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_product"); ?></label>
                            <select name="product_id" id="gd-cr-product" class="form-control" style="width:100%"></select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label><?php echo app_lang("gd_price_list"); ?></label>
                            <select name="price_list_id" id="gd-cr-price-list" class="form-control" style="width:100%"></select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" id="gd-cr-resolve" class="btn btn-default btn-block"><?php echo app_lang("gd_resolve_price"); ?></button>
                        </div>
                    </div>
                </div>
                <div id="gd-cr-price-info" class="mb15"></div>
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
    <button type="button" id="gd-cr-check" class="btn btn-info">
        <i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_check_availability"); ?>
    </button>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><i data-feather="save" class="icon-16"></i> <?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function(){
    var form = $("#gd-court-single-form"),
        customer = $("#gd-cr-customer"),
        contact = $("#gd-cr-contact"),
        customerId = $("#gd-cr-customer-id"),
        contactId = $("#gd-cr-contact-id"),
        checkButton = $("#gd-cr-check"),
        checkResult = $("#gd-cr-check-result"),
        messages = <?php echo json_encode($availability_messages, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        invalidDateMessage = <?php echo json_encode(app_lang("gd_invalid_date"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        invalidDateTimeMessage = <?php echo json_encode(app_lang("gd_invalid_local_datetime"), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function escapeHtml(value) { return $("<div>").text(value || "").html(); }
    function selectedResourceName() {
        var selected = $(".gd-resource-toggle:checked").first();
        return selected.length ? selected.attr("name").replace(/\D+/g, "") : "";
    }
    function setResult(type, icon, text) {
        checkResult.html('<div class="alert alert-' + type + ' mb0"><i data-feather="' + icon + '" class="icon-16"></i> ' + escapeHtml(text) + '</div>');
        if (typeof feather !== "undefined") { feather.replace(); }
    }
    function syncResources() {
        $("[data-resource-card]").each(function(){
            var card = $(this), checked = card.find(".gd-resource-toggle").is(":checked");
            card.toggleClass("is-selected border-primary", checked);
            card.find(".gd-resource-buffer input").prop("disabled", !checked);
        });
    }
    function digitsOnly(value) {
        return String(value || "").replace(/\D+/g, "");
    }
    function maskPhone(value) {
        var digits = digitsOnly(value);
        if (digits.length > 11 && digits.indexOf("55") === 0) { digits = digits.substring(2); }
        digits = digits.substring(0, 11);
        if (digits.length <= 2) { return digits; }
        if (digits.length <= 6) { return "(" + digits.substring(0, 2) + ") " + digits.substring(2); }
        if (digits.length <= 10) { return "(" + digits.substring(0, 2) + ") " + digits.substring(2, 6) + "-" + digits.substring(6); }
        return "(" + digits.substring(0, 2) + ") " + digits.substring(2, 7) + "-" + digits.substring(7);
    }
    function maskDate(value) {
        var digits = digitsOnly(value).substring(0, 8);
        if (digits.length <= 2) { return digits; }
        if (digits.length <= 4) { return digits.substring(0, 2) + "/" + digits.substring(2); }
        return digits.substring(0, 2) + "/" + digits.substring(2, 4) + "/" + digits.substring(4);
    }
    function maskDateTime(value) {
        var digits = digitsOnly(value).substring(0, 12),
            date = maskDate(digits.substring(0, 8)),
            time = digits.substring(8);
        if (!time) { return date; }
        if (time.length <= 2) { return date + " " + time; }
        return date + " " + time.substring(0, 2) + ":" + time.substring(2);
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
    function formatMoneyValue(value) {
        value = String(value || "").trim();
        if (!value) { return ""; }
        if (value.indexOf(",") !== -1) { return maskMoney(value); }
        var parts = value.split(".");
        if (parts.length === 2) {
            return maskMoney(parts[0] + parts[1].substring(0, 2).padEnd(2, "0"));
        }
        return maskMoney(value + "00");
    }
    function normalizeMoney(value) {
        value = String(value || "").trim().replace(/[^\d,.-]/g, "");
        if (!value) { return ""; }
        if (value.indexOf(",") !== -1) {
            value = value.replace(/\./g, "").replace(",", ".");
        } else {
            value = value.replace(/,/g, "");
        }
        return value;
    }
    function validDateParts(day, month, year) {
        var date = new Date(year, month - 1, day);
        return year >= 1900 && month >= 1 && month <= 12 && day >= 1 && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    }
    function normalizeDate(value) {
        value = String(value || "").trim();
        if (!value) { return ""; }
        if (/^\d{4}-\d{2}-\d{2}$/.test(value)) { return value; }
        var digits = digitsOnly(value);
        if (digits.length !== 8) { return null; }
        var day = parseInt(digits.substring(0, 2), 10),
            month = parseInt(digits.substring(2, 4), 10),
            year = parseInt(digits.substring(4, 8), 10);
        if (!validDateParts(day, month, year)) { return null; }
        return String(year).padStart(4, "0") + "-" + String(month).padStart(2, "0") + "-" + String(day).padStart(2, "0");
    }
    function normalizeDateTime(value) {
        value = String(value || "").trim();
        if (!value) { return ""; }
        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) { return value; }
        var digits = digitsOnly(value);
        if (digits.length !== 12) { return null; }
        var date = normalizeDate(digits.substring(0, 8)),
            hour = parseInt(digits.substring(8, 10), 10),
            minute = parseInt(digits.substring(10, 12), 10);
        if (!date || hour > 23 || minute > 59) { return null; }
        return date + "T" + String(hour).padStart(2, "0") + ":" + String(minute).padStart(2, "0");
    }
    function normalizePhone(value) {
        var digits = digitsOnly(value);
        if (digits.length > 11 && digits.indexOf("55") === 0) { digits = digits.substring(2); }
        return digits.substring(0, 11);
    }
    function editableValue(field) {
        var value = $.trim(field.val() || "");
        return value ? "new:" + value : "";
    }
    function setPostValue(data, name, value) {
        var found = false;
        $.each(data, function(_, field){
            if (field && field.name === name) {
                field.value = value;
                found = true;
                return false;
            }
        });
        if (!found) { data.push({name: name, value: value}); }
    }
    function syncEditableFields(data) {
        var customerValue = editableValue(customer),
            contactValue = editableValue(contact);
        customerId.val(customerValue);
        contactId.val(contactValue);
        if (data) {
            setPostValue(data, "customer_account_id", customerValue);
            setPostValue(data, "contact_person_id", contactValue);
        }
    }
    function normalizePostData(data) {
        var ok = true;
        syncEditableFields(data);
        $.each(data, function(_, field){
            if (!field || !field.name) { return; }
            if (field.name === "contact_phone") { field.value = normalizePhone(field.value); }
            if (["negotiated_amount", "list_amount", "discount_amount"].indexOf(field.name) !== -1) { field.value = normalizeMoney(field.value); }
            if (["effective_from", "effective_until"].indexOf(field.name) !== -1) {
                var date = normalizeDate(field.value);
                if (date === null) { ok = false; appAlert.error(invalidDateMessage, {container: ".modal-body", animate: false}); return false; }
                field.value = date;
            }
            if (["starts_at_local", "ends_at_local"].indexOf(field.name) !== -1) {
                var dateTime = normalizeDateTime(field.value);
                if (dateTime === null) { ok = false; appAlert.error(invalidDateTimeMessage, {container: ".modal-body", animate: false}); return false; }
                field.value = dateTime;
            }
        });
        return ok;
    }
    function normalizedSerializedForm() {
        syncEditableFields();
        var data = form.serializeArray();
        if (!normalizePostData(data)) { return false; }
        return $.param(data);
    }

    customer.add(contact).on("input", function(){ syncEditableFields(); });
    form.on("input", "[data-gd-mask='phone-br']", function(){ this.value = maskPhone(this.value); });
    form.on("input", "[data-gd-mask='date']", function(){ this.value = maskDate(this.value); });
    form.on("input", "[data-gd-mask='datetime-local']", function(){ this.value = maskDateTime(this.value); });
    form.on("input", "[data-gd-mask='money']", function(){ this.value = maskMoney(this.value); });
    form.on("blur", "[data-gd-mask='money']", function(){ this.value = formatMoneyValue(this.value); });

    form.on("submit.gdRentalValidate", function(event){
        if (!$(".gd-resource-toggle:checked").length) {
            event.preventDefault();
            event.stopImmediatePropagation();
            setResult("danger", "alert-triangle", messages.resource_required);
            return false;
        }
    });

    form.appForm({
        beforeAjaxSubmit: function(data) { return normalizePostData(data); },
        onSuccess:function(response){
            location.href = '<?php echo_uri("grupo_donato/court-rentals/view/"); ?>' + response.id;
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
    gdRentalSelect($("#gd-cr-product"), '<?php echo_uri("grupo_donato/court-rentals/product-options"); ?>');
    gdRentalSelect($("#gd-cr-price-list"), '<?php echo_uri("grupo_donato/court-rentals/price-list-options"); ?>');

    $(document).off("change.gdSingleResource").on("change.gdSingleResource", ".gd-resource-toggle", syncResources);
    syncResources();

    checkButton.on("click", function(){
        if (!$(".gd-resource-toggle:checked").length) {
            setResult("danger", "alert-triangle", messages.resource_required);
            return;
        }
        var payload = normalizedSerializedForm();
        if (payload === false) { return; }
        var original = checkButton.html();
        checkButton.prop("disabled", true).text(messages.checking);
        $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/check-availability"); ?>',
            type: "POST",
            data: payload,
            dataType: "json"
        }).done(function(response){
                if (response && response.success && response.data && response.data.available) {
                    setResult("success", "check-circle", messages.available);
                } else {
                    setResult("danger", "alert-triangle", (response && response.message) || messages.unavailable);
                }
            })
            .fail(function(){ setResult("danger", "alert-triangle", messages.error); })
            .always(function(){ checkButton.prop("disabled", false).html(original); if (typeof feather !== "undefined") { feather.replace(); } });
    });

    $("#gd-cr-resolve").on("click", function(){
        $.ajax({
            url: '<?php echo_uri("grupo_donato/court-rentals/resolve-price"); ?>',
            type: "POST",
            dataType: "json",
            data: {
                product_id: $("#gd-cr-product").val(),
                price_list_id: $("#gd-cr-price-list").val(),
                resource_id: selectedResourceName(),
                quantity: "1"
            }
        }).done(function(response){
            if (response.success && response.data && response.data.found) {
                $("#gd-cr-list").val(formatMoneyValue(response.data.amount));
                $("#gd-cr-price-info").html('<span class="text-success"><i data-feather="check-circle" class="icon-14"></i> <?php echo addslashes(app_lang("gd_suggested_price")); ?>: ' + escapeHtml(response.data.currency + " " + response.data.amount) + '</span>');
            } else {
                $("#gd-cr-price-info").html('<span class="text-warning"><?php echo addslashes(app_lang("gd_no_price_suggestion")); ?></span>');
            }
            if (typeof feather !== "undefined") { feather.replace(); }
        }).fail(function(){
            $("#gd-cr-price-info").html('<span class="text-danger"><?php echo addslashes(app_lang("error_occurred")); ?></span>');
        });
    });

    if (typeof feather !== "undefined") { feather.replace(); }
});
</script>
