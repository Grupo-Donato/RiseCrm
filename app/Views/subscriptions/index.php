<div id="page-content" class="clearfix grid-button subscriptions-list-view page-wrapper">
    <div class="flex-shrink-0">
        <div class="list-section">
            <?php
            $manage_labels = modal_anchor(get_uri("labels/modal_form"), "<i data-feather='tag' class='icon-16'></i> " . app_lang('manage_labels'), array("class" => "btn btn-default mb0", "title" => app_lang('manage_labels'), "data-post-type" => "subscription"));
            $add_subscription = modal_anchor(get_uri("subscriptions/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_subscription'), array("class" => "btn btn-default mb0", "title" => app_lang('add_subscription')));
            ?>

            <?php if (isset($subscription_id) && $subscription_id) { ?>
                <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="<?php echo_uri('subscriptions'); ?>" role="tab"> <?php echo app_lang('subscriptions'); ?></a>
                    </li>
                    <div class="tab-title clearfix no-border">
                        <div class="title-button-group">
                            <?php if ($can_edit_subscriptions) {
                                echo $manage_labels . " " . $add_subscription;
                            } ?>
                        </div>
                    </div>
                </ul>
            <?php } else { ?>
                <div class="page-title clearfix b-a">
                    <h1> <?php echo app_lang('subscriptions'); ?></h1>
                    <div class="title-button-group">
                        <?php if ($can_edit_subscriptions) {
                            echo $manage_labels . " " . $add_subscription;
                        } ?>
                    </div>
                </div>
            <?php } ?>

            <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
                <div class="table-responsive scrollable-table">
                    <table id="subscriptions-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var compactViewId = 0;
    <?php if (isset($subscription_id) && $subscription_id) { ?>
        compactViewId = "<?php echo $subscription_id; ?>";
    <?php } ?>

    $(document).ready(function() {
        var optionVisibility = false;
        if ("<?php echo $can_edit_subscriptions ?>") {
            optionVisibility = true;
        }

        var currency_dropdown = <?php echo $currencies_dropdown; ?>;
        var filterDropdowns = [];
        if (currency_dropdown.length > 1) {
            filterDropdowns.push({
                name: "currency",
                class: "w150",
                options: currency_dropdown
            });
        }

        filterDropdowns.push({
            name: "repeat_type",
            class: "w200",
            options: <?php echo $repeat_types_dropdown; ?>
        });

        filterDropdowns.push(<?php echo $custom_field_filters; ?>);

        var subscriptionCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('subscriptions'); ?>",
            dataSourceUrl: "<?php echo get_uri('subscriptions/view/' . $subscription_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('subscriptions/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            subscriptionCompactView.setActiveRow();
        }, 100);

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#subscriptions-table").appTable({
            source: '<?php echo_uri("subscriptions/list_data/") ?>' + mobileView,
            order: [
                [0, "desc"]
            ],
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            smartFilterIdentity: "subscriptions_list", //a to z and _ only. should be unique to avoid conflicts
            rangeDatepicker: [{
                startDate: {
                    name: "next_billing_start_date",
                    value: ""
                },
                endDate: {
                    name: "next_billing_end_date",
                    value: ""
                },
                showClearButton: true,
                label: "<?php echo app_lang('next_billing_date'); ?>",
                ranges: ['tomorrow', 'next_7_days', 'this_month', 'next_month', 'this_year', 'next_year']
            }],
            filterDropdown: filterDropdowns,
            columns: [{
                    visible: false,
                    searchable: false
                },
                {
                    title: "<?php echo app_lang("subscription_id") ?>",
                    "class": "",
                    "iDataSort": 0
                },
                {
                    title: "<?php echo app_lang("title") ?> ",
                    "class": "all"
                },
                {
                    title: "<?php echo app_lang("type") ?> ",
                    "class": "w50"
                },
                {
                    title: "<?php echo app_lang("client") ?>",
                    "class": ""
                },
                {
                    visible: false,
                    searchable: false
                },
                {
                    title: "<?php echo app_lang("first_billing_date") ?>",
                    "iDataSort": 5,
                    "class": ""
                },
                {
                    visible: false,
                    searchable: false
                },
                {
                    title: "<?php echo app_lang("next_billing_date") ?>",
                    "iDataSort": 7,
                    "class": ""
                },
                {
                    title: "<?php echo app_lang("repeat_every") ?>",
                    "class": "text-center"
                },
                {
                    title: "<?php echo app_lang("cycles") ?>",
                    "class": "w50 text-center"
                },
                {
                    title: "<?php echo app_lang("status") ?>",
                    "class": "w100 text-center"
                },
                {
                    title: "<?php echo app_lang("amount") ?>",
                    "class": "w100 text-right"
                }
                <?php echo $custom_field_headers; ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center w100",
                    visible: optionVisibility
                }
            ],
            rowCallback: function(nRow, aData, iDisplayIndex, iDisplayIndexFull) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            onInitComplete: function() {
                if (compactViewId) {
                    $("#subscriptions-table").wrap("<div id='subscriptions-table-container'></div>");
                    var windowHeight = $(window).height();
                    var subscriptionsListHeight = 388;
                    var heightDiff = windowHeight - subscriptionsListHeight;
                    $("#subscriptions-table-container").attr('style', 'min-height: ' + heightDiff + 'px !important');
                }
            },
            printColumns: combineCustomFieldsColumns([1, 2, 3, 4, 6, 8, 9, 10, 11, 12], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([1, 2, 3, 4, 6, 8, 9, 10, 11, 12], '<?php echo $custom_field_headers; ?>'),
            summation: compactViewId ? [] : [{
                column: 12,
                dataType: 'currency',
                currencySymbol: AppHelper.settings.currencySymbol,
                conversionRate: <?php echo $conversion_rate; ?>
            }]

        });
    });
</script>