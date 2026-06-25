<div id="page-content" class="clearfix grid-button contracts-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <?php if (isset($contract_id) && $contract_id) { ?>
                <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="<?php echo_uri('contracts'); ?>" role="tab"> <?php echo app_lang('contracts'); ?></a>
                    </li>
                    <div class="tab-title clearfix no-border">
                        <div class="title-button-group">
                            <?php
                            if ($can_edit_contracts) {
                                echo modal_anchor(get_uri("contracts/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_contract'), array("class" => "btn btn-default", "title" => app_lang('add_contract')));
                            }
                            ?>
                        </div>
                    </div>
                </ul>
            <?php } else { ?>
                <div class="page-title clearfix b-a">
                    <h1> <?php echo app_lang('contracts'); ?></h1>
                    <div class="title-button-group">
                        <?php
                        if ($can_edit_contracts) {
                            echo modal_anchor(get_uri("contracts/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_contract'), array("class" => "btn btn-default", "title" => app_lang('add_contract')));
                        }
                        ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
                <div class="table-responsive scrollable-table">
                    <table id="contract-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($contract_id) && $contract_id) { ?>
        compactViewId = "<?php echo $contract_id; ?>";
    <?php } ?>

    $(document).ready(function () {
        var showOptionColumn = <?php echo $can_edit_contracts ? "true" : "false"; ?>;

        var contractCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('contracts'); ?>",
            dataSourceUrl: "<?php echo get_uri('contracts/view/' . $contract_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('contracts/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            contractCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#contract-table").appTable({
            source: '<?php echo_uri("contracts/list_data/") ?>' + mobileView,
            serverSide: true,
            order: [[0, "desc"]],
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: "contracts_list", //a to z and _ only. should be unique to avoid conflicts
            rangeRadioButtons: [{name: "range_radio_button", selectedOption: 'yearly', options: ['monthly', 'yearly', 'custom', 'dynamic'], dynamicRanges:['this_month', 'last_month', 'next_month', 'this_year', 'last_year']}],
            filterDropdown: [{name: "status", class: "w150", options: <?php echo view("contracts/contract_statuses_dropdown"); ?>}, <?php echo $custom_field_filters; ?>],
            columns: [
                {title: '<?php echo app_lang("contract") ?>', order_by: "id"},
                {title: "<?php echo app_lang("title") ?> ", "class": "w15p all", order_by: "title"},
                {title: "<?php echo app_lang("client") ?>", "class": "w15p", order_by: "company_name" },
                {title: "<?php echo app_lang("project") ?>"},
                {visible: false, searchable: false, order_by: "contract_date"},
                {title: "<?php echo app_lang("contract_date") ?>", "iDataSort": 4, "class": "w10p"},
                {visible: false, searchable: false, order_by: "valid_until"},
                {title: "<?php echo app_lang("valid_until") ?>", "iDataSort": 6, "class": "w10p"},
                {title: "<?php echo app_lang("amount") ?>", "class": "text-right"},
                {title: "<?php echo app_lang("status") ?>", "class": "text-center"}
<?php echo $custom_field_headers; ?>,
                {title: "<i data-feather='menu' class='icon-16'></i>", "class": "text-center w150", visible: showOptionColumn}
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            printColumns: combineCustomFieldsColumns([0, 1, 2, 3, 5, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 2, 3, 5, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [{column: 8, fieldName: "total_contract_value", dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol}]
        });
    });
</script>