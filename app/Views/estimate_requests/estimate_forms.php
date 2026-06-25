<div id="page-content" class="page-wrapper clearfix">
    <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
        <?php echo view("estimates/tabs", array("active_tab" => "estimate_forms", "can_edit_estimates" => $can_edit_estimate_requests)); ?>

        <div class="tab-title clearfix no-border tickets-page-title">
            <div class="title-button-group">
                <?php echo modal_anchor(get_uri("estimate_requests/estimate_request_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_form'), array("class" => "btn btn-default", "title" => app_lang('add_form'))); ?>
            </div>
        </div>
    </ul>

    <div class="card border-top-0 rounded-top-0">
        <div class="table-responsive">
            <table id="estimate-form-main-table" class="display" cellspacing="0" width="100%">            
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#estimate-form-main-table").appTable({
            source: '<?php echo_uri("estimate_requests/estimate_forms_list_data") ?>',
            order: [[0, 'asc']],
            columns: [
                {title: "<?php echo app_lang("title"); ?>", "class": "all"},
                {title: "<?php echo app_lang("public"); ?>", "class": "w150"},
                {title: "<?php echo app_lang("embed"); ?>", "class": "option w150"},
                {title: "<?php echo app_lang("status"); ?>", "class": "w150"},
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ]
        });
    });
</script>