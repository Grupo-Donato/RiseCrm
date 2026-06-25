<?php
// Filtro visível: Todas / Turmas em grupo / Personal (backend já aceita class_type).
$type_options = [["id" => "", "text" => app_lang("gd_school_class_all")]];
foreach ($types as $value) {
    $type_options[] = ["id" => $value, "text" => app_lang("gd_school_class_type_" . $value)];
}
$status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach ($statuses as $value) {
    $status_options[] = ["id" => $value, "text" => app_lang("gd_school_status_" . $value)];
}
?>
<div class="card">
    <div class="page-title clearfix">
        <h1><?php echo app_lang('gd_menu_classes_personal'); ?></h1>
        <div class="title-button-group"><?php if ($can_manage) echo modal_anchor(get_uri('grupo_donato/school/classes/modal'), '<i data-feather="plus-circle" class="icon-16"></i> ' . app_lang('add'), ['class' => 'btn btn-default', 'title' => app_lang('gd_school_class')]); ?></div>
    </div>
    <div class="table-responsive"><table id="school-classes-table" class="display" width="100%"></table></div>
</div>
<script>
$(document).ready(function () {
    "use strict";
    $("#school-classes-table").appTable({
        source: '<?php echo_uri('grupo_donato/school/classes/list-data'); ?>',
        filterDropdown: [
            {name: "class_type", class: "w200", options: <?php echo json_encode($type_options); ?>},
            {name: "status", class: "w200", options: <?php echo json_encode($status_options); ?>}
        ],
        columns: [
            {title: '<?php echo app_lang('gd_school_class'); ?>', data: 'name'},
            {title: '<?php echo app_lang('gd_type'); ?>', data: 'type'},
            {title: '<?php echo app_lang('gd_school_instructor'); ?>', data: 'instructor'},
            {title: '<?php echo app_lang('gd_resource'); ?>', data: 'resource'},
            {title: '<?php echo app_lang('gd_school_schedule'); ?>', data: 'schedule'},
            {title: '<?php echo app_lang('gd_school_capacity'); ?>', data: 'capacity'},
            {title: '<?php echo app_lang('gd_status'); ?>', data: 'status'},
            {title: '', data: 'options', class: 'text-center option w100'}
        ]
    });
});
</script>
