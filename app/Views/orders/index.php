<div id="page-content" class="clearfix grid-button orders-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <?php if (isset($order_id) && $order_id) { ?>
                <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="<?php echo_uri('orders'); ?>" role="tab"> <?php echo app_lang('orders'); ?></a>
                    </li>
                    <div class="tab-title clearfix no-border">
                        <div class="title-button-group">
                            <?php echo js_anchor("<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_order'), array("class" => "btn btn-default", "id" => "add-order-btn")); ?>
                        </div>
                    </div>
                </ul>
            <?php } else { ?>
                <div class="page-title clearfix b-a">
                    <h1> <?php echo app_lang('orders'); ?></h1>
                    <div class="title-button-group">
                        <?php echo js_anchor("<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_order'), array("class" => "btn btn-default", "id" => "add-order-btn")); ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
                <div class="table-responsive scrollable-table">
                    <table id="orders-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($order_id) && $order_id) { ?>
        compactViewId = "<?php echo $order_id; ?>";
    <?php } ?>

    $(document).ready(function () {
        var orderCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('orders'); ?>",
            dataSourceUrl: "<?php echo get_uri('orders/view/' . $order_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('orders/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            orderCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#orders-table").appTable({
            source: '<?php echo_uri("orders/list_data/") ?>' + mobileView,
            order: [[0, "desc"]],
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: "orders_list", //a to z and _ only. should be unique to avoid conflicts
            rangeRadioButtons: [{name: "range_radio_button", selectedOption: 'monthly', options: ['monthly', 'yearly', 'custom', 'dynamic'], dynamicRanges:['this_month', 'last_month', 'next_month', 'this_year', 'last_year']}],
            filterDropdown: [{name: "status_id", class: "w150", options: <?php echo view("orders/order_statuses_dropdown"); ?>}, <?php echo $custom_field_filters; ?>],
            columns: [
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("order") ?> ", "class": "w10p all", "iDataSort": 0},
                {title: "<?php echo app_lang("client") ?>", "class": "w20p"},
                {title: "<?php echo app_lang("invoices") ?>", "class": "w20p"},
                {visible: false, searchable: false},
                {title: "<?php echo app_lang("order_date") ?>", "iDataSort": 4},
                {title: "<?php echo app_lang("amount") ?>", "class": "text-right w10p"},
                {title: "<?php echo app_lang("status") ?>", "class": "text-center"}
<?php echo $custom_field_headers; ?>,
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w140"}
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            printColumns: combineCustomFieldsColumns([0, 1, 2, 4, 5, 6], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 2, 4, 5, 6], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [{column: 6, dataType: 'currency', currencySymbol: AppHelper.settings.currencySymbol}]
        });


        $("#add-order-btn").click(function () {
            window.location.href = "<?php echo get_uri("store"); ?>";
        });
    });

</script>

<?php echo view("orders/update_order_status_script"); ?>