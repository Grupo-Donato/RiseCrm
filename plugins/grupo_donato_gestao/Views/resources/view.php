<?php
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
$metadata_pretty = "";
if (!empty($resource->metadata)) {
    $decoded = json_decode((string) $resource->metadata, true);
    $metadata_pretty = is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $resource->metadata;
}
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="page-title clearfix">
        <h4><?php echo $e($resource->code . " — " . $resource->name); ?></h4>
        <div class="title-button-group">
            <?php if ($can_manage) echo modal_anchor(get_uri("grupo_donato/resources/modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang("edit"), ["class" => "btn btn-default", "data-post-id" => $resource->id]); ?>
            <?php echo anchor(get_uri("grupo_donato/resources"), app_lang("back"), ["class" => "btn btn-default"]); ?>
        </div>
    </div>
    <div class="row"><div class="col-md-7"><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_main_data"); ?></h4></div><div class="card-body">
        <div class="row">
            <div class="col-md-4"><strong><?php echo app_lang("gd_resource_type"); ?></strong><br><?php echo app_lang("gd_resource_type_" . $resource->resource_type); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_capacity"); ?></strong><br><?php echo $resource->capacity !== null ? (int) $resource->capacity : "-"; ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_bookable"); ?></strong><br><?php echo $resource->is_bookable ? app_lang("yes") : app_lang("no"); ?></div>
        </div><hr>
        <div class="row">
            <div class="col-md-4"><strong><?php echo app_lang("gd_business_area"); ?></strong><br><?php echo $e($resource->business_area_name ?? "-"); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_cost_center"); ?></strong><br><?php echo $e($resource->cost_center_name ?? "-"); ?></div>
            <div class="col-md-4"><strong><?php echo app_lang("gd_status"); ?></strong><br><?php echo $resource->is_active ? app_lang("gd_status_active") : app_lang("gd_status_inactive"); ?></div>
        </div>
        <?php if (!empty($resource->description)) { ?><hr><strong><?php echo app_lang("gd_description"); ?></strong><br><?php echo nl2br($e($resource->description)); ?><?php } ?>
        <?php if ($metadata_pretty !== "") { ?><hr><strong><?php echo app_lang("gd_metadata"); ?></strong><br><pre class="mb0"><?php echo $e($metadata_pretty); ?></pre><?php } ?>
    </div></div></div><div class="col-md-5">
        <?php if ($can_calendar) { ?><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_availability_and_calendar"); ?></h4></div><div class="card-body"><p class="text-muted"><?php echo app_lang("gd_unit_timezone").": ".$e($timezone); ?></p>
            <?php echo anchor(get_uri("grupo_donato/resources/availability/".$resource->id),app_lang("gd_weekly_availability"),["class"=>"btn btn-default btn-sm mr5"]); ?>
            <?php echo anchor(get_uri("grupo_donato/resources/exceptions/".$resource->id),app_lang("gd_availability_exceptions"),["class"=>"btn btn-default btn-sm mr5"]); ?>
            <?php echo anchor(get_uri("grupo_donato/resources/blocks/".$resource->id),app_lang("gd_resource_blocks"),["class"=>"btn btn-default btn-sm mr5"]); ?>
            <?php echo anchor(get_uri("grupo_donato/calendar"),app_lang("gd_menu_calendar"),["class"=>"btn btn-default btn-sm"]); ?>
        </div></div><?php } ?>
        <div class="card"><div class="card-header"><h4><?php echo app_lang("gd_resource_specific_prices"); ?></h4></div><div class="card-body">
            <?php if (!$prices) { ?><span class="text-muted"><?php echo app_lang("gd_no_prices"); ?></span><?php } else { foreach ($prices as $price) { ?>
                <div class="mb10"><strong><?php echo $e(to_currency((float) $price->amount)); ?></strong> — <?php echo $e($price->product_name); ?><br><small><?php echo app_lang("gd_min_quantity"); ?>: <?php echo $e(rtrim(rtrim((string) $price->minimum_quantity, "0"), ".")); ?></small></div>
            <?php }} ?>
        </div></div>
        <?php if ($can_audit) { ?><div class="card"><div class="card-header"><h4><?php echo app_lang("gd_recent_audit"); ?></h4></div><div class="card-body"><?php if (!$audits) echo '<span class="text-muted">' . app_lang("gd_no_audit_events") . '</span>'; foreach ($audits as $event) { ?><div class="mb10"><strong><?php echo $e($event->action); ?></strong><br><small><?php echo $event->created_at ? format_to_datetime($event->created_at) : ""; ?></small></div><?php } ?></div></div><?php } ?>
    </div></div>
</div>
