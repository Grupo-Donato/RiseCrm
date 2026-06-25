<div class="clearfix default-bg details-view-container">
    <?php if ($estimate_info->estimate_request_id > 0 && isset($estimate_request_form_info)) { ?>
        <div class="card" id="estimate-request-card">
            <div class="card-header fw-bold">
                <span class="d-inline-block mt-1"><i data-feather="list" class="icon-16 mr5"></i><?php echo get_estimate_request_id($estimate_info->estimate_request_id); ?></span>
                <div class="float-end">
                    <div class="action-option light js-cookie-button" data-bs-toggle="collapse" data-bs-target="#estimate-request-content" aria-expanded="true" aria-controls="estimate-request-content">
                        <i data-feather="chevron-right" class="icon-16"></i>
                    </div>
                </div>
            </div>
            <div class="collapse show" id="estimate-request-content">
                <div class="card-body">
                    <div class="clearfix pl10 pr10">
                        <div id="estimate-form-title" class="strong"> <?php echo $estimate_request_form_info->title; ?></div>
                        <div><?php echo custom_nl2br($estimate_request_form_info->description ? process_images_from_content($estimate_request_form_info->description) : ""); ?></div>

                        <div class="table-responsive mt20 general-form">
                            <table id="estimate-request-table" class="display no-thead b-t no-hover border-bottom-0" cellspacing="0" width="100%">
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="card p15 w-100">
        <div id="page-content" class="clearfix">
            <div style="max-width: 1000px; margin: auto;">
                <div>
                    <div class="clearfix p20">
                        <!-- small font size is required to generate the pdf, overwrite that for screen -->
                        <style type="text/css">
                            .invoice-meta {
                                font-size: 100% !important;
                            }
                        </style>

                        <?php
                        $color = get_setting("estimate_color");
                        if (!$color) {
                            $color = get_setting("invoice_color");
                        }
                        $style = get_setting("invoice_style");
                        ?>
                        <?php
                        $data = array(
                            "client_info" => $client_info,
                            "color" => $color ? $color : "#2AA384",
                            "estimate_info" => $estimate_info
                        );

                        if ($style === "style_3") {
                            echo view('estimates/estimate_parts/header_style_3.php', $data);
                        } else if ($style === "style_2") {
                            echo view('estimates/estimate_parts/header_style_2.php', $data);
                        } else {
                            echo view('estimates/estimate_parts/header_style_1.php', $data);
                        }
                        ?>

                    </div>

                    <div class="table-responsive mt15 pl15 pr15">
                        <table id="estimate-item-table" class="display" width="100%">
                        </table>
                    </div>

                    <div class="clearfix">
                        <?php if ($is_estimate_editable && $can_edit_estimates) { ?>
                            <div class="float-start mt20 ml15">
                                <?php echo modal_anchor(get_uri("estimates/item_modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_item'), array("class" => "btn btn-primary text-white add-item-btn", "title" => app_lang('add_item'), "data-post-estimate_id" => $estimate_info->id)); ?>
                            </div>
                        <?php } ?>
                        <div class="float-end pr15" id="estimate-total-section">
                            <?php echo view("estimates/estimate_total_section", array("is_estimate_editable" => $is_estimate_editable)); ?>
                        </div>
                    </div>

                    <p class="b-t b-info pt10 m15 pb10"><?php echo custom_nl2br($estimate_info->note ? process_images_from_content($estimate_info->note) : ""); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#estimate-request-table").appTable({
            source: '<?php echo_uri("estimate_requests/estimate_request_filed_list_data/" . $estimate_info->estimate_request_id) ?>',
            order: [
                [1, "asc"]
            ],
            hideTools: true,
            displayLength: 100,
            columns: [{
                    title: '<?php echo app_lang("title") ?>'
                },
                {
                    visible: false
                }
            ],
            onInitComplete: function() {
                $(".dataTables_empty").hide();
            }
        });

        // Set cookie for estimate request items list
        var userID = "<?php echo $login_user->id; ?>",
            widgetCookieName = "estimate_view_estimate_request_items_list_" + userID,
            $widgetContent = $("#estimate-request-content"),
            $toggleButton = $(".js-cookie-button"),
            $widgetContainer = $("#estimate-request-card"),
            $cardHeader = $widgetContainer.find(".card-header");

        var widgetVisibility = getCookie(widgetCookieName);

        // If no cookie is set (first visit), or it's "visible", show the widget
        if (!widgetVisibility || widgetVisibility === "visible") {
            $widgetContent.addClass("show");
            $toggleButton.removeClass("collapsed");
            $cardHeader.removeClass("rounded");
        } else {
            $widgetContent.removeClass("show");
            $toggleButton.addClass("collapsed");
            $cardHeader.addClass("rounded");
        }

        $widgetContent.on("shown.bs.collapse", function() {
            setCookie(widgetCookieName, "visible");
            $cardHeader.removeClass("rounded");
        });

        $widgetContent.on("hidden.bs.collapse", function() {
            setCookie(widgetCookieName, "hidden");
            $cardHeader.addClass("rounded");
        });
    });
</script>