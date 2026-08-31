<?php
$money = static function ($value): string { $raw = trim((string) $value); if (strpos($raw, ",") !== false && strpos($raw, ".") === false) $raw = str_replace(",", ".", $raw); [$i, $f] = array_pad(explode(".", $raw, 2), 2, "00"); $i = ltrim($i, "0") ?: "0"; return "R$ " . (preg_replace('/\B(?=(\d{3})+(?!\d))/', ".", $i) ?: $i) . "," . str_pad(substr($f, 0, 2), 2, "0"); };
?>
<?php echo form_open(get_uri("grupo_donato/finance/costs/pay"), ["id" => "gd-cost-payment-form", "class" => "general-form"]); ?>
<div class="modal-body">
    <input type="hidden" name="expense_id" value="<?php echo (int) $cost->id; ?>">
    <input type="hidden" name="idempotency_key" value="">
    <p class="mb15"><strong><?php echo esc($cost->expense_number); ?></strong> — <?php echo esc($cost->description); ?></p>
    <div class="row mb10"><div class="col-md-6"><span class="text-muted"><?php echo app_lang("gd_costs_final"); ?></span><br><strong><?php echo $money($cost->final_amount); ?></strong></div><div class="col-md-6"><span class="text-muted"><?php echo app_lang("gd_costs_balance"); ?></span><br><strong><?php echo $money($cost->balance_amount); ?></strong></div></div>
    <div class="form-group"><label><?php echo app_lang("gd_costs_amount_to_pay"); ?> *</label><div class="input-group"><span class="input-group-text">R$</span><input required name="amount" class="form-control" inputmode="decimal" autocomplete="off" data-gd-mask="money" value="<?php echo esc($cost->balance_amount); ?>"></div></div>
    <div class="row"><div class="col-md-6 form-group"><label><?php echo app_lang("gd_finance_payment_date"); ?> *</label><input required type="date" name="payment_date" class="form-control" value="<?php echo date("Y-m-d"); ?>"></div><div class="col-md-6 form-group"><label><?php echo app_lang("gd_finance_account"); ?> *</label><select required name="financial_account_id" class="form-control"><option value=""><?php echo app_lang("gd_costs_no_payment_account"); ?></option><?php foreach ($accounts as $account): ?><option value="<?php echo (int) $account["id"]; ?>"><?php echo esc($account["name"]); ?></option><?php endforeach; ?></select></div></div>
    <div class="form-group"><label><?php echo app_lang("gd_finance_method"); ?> *</label><select required name="payment_method" class="form-control"><?php foreach ($methods as $method): ?><option value="<?php echo esc($method); ?>"><?php echo esc(app_lang("gd_finance_method_" . $method)); ?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label><?php echo app_lang("gd_costs_payment_reference"); ?></label><input name="external_reference" class="form-control"></div>
    <div class="form-group"><label><?php echo app_lang("notes"); ?></label><textarea name="notes" class="form-control" rows="2"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("gd_cost_register_payment"); ?></button></div>
<?php echo form_close(); ?>
<script>
$(document).ready(function () {
    "use strict";
    var form = $("#gd-cost-payment-form");
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
    form.find("[data-gd-mask='money']").each(function () { this.value = formatMoneyValue(this.value); });
    form.on("input", "[data-gd-mask='money']", function () { this.value = maskMoney(this.value); });
    form.on("blur", "[data-gd-mask='money']", function () { this.value = formatMoneyValue(this.value); });
    form.find("[name='idempotency_key']").val((window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + "-" + Math.random()));
    form.on("submit.gdMoneyNormalize", function () { form.find("[data-gd-mask='money']").each(function () { this.value = normalizeMoney(this.value); }); });
    form.appForm({onSuccess: function () { location.reload(); }});
});
</script>
