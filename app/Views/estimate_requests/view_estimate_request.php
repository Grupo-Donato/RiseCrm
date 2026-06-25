<div class="details-view-top-button clearfix">
    <?php echo view("includes/back_button", array("button_url" => get_uri("estimate_requests/index"), "button_text" => app_lang("estimate_requests"), "extra_class" => "float-start dark")); ?>
</div>

<div class="page-content estimate-request-details-view clearfix xs-full-width">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div id="estimate-request-details-top-bar">
                    <?php echo view("estimate_requests/estimate_request_top_bar"); ?>
                </div>

                <div class="details-view-wrapper d-flex">
                    <div class="w-100">
                        <?php echo view("estimate_requests/estimate_request_form_content"); ?>
                    </div>
                    <div class="flex-shrink-0 details-view-right-section">
                        <div id="estimate-request-info">
                            <?php echo view("estimate_requests/estimate_request_info"); ?>
                        </div>

                        <?php if ($lead_info) { ?>
                            <?php echo view("estimate_requests/estimate_request_lead_info"); ?>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        appContentBuilder.init("<?php echo get_uri('estimate_requests/view_estimate_request/' . $model_info->id); ?>", {
            id: "estimate-request-details-page-builder",
            data: {
                view_type: "estimate_request_meta"
            },
            reloadHooks: [{
                type: "app_modifier",
                group: "estimate_request_info"
            }],
            reload: function(bind, result) {
                bind("#estimate-request-details-top-bar", result.top_bar);
                bind("#estimate-request-info", result.estimate_request_info);
            }
        });

        <?php if ($show_actions) { ?>
            $('body').on('click', '[data-act=estimate-request-modifier]', function(e) {
                $(this).appModifier({
                    dropdownData: {
                        assigned_to: <?php echo json_encode($assign_to_dropdown); ?>
                    }
                });

                return false;
            });
        <?php } ?>

        //initialize mobile view layout
        initMobileViewLayout();
    });
</script>