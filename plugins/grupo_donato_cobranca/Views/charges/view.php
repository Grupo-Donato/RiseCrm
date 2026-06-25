<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'charges']); ?>
<div class="card">
    <div class="page-title clearfix"><h1><?php echo app_lang('gdc_charge'); ?> <?php echo esc(substr($charge->charge_uuid, 0, 8)); ?></h1>
        <div class="title-button-group">
            <?php if ($can_manage && !empty($charge->external_charge_id)): ?>
                <?php echo ajax_anchor(get_uri('cobranca/charges/sync'), '<i data-feather="refresh-cw" class="icon-16"></i> ' . app_lang('gdc_sync'), ['class' => 'btn btn-default', 'data-post-id' => $charge->id, 'data-reload-on-success' => 1]); ?>
            <?php endif; ?>
            <?php if ($can_manage && !in_array($charge->status, ['paid','cancelled','refunded'], true)): ?>
                <?php echo ajax_anchor(get_uri('cobranca/charges/cancel'), '<i data-feather="x-circle" class="icon-16"></i> ' . app_lang('gdc_cancel_charge'), ['class' => 'btn btn-danger', 'data-post-id' => $charge->id, 'data-reload-on-success' => 1]); ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong><?php echo app_lang('gdc_status'); ?></strong><br><?php echo app_lang('gdc_status_' . $charge->status); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang('gdc_method'); ?></strong><br><?php echo app_lang('gdc_method_' . $charge->collection_method); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang('gdc_amount'); ?></strong><br><?php echo esc($charge->amount); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang('gdc_paid_amount'); ?></strong><br><?php echo esc($charge->paid_amount); ?></div>
        </div>
        <div class="row mt-3">
            <div class="col-md-4"><strong><?php echo app_lang('gdc_external_id'); ?></strong><br><?php echo esc($charge->external_charge_id ?: '-'); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang('gdc_due_date'); ?></strong><br><?php echo format_to_date($charge->due_date, false); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang('gdc_finance_payment'); ?></strong><br><?php echo $charge->gd_payment_id ? '#' . (int) $charge->gd_payment_id : '-'; ?></div>
        </div>
        <?php if ($charge->collection_method === 'pix' && $charge->pix_copy_paste): ?>
            <hr><h4><?php echo app_lang('gdc_pix'); ?></h4>
            <?php if ($charge->pix_qr_code_url): ?><img src="<?php echo esc($charge->pix_qr_code_url); ?>" alt="QR Code PIX" style="max-width:220px" class="mb-3"><?php endif; ?>
            <div class="input-group"><input id="gdc-pix-code" class="form-control" readonly value="<?php echo esc($charge->pix_copy_paste); ?>"><button type="button" id="gdc-copy-pix" class="btn btn-default"><?php echo app_lang('gdc_copy_pix'); ?></button></div>
        <?php endif; ?>
        <?php if ($charge->last_error_message): ?><div class="alert alert-danger mt-3"><?php echo esc($charge->last_error_message); ?></div><?php endif; ?>
        <hr><h4><?php echo app_lang('gdc_timeline'); ?></h4>
        <table class="table"><thead><tr><th><?php echo app_lang('gdc_date'); ?></th><th><?php echo app_lang('gdc_event'); ?></th><th><?php echo app_lang('gdc_transition'); ?></th><th><?php echo app_lang('gdc_message'); ?></th></tr></thead><tbody>
        <?php foreach ($charge->events as $event): ?><tr><td><?php echo esc($event->occurred_at ?: $event->created_at); ?></td><td><?php echo esc($event->event_type); ?></td><td><?php echo esc(($event->status_before ?: '-') . ' → ' . ($event->status_after ?: '-')); ?></td><td><?php echo esc($event->message ?: '-'); ?></td></tr><?php endforeach; ?>
        </tbody></table>
    </div>
</div>
<script>$(document).on("click","#gdc-copy-pix",function(){navigator.clipboard.writeText($("#gdc-pix-code").val());});</script>
