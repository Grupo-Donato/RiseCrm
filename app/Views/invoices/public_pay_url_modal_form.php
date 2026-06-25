<div id="public-pay-url-modal-form" class="modal-body clearfix general-form">
    <div class="container-fluid">
        <div class="form-group">
            <div class="row">
                <label for="contact_id" class=" col-md-12 mb10"><?php echo app_lang('select_who_will_pay_the_invoice') . ":"; ?></label>
                <div class=" col-md-12">
                    <?php
                    echo form_dropdown("contact_id", $contacts_dropdown, array(), "class='select2 validate-hidden' id='contact_id' data-rule-required='true', data-msg-required='" . app_lang('field_required') . "'");
                    ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer">
        <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang('close'); ?></button>
        <button type="button" class="btn btn-primary" id="view-invoice-btn"><span data-feather="external-link" class="icon-16"></span> <?php echo app_lang('view_invoice'); ?></button>
        <button type="button" class="btn btn-primary" id="copy-url-btn"><span data-feather="copy" class="icon-16"></span> <?php echo app_lang('copy'); ?></button>
    </div>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#public-pay-url-modal-form .select2').select2();

            $('#view-invoice-btn').on('click', function() {
                var contactId = $('#contact_id').val();
                if (!contactId) {
                    appAlert.error('<?php echo app_lang("select_who_will_pay_the_invoice"); ?>');
                    return false;
                }

                appAjaxRequest({
                    url: '<?php echo get_uri("invoices/get_public_pay_url/" . $invoice_info->id . "/" . $invoice_info->client_id); ?>',
                    type: 'POST',
                    data: {
                        contact_id: contactId
                    },
                    dataType: 'json',
                    success: function(result) {
                        if (result.success) {
                            window.open(result.url, '_blank');
                        } else {
                            appAlert.error(result.message);
                        }
                    }
                });
            });

            $("#copy-url-btn").click(function() {
                var contactId = $('#contact_id').val();
                var $button = $(this);

                if (!contactId) {
                    appAlert.error('<?php echo app_lang("select_who_will_pay_the_invoice"); ?>');
                    return false;
                }

                appAjaxRequest({
                    url: '<?php echo get_uri("invoices/get_public_pay_url/" . $invoice_info->id . "/" . $invoice_info->client_id); ?>',
                    type: 'POST',
                    data: {
                        contact_id: contactId
                    },
                    dataType: 'json',
                    success: function(result) {
                        if (result.success && result.url) {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(result.url).then(function() {}).catch(function() {
                                    fallbackCopy(result.url, $button);
                                });
                            } else {
                                fallbackCopy(result.url, $button);
                            }

                            $('#ajaxModal').modal('hide');
                        } else {
                            appAlert.error(result.message || '<?php echo app_lang("error_occurred"); ?>');
                        }
                    }
                });

                function fallbackCopy(text, $btn) {
                    var tempInput = document.createElement("input");
                    tempInput.style = "position: absolute; left: -1000px; top: -1000px";
                    tempInput.value = text;
                    document.body.appendChild(tempInput);
                    tempInput.select();
                    document.execCommand("copy");
                    document.body.removeChild(tempInput);
                }
            });

        });
    </script>