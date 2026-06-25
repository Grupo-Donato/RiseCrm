<?php echo form_open(get_uri('cobranca/charges/create'), ['id' => 'gdc-charge-form', 'class' => 'general-form']); ?>
<div class="modal-body">
    <div class="form-group">
        <label><?php echo app_lang('gdc_receivable'); ?></label>
        <select name="receivable_id" id="gdc-receivable" class="form-control select2" required>
            <option value=""></option>
            <?php foreach ($receivables as $row): ?>
                <option value="<?php echo (int) $row['id']; ?>" data-customer="<?php echo (int) $row['customer_account_id']; ?>">
                    <?php echo esc($row['receivable_number'] . ' — ' . $row['customer_name'] . ' — R$ ' . $row['balance_amount'] . ' — ' . $row['due_date']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label><?php echo app_lang('gdc_method'); ?></label>
        <select name="collection_method" id="gdc-method" class="form-control" required>
            <option value="pix"><?php echo app_lang('gdc_method_pix'); ?></option>
            <option value="credit_card"><?php echo app_lang('gdc_method_credit_card'); ?></option>
        </select>
    </div>
    <div class="form-group" id="gdc-card-group" style="display:none">
        <label><?php echo app_lang('gdc_card'); ?></label>
        <select name="payment_method_id" id="gdc-card" class="form-control">
            <option value=""></option>
            <?php foreach ($payment_methods as $customerId => $cards): foreach ($cards as $card): ?>
                <option value="<?php echo (int) $card['id']; ?>" data-customer="<?php echo (int) $customerId; ?>">
                    <?php echo esc(($card['brand'] ?: 'Cartão') . ' •••• ' . ($card['last4'] ?: '----') . ' ' . sprintf('%02d/%d', $card['exp_month'] ?: 0, $card['exp_year'] ?: 0)); ?>
                </option>
            <?php endforeach; endforeach; ?>
        </select>
        <small class="text-muted"><?php echo app_lang('gdc_no_raw_card_notice'); ?></small>
    </div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang('close'); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang('gdc_generate_charge'); ?></button></div>
<?php echo form_close(); ?>
<script>
$(document).ready(function(){"use strict";
    $("#gdc-charge-form .select2").select2();
    var allCards=$("#gdc-card option[data-customer]").clone();
    function refreshCards(){
        var customer=$("#gdc-receivable option:selected").data("customer")||0;
        var current=$("#gdc-card").empty().append('<option value=""></option>');
        allCards.each(function(){if(String($(this).data("customer"))===String(customer)){current.append($(this).clone());}});
    }
    function toggleCard(){var isCard=$("#gdc-method").val()==="credit_card";$("#gdc-card-group").toggle(isCard);$("#gdc-card").prop("required",isCard);}
    $("#gdc-receivable").on("change",refreshCards);$("#gdc-method").on("change",toggleCard);toggleCard();refreshCards();
    $("#gdc-charge-form").appForm({onSuccess:function(r){window.location="<?php echo_uri('cobranca/charges/view'); ?>/"+r.id;}});
});
</script>
