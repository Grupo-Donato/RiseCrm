<?php
$roots = array_values(array_filter($categories, static fn($c) => !$c->parent_id));
$frequency_labels = [
    "weekly" => "Semanal",
    "biweekly" => "Quinzenal",
    "monthly" => "Mensal",
    "quarterly" => "Trimestral",
    "semiannual" => "Semestral",
    "annual" => "Anual",
    "custom" => "Personalizada",
];
?>
<?php echo form_open(get_uri("grupo_donato/finance/costs/recurrences/save"), ["id" => "gd-cost-recurrence-form", "class" => "general-form"]); ?>
<div class="modal-body">
    <div class="row">
        <div class="col-md-6 form-group"><label>Nome *</label><input required name="name" class="form-control"></div>
        <div class="col-md-6 form-group"><label><?php echo app_lang("gd_finance_payee"); ?></label><input name="payee" class="form-control"></div>
    </div>
    <div class="form-group"><label><?php echo app_lang("gd_finance_description"); ?> *</label><input required name="description" class="form-control"></div>
    <div class="row">
        <div class="col-md-4 form-group">
            <label>Valor bruto *</label>
            <div class="input-group"><span class="input-group-text">R$</span><input required name="gross_amount" class="form-control" inputmode="decimal" autocomplete="off" placeholder="0,00" data-gd-mask="money"></div>
        </div>
        <div class="col-md-4 form-group">
            <label>Periodicidade *</label>
            <select name="frequency" class="form-control">
                <?php foreach ($frequencies as $frequency): ?><option value="<?php echo esc($frequency); ?>"><?php echo esc($frequency_labels[$frequency] ?? $frequency); ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 form-group"><label>Intervalo</label><input type="number" min="1" name="interval_value" value="1" class="form-control"></div>
    </div>
    <div class="row">
        <div class="col-md-4 form-group"><label>Data inicial *</label><input required type="date" name="start_date" value="<?php echo date("Y-m-d"); ?>" class="form-control"></div>
        <div class="col-md-4 form-group"><label>Data final</label><input type="date" name="end_date" class="form-control"></div>
        <div class="col-md-4 form-group"><label>Dia do vencimento</label><input type="number" min="1" max="31" step="1" name="due_day" class="form-control" placeholder="1 a 31"><small class="text-muted">Informe um dia entre 1 e 31.</small></div>
    </div>
    <div class="row">
        <div class="col-md-6 form-group"><label><?php echo app_lang("gd_costs_category"); ?></label><select name="category_id" class="form-control"><option value="">-</option><?php foreach ($roots as $root): ?><option value="<?php echo (int) $root->id; ?>"><?php echo esc($root->name); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6 form-group"><label><?php echo app_lang("gd_costs_center"); ?></label><select name="cost_center_id" class="form-control"><option value="">-</option><?php foreach ($centers as $center): ?><option value="<?php echo (int) $center["id"]; ?>"><?php echo esc($center["text"]); ?></option><?php endforeach; ?></select><small class="text-muted">Onde este custo pertence na apuração: ex. Bar, Quadra Q2 ou Festas.</small></div>
    </div>
    <div class="form-group"><label><?php echo app_lang("notes"); ?></label><textarea name="notes" class="form-control"></textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script>
$(document).ready(function () {
    "use strict";
    var form = $("#gd-cost-recurrence-form");
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
    form.on("input", "[name='due_day']", function () {
        if (this.value === "") return;
        this.value = Math.max(1, Math.min(31, parseInt(this.value, 10) || 1));
    });
    form.on("submit.gdMoneyNormalize", function () { form.find("[data-gd-mask='money']").each(function () { this.value = normalizeMoney(this.value); }); });
    form.appForm({onSuccess: function () { location.reload(); }});
});
</script>
