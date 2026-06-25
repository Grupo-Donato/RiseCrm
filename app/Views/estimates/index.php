<div id="page-content" class="clearfix grid-button estimates-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                <?php echo view("estimates/tabs", array("active_tab" => "estimates_list", "can_edit_estimates" => $can_edit_estimates)); ?>

                <div class="tab-title clearfix no-border estimates-page-title">
                    <div class="title-button-group">
                        <?php
                        if ($can_edit_estimates) {
                            echo modal_anchor(get_uri("estimates/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_estimate'), array("class" => "btn btn-default", "title" => app_lang('add_estimate')));
                        }
                        ?>
                    </div>
                </div>
            </ul>

            <div class="card border-top-0 rounded-top-0">
                <div class="table-responsive scrollable-table">
                    <table id="estimates-table" class="display xs-hide-dtr-control no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var compactViewId = 0;
        <?php if (isset($estimate_id) && $estimate_id) { ?>
            compactViewId = "<?php echo $estimate_id; ?>";
        <?php } ?>

        var estimatesCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('estimates'); ?>",
            dataSourceUrl: "<?php echo get_uri('estimates/view/' . $estimate_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('estimates/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            estimatesCompactView.setActiveRow();
        }, 100);

        var showCommentOption = false;
        if ("<?php echo get_setting("enable_comments_on_estimates") == "1" ?>") {
            showCommentOption = true;
        }

        var mobileView = 0,
        idColumnClass = "w15p";
        if (isMobile() || compactViewId) {
            mobileView = 1;
            idColumnClass = "";
        }

        var optionVisibility = false;
        if ("<?php echo $can_edit_estimates ?>") {
            optionVisibility = true;
        }

        $("#estimates-table").appTable({
            source: '<?php echo_uri("estimates/list_data/") ?>' + mobileView,
            order: [[1, "desc"]],
            smartFilterIdentity: "estimates_list", //a to z and _ only. should be unique to avoid conflicts
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            rangeRadioButtons: [{name: "range_radio_button", selectedOption: 'yearly', options: ['monthly', 'yearly', 'custom', 'dynamic'], dynamicRanges:['this_month', 'last_month', 'next_month', 'this_year', 'last_year']}],
            filterDropdown: [{name: "status", class: "w150", options: <?php echo view("estimates/estimate_statuses_dropdown"); ?>}, <?php echo $custom_field_filters; ?>],
            columns: [
                {visible: false, searchable: false},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("estimate") ?> ", "class": "all " + idColumnClass, "iDataSort": 1},
                {title: "<?php echo app_lang("client") ?>"},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("estimate_date") ?>", "iDataSort": 4},
                {title: "<?php echo app_lang("created_by") ?>"},
                {title: "<?php echo app_lang("amount") ?>", "class": "text-right"},
                {title: "<?php echo app_lang("status") ?>", "class": "text-center"},
                {visible: showCommentOption, title: "<?php echo app_lang("comments") ?>", "class": "text-center"}
                <?php echo $custom_field_headers; ?>,
                {title: "<i data-feather='menu' class='icon-16'></i>", "class": "text-center w140", visible: optionVisibility}
            ],
            rowCallback: function(nRow, aData) {
                if (mobileView) {
                    $("td:eq(0)", nRow).attr("style", "border-left-color:" + aData[0] + " !important;").addClass('list-status-border');
                }

                if (compactViewId) {
                    applyActiveRow();
                }
            },
            onInitComplete: function() {
                if (compactViewId) {
                    $("#estimates-table").wrap("<div id='estimates-table-container'></div>");
                    var windowHeight = $(window).height();
                    var estimatesListHeight = 388;
                    var heightDiff = windowHeight - estimatesListHeight;
                    $("#estimates-table-container").attr('style', 'min-height: ' + heightDiff + 'px !important');
                }
            },
            printColumns: combineCustomFieldsColumns([2, 3, 5, 6, 7], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([2, 3, 5, 6, 7], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [{column: 7, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol, conversionRate: <?php echo $conversion_rate; ?>}]
        });
    });
</script>