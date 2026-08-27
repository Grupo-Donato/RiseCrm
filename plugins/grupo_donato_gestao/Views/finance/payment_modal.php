<?php
$reload_target = (string) ($reload_target ?? "");
echo form_open(get_uri("grupo_donato/finance/payments/save"), ["id" => "payment-form", "class" => "general-form"]);
?>
<style>
    .gd-payment-modal {
        max-width: 960px;
        width: calc(100% - 30px);
    }
    #payment-form .gd-payment-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
    }
    #payment-form .gd-allocation-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
    }
    #payment-form .gd-allocation-table-wrap {
        border: 1px solid var(--gd-border, rgba(127, 127, 127, .25));
        border-radius: 6px;
        max-height: min(38vh, 360px);
        overflow: auto;
    }
    #payment-form .gd-allocation-table {
        color: var(--gd-text, inherit);
        margin-bottom: 0;
        min-width: 650px;
        table-layout: fixed;
        width: 100%;
    }
    #payment-form .gd-allocation-table > :not(caption) > * > * {
        background-color: var(--gd-surface, #fff) !important;
        border-color: var(--gd-border, rgba(127, 127, 127, .2));
        color: inherit;
        padding: 9px 12px;
        vertical-align: middle;
    }
    #payment-form .gd-allocation-table thead th {
        background-color: var(--gd-surface-2, #f5f7f9) !important;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .02em;
        position: sticky;
        text-transform: uppercase;
        top: 0;
        z-index: 2;
    }
    #payment-form .gd-allocation-table tbody tr:hover td {
        background-color: var(--gd-surface-2, #f8f9fa) !important;
    }
    #payment-form .gd-allocation-table tbody tr.is-selected td {
        background-color: rgba(210, 166, 58, .14) !important;
    }
    #payment-form .gd-allocation-number,
    #payment-form .gd-allocation-balance {
        white-space: nowrap;
    }
    #payment-form .gd-allocation-number {
        font-weight: 600;
    }
    #payment-form .gd-allocation-balance {
        font-variant-numeric: tabular-nums;
        text-align: right;
    }
    #payment-form .gd-allocation-input {
        font-variant-numeric: tabular-nums;
        margin-left: auto;
        max-width: 150px;
        min-width: 110px;
        text-align: right;
    }
    @media (max-width: 767.98px) {
        .gd-payment-modal {
            width: calc(100% - 16px);
        }
        #payment-form .gd-payment-field + .gd-payment-field {
            margin-top: 12px;
        }
        #payment-form .gd-allocation-table-wrap {
            max-height: 42vh;
        }
    }
</style>
<div class="modal-body">
    <div class="row">
        <div class="col-md-6 gd-payment-field">
            <label><?php echo app_lang("gd_finance_payment_date"); ?></label>
            <input type="date" name="payment_date" class="form-control" value="<?php echo date("Y-m-d"); ?>">
        </div>
        <div class="col-md-6 gd-payment-field">
            <label><?php echo app_lang("gd_finance_amount"); ?></label>
            <input id="payment-amount" name="amount" class="form-control" inputmode="decimal" autocomplete="off" value="<?php echo esc($balance); ?>">
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-6 gd-payment-field">
            <label><?php echo app_lang("gd_finance_method"); ?></label>
            <?php echo form_dropdown("payment_method", array_combine($methods, array_map(fn($x) => app_lang("gd_finance_method_" . $x), $methods)), "pix", "class='form-control'"); ?>
        </div>
        <div class="col-md-6 gd-payment-field">
            <label><?php echo app_lang("gd_finance_account"); ?></label>
            <?php echo form_dropdown("financial_account_id", array_column($accounts, "name", "id"), "", "class='form-control select2' required"); ?>
        </div>
    </div>
    <div class="form-group mt-3">
        <label class="gd-allocation-label"><?php echo app_lang("gd_finance_allocations"); ?></label>
        <div class="table-responsive gd-allocation-table-wrap">
            <table class="table table-hover gd-allocation-table">
                <colgroup>
                    <col style="width: 24%;">
                    <col style="width: 34%;">
                    <col style="width: 18%;">
                    <col style="width: 24%;">
                </colgroup>
                <thead>
                    <tr>
                        <th><?php echo app_lang("gd_finance_number"); ?></th>
                        <th><?php echo app_lang("gd_finance_customer"); ?></th>
                        <th class="text-right"><?php echo app_lang("gd_finance_balance"); ?></th>
                        <th class="text-right"><?php echo app_lang("gd_finance_amount"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receivables as $r) {
                        $is_selected = (int) $receivable_id === (int) $r->id;
                    ?>
                        <tr<?php echo $is_selected ? ' class="is-selected"' : ""; ?>>
                            <td class="gd-allocation-number"><?php echo esc($r->receivable_number); ?></td>
                            <td><?php echo esc($r->customer_name); ?></td>
                            <td class="gd-allocation-balance"><?php echo esc(to_currency($r->balance_amount)); ?></td>
                            <td>
                                <input
                                    class="form-control allocation gd-allocation-input"
                                    name="allocations[<?php echo (int) $r->id; ?>]"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    placeholder="0,00"
                                    aria-label="<?php echo esc(app_lang("gd_finance_amount") . " — " . $r->receivable_number); ?>"
                                    value="<?php echo $is_selected ? esc($balance) : ""; ?>">
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
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
    var reloadTarget = "<?php echo addslashes($reload_target); ?>",
        form = $("#payment-form");

    form.appForm({
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

    form.find(".select2").select2();
    form.find(".allocation").on("input", function () {
        var cents = 0;
        form.find(".allocation").each(function () {
            var value = $(this).val().trim().replace(",", ".");
            if (/^\d+(\.\d{0,2})?$/.test(value)) {
                cents += Math.round(parseFloat(value) * 100);
            }
        });
        $("#payment-amount").val((cents / 100).toFixed(2));
    });
});
</script>
