<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$id = (int) ($model_info->id ?? 0);
$select = static function (string $name, array $options, string $selected) use ($e): string {
    $html = '<select name="' . $e($name) . '" class="form-control">';
    foreach ($options as $option) {
        $is_selected = (string) $option["id"] === $selected ? " selected" : "";
        $html .= '<option value="' . $e($option["id"]) . '"' . $is_selected . '>' . $e($option["text"]) . '</option>';
    }
    return $html . '</select>';
};
?>
<?php echo form_open(get_uri("grupo_donato/customers/save"), ["id" => "gd-customer-account-form", "class" => "general-form", "role" => "form"]); ?>
<div class="modal-body clearfix"><div class="container-fluid">
    <input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="duplicate_override" value="0">
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_display_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "display_name", "value" => $model_info->display_name ?? "", "class" => "form-control", "maxlength" => 190, "data-rule-required" => true, "data-msg-required" => app_lang("field_required")]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_account_type"); ?></label><div class="col-md-9"><?php echo $select("account_type", $types, (string) ($model_info->account_type ?? "individual")); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_legal_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "legal_name", "value" => $model_info->legal_name ?? "", "class" => "form-control", "maxlength" => 190]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_trade_name"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "trade_name", "value" => $model_info->trade_name ?? "", "class" => "form-control", "maxlength" => 190]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_document"); ?></label><div class="col-md-3"><?php echo $select("document_type", $document_types, (string) ($model_info->document_type ?? "none")); ?></div><div class="col-md-6"><?php echo form_input(["name" => "document_number", "value" => $model_info->document_number ?? "", "class" => "form-control", "maxlength" => 40]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("email"); ?></label><div class="col-md-9"><?php echo form_input(["type" => "email", "name" => "email", "value" => $model_info->email ?? "", "class" => "form-control", "maxlength" => 190]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("phone"); ?></label><div class="col-md-4"><?php echo form_input(["name" => "phone", "value" => $model_info->phone ?? "", "class" => "form-control", "maxlength" => 40]); ?></div><label class="col-md-2"><?php echo app_lang("gd_whatsapp"); ?></label><div class="col-md-3"><?php echo form_input(["name" => "whatsapp", "value" => $model_info->whatsapp ?? "", "class" => "form-control", "maxlength" => 40]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_status"); ?></label><div class="col-md-9"><?php echo $select("status", $statuses, (string) ($model_info->status ?? "active")); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("gd_rise_client_id"); ?></label><div class="col-md-9"><?php echo form_input(["type" => "number", "name" => "rise_client_id", "value" => $model_info->rise_client_id ?? "", "class" => "form-control", "min" => 1]); ?></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-3"><?php echo app_lang("note"); ?></label><div class="col-md-9"><?php echo form_textarea(["name" => "notes", "value" => $model_info->notes ?? "", "class" => "form-control", "rows" => 3]); ?></div></div></div>
    <?php if ($id) { ?><div class="form-group"><div class="row"><label class="col-md-3 text-danger"><?php echo app_lang("gd_delete_reason"); ?></label><div class="col-md-9"><?php echo form_input(["name" => "reason", "class" => "form-control", "maxlength" => 500]); ?></div></div></div><?php } ?>
</div></div>
<div class="modal-footer">
    <?php if ($id) { ?><button type="button" id="gd-delete-customer-account" class="btn btn-danger me-auto"><i data-feather="trash-2" class="icon-16"></i> <?php echo app_lang("delete"); ?></button><?php } ?>
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button>
</div><?php echo form_close(); ?>
<script type="text/javascript">
$(document).ready(function () {
    var form = $("#gd-customer-account-form");
    form.appForm({
        onSuccess: function (result) { if ($("#gd-customer-accounts-table").length) { $("#gd-customer-accounts-table").appTable({newData: result.data, dataId: result.id}); } else { location.reload(); } },
        onError: function (result) { if (result.duplicate_confirmation_required && window.confirm('<?php echo addslashes(app_lang("gd_confirm_duplicate_override")); ?>')) { form.find("[name=duplicate_override]").val("1"); setTimeout(function () { form.trigger("submit"); }, 100); } return true; }
    });
    $("#gd-delete-customer-account").click(function () { var reason = form.find("[name=reason]").val(); if (!reason) { appAlert.error('<?php echo addslashes(app_lang("gd_delete_reason_required")); ?>', {container: ".modal-body", animate: false}); return; } $.post('<?php echo_uri("grupo_donato/customers/delete"); ?>', form.serialize(), function (result) { if (result.success) { location.href = '<?php echo_uri("grupo_donato/customers"); ?>'; } else { appAlert.error(result.message, {container: ".modal-body", animate: false}); } }, "json"); });
});
</script>
