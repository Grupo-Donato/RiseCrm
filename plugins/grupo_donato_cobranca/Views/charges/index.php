<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'charges']); ?>
<div class="card">
    <div class="page-title clearfix"><h1><?php echo app_lang('gdc_charges'); ?></h1>
        <div class="title-button-group"><?php if ($can_manage) echo modal_anchor(get_uri('cobranca/charges/modal'), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang('gdc_new_charge'), ['class' => 'btn btn-primary', 'title' => app_lang('gdc_new_charge')]); ?></div>
    </div>
    <div class="table-responsive"><table id="gdc-charges-table" class="display" width="100%"></table></div>
</div>
<script>
$(document).ready(function(){"use strict";
    $("#gdc-charges-table").appTable({
        source:"<?php echo_uri('cobranca/charges/data'); ?>",
        columns:[
            {title:"<?php echo app_lang('gdc_charge'); ?>",data:"charge"},
            {title:"<?php echo app_lang('gdc_receivable'); ?>",data:"receivable"},
            {title:"<?php echo app_lang('gdc_customer'); ?>",data:"customer"},
            {title:"<?php echo app_lang('gdc_method'); ?>",data:"method"},
            {title:"<?php echo app_lang('gdc_amount'); ?>",data:"amount"},
            {title:"<?php echo app_lang('gdc_due_date'); ?>",data:"due"},
            {title:"<?php echo app_lang('gdc_status'); ?>",data:"status"},
            {title:"<?php echo app_lang('gdc_external_id'); ?>",data:"external"},
            {title:"",data:"options",class:"text-center option w75"}
        ]
    });
});
</script>
