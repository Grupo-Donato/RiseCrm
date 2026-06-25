<div class="estimate-request-alert">
    <div class="alert alert-primary" role="alert">
        <?php echo $estimate_request_count == 1 ? app_lang("estimate_request_pending_alert_1") : sprintf(app_lang("estimate_request_pending_alert_2"), $estimate_request_count); ?>
        <a href="javascript:;" data-target="#lead-estimate-requests" class="lead-overview-widget-link text-default">
            <?php echo app_lang("view"); ?>
        </a>
    </div>
</div>