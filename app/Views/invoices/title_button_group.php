<div class="tab-title clearfix no-border">
    <div class="title-button-group">
        <?php if ($can_edit_invoices) { ?>
            <?php echo modal_anchor(get_uri("labels/modal_form"), "<i data-feather='tag' class='icon-16'></i> " . app_lang('manage_labels'), array("class" => "btn btn-default mb0", "title" => app_lang('manage_labels'), "data-post-type" => "invoice")); ?>
            <?php echo modal_anchor(get_uri("invoice_payments/payment_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_payment'), array("class" => "btn btn-default mb0", "title" => app_lang('add_payment'))); ?>
            <?php echo modal_anchor(get_uri("invoices/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_invoice'), array("class" => "btn btn-default mb0", "title" => app_lang('add_invoice'))); ?>
        <?php } ?>
    </div>
</div>