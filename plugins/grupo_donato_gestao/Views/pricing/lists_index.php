<div id="page-content" class="page-wrapper clearfix">
    <div class="card">
        <div class="page-title clearfix">
            <h4><?php echo app_lang("gd_menu_price_lists"); ?></h4>
            <div class="title-button-group">
                <?php echo anchor(get_uri("grupo_donato/pricing/resolver"), "<i data-feather='search' class='icon-16'></i> " . app_lang("gd_price_resolver"), ["class" => "btn btn-default"]); ?>
                <?php if (!empty($can_manage)) { ?>
                    <?php echo modal_anchor(get_uri("grupo_donato/pricing/lists/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang("gd_add_price_list"), ["class" => "btn btn-default", "title" => app_lang("gd_add_price_list")]); ?>
                <?php } ?>
            </div>
        </div>
        <div class="table-responsive"><table id="gd-price-lists-table" class="display" width="100%"></table></div>
    </div>
</div>
<script type="text/javascript">
$(document).ready(function () {
    $("#gd-price-lists-table").appTable({
        source: '<?php echo_uri("grupo_donato/pricing/lists/list_data"); ?>',
        columns: [
            {title: '<?php echo app_lang("gd_code"); ?>', "class": "w150"},
            {title: '<?php echo app_lang("gd_name"); ?>'},
            {title: '<?php echo app_lang("gd_currency"); ?>', "class": "text-center w80"},
            {title: '<?php echo app_lang("gd_validity"); ?>'},
            {title: '<?php echo app_lang("gd_priority"); ?>', "class": "text-center w80"},
            {title: '<?php echo app_lang("gd_default"); ?>', "class": "text-center w80"},
            {title: '<?php echo app_lang("gd_status"); ?>', "class": "text-center w100"},
            {title: '<?php echo app_lang("gd_price_count"); ?>', "class": "text-center w80"},
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
        ]
    });
});
</script>
