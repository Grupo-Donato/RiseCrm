<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'dashboard']); ?>
<div class="row">
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted"><?php echo app_lang('gdc_pending_charges'); ?></div><h2><?php echo (int) $metrics['pending']; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted"><?php echo app_lang('gdc_paid_this_month'); ?></div><h2><?php echo to_currency((float) $metrics['paid_month']); ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted"><?php echo app_lang('gdc_failed_review'); ?></div><h2><?php echo (int) $metrics['failed']; ?></h2></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted"><?php echo app_lang('gdc_active_subscriptions'); ?></div><h2><?php echo (int) $metrics['subscriptions']; ?></h2></div></div></div>
</div>
<div class="card mt-3">
    <div class="page-title clearfix"><h1><?php echo app_lang('gdc_app_title'); ?></h1>
        <div class="title-button-group">
            <?php if ($can_manage): ?>
                <?php echo modal_anchor(get_uri('cobranca/charges/modal'), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang('gdc_new_charge'), ['class' => 'btn btn-primary', 'title' => app_lang('gdc_new_charge')]); ?>
                <?php echo modal_anchor(get_uri('cobranca/subscriptions/modal'), '<i data-feather="repeat" class="icon-16"></i> ' . app_lang('gdc_new_subscription'), ['class' => 'btn btn-default', 'title' => app_lang('gdc_new_subscription')]); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (!$metrics['connector_configured']): ?>
            <div class="alert alert-warning mb-0"><?php echo app_lang('gdc_connector_pending_message'); ?></div>
        <?php else: ?>
            <div class="alert alert-info mb-0"><?php echo app_lang('gdc_dashboard_explanation'); ?></div>
        <?php endif; ?>
    </div>
</div>
