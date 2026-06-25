<?php
if(!empty($financial)) include dirname(__DIR__).'/finance/context_summary.php';
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$maskPhone = static fn($value) => \grupo_donato_gestao\Services\DataPrivacyService::maskPhone($value);
$maskEmail = static fn($value) => \grupo_donato_gestao\Services\DataPrivacyService::maskEmail($value);
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($account->display_name); ?></h4>
        <div class="title-button-group">
            <?php if ($can_manage) echo modal_anchor(get_uri("grupo_donato/customers/modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang("edit"), ["class" => "btn btn-default", "title" => app_lang("edit"), "data-post-id" => $account->id]); ?>
            <?php echo anchor(get_uri("grupo_donato/customers"), app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-7"><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_main_data"); ?></h4></div><div class="card-body">
            <div class="row"><div class="col-md-6"><strong><?php echo app_lang("gd_account_type"); ?></strong><br><?php echo app_lang("gd_account_type_" . $account->account_type); ?></div><div class="col-md-6"><strong><?php echo app_lang("gd_status"); ?></strong><br><?php echo app_lang("gd_status_" . $account->status); ?></div></div><hr>
            <div class="row"><div class="col-md-6"><strong><?php echo app_lang("gd_legal_name"); ?></strong><br><?php echo $e($account->legal_name); ?></div><div class="col-md-6"><strong><?php echo app_lang("gd_trade_name"); ?></strong><br><?php echo $e($account->trade_name); ?></div></div><hr>
            <div class="row"><div class="col-md-4"><strong><?php echo app_lang("gd_document"); ?></strong><br><?php echo $e($document_masked); ?></div><div class="col-md-4"><strong><?php echo app_lang("email"); ?></strong><br><?php echo $e($maskEmail($account->email)); ?></div><div class="col-md-4"><strong><?php echo app_lang("phone"); ?></strong><br><?php echo $e($maskPhone($account->phone ?: $account->whatsapp)); ?></div></div><hr>
            <strong><?php echo app_lang("gd_rise_client_id"); ?></strong><br><?php echo $account->rise_client_id ? (int) $account->rise_client_id : app_lang("gd_not_linked"); ?><hr>
            <strong><?php echo app_lang("note"); ?></strong><br><?php echo nl2br($e($account->notes)); ?>
        </div></div></div>
        <div class="col-md-5">
            <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_possible_duplicates"); ?></h4></div><div class="card-body">
                <?php if (!$duplicates) { ?><span class="text-muted"><?php echo app_lang("gd_no_possible_duplicates"); ?></span><?php } else { foreach ($duplicates as $duplicate) { ?>
                    <div class="mb10"><strong><?php echo $e($duplicate["display_summary"]); ?></strong> — <?php echo app_lang("gd_confidence_" . $duplicate["confidence"]); ?><br><small><?php echo $e(implode(", ", $duplicate["matched_fields"])); ?></small></div>
                <?php }} ?>
            </div></div>
            <?php if ($can_audit) { ?><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_recent_audit"); ?></h4></div><div class="card-body">
                <?php if (!$audits) echo '<span class="text-muted">' . app_lang("gd_no_audit_events") . '</span>'; foreach ($audits as $event) { ?><div class="mb10"><strong><?php echo $e($event->action); ?></strong><br><small><?php echo $event->created_at ? format_to_datetime($event->created_at) : ""; ?></small></div><?php } ?>
            </div></div><?php } ?>
        </div>
    </div>
    <div class="card"><div class="page-title clearfix"><h4><?php echo app_lang("gd_linked_people"); ?></h4><?php if ($can_relations) { ?><div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/account-people/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_relation"), ["class" => "btn btn-default", "title" => app_lang("gd_add_relation"), "data-post-account_id" => $account->id]); ?></div><?php } ?></div><div class="table-responsive"><table id="gd-account-people-table" class="display" width="100%"></table></div></div>
    <div class="card"><div class="page-title clearfix"><h4><?php echo app_lang("gd_addresses"); ?></h4><?php if ($can_addresses) { ?><div class="title-button-group"><?php echo modal_anchor(get_uri("grupo_donato/addresses/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_address"), ["class" => "btn btn-default", "title" => app_lang("gd_add_address"), "data-post-account_id" => $account->id]); ?></div><?php } ?></div><div class="table-responsive"><table id="gd-addresses-table" class="display" width="100%"></table></div></div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-account-people-table").appTable({source: '<?php echo_uri("grupo_donato/account-people/list_data"); ?>', serverSide: true, filterParams: {account_id: "<?php echo (int) $account->id; ?>"}, columns: [
        {title: '<?php echo app_lang("gd_person"); ?>'}, {title: '<?php echo app_lang("gd_role"); ?>'}, {title: '<?php echo app_lang("gd_primary"); ?>'}, {title: '<?php echo app_lang("gd_financial_responsible"); ?>'}, {title: '<?php echo app_lang("gd_status"); ?>'}, {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w80"}
    ]});
    $("#gd-addresses-table").appTable({source: '<?php echo_uri("grupo_donato/addresses/list_data"); ?>', serverSide: true, filterParams: {account_id: "<?php echo (int) $account->id; ?>"}, columns: [
        {title: '<?php echo app_lang("gd_address_type"); ?>'}, {title: '<?php echo app_lang("gd_address"); ?>'}, {title: '<?php echo app_lang("gd_postal_code"); ?>'}, {title: '<?php echo app_lang("gd_primary"); ?>'}, {title: '<?php echo app_lang("gd_status"); ?>'}, {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w80"}
    ]});
});
</script>
