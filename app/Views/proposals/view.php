<div class="details-view-top-button clearfix">
    <?php
    echo view("includes/back_button", array("button_url" => get_uri("proposals/index"), "button_text" => app_lang("proposals"), "extra_class" => "float-start dark"));

    if (get_setting("enable_comments_on_proposals") && !($proposal_info->status === "draft")) {
        echo "<a href='javascript:;' class='reply-button dark d-sm-none pe-auto'><i data-feather='corner-up-left' class='icon-22 mr5 pe-auto'></i> " . app_lang('reply') . "</a>";
    }
    ?>
</div>

<div class="page-content xs-full-width clearfix">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="proposal-details-view">
                    <div id="proposal-details-top-bar">
                        <?php echo view("proposals/proposal_top_bar"); ?>
                    </div>

                    <div class="details-view-wrapper d-flex">
                        <div class="w-100">
                            <?php echo view("proposals/details"); ?>
                        </div>
                        <div class="flex-shrink-0 details-view-right-section">
                            <?php echo view("proposals/proposal_info"); ?>

                            <?php echo view("proposals/proposal_actions"); ?>

                            <?php echo view("proposals/proposal_custom_fields_info"); ?>

                            <?php if ($proposal_info->note) { ?>
                                <div id="proposal-note-section">
                                    <?php echo view("proposals/proposal_note"); ?>
                                </div>
                            <?php } ?>

                            <?php echo view("proposals/signer_info") ?>

                            <div id="proposal-tasks-section">
                                <?php echo view("proposals/tasks/index"); ?>
                            </div>

                            <?php if (can_access_reminders_module()) { ?>
                                <div class="card reminders-card" id="proposal-reminders">
                                    <div class="card-header fw-bold">
                                        <i data-feather="clock" class="icon-16"></i> &nbsp;<?php echo app_lang("reminders") . " (" . app_lang('private') . ")"; ?>
                                    </div>
                                    <div class="card-body">
                                        <?php echo view("reminders/reminders_view_data", array("proposal_id" => $proposal_info->id, "hide_form" => true, "reminder_view_type" => "proposal")); ?>
                                    </div>
                                </div>
                            <?php } ?>

                            <?php if (get_setting("enable_comments_on_proposals") && !($proposal_info->status === "draft")) { ?>
                                <?php echo view("proposals/comment_form"); ?>
                            <?php } ?>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    //RELOAD_VIEW_AFTER_UPDATE = true;
    $(document).ready(function() {
        var optionVisibility = false;
        <?php if ($is_proposal_editable) { ?>
            optionVisibility = true;
        <?php } ?>

        $("#proposal-item-table").appTable({
            source: '<?php echo_uri("proposals/item_list_data/" . $proposal_info->id . "/") ?>',
            order: [
                [0, "asc"]
            ],
            hideTools: true,
            displayLength: 100,
            stateSave: false,
            columns: [{
                    visible: false,
                    searchable: false
                },
                {
                    title: "<?php echo app_lang("item") ?> ",
                    "class": "all",
                    sortable: false
                },
                {
                    title: "<?php echo app_lang("quantity") ?>",
                    "class": "text-right w15p",
                    sortable: false
                },
                {
                    title: "<?php echo app_lang("rate") ?>",
                    "class": "text-right w15p",
                    sortable: false
                },
                {
                    title: "<?php echo app_lang("total") ?>",
                    "class": "text-right w15p all",
                    sortable: false
                },
                {
                    title: "<i data-feather='menu' class='icon-16'></i>",
                    "class": "text-center option w100",
                    sortable: false,
                    visible: optionVisibility
                }
            ],

            onInitComplete: function() {
                <?php if ($is_proposal_editable) { ?>
                    //apply sortable
                    $("#proposal-item-table").find("tbody").attr("id", "proposal-item-table-sortable");
                    var $selector = $("#proposal-item-table-sortable");

                    Sortable.create($selector[0], {
                        animation: 150,
                        handle: '.move-icon',
                        chosenClass: "sortable-chosen",
                        ghostClass: "sortable-ghost",
                        onUpdate: function(e) {
                            appLoader.show();
                            //prepare sort indexes 
                            var data = "";
                            $.each($selector.find(".item-row"), function(index, ele) {
                                if (data) {
                                    data += ",";
                                }

                                data += $(ele).attr("data-id") + "-" + index;
                            });

                            //update sort indexes
                            appAjaxRequest({
                                url: '<?php echo_uri("proposals/update_item_sort_values") ?>',
                                type: "POST",
                                data: {
                                    sort_values: data
                                },
                                success: function() {
                                    appLoader.hide();
                                }
                            });
                        }
                    });
                <?php } ?>
            },

            onDeleteSuccess: function(result) {
                $("#proposal-total-section").html(result.proposal_total_view);
            },
            onUndoSuccess: function(result) {
                $("#proposal-total-section").html(result.proposal_total_view);
            }
        });

        $("body").on("click", "#proposal-save-and-show-btn", function() {
            $(this).trigger("submit");
            $("#proposal-preview-btn")[0].click();
        });

        setTimeout(function() {
            $(".hidden-input-field").focus();
            $(".hidden-input-field").remove();
        });

        //make sticky when scroll
        var editorToolbar = $(".preview-editor-button-group, .note-toolbar");

        $(".scrollable-page").scroll(function() {
            var offset = editorToolbar.offset().top;

            if ($(window).scrollTop() > offset) {
                editorToolbar.addClass("sticky-top");
                $(".note-toolbar").css("top", 52);
                $(".note-toolbar").css("box-shadow", "rgba(0, 0, 0, 0.1) 0 3px 6px -3px");
            }
        });

        appContentBuilder.init("<?php echo get_uri('proposals/view/' . $proposal_info->id); ?>", {
            id: "proposal-details-page-builder",
            data: {
                view_type: "proposal_meta"
            },
            reloadHooks: [{
                type: "app_form",
                id: "send-proposal-form"
            }],
            reload: function(bind, result) {
                bind("#proposal-details-top-bar", result.top_bar);
            }
        });

        //initialize mobile view layout
        initMobileViewLayout();
    });
</script>

<?php echo view("proposals/print_proposal_helper_js"); ?>