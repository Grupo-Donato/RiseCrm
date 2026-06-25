<?php
$type_options = [["id" => "", "text" => "-"]];
foreach ($types as $t) { $type_options[] = ["id" => $t, "text" => app_lang("gd_import_type_" . $t)]; }
$status_options = [["id" => "", "text" => "-"]];
foreach ($statuses as $s) { $status_options[] = ["id" => $s, "text" => app_lang("gd_import_status_" . $s)]; }
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_imports"); ?></h4>
            <div class="title-button-group">
                <?php if ($can_manage) { echo anchor(get_uri("grupo_donato/imports/new"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_import_new"), ["class" => "btn btn-default"]); } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-imports-table" class="display" cellspacing="0" width="100%"></table></div>
    </div>
</div>
<script>
$(document).ready(function(){
    $("#gd-imports-table").appTable({
        source:'<?php echo_uri("grupo_donato/imports/list-data"); ?>', serverSide:true, order:[[7,"desc"]],
        filterDropdown:[
            {name:"import_type",class:"w200",options:<?php echo json_encode($type_options); ?>},
            {name:"status",class:"w180",options:<?php echo json_encode($status_options); ?>}
        ],
        columns:[
            {title:'<?php echo app_lang("gd_import_batch"); ?>'},
            {title:'<?php echo app_lang("gd_import_select_type"); ?>'},
            {title:'<?php echo app_lang("gd_import_filename"); ?>',class:"all"},
            {title:'<?php echo app_lang("gd_import_row_count"); ?>'},
            {title:'<?php echo app_lang("gd_import_imported"); ?>'},
            {title:'<?php echo app_lang("gd_import_issues"); ?>'},
            {title:'<?php echo app_lang("gd_status"); ?>'},
            {title:'<?php echo app_lang("gd_created_at"); ?>'},
            {title:'<i data-feather="menu" class="icon-16"></i>',class:"text-center option w100"}
        ]
    });
});
</script>
