<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'subscriptions']); ?>
<div class="card">
    <div class="page-title clearfix">
        <h1><?php echo app_lang('gdc_subscriptions'); ?></h1>
        <div class="title-button-group">
            <?php if ($can_manage): ?>
                <?php echo modal_anchor(get_uri('cobranca/subscriptions/modal'), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang('gdc_new_subscription'), ['class' => 'btn btn-primary', 'title' => app_lang('gdc_new_subscription')]); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table id="gdc-subscriptions-table" class="display" width="100%"></table>
    </div>
</div>
<script>
$(document).ready(function(){"use strict";
    $("#gdc-subscriptions-table").appTable({
        source:"<?php echo_uri('cobranca/subscriptions/data'); ?>",
        columns:[
            {title:"<?php echo app_lang('gdc_customer'); ?>",data:"customer"},
            {title:"<?php echo app_lang('gdc_source'); ?>",data:"source"},
            {title:"<?php echo app_lang('gdc_method'); ?>",data:"method"},
            {title:"<?php echo app_lang('gdc_card'); ?>",data:"card"},
            {title:"<?php echo app_lang('gdc_charge_day'); ?>",data:"day",class:"text-center"},
            {title:"<?php echo app_lang('gdc_status'); ?>",data:"status"},
            {title:"<?php echo app_lang('gdc_last_charge'); ?>",data:"last_charge"},
            {title:"",data:"options",class:"text-center option w100"}
        ]
    });
});
</script>
