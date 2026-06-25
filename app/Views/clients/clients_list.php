<div id="page-content" class="clearfix grid-button clients-list-view no-padding">
    <div class="flex-shrink-0">
        <div class="list-section">
            <?php if (isset($client_id) && $client_id) { ?>
                <ul class=" nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" href="<?php echo_uri('clients'); ?>" role="tab"> <?php echo app_lang('clients'); ?></a></li>
                </ul>
            <?php } ?>

            <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
                <div class="table-responsive scrollable-table">
                    <table id="client-table" class="display no-title" cellspacing="0" width="100%">
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var compactViewId = 0;
        <?php if (isset($client_id) && $client_id) { ?>
            compactViewId = "<?php echo $client_id; ?>";
        <?php } ?>

        var clientsCompactView = appCompactView.init({
            compactViewId: compactViewId,
            backButtonUrl: "<?php echo_uri('clients'); ?>",
            dataSourceUrl: "<?php echo get_uri('clients/view/' . $client_id); ?>",
            compactViewBaseUrl: "<?php echo get_uri('clients/compact_view/'); ?>",
        });

        var applyActiveRow = delayAction(function() {
            clientsCompactView.setActiveRow();
        }, 100);

        var showInvoiceInfo = true;
        if (!"<?php echo $show_invoice_info; ?>") {
            showInvoiceInfo = false;
        }

        var showOptions = true;
        if (!"<?php echo $can_edit_clients; ?>") {
            showOptions = false;
        }

        var ignoreSavedFilter = false;
        var quick_filters_dropdown = <?php echo view("clients/quick_filters_dropdown"); ?>;
        if (window.selectedClientQuickFilter) {
            var filterIndex = quick_filters_dropdown.findIndex(x => x.id === window.selectedClientQuickFilter);
            if ([filterIndex] > -1) {
                //match found
                ignoreSavedFilter = true;
                quick_filters_dropdown[filterIndex].isSelected = true;
            }
        }

        var batchUpdateUrl = "<?php echo_uri('clients/batch_update_modal_form'); ?>";
        var batchDeleteUrl = "<?php echo_uri('clients/delete_selected_clients'); ?>";

        var mobileView = 0;
        if (isMobile() || compactViewId) {
            mobileView = 1;
        }

        $("#client-table").appTable({
            source: '<?php echo_uri("clients/list_data/") ?>' + mobileView,
            serverSide: true,
            smartFilterIdentity: "all_clients_list", //a to z and _ only. should be unique to avoid conflicts
            mobileMirror: mobileView,
            compactView: compactViewId ? true : false,
            selectionHandler: {
                batchUpdateUrl: batchUpdateUrl,
                batchDeleteUrl: batchDeleteUrl
            },
            ignoreSavedFilter: ignoreSavedFilter,
            filterDropdown: [{
                    name: "quick_filter",
                    class: "w200",
                    options: quick_filters_dropdown
                },
                <?php if ($login_user->is_admin || get_array_value($login_user->permissions, "client") === "all") { ?> {
                        name: "created_by",
                        class: "w200",
                        options: <?php echo $team_members_dropdown; ?>
                    },
                <?php } ?> {
                    name: "group_id",
                    class: "w200",
                    options: <?php echo $groups_dropdown; ?>
                },
                {
                    name: "label_id",
                    class: "w200",
                    options: <?php echo $labels_dropdown; ?>
                },
                <?php echo $custom_field_filters; ?>
            ],
            columns: [{
                    title: "<?php echo app_lang("id") ?>",
                    "class": "text-center w50 desktop",
                    order_by: "id"
                },
                {
                    title: "<?php echo app_lang("name") ?>",
                    "class": "all",
                    order_by: "company_name"
                },
                {
                    title: "<?php echo app_lang("primary_contact") ?>",
                    order_by: "primary_contact"
                },
                {
                    title: "<?php echo app_lang("phone") ?>",
                    order_by: "phone"
                },
                {
                    title: "<?php echo app_lang("client_groups") ?>",
                    order_by: "client_groups"
                },
                {
                    title: "<?php echo app_lang("labels") ?>"
                },
                {
                    title: "<?php echo app_lang("projects") ?>"
                },
                {
                    visible: showInvoiceInfo,
                    searchable: showInvoiceInfo,
                    title: "<?php echo app_lang("total_invoiced") ?>",
                    "class": "text-right"
                },
                {
                    visible: showInvoiceInfo,
                    searchable: showInvoiceInfo,
                    title: "<?php echo app_lang("payment_received") ?>",
                    "class": "text-right"
                },
                {
                    visible: showInvoiceInfo,
                    searchable: showInvoiceInfo,
                    title: "<?php echo app_lang("due") ?>",
                    "class": "text-right"
                }
                <?php echo $custom_field_headers; ?>,
                {
                    title: '<i data-feather="menu" class="icon-16"></i>',
                    "class": "text-center option w140",
                    visible: showOptions
                }
            ],
            rowCallback: function(nRow, aData) {
                if (compactViewId) {
                    applyActiveRow();
                }
            },
            onInitComplete: function() {
                if (compactViewId) {
                    $("#client-table").wrap("<div id='client-table-container'></div>");
                    var windowHeight = $(window).height();
                    var clientListHeight = 388;
                    var heightDiff = windowHeight - clientListHeight;
                    $("#client-table-container").attr('style', 'min-height: ' + heightDiff + 'px !important');
                }
            },
            printColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], '<?php echo $custom_field_headers; ?>'),
            reloadHooks: [{
                type: "app_form",
                id: "client-form"
            }],
        });
    });
</script>