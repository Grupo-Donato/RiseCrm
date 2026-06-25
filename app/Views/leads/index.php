<div id="page-content" class="clearfix grid-button leads-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <ul class="nav nav-tabs bg-white title" role="tablist">
                <?php echo view("leads/tabs", array("active_tab" => "leads_list")); ?>

                <div class="tab-title clearfix no-border">
                    <div class="title-button-group">
                        <?php echo modal_anchor(get_uri("labels/modal_form"), "<i data-feather='tag' class='icon-16'></i> " . app_lang('manage_labels'), array("class" => "btn btn-default", "title" => app_lang('manage_labels'), "data-post-type" => "client")); ?>
                        <?php echo modal_anchor(get_uri("leads/import_modal_form"), "<i data-feather='upload' class='icon-16'></i> " . app_lang('import_leads'), array("class" => "btn btn-default", "title" => app_lang('import_leads'))); ?>
                        <?php echo modal_anchor(get_uri("leads/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_lead'), array("class" => "btn btn-default", "title" => app_lang('add_lead'))); ?>
                    </div>
                </div>
            </ul>

            <div class="card border-top-0 rounded-top-0">
                <div class="table-responsive scrollable-table">
                    <table id="lead-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($lead_id) && $lead_id) { ?>
        compactViewId = "<?php echo $lead_id; ?>";
    <?php } ?>

    $(document).ready(function () {
        var ignoreSavedFilter = false;
        var hasString = window.location.hash.substring(1);
        var hasSelectedStatus = "<?php echo isset($selected_status_id) && $selected_status_id ?>";
        if (hasString || hasSelectedStatus) {
            ignoreSavedFilter = true;
        }

        var batchUpdateUrl = "<?php echo_uri('leads/batch_update_modal_form'); ?>";
        var batchDeleteUrl = "<?php echo_uri('leads/delete_selected_leads'); ?>";

        var leadCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('leads'); ?>",
            dataSourceUrl: "<?php echo get_uri('leads/view/' . $lead_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('leads/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            leadCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#lead-table").appTable({
            source: '<?php echo_uri("leads/list_data/") ?>' + mobileView,
            serverSide: true,
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: "all_leads_list", //a to z and _ only. should be unique to avoid conflicts
            selectionHandler: {batchUpdateUrl: batchUpdateUrl, batchDeleteUrl: batchDeleteUrl},
            ignoreSavedFilter: ignoreSavedFilter,
            order: [[5, "desc"]],
            columns: [
                {title: "<?php echo app_lang("name") ?>", "class": "all", order_by: "company_name"},
                {title: "<?php echo app_lang("primary_contact") ?>", order_by: "primary_contact"},
                {title: "<?php echo app_lang("phone") ?>"},
                {title: "<?php echo app_lang("owner") ?>", order_by: "owner_name"},
                {title: "<?php echo app_lang("labels") ?>"},
                {visible: false, searchable: false, order_by: "created_date"},
                {title: "<?php echo app_lang("created_at") ?>", "iDataSort": 5, order_by: "created_date"},
                {title: "<?php echo app_lang("status") ?>", order_by: "status"}
                <?php echo $custom_field_headers; ?>,
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w140"}
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            filterDropdown: [
                <?php if (get_array_value($login_user->permissions, "lead") !== "own") { ?>
                 {name: "owner_id", class: "w200", options: <?php echo json_encode($owners_dropdown); ?>},
                <?php } ?>
                {name: "status", class: "w200", options: <?php echo view("leads/lead_statuses"); ?>},
            {name: "label_id", class: "w200", options: <?php echo $labels_dropdown; ?>},
            {name: "source", class: "w200", options: <?php echo view("leads/lead_sources"); ?>} ,
            <?php echo $custom_field_filters; ?>
            ],
            rangeDatepicker: [{startDate: {name: "start_date", value: ""}, endDate: {name: "end_date", value: ""}, label: "<?php echo app_lang('created_date'); ?>", showClearButton: true}],
            printColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 6, 7], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 6, 7], '<?php echo $custom_field_headers; ?>')
        });
    });
</script>

<?php echo view("leads/update_lead_status_script"); ?>