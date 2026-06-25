<?php
$pretty = function ($json) {
    if (!$json) {
        return "<span class='text-muted'>-</span>";
    }
    $decoded = json_decode((string) $json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "<pre class='bg-light p-2'>" . htmlspecialchars((string) $json) . "</pre>";
    }
    return "<pre class='bg-light p-2'>" . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . "</pre>";
};
?>
<div class="modal-body clearfix">
    <div class="container-fluid">
        <table class="table table-bordered">
            <tr><th class="w150"><?php echo app_lang("id"); ?></th><td><?php echo (int) $model_info->id; ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_when"); ?></th><td><?php echo $model_info->created_at ? format_to_datetime($model_info->created_at) : ""; ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_action"); ?></th><td><?php echo htmlspecialchars((string) $model_info->action); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_entity"); ?></th><td><?php echo htmlspecialchars((string) $model_info->entity_type) . ($model_info->entity_id ? " #" . $model_info->entity_id : ""); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_actor"); ?></th><td><?php echo htmlspecialchars((string) $model_info->actor_type) . ($model_info->actor_id ? " #" . $model_info->actor_id : ""); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_request"); ?></th><td><?php echo htmlspecialchars((string) $model_info->request_id); ?></td></tr>
            <tr><th>IP</th><td><?php echo htmlspecialchars((string) $model_info->ip_address); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_before"); ?></th><td><?php echo $pretty($model_info->before_data); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_after"); ?></th><td><?php echo $pretty($model_info->after_data); ?></td></tr>
            <tr><th><?php echo app_lang("gd_audit_metadata"); ?></th><td><?php echo $pretty($model_info->metadata); ?></td></tr>
        </table>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
</div>
