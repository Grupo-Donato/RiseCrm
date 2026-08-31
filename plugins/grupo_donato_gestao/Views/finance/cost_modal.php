<?php
$cost = $cost ?? null;
$value = static fn($key, $default = "") => esc($cost ? ($cost->{$key} ?? $default) : $default);
$roots = array_values(array_filter($categories, static fn($c) => !$c->parent_id));
$children = array_values(array_filter($categories, static fn($c) => (bool) $c->parent_id));
?>
<?php echo form_open(get_uri("grupo_donato/finance/costs/save"), ["id" => "gd-cost-form", "class" => "general-form"]); ?>
<div class="modal-body">
    <input type="hidden" name="id" value="<?php echo $cost ? (int) $cost->id : 0; ?>">
    <input type="hidden" name="lock_version" value="<?php echo $cost ? (int) $cost->lock_version : ""; ?>">
    <div class="row">
        <div class="col-md-8 form-group"><label><?php echo app_lang("gd_finance_description"); ?> *</label><input required name="description" class="form-control" value="<?php echo $value("description"); ?>"></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_finance_payee"); ?></label><input name="payee" class="form-control" value="<?php echo $value("payee"); ?>"></div>
    </div>
    <div class="row">
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_competence"); ?> *</label><input required type="month" name="reference_month" class="form-control" value="<?php echo $value("reference_month", date("Y-m")); ?>"></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_issue_date"); ?> *</label><input required type="date" name="issue_date" class="form-control" value="<?php echo $value("issue_date", date("Y-m-d")); ?>"></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_finance_due"); ?></label><input type="date" name="due_date" class="form-control" value="<?php echo $value("due_date"); ?>"></div>
    </div>
    <hr>
    <div class="row">
        <div class="col-md-3 form-group"><label><?php echo app_lang("gd_costs_gross"); ?> *</label><div class="input-group"><span class="input-group-text">R$</span><input required name="gross_amount" class="form-control gd-money" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money" value="<?php echo $value("gross_amount", $cost->amount ?? ""); ?>"></div></div>
        <div class="col-md-3 form-group"><label><?php echo app_lang("gd_costs_discount"); ?></label><div class="input-group"><span class="input-group-text">R$</span><input name="discount_amount" class="form-control gd-money" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money" value="<?php echo $value("discount_amount", "0.00"); ?>"></div></div>
        <div class="col-md-3 form-group"><label><?php echo app_lang("gd_costs_interest"); ?></label><div class="input-group"><span class="input-group-text">R$</span><input name="interest_amount" class="form-control gd-money" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money" value="<?php echo $value("interest_amount", "0.00"); ?>"></div></div>
        <div class="col-md-3 form-group"><label><?php echo app_lang("gd_costs_penalty"); ?></label><div class="input-group"><span class="input-group-text">R$</span><input name="penalty_amount" class="form-control gd-money" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money" value="<?php echo $value("penalty_amount", "0.00"); ?>"></div></div>
    </div>
    <div class="alert alert-light py10"><strong><?php echo app_lang("gd_costs_final"); ?>:</strong> <span id="gd-cost-final-preview">R$ <?php echo esc($cost->final_amount ?? "0,00"); ?></span></div>
    <div class="row">
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_nature"); ?></label><select name="nature" class="form-control"><?php foreach ($natures as $nature): ?><option value="<?php echo esc($nature); ?>" <?php echo ($cost->nature ?? "operational_cost") === $nature ? "selected" : ""; ?>><?php echo esc(app_lang("gd_cost_nature_" . $nature)); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_behavior"); ?></label><select name="cost_behavior" class="form-control"><?php foreach ($behaviors as $behavior): ?><option value="<?php echo esc($behavior); ?>" <?php echo ($cost->cost_behavior ?? "unclassified") === $behavior ? "selected" : ""; ?>><?php echo esc(app_lang("gd_cost_behavior_" . $behavior)); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_status"); ?></label><select name="status" class="form-control"><option value="pending" <?php echo ($cost->status ?? "pending") === "pending" ? "selected" : ""; ?>><?php echo app_lang("gd_cost_status_pending"); ?></option><option value="planned" <?php echo ($cost->status ?? "") === "planned" ? "selected" : ""; ?>><?php echo app_lang("gd_cost_status_planned"); ?></option></select></div>
    </div>
    <div class="row">
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_category"); ?></label><select id="gd-cost-category" name="category_id" class="form-control"><option value="">-</option><?php foreach ($roots as $category): ?><option value="<?php echo (int) $category->id; ?>" <?php echo (int) ($cost->category_id ?? 0) === (int) $category->id ? "selected" : ""; ?>><?php echo esc($category->name); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_subcategory"); ?></label><select id="gd-cost-subcategory" name="subcategory_id" class="form-control"><option value="">-</option><?php foreach ($children as $category): ?><option data-parent="<?php echo (int) $category->parent_id; ?>" value="<?php echo (int) $category->id; ?>" <?php echo (int) ($cost->subcategory_id ?? 0) === (int) $category->id ? "selected" : ""; ?>><?php echo esc($category->name); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4 form-group"><label><?php echo app_lang("gd_costs_center"); ?></label><select name="cost_center_id" class="form-control select2"><option value="">-</option><?php foreach ($centers as $center): ?><option value="<?php echo (int) $center["id"]; ?>" <?php echo (int) ($cost->cost_center_id ?? 0) === (int) $center["id"] ? "selected" : ""; ?>><?php echo esc($center["text"]); ?></option><?php endforeach; ?></select><small class="text-muted">Onde este custo pertence na apuração: ex. Bar, Quadra Q2 ou Festas.</small></div>
    </div>
    <div class="row">
        <div class="col-md-6 form-group"><label><?php echo app_lang("gd_costs_business_area"); ?></label><select name="business_area_id" class="form-control select2"><option value="">-</option><?php foreach ($areas as $area): ?><option value="<?php echo (int) $area["id"]; ?>" <?php echo (int) ($cost->business_area_id ?? 0) === (int) $area["id"] ? "selected" : ""; ?>><?php echo esc($area["text"]); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6 form-group"><label><?php echo app_lang("gd_costs_resource"); ?></label><select name="resource_id" class="form-control select2"><option value="">-</option><?php foreach ($resources as $resource): ?><option value="<?php echo (int) $resource["id"]; ?>" <?php echo (int) ($cost->resource_id ?? 0) === (int) $resource["id"] ? "selected" : ""; ?>><?php echo esc($resource["text"]); ?></option><?php endforeach; ?></select></div>
    </div>
    <?php if (!$cost): ?><div class="row"><div class="col-md-4 form-group"><label>Parcelas</label><input type="number" min="1" max="120" value="1" name="installment_total" class="form-control"><small class="text-muted">Cada parcela será um custo independente.</small></div></div><?php endif; ?>
    <div class="form-group"><label><?php echo app_lang("notes"); ?></label><textarea name="notes" class="form-control" rows="2"><?php echo $value("notes"); ?></textarea></div>
    <?php if (!$cost): ?><div class="form-check mb10"><input class="form-check-input" type="checkbox" name="activate_allocation" value="1" id="gd-cost-enable-allocation"><label class="form-check-label" for="gd-cost-enable-allocation">Ativar rateio agora</label></div><div id="gd-cost-allocations" class="hide"><div class="gd-cost-allocation-row row mb5"><div class="col-md-3"><input name="allocations[0][percentage]" class="form-control" placeholder="%"></div><div class="col-md-3"><div class="input-group"><span class="input-group-text">R$</span><input name="allocations[0][amount]" class="form-control gd-money" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money"></div></div><div class="col-md-5"><select name="allocations[0][cost_center_id]" class="form-control"><option value="">Centro de resultado</option><?php foreach ($centers as $center): ?><option value="<?php echo (int) $center["id"]; ?>"><?php echo esc($center["text"]); ?></option><?php endforeach; ?></select></div><div class="col-md-1"><button type="button" class="btn btn-light gd-add-allocation">+</button></div></div><small class="text-muted">Rateio divide o custo entre centros para mostrar onde ele aconteceu.</small></div><?php endif; ?>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("gd_costs_save"); ?></button></div>
<?php echo form_close(); ?>
<script>
$(document).ready(function () {
    "use strict";
    var form = $("#gd-cost-form");
    function digitsOnly(value) { return String(value || "").replace(/\D/g, ""); }
    function maskMoney(value) {
        var digits = digitsOnly(value);
        if (!digits) return "";
        digits = digits.replace(/^0+(?=\d{3})/, "");
        while (digits.length < 3) digits = "0" + digits;
        var cents = digits.slice(-2), integer = digits.slice(0, -2);
        return integer.replace(/\B(?=(\d{3})+(?!\d))/g, ".") + "," + cents;
    }
    function normalizeMoney(value) {
        value = String(value || "").trim().replace(/[^\d,.-]/g, "");
        if (!value) return "";
        if (value.indexOf(",") !== -1) return value.replace(/\./g, "").replace(",", ".");
        return value.replace(/,/g, "");
    }
    function formatMoneyValue(value) {
        value = String(value || "").trim();
        if (!value) return "";
        if (value.indexOf(",") !== -1) return maskMoney(value);
        var parts = value.split(".");
        return parts.length === 2 ? maskMoney(parts[0] + parts[1].substring(0, 2).padEnd(2, "0")) : maskMoney(value + "00");
    }
    function amount(name) { var value = normalizeMoney(form.find("[name='" + name + "']").val() || "0"); var number = Number(value); return isNaN(number) ? 0 : number; }
    function preview() {
        var final = amount("gross_amount") - amount("discount_amount") + amount("interest_amount") + amount("penalty_amount");
        var sign = final < 0 ? "-" : "";
        $("#gd-cost-final-preview").text(sign + "R$ " + formatMoneyValue(Math.abs(final).toFixed(2)));
    }
    form.find("[data-gd-mask='money']").each(function () { this.value = formatMoneyValue(this.value); });
    form.on("input", "[data-gd-mask='money']", function () { this.value = maskMoney(this.value); });
    form.on("blur", "[data-gd-mask='money']", function () { this.value = formatMoneyValue(this.value); });
    form.find(".gd-money").on("input", preview);
    preview();
    function subcategories() { var parent = $("#gd-cost-category").val(); $("#gd-cost-subcategory option[data-parent]").each(function () { $(this).toggle(!parent || String($(this).data("parent")) === String(parent)); }); if (!parent) $("#gd-cost-subcategory").val(""); }
    $("#gd-cost-category").on("change", subcategories); subcategories();
    $("#gd-cost-enable-allocation").on("change", function () { $("#gd-cost-allocations").toggleClass("hide", !this.checked); });
    $("#gd-cost-allocations").on("click", ".gd-add-allocation", function () { var index = $("#gd-cost-allocations .gd-cost-allocation-row").length; var row = $(this).closest(".gd-cost-allocation-row").clone(); row.find("input,select").each(function () { this.name = this.name.replace(/allocations\[\d+\]/, "allocations[" + index + "]"); this.value = ""; }); row.find(".gd-add-allocation").text("×").removeClass("gd-add-allocation").addClass("gd-remove-allocation"); $("#gd-cost-allocations").append(row); });
    $("#gd-cost-allocations").on("click", ".gd-remove-allocation", function () { $(this).closest(".gd-cost-allocation-row").remove(); });
    form.on("submit.gdMoneyNormalize", function () { form.find("[data-gd-mask='money']").each(function () { this.value = normalizeMoney(this.value); }); });
    form.appForm({onSuccess: function () { location.reload(); }});
});
</script>
