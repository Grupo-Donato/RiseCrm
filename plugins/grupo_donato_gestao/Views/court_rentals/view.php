<?php
if(!empty($financial)) include dirname(__DIR__).'/finance/context_summary.php';
if(!empty($can_generate_receivable)&&($rental->rental_type??'')==='single'&&($rental->negotiated_amount??null)!==null){echo '<div class="card"><div class="card-body">'.form_open(get_uri('grupo_donato/finance/generate-rental'),['class'=>'general-form']).'<input type="hidden" name="rental_id" value="'.(int)$rental->id.'"><div class="row"><div class="col-md-4"><input name="amount" class="form-control" value="'.esc($rental->negotiated_amount).'"></div><div class="col-md-4"><input type="date" name="due_date" class="form-control" value="'.date('Y-m-d').'"></div><div class="col-md-4"><button class="btn btn-primary" type="submit">'.app_lang('gd_finance_new_receivable').'</button></div></div>'.form_close().'</div></div>';}
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$money = static fn($v) => $v === null ? "-" : ($e($rental->currency . " " . $v));
$status = (string) $rental->status;
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($rental->rental_number . " — " . $rental->title); ?> <span class="badge bg-secondary"><?php echo app_lang("gd_court_rental_status_" . $status); ?></span></h4>
        <div class="title-button-group"><?php echo anchor(get_uri("grupo_donato/court-rentals"), app_lang("back"), ["class" => "btn btn-default"]); ?></div>
    </div>
    <div class="row"><div class="col-md-7">
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_court_rental"); ?></h4></div><div class="card-body">
            <div class="row"><div class="col-md-4"><strong><?php echo app_lang("gd_rental_type"); ?></strong><br><?php echo app_lang("gd_court_rental_type_" . $rental->rental_type); ?></div><div class="col-md-4"><strong><?php echo app_lang("gd_customer"); ?></strong><br><?php echo $e($rental->customer_name ?? "-"); ?></div><div class="col-md-4"><strong><?php echo app_lang("gd_contact"); ?></strong><br><?php echo $e($rental->contact_name ?? "-"); ?></div></div><hr>
            <div class="row"><div class="col-md-4"><strong><?php echo app_lang("gd_validity"); ?></strong><br><?php echo $e(($rental->effective_from ?: "…") . " → " . ($rental->effective_until ?: "…")); ?></div><div class="col-md-4"><strong><?php echo app_lang("gd_preferred_due_day"); ?></strong><br><?php echo $rental->preferred_due_day ? (int) $rental->preferred_due_day : "-"; ?></div><div class="col-md-4"><strong><?php echo app_lang("gd_lock_version"); ?></strong><br><?php echo (int) $rental->lock_version; ?></div></div><hr>
            <strong><?php echo app_lang("gd_resources"); ?>:</strong> <?php echo $e($rental->schedule["resource_names"] ?? "-"); ?>
        </div></div>
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_commercial_terms"); ?></h4></div><div class="card-body">
            <div class="row"><div class="col-md-3"><strong><?php echo app_lang("gd_list_amount"); ?></strong><br><?php echo $money($rental->list_amount); ?></div><div class="col-md-3"><strong><?php echo app_lang("gd_negotiated_amount"); ?></strong><br><?php echo $money($rental->negotiated_amount); ?></div><div class="col-md-3"><strong><?php echo app_lang("gd_discount_amount"); ?></strong><br><?php echo $money($rental->discount_amount); ?></div><div class="col-md-3"><strong><?php echo app_lang("gd_price_difference"); ?></strong><br><?php echo $rental->price_difference !== null ? $money($rental->price_difference) : "-"; ?></div></div>
            <?php if ($rental->discount_reason) { ?><hr><strong><?php echo app_lang("gd_discount_reason"); ?>:</strong> <?php echo $e($rental->discount_reason); ?><?php } ?>
            <?php if ($rental->commercial_notes) { ?><hr><?php echo $e($rental->commercial_notes); ?><?php } ?>
            <?php if (($can_manage || $can_override) && !in_array($status, ["cancelled", "completed", "archived"], true)) { ?>
            <hr><form id="gd-cr-reprice" class="row g-2"><input type="hidden" name="id" value="<?php echo (int) $rental->id; ?>"><input type="hidden" name="lock_version" value="<?php echo (int) $rental->lock_version; ?>"><input type="hidden" name="rental_type" value="<?php echo $e($rental->rental_type); ?>"><input type="hidden" name="title" value="<?php echo $e($rental->title); ?>"><input type="hidden" name="customer_account_id" value="<?php echo (int) $rental->customer_account_id; ?>">
                <div class="col-md-3"><input name="negotiated_amount" class="form-control" placeholder="<?php echo app_lang("gd_negotiated_amount"); ?>" value="<?php echo $e($rental->negotiated_amount); ?>"></div>
                <div class="col-md-3"><input name="discount_amount" class="form-control" placeholder="<?php echo app_lang("gd_discount_amount"); ?>" value="<?php echo $e($rental->discount_amount); ?>"></div>
                <div class="col-md-4"><input name="discount_reason" class="form-control" placeholder="<?php echo app_lang("gd_discount_reason"); ?>"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-warning btn-block"><?php echo app_lang("gd_reprice"); ?></button></div>
            </form><?php } ?>
        </div></div>
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_links"); ?></h4></div><div class="card-body table-responsive"><table class="table table-sm"><tbody>
            <?php foreach ($rental->links as $link) { ?><tr><td><?php echo app_lang("gd_court_rental_link_kind_" . $link->link_kind); ?></td><td><?php if (!empty($link->booking)) { echo anchor(get_uri("grupo_donato/bookings/view/" . $link->booking->id), $e($link->booking->booking_number)) . " — " . app_lang("gd_booking_status_" . $link->booking->status); } elseif (!empty($link->series)) { echo anchor(get_uri("grupo_donato/booking-series/view/" . $link->series->id), $e($link->series->series_number)) . " — " . app_lang("gd_booking_series_status_" . $link->series->status); } ?></td></tr><?php } ?>
        </tbody></table><?php if ($can_manage && !in_array($status, ["cancelled", "completed", "archived"], true)) { echo modal_anchor(get_uri("grupo_donato/court-rentals/link-modal"), "<i data-feather='link' class='icon-16'></i> " . app_lang("gd_link_existing"), ["class" => "btn btn-default btn-sm", "data-post-id" => $rental->id]); } ?></div></div>
    </div><div class="col-md-5">
        <?php if ($can_status) { ?><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_actions"); ?></h4></div><div class="card-body">
            <?php if (in_array($status, ["active", "suspended"], true)) { ?><div class="form-group"><label><?php echo app_lang("gd_future_policy"); ?></label><select id="gd-cr-future-policy" class="form-control"><?php foreach ($future_policies as $p) { ?><option value="<?php echo $e($p); ?>"><?php echo app_lang("gd_court_rental_future_policy_" . $p); ?></option><?php } ?></select></div><?php } ?>
            <div id="gd-cr-actions">
                <?php if ($status === "draft") { ?><button data-action="activate" class="btn btn-success mr5"><?php echo app_lang("gd_activate"); ?></button><button data-action="cancel" data-reason="1" data-policy="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button><?php } ?>
                <?php if ($status === "active") { ?><button data-action="suspend" data-policy="1" class="btn btn-warning mr5"><?php echo app_lang("gd_suspend"); ?></button><button data-action="complete" class="btn btn-secondary mr5"><?php echo app_lang("gd_complete"); ?></button><button data-action="cancel" data-reason="1" data-policy="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button><?php } ?>
                <?php if ($status === "suspended") { ?><button data-action="resume" class="btn btn-success mr5"><?php echo app_lang("gd_resume"); ?></button><button data-action="cancel" data-reason="1" data-policy="1" class="btn btn-danger"><?php echo app_lang("gd_cancel_rental"); ?></button><?php } ?>
            </div>
        </div></div><?php } ?>
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_events"); ?></h4></div><div class="card-body"><?php foreach ($rental->events as $event) { ?><div class="mb10"><strong><?php echo app_lang("gd_court_rental_event_" . $event->event_type); ?></strong> <small class="text-muted"><?php echo $e($event->created_at . " UTC"); ?></small><?php if ($event->reason) { ?><br><small><?php echo $e($event->reason); ?></small><?php } ?></div><?php } ?></div></div>
    </div></div>
</div>
<script>
$(document).ready(function(){
    function post(url,data){$.post(url,data).done(function(response){if(response.success){location.reload();}else{appAlert.error(response.message);}});}
    $("#gd-cr-actions button").on("click",function(){var button=$(this),data={lock_version:<?php echo (int) $rental->lock_version; ?>};if(button.data("policy")){data.future_policy=$("#gd-cr-future-policy").val();}if(button.data("reason")){var r=window.prompt('<?php echo addslashes(app_lang("gd_reason")); ?>','');if(!r){return;}data.reason=r;}post('<?php echo_uri("grupo_donato/court-rentals/" . (int) $rental->id . "/"); ?>'+button.data("action"),data);});
    $("#gd-cr-reprice").on("submit",function(ev){ev.preventDefault();post('<?php echo_uri("grupo_donato/court-rentals/reprice"); ?>',$(this).serialize());});
});
</script>
