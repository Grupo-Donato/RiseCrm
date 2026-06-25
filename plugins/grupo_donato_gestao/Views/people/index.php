<?php
$status_options = [["id" => "", "text" => "- " . app_lang("gd_all_statuses") . " -"]];
foreach (\grupo_donato_gestao\Config\Constants::PERSON_STATUSES as $value) {
    $status_options[] = ["id" => $value, "text" => app_lang("gd_status_" . $value)];
}
?>
<div id="page-content" class="page-wrapper clearfix">
    <?php echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["active" => "people", "items" => [
        ["key" => "students", "url" => "grupo_donato/school/students", "label" => app_lang("gd_tab_students"), "icon" => "user"],
        ["key" => "customers", "url" => "grupo_donato/customers", "label" => app_lang("gd_tab_customers"), "icon" => "briefcase"],
        ["key" => "people", "url" => "grupo_donato/people", "label" => app_lang("gd_tab_people"), "icon" => "users"],
    ]]); ?>
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_people"); ?></h4>
            <?php if ($can_manage) { ?>
                <div class="title-button-group">
                    <?php echo modal_anchor(get_uri("grupo_donato/people/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_person"), ["class" => "btn btn-default", "title" => app_lang("gd_add_person")]); ?>
                </div>
            <?php } ?>
        </div>
        <div class="table-responsive"><table id="gd-people-table" class="display" width="100%"></table></div>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-people-table").appTable({
        source: '<?php echo_uri("grupo_donato/people/list_data"); ?>',
        serverSide: true,
        order: [[0, "asc"]],
        filterDropdown: [{name: "status", class: "w200", options: <?php echo json_encode($status_options); ?>}],
        columns: [
            {title: '<?php echo app_lang("gd_full_name"); ?>', order_by: "full_name", "class": "all"},
            {title: '<?php echo app_lang("gd_preferred_name"); ?>'},
            {title: '<?php echo app_lang("gd_primary_contact"); ?>'},
            {title: '<?php echo app_lang("gd_birth_date"); ?>', order_by: "birth_date"},
            {title: '<?php echo app_lang("gd_linked_accounts"); ?>', "class": "text-center"},
            {title: '<?php echo app_lang("gd_status"); ?>', order_by: "status"},
            {title: '<?php echo app_lang("gd_updated_at"); ?>', order_by: "updated_at"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>
