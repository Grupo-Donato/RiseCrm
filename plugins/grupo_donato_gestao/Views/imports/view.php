<?php
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$status = (string) $batch->status;
$targetUri = [
    "customer_account" => "grupo_donato/customers/view/", "person" => "grupo_donato/people/view/",
    "court_rental" => "grupo_donato/court-rentals/view/", "booking_series" => "grupo_donato/booking-series/view/",
    "receivable" => "grupo_donato/finance/receivables/view/",
];
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($batch->batch_number . " — " . $batch->original_filename); ?> <span class="badge bg-secondary"><?php echo app_lang("gd_import_status_" . $status); ?></span></h4>
        <div class="title-button-group">
            <?php if ($can_manage && in_array($status, ["partially_imported", "validated"], true)) { ?><button id="gd-import-reprocess" class="btn btn-warning"><?php echo app_lang("gd_import_reprocess"); ?></button><?php } ?>
            <?php echo anchor(get_uri("grupo_donato/imports"), app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>
    <div class="row"><div class="col-md-7">
        <div class="card"><div class="card-body">
            <div class="row">
                <div class="col-md-3"><strong><?php echo app_lang("gd_import_select_type"); ?></strong><br><?php echo app_lang("gd_import_type_" . $batch->import_type); ?></div>
                <div class="col-md-3"><strong><?php echo app_lang("gd_import_row_count"); ?></strong><br><?php echo (int) $batch->row_count; ?></div>
                <div class="col-md-3"><strong><?php echo app_lang("gd_import_imported"); ?></strong><br><?php echo (int) $batch->imported_count; ?></div>
                <div class="col-md-3"><strong><?php echo app_lang("gd_import_issues"); ?></strong><br><?php echo (int) $batch->issue_count; ?></div>
            </div>
            <hr>
            <strong><?php echo app_lang("gd_import_pending_review"); ?>:</strong>
            <?php foreach (($batch->row_status ?? []) as $st => $count) { echo " " . app_lang("gd_import_row_status_" . $st) . " (" . (int) $count . ")"; } ?>
        </div></div>
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_import_issues"); ?></h4></div><div class="card-body table-responsive">
            <table class="table table-sm"><thead><tr><th>#</th><th><?php echo app_lang("gd_status"); ?></th><th><?php echo app_lang("gd_reason"); ?></th></tr></thead><tbody>
            <?php foreach ($batch->issues as $issue) { ?><tr><td><?php echo (int) $issue->row_number; ?></td><td><span class="badge bg-<?php echo $issue->severity === "error" ? "danger" : ($issue->severity === "warning" ? "warning" : "info"); ?>"><?php echo $e($issue->issue_type); ?></span></td><td><?php echo $e($issue->message ? app_lang($issue->message) : ""); ?></td></tr><?php } ?>
            <?php if (!$batch->issues) { ?><tr><td colspan="3" class="text-muted">—</td></tr><?php } ?>
            </tbody></table>
        </div></div>
    </div><div class="col-md-5">
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_import_links"); ?></h4></div><div class="card-body table-responsive">
            <table class="table table-sm"><thead><tr><th>#</th><th><?php echo app_lang("gd_import_target"); ?></th></tr></thead><tbody>
            <?php foreach ($batch->links as $link) { $uri = $targetUri[$link->target_type] ?? ""; ?><tr><td><?php echo (int) $link->row_number; ?></td><td><?php echo app_lang("gd_import_target_" . $link->target_type) !== "gd_import_target_" . $link->target_type ? app_lang("gd_import_target_" . $link->target_type) : $e($link->target_type); ?> #<?php echo $uri ? anchor(get_uri($uri . $link->target_id), (int) $link->target_id) : (int) $link->target_id; ?></td></tr><?php } ?>
            <?php if (!$batch->links) { ?><tr><td colspan="2" class="text-muted">—</td></tr><?php } ?>
            </tbody></table>
        </div></div>
    </div></div>
</div>
<script>
$(document).ready(function(){
    $("#gd-import-reprocess").on("click",function(){
        appLoader.show();
        $.post('<?php echo_uri("grupo_donato/imports/reprocess"); ?>',{id:<?php echo (int) $batch->id; ?>}).done(function(r){
            appLoader.hide();
            if(r.success){location.reload();}else{appAlert.error(r.message);}
        });
    });
});
</script>
