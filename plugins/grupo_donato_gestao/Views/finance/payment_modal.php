<?php
$reload_target = (string) ($reload_target ?? "");
echo form_open(get_uri("grupo_donato/finance/payments/save"), ["id" => "payment-form", "class" => "general-form"]);
?>
<div class="modal-body">
    <div class="row">
        <div class="col-md-6">
            <label><?php echo app_lang("gd_finance_payment_date"); ?></label>
            <input type="date" name="payment_date" class="form-control" value="<?php echo date("Y-m-d"); ?>">
        </div>
        <div class="col-md-6">
            <label><?php echo app_lang("gd_finance_amount"); ?></label>
            <input id="payment-amount" name="amount" class="form-control" value="<?php echo esc($balance); ?>">
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <label><?php echo app_lang("gd_finance_method"); ?></label>
            <?php echo form_dropdown("payment_method", array_combine($methods, array_map(fn($x) => app_lang("gd_finance_method_" . $x), $methods)), "pix", "class='form-control'"); ?>
        </div>
        <div class="col-md-6">
            <label><?php echo app_lang("gd_finance_account"); ?></label>
            <?php echo form_dropdown("financial_account_id", array_column($accounts, "name", "id"), "", "class='form-control select2' required"); ?>
        </div>
    </div>
    <div class="form-group mt-3">
        <label><?php echo app_lang("gd_finance_allocations"); ?></label>
        <div class="table-responsive">
            <table class="table">
                <tr>
                    <th><?php echo app_lang("gd_finance_number"); ?></th>
                    <th><?php echo app_lang("gd_finance_customer"); ?></th>
                    <th><?php echo app_lang("gd_finance_balance"); ?></th>
                    <th><?php echo app_lang("gd_finance_amount"); ?></th>
                </tr>
                <?php foreach ($receivables as $r) { ?>
                    <tr>
                        <td><?php echo esc($r->receivable_number); ?></td>
                        <td><?php echo esc($r->customer_name); ?></td>
                        <td><?php echo esc($r->balance_amount); ?></td>
                        <td><input class="form-control allocation" name="allocations[<?php echo $r->id; ?>]" value="<?php echo (int) $receivable_id === (int) $r->id ? esc($balance) : ""; ?>"></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    </div>
    <div class="form-group">
        <input name="external_reference" class="form-control" placeholder="<?php echo app_lang("gd_finance_external_reference"); ?>">
    </div>
    <div class="form-group">
        <textarea name="notes" class="form-control" placeholder="<?php echo app_lang("gd_notes"); ?>"></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    "use strict";
    var reloadTarget = "<?php echo addslashes($reload_target); ?>";

    $("#payment-form").appForm({
        onSuccess: function (result) {
            if (reloadTarget) {
                $("#ajaxModal").modal("hide");
                $("#" + reloadTarget).appTable({reload: true});
                if (window.reloadGdRentalPaymentsSummary) {
                    window.reloadGdRentalPaymentsSummary();
                }
                appAlert.success(result.message || "<?php echo addslashes(app_lang("record_saved")); ?>");
                return;
            }

            window.location = "<?php echo_uri("grupo_donato/finance/payments/receipt"); ?>/" + result.id;
        }
    });

    $("#payment-form .select2").select2();
    $(".allocation").on("input", function () {
        var cents = 0;
        $(".allocation").each(function () {
            var value = $(this).val().replace(",", ".");
            if (/^\d+(\.\d{0,2})?$/.test(value)) {
                cents += Math.round(parseFloat(value) * 100);
            }
        });
        $("#payment-amount").val((cents / 100).toFixed(2));
    });
});
</script>
