<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'payment_methods']); ?>
<div class="card">
    <div class="page-title clearfix">
        <h1><?php echo app_lang('gdc_cards'); ?></h1>
    </div>
    <?php if ($can_manage): ?>
        <div class="card-body border-bottom">
            <?php echo form_open(get_uri('cobranca/payment-methods/session'), ['id' => 'gdc-tokenization-form', 'class' => 'general-form']); ?>
            <div class="row align-items-end">
                <div class="col-md-8">
                    <div class="form-group mb-0">
                        <label><?php echo app_lang('gdc_customer'); ?></label>
                        <select name="customer_account_id" class="form-control select2" required>
                            <option value=""></option>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php echo (int) $customer['id']; ?>"><?php echo esc($customer['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100"><i data-feather="external-link" class="icon-16"></i> <?php echo app_lang('gdc_add_card'); ?></button>
                </div>
            </div>
            <?php echo form_close(); ?>
            <small class="text-muted d-block mt-2"><?php echo app_lang('gdc_tokenization_notice'); ?></small>
        </div>
    <?php endif; ?>
    <div class="table-responsive"><table id="gdc-payment-methods-table" class="display" width="100%"></table></div>
</div>
<script>
$(document).ready(function(){"use strict";
    $("#gdc-tokenization-form .select2").select2();
    $("#gdc-tokenization-form").appForm({
        onSuccess:function(r){
            if(r.checkout_url){window.location.href=r.checkout_url;return;}
            if(r.client_token){appAlert.success("<?php echo app_lang('gdc_client_token_received'); ?>");return;}
            appAlert.error("<?php echo app_lang('gdc_connector_operation_failed'); ?>");
        }
    });
    $("#gdc-payment-methods-table").appTable({
        source:"<?php echo_uri('cobranca/payment-methods/data'); ?>",
        columns:[
            {title:"<?php echo app_lang('gdc_customer'); ?>",data:"customer"},
            {title:"<?php echo app_lang('gdc_card'); ?>",data:"card"},
            {title:"<?php echo app_lang('gdc_expiration'); ?>",data:"expires"},
            {title:"<?php echo app_lang('gdc_default'); ?>",data:"default",class:"text-center"},
            {title:"<?php echo app_lang('gdc_status'); ?>",data:"status"},
            {title:"",data:"options",class:"text-center option w75"}
        ]
    });
});
</script>
