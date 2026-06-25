<div id="page-content" class="clearfix grid-button subscriptions-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <ul class="nav nav-tabs bg-white title" role="tablist">
                <?php
                echo view("invoices/tabs", array("active_tab" => "invoices_list")); 
                
                echo view("invoices/title_button_group", array("can_edit_invoices" => $can_edit_invoices));
                ?>
            </ul>

            <div class="card border-top-0 rounded-top-0">
                <div class="table-responsive scrollable-table">
                    <table id="invoice-list-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($invoice_id) && $invoice_id) { ?>
        compactViewId = "<?php echo $invoice_id; ?>";
    <?php } ?>

    $(document).ready(function() {
        var ignoreSavedFilter = false;

        var optionVisibility = false;
        if ("<?php echo $can_edit_invoices ?>") {
            optionVisibility = true;
        }

        var idColumnClass = "w10p";
        if (isMobile()) {
            idColumnClass = "";
        }

        var RangeButtonSelectedOption = 'monthly';
        var tab = "<?php echo $tab; ?>";
        if (tab === "custom") {
            var ignoreSavedFilter = true;
            RangeButtonSelectedOption = 'custom';
        }

        var status = "<?php echo $status; ?>";
        var invoice_statuses_dropdown = <?php echo view("invoices/invoice_statuses_dropdown"); ?>;
        if (status !== "") {
            var filterIndex = invoice_statuses_dropdown.findIndex(x => x.id === status);
            if ([filterIndex] > -1) {
                //match found
                invoice_statuses_dropdown[filterIndex].isSelected = true;

                var ignoreSavedFilter = true;
            }
        }

        var invoiceCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('invoices'); ?>",
            dataSourceUrl: "<?php echo get_uri('invoices/view/' . $invoice_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('invoices/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            invoiceCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#invoice-list-table").appTable({
            source: '<?php echo_uri("invoices/list_data/") ?>' + mobileView,
            order: [[0, "desc"]],
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: 'invoice_list', //a to z and _ only. should be unique to avoid conflicts
            ignoreSavedFilter: ignoreSavedFilter,
            rangeRadioButtons: [{name: "range_radio_button", selectedOption: RangeButtonSelectedOption, options: ['monthly', 'yearly', 'custom', 'dynamic'], dynamicRanges:['this_month', 'last_month', 'next_month', 'this_year', 'last_year']}],
            filterDropdown: [
            {name: "type", class: "w150", options: <?php echo $types_dropdown; ?>},
            {name: "status", class: "w150", options: invoice_statuses_dropdown}
            <?php if ($currencies_dropdown) { ?>
                , {name: "currency", class: "w150", options: <?php echo $currencies_dropdown; ?>}
            <?php } ?>
            , <?php echo $custom_field_filters; ?>
            ],
            columns: [
            {visible: false, searchable: false},
            {title: "<?php echo app_lang("invoice_id") ?>", "class": idColumnClass + " all", "iDataSort": 0},
            {title: "<?php echo app_lang("client") ?>"},
            {title: "<?php echo app_lang("project") ?>", "class": "w15p"},
            {visible: false, searchable: false},
            {title: "<?php echo app_lang("bill_date") ?>", "class": "w10p", "iDataSort": 4},
            {visible: false, searchable: false},
            {title: "<?php echo app_lang("due_date") ?>", "class": "w10p", "iDataSort": 6},
            {title: "<?php echo app_lang("total_invoiced") ?>", "class": "w10p text-right"},
            {title: "<?php echo app_lang("payment_received") ?>", "class": "w10p text-right"},
            {title: "<?php echo app_lang("due") ?>", "class": "w10p text-right"},
            {title: "<?php echo app_lang("status") ?>", "class": "w100 text-center"}
            <?php echo $custom_field_headers; ?>,
            {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center w140", visible: optionVisibility}
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            printColumns: combineCustomFieldsColumns([1, 2, 3, 4, 7, 8, 9, 10, 11], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([1, 2, 3, 4, 7, 8, 9, 10, 11], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [
            {column: 8, dataType: 'currency', conversionRate: <?php echo $conversion_rate; ?>},
            {column: 9, dataType: 'currency', conversionRate: <?php echo $conversion_rate; ?>},
            {column: 10, dataType: 'currency', conversionRate: <?php echo $conversion_rate; ?>}
            ]
    });

});
</script>