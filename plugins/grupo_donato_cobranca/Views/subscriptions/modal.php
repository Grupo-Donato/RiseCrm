<?php
$subscription = $subscription ?? null;
$selectedCustomer = (int) ($subscription->customer_account_id ?? 0);
$selectedMethod = (string) ($subscription->collection_method ?? 'pix');
$selectedPaymentMethod = (int) ($subscription->payment_method_id ?? 0);
?>
<?php echo form_open(get_uri('cobranca/subscriptions/save'), ['id' => 'gdc-subscription-form', 'class' => 'general-form']); ?>
<input type="hidden" name="id" value="<?php echo (int) ($subscription->id ?? 0); ?>">
<div class="modal-body">
    <div class="form-group">
        <label><?php echo app_lang('gdc_customer'); ?></label>
        <select name="customer_account_id" id="gdc-subscription-customer" class="form-control select2" required>
            <option value=""></option>
            <?php foreach ($customers as $customer): ?>
                <option value="<?php echo (int) $customer['id']; ?>" <?php echo $selectedCustomer === (int) $customer['id'] ? 'selected' : ''; ?>><?php echo esc($customer['display_name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang('gdc_source_type'); ?></label>
                <select name="source_type" class="form-control" required>
                    <?php foreach (['enrollment','court_rental','manual','other'] as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($subscription->source_type ?? 'manual') === $type ? 'selected' : ''; ?>><?php echo app_lang('gdc_source_' . $type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang('gdc_source_id'); ?></label>
                <input type="number" min="1" name="source_id" class="form-control" value="<?php echo esc((string) ($subscription->source_id ?? '')); ?>" placeholder="<?php echo app_lang('gdc_source_id_help'); ?>">
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label><?php echo app_lang('gdc_method'); ?></label>
                <select name="collection_method" id="gdc-subscription-method" class="form-control" required>
                    <option value="pix" <?php echo $selectedMethod === 'pix' ? 'selected' : ''; ?>><?php echo app_lang('gdc_method_pix'); ?></option>
                    <option value="credit_card" <?php echo $selectedMethod === 'credit_card' ? 'selected' : ''; ?>><?php echo app_lang('gdc_method_credit_card'); ?></option>
                </select>
            </div>
        </div>
        <div class="col-md-6" id="gdc-subscription-card-group">
            <div class="form-group">
                <label><?php echo app_lang('gdc_card'); ?></label>
                <select name="payment_method_id" id="gdc-subscription-card" class="form-control">
                    <option value=""></option>
                    <?php foreach ($payment_methods as $method): ?>
                        <option value="<?php echo (int) $method['id']; ?>" data-customer="<?php echo (int) $method['customer_account_id']; ?>" <?php echo $selectedPaymentMethod === (int) $method['id'] ? 'selected' : ''; ?>>
                            <?php echo esc(($method['brand'] ?: 'Cartão') . ' •••• ' . ($method['last4'] ?: '----') . ' ' . sprintf('%02d/%d', (int) ($method['exp_month'] ?: 0), (int) ($method['exp_year'] ?: 0))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo app_lang('gdc_charge_day'); ?></label>
                <input type="number" min="1" max="28" name="charge_day" class="form-control" value="<?php echo (int) ($subscription->charge_day ?? 5); ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo app_lang('gdc_max_attempts'); ?></label>
                <input type="number" min="1" max="10" name="max_attempts" class="form-control" value="<?php echo (int) ($subscription->max_attempts ?? 3); ?>" required>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label><?php echo app_lang('gdc_retry_interval'); ?></label>
                <input type="number" min="1" max="30" name="retry_interval_days" class="form-control" value="<?php echo (int) ($subscription->retry_interval_days ?? 3); ?>" required>
            </div>
        </div>
    </div>
    <div class="form-group">
        <label><?php echo app_lang('gdc_notes'); ?></label>
        <textarea name="notes" class="form-control" rows="3"><?php echo esc((string) ($subscription->notes ?? '')); ?></textarea>
    </div>
    <div class="alert alert-info mb-0"><?php echo app_lang('gdc_subscription_explanation'); ?></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button>
    <button type="submit" class="btn btn-primary"><?php echo app_lang('save'); ?></button>
</div>
<?php echo form_close(); ?>
<script>
$(document).ready(function(){"use strict";
    $("#gdc-subscription-form .select2").select2();
    var allCards=$("#gdc-subscription-card option[data-customer]").clone();
    var initialCard="<?php echo $selectedPaymentMethod; ?>";
    function refreshCards(){
        var customer=$("#gdc-subscription-customer").val()||"";
        var select=$("#gdc-subscription-card").empty().append('<option value=""></option>');
        allCards.each(function(){if(String($(this).data("customer"))===String(customer)){select.append($(this).clone());}});
        if(initialCard){select.val(initialCard);initialCard="";}
    }
    function toggleCard(){
        var card=$("#gdc-subscription-method").val()==="credit_card";
        $("#gdc-subscription-card-group").toggle(card);
        $("#gdc-subscription-card").prop("required",card);
    }
    $("#gdc-subscription-customer").on("change",refreshCards);
    $("#gdc-subscription-method").on("change",toggleCard);
    refreshCards();toggleCard();
    $("#gdc-subscription-form").appForm({onSuccess:function(){location.reload();}});
});
</script>
