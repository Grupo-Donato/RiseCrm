<?php
$resource_options = [["id" => "", "text" => "-"]];
foreach ($resources as $resource) { $resource_options[] = ["id" => (string) $resource["id"], "text" => $resource["code"] . " — " . $resource["name"]]; }
$status_options = [["id" => "", "text" => "-"]];
foreach ($statuses as $status) { $status_options[] = ["id" => $status, "text" => app_lang("gd_court_rental_status_" . $status)]; }
$weekday_options = [["id" => "", "text" => "-"]];
for ($d = 1; $d <= 7; $d++) { $weekday_options[] = ["id" => (string) $d, "text" => app_lang("gd_weekday_short_" . $d)]; }
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_monthly_renters"); ?> <small class="text-muted">(<?php echo app_lang("gd_unit_timezone") . ": " . htmlspecialchars((string) $timezone, ENT_QUOTES, "UTF-8"); ?>)</small></h4>
            <div class="title-button-group">
                <?php echo anchor(get_uri("grupo_donato/court-rentals"), "<i data-feather='arrow-left' class='icon-16'></i> " . app_lang("gd_court_rentals"), ["class" => "btn btn-default"]); ?>
                <?php if ($can_manage) { echo modal_anchor(get_uri("grupo_donato/court-rentals/monthly-modal"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_new_court_rental_monthly"), ["class" => "btn btn-default"]); } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-court-renters-table" class="display" cellspacing="0" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-court-renters-table").appTable({
        source:'<?php echo_uri("grupo_donato/court-rentals/monthly-data"); ?>', serverSide:true, order:[[0,"asc"]],
        filterDropdown:[
            {name:"resource_id",class:"w200",options:<?php echo json_encode($resource_options); ?>},
            {name:"weekday",class:"w120",options:<?php echo json_encode($weekday_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>}
        ],
        rangeDatepicker:[{startDate:{name:"date_from",value:""},endDate:{name:"date_to",value:""},showClearButton:true}],
        columns:[
            {title:'<?php echo app_lang("gd_customer"); ?>',class:"all"},
            {title:'<?php echo app_lang("gd_contact"); ?>'},
            {title:'<?php echo app_lang("gd_resources"); ?>'},
            {title:'<?php echo app_lang("gd_weekdays"); ?>'},
            {title:'<?php echo app_lang("gd_local_time"); ?>'},
            {title:'<?php echo app_lang("gd_contracted_amount"); ?>'},
            {title:'<?php echo app_lang("gd_due_day"); ?>'},
            {title:'<?php echo app_lang("gd_status"); ?>'},
            {title:'<?php echo app_lang("gd_next_occurrence"); ?>'},
            {title:'<?php echo app_lang("gd_finance_situation"); ?>'},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w120"}
        ]
    });
    $(document).on('click','.gd-cr-act',function(e){e.preventDefault();var b=$(this);var data={lock_version:b.data('lock')};if(b.data('action')==='suspend'){data.future_policy='keep';}$.post('<?php echo_uri("grupo_donato/court-rentals/"); ?>'+b.data('id')+'/'+b.data('action'),data).done(function(r){if(r.success){location.reload();}else{appAlert.error(r.message);}});});
});
</script>
