<?php $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
<?php echo form_open(get_uri("grupo_donato/court-rentals/link-existing"), ["id" => "gd-cr-link-form", "class" => "general-form", "role" => "form"]); ?>
<input type="hidden" name="id" value="<?php echo (int) $rental->id; ?>">
<div class="modal-body clearfix"><div class="container-fluid">
    <p class="text-muted"><?php echo app_lang("gd_link_existing"); ?> — <?php echo $e($rental->rental_number); ?></p>
    <div class="form-group"><div class="row"><label class="col-md-4"><?php echo app_lang("gd_booking"); ?> (ID)</label><div class="col-md-8"><input type="number" name="booking_id" class="form-control" placeholder="ID"></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-4"><?php echo app_lang("gd_series"); ?> (ID)</label><div class="col-md-8"><input type="number" name="booking_series_id" class="form-control" placeholder="ID"></div></div></div>
    <div class="form-group"><div class="row"><label class="col-md-4"><?php echo app_lang("gd_link_kind"); ?></label><div class="col-md-8"><select name="link_kind" class="form-control"><?php foreach ($link_kinds as $k) { ?><option value="<?php echo $e($k); ?>"><?php echo app_lang("gd_court_rental_link_kind_" . $k); ?></option><?php } ?></select></div></div></div>
    <small class="text-muted"><?php echo app_lang("gd_court_rental_link_target_required"); ?></small>
</div></div>
<div class="modal-footer"><button type="button" class="btn btn-default" data-bs-dismiss="modal"><?php echo app_lang("close"); ?></button><button type="submit" class="btn btn-primary"><?php echo app_lang("save"); ?></button></div>
<?php echo form_close(); ?>
<script>$(document).ready(function(){$("#gd-cr-link-form").appForm({onSuccess:function(r){location.reload();}});});</script>
