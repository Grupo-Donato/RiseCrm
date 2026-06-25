<div id="page-content" class="page-wrapper clearfix grid-button">
    <div class="flex-shrink-0">
        <div class="list-section">
            <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                <?php echo view("estimates/tabs", array("active_tab" => "estimate_requests", "can_edit_estimates" => $can_edit_estimate_requests)); ?>

                <div class="tab-title clearfix no-border estimate-requests-page-title">
                    <div class="title-button-group">
                        <?php
                        if ($can_edit_estimate_requests) {
                            echo modal_anchor(get_uri("estimate_requests/request_an_estimate_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('create_estimate_request'), array("class" => "btn btn-default", "title" => app_lang('create_estimate_request')));
                        }
                        ?>
                    </div>
                </div>
            </ul>

            <div class="card border-top-0 rounded-top-0">
                <div class="table-responsive scrollable-table">
                    <table id="estimate-requests-table" class="display xs-hide-dtr-control no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        var compactViewId = 0;
        <?php if (isset($estimate_request_id) && $estimate_request_id) { ?>
            compactViewId = "<?php echo $estimate_request_id; ?>";
        <?php } ?>

        var estimateRequestsCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('estimate_requests'); ?>",
            dataSourceUrl: "<?php echo get_uri('estimate_requests/view_estimate_request/' . $estimate_request_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('estimate_requests/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            estimateRequestsCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        var optionVisibility = false;
        if ("<?php echo $can_edit_estimate_requests ?>") {
            optionVisibility = true;
        }

        $("#estimate-requests-table").appTable({
            source: '<?php echo_uri("estimate_requests/estimate_request_list_data/") ?>' + mobileView,
            order: [[4, 'desc']],
            smartFilterIdentity: "estimate_requests_list", //a to z and _ only. should be unique to avoid conflicts
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            filterDropdown: [{name: "assigned_to", class: "w150", options: <?php echo $assigned_to_dropdown; ?>}, {name: "status", class: "w150", options: <?php echo $statuses_dropdown; ?>}],
            columns: [
                {visible: false, searchable: false},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang('id'); ?>", "class": "all", "iDataSort": 1},
                {title: "<?php echo app_lang('client'); ?>"},
                {title: "<?php echo app_lang('title'); ?>"},
                {title: "<?php echo app_lang('assigned_to'); ?>"},
                {visible: false, searchable: false},
                {title: '<?php echo app_lang("created_date") ?>', "iDataSort": 4},
                {title: "<?php echo app_lang('status'); ?>"},
                {title: "<i data-feather='menu' class='icon-16'></i>", "class": "text-center w100", visible: optionVisibility}
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
                    $("#estimate-requests-table").wrap("<div id='estimate-requests-table-container'></div>");
                    var windowHeight = $(window).height();
                    var estimateRequestsListHeight = 388;
                    var heightDiff = windowHeight - estimateRequestsListHeight;
                    $("#estimate-requests-table-container").attr('style', 'min-height: ' + heightDiff + 'px !important');
                }
            },
            printColumns: [2, 3, 4, 5, 7, 8]
        });
    });
</script>