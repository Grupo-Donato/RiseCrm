<?php
$is_admin = !empty($login_user->is_admin);
$can_manage = $is_admin || get_array_value($login_user->permissions, "gd_business_areas_manage");
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php echo view("grupo_donato_gestao\\Views\\components\\settings_nav", ["active_tab" => "business_areas"]); ?>
        </div>
        <div class="col-sm-9 col-lg-10">
            <div class="card">
                <div class="page-title clearfix">
                    <h4><?php echo app_lang("gd_business_areas"); ?></h4>
                    <?php if ($can_manage) { ?>
                        <div class="title-button-group">
                            <?php echo modal_anchor(get_uri("grupo_donato/settings/business-areas/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_business_area"), ["class" => "btn btn-default", "title" => app_lang("gd_add_business_area")]); ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="table-responsive">
                    <table id="gd-areas-table" class="display" cellspacing="0" width="100%"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#gd-areas-table").appTable({
            source: '<?php echo_uri("grupo_donato/settings/business-areas/list_data"); ?>',
            columns: [
                {title: '<?php echo app_lang("gd_code"); ?>', "class": "w150"},
                {title: '<?php echo app_lang("gd_name"); ?>'},
                {title: '<?php echo app_lang("gd_unit"); ?>'},
                {title: '<?php echo app_lang("gd_status"); ?>', "class": "text-center w100"},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ]
        });
    });
</script>
