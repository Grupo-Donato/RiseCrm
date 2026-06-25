<div id="page-content" class="clearfix grid-button proposals-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <?php if (isset($proposal_id) && $proposal_id) { ?>
                <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="<?php echo_uri('proposals'); ?>" role="tab"> <?php echo app_lang('proposals'); ?></a>
                    </li>
                    <div class="tab-title clearfix no-border">
                        <div class="title-button-group">
                            <?php 
                            if ($can_edit_proposals) {
                                echo modal_anchor(get_uri("proposals/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_proposal'), array("class" => "btn btn-default", "title" => app_lang('add_proposal')));
                            }
                            ?>
                        </div>
                    </div>
                </ul>
            <?php } else { ?>
                <div class="page-title clearfix b-a">
                    <h1><?php echo app_lang('proposals'); ?></h1>
                    <div class="title-button-group">
                        <?php 
                        if ($can_edit_proposals) {
                            echo modal_anchor(get_uri("proposals/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_proposal'), array("class" => "btn btn-default", "title" => app_lang('add_proposal')));
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
                <div class="table-responsive scrollable-table">
                    <table id="proposal-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($proposal_id) && $proposal_id) { ?>
        compactViewId = "<?php echo $proposal_id; ?>";
    <?php } ?>

    $(document).ready(function () {
        var showCommentOption = false;
        if ("<?php echo get_setting("enable_comments_on_proposals") == "1" ?>") {
            showCommentOption = true;
        }

        var proposalCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('proposals'); ?>",
            dataSourceUrl: "<?php echo get_uri('proposals/view/' . $proposal_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('proposals/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            proposalCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        var optionVisibility = false;
        if ("<?php echo $can_edit_proposals ?>") {
            optionVisibility = true;
        }

        $("#proposal-table").appTable({
            source: '<?php echo_uri("proposals/list_data/") ?>' + mobileView,
            order: [[0, "desc"]],
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: "proposals_list", //a to z and _ only. should be unique to avoid conflicts
            rangeRadioButtons: [{name: "range_radio_button", selectedOption: 'yearly', options: ['monthly', 'yearly', 'custom', 'dynamic'], dynamicRanges:['this_month', 'last_month', 'next_month', 'this_year', 'last_year']}],
            filterDropdown: [{name: "status", class: "w150", options: <?php echo view("proposals/proposal_statuses_dropdown"); ?>}, <?php echo $custom_field_filters; ?>],
            rangeDatepicker: [
                {startDate: {name: "last_email_seen_start_date", value: ""}, endDate: {name: "last_email_seen_end_date", value: ""}, showClearButton: true, label: "<?php echo app_lang('last_email_seen'); ?>", ranges: ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month']},
                {startDate: {name: "last_preview_seen_start_date", value: ""}, endDate: {name: "last_preview_seen_end_date", value: ""}, showClearButton: true, label: "<?php echo app_lang('last_preview_seen'); ?>", ranges: ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month']}
            ],
            columns: [
                {title: "<?php echo app_lang("proposal") ?> ", "class": "all"},
                {title: "<?php echo app_lang("client") ?>"},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("proposal_date") ?>", "iDataSort": 2, "class": "w100"},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("valid_until") ?>", "iDataSort": 4, "class": "w100"},
                {title: "<?php echo app_lang("last_email_seen") ?>", "class": "text-center"},
                {title: "<?php echo app_lang("last_preview_seen") ?>", "class": "text-center"},
                {title: "<?php echo app_lang("amount") ?>", "class": "text-right"},
                {title: "<?php echo app_lang("status") ?>", "class": "w100 text-center"},
                {visible: showCommentOption, title: "<?php echo app_lang("comments") ?>", "class": "text-center w50"}
<?php echo $custom_field_headers; ?>,
                {title: "<i data-feather='menu' class='icon-16'></i>", "class": "text-center w140", visible: optionVisibility}
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            printColumns: combineCustomFieldsColumns([0, 1, 3, 5, 6, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 3, 5, 6, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [{column: 8, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol}]
        });
    });
</script>