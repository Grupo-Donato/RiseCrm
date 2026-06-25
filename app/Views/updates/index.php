<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php
            $tab_view['active_tab'] = "updates";
            echo view("settings/tabs", $tab_view);
            ?>
        </div>

        <div class="col-sm-9 col-lg-10">
            <div class="card">
                <div class="page-title clearfix">
                    <h4> <?php echo "App " . app_lang('updates'); ?></h4>
                    <div class="title-button-group">
                        <?php echo anchor("Updates/systeminfo",  "<i data-feather='package' class='icon-16 mr5'></i>" . "Php Info", array("class" => "btn btn-warning php-info-btn", "target" => "_blank")); ?>
                        <a href='https://risedocs.fairsketch.com/doc/view/56' class='btn btn-info text-white' target='_blank'><i data-feather='help-circle' class='icon-16 mr5'></i><?php echo app_lang('help'); ?></a>
                    </div>
                </div>

                <div id="app-update-container" class="card-body font-14">
                    <?php

                    $current_version_info = "<p><strong>" . app_lang("current_version") . ": " . $current_version . "</strong></p>";

                    if (count($installable_updates) || count($downloadable_updates)) { ?>

                        <div class="alert alert-warning" role="alert">
                            <i data-feather='alert-triangle' class='icon-16'></i> Before updating, please <strong>backup all files and database</strong> to avoid any accidental data loss.
                        </div>

                    <?php

                        echo $current_version_info;

                        $count = 1;
                        foreach ($installable_updates as $salt => $version) {

                            if ($count > 1) {
                                echo "<p>Click here to Install the version - <b>$version</b></p>";
                            } else {
                                echo "<p><a class='do-update' data-version='$version' href='#'>Click here to Install the version - <b>$version</b></a></p>";
                            }

                            $count++;
                        }
                        foreach ($downloadable_updates as $salt => $version) {
                            $count++;
                            echo "<p class='download-updates' data-salt='$salt' data-version='$version'>Version - <b>$version</b> available, awaiting for download.</p>";
                        }
                    } else {

                        echo $current_version_info;

                        echo "<p>No updates found.</p>";
                    }

                    if ($current_version === "3.4") {
                        //check session configs between App.php and Session.php
                        //both configs should be same
                        $app_config = config("App");
                        $session_config = config("Session");
                        if (!($app_config->sessionDriver === $session_config->driver && $app_config->sessionSavePath === $session_config->savePath)) {
                            echo "<div class='alert alert-warning'>There has custom session configurations in ...app/Config/App.php file. Please add these configurations to the ...app/Config/Session.php file.</div>";
                        }
                    }
                    ?>

                </div>

            </div>
            <?php if ($supported_until) { ?>
                <div class="card support-info-card">
                    <div class="card-header">
                        <i data-feather='life-buoy' class='icon-16 mr5'></i>
                        <span class="fw-bold"><?php echo app_lang("support_info"); ?></span>
                    </div>

                    <div class="card-body">
                        <div>
                            <?php
                            if ($supported_until) {
                                if ($has_support) {
                                    echo "Support available till <strong>$supported_until</strong> <span class='badge bg-primary ml5'>Supported</span> ";
                                } else {
                                    echo "<p>Support expired on <strong>$supported_until</strong> <span class='badge bg-danger ml5'>Support Expired</span> </p>";
                                    echo "<p>To purchase support, please visit <a href='https://codecanyon.net/item/rise-ultimate-project-manager/15455641'>here</a>.</p>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="card license-card">
                <div class="card-header">
                    <i data-feather='file-text' class='icon-16 mr5'></i>
                    <span class="fw-bold"><?php echo "License"; ?></span>
                </div>


                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">

                            <?php if ($license_error) {
                                if ($installation_disabled) {
                                    echo "<div class='alert alert-danger'><i data-feather='alert-triangle' class='icon-16'></i> You've disabled the license for this site.</div>";
                                } else {
                                    echo "<div class='alert alert-danger'><i data-feather='alert-triangle' class='icon-16'></i> " . $license_error . "</div>";
                                }
                            } ?>

                            <?php if (isset($last4_digits_of_purchase_code) && $last4_digits_of_purchase_code) { ?>
                                <div class="alert alert-secondary"><i data-feather='credit-card' class='icon-16'></i> <?php echo "Current purchase code ......" . $last4_digits_of_purchase_code; ?></div>
                            <?php } ?>

                            <?php echo form_open(get_uri("settings/save_item_purchase_code"), array("id" => "item-purchase-code-form", "class" => "general-form", "role" => "form")); ?>
                            <div class="form-group">
                                <div class="row mt15">
                                    <label class="col-md-12" for="item_purchase_code"><?php echo app_lang('item_purchase_code'); ?></label>
                                    <div class="col-md-12">
                                        <?php
                                        echo form_input(array(
                                            "id" => "item_purchase_code",
                                            "name" => "item_purchase_code",
                                            "value" => get_setting('item_purchase_code') ? "******" : "",
                                            "class" => "form-control",
                                            "placeholder" => "RISE Purchase Code",
                                            "data-rule-required" => true,
                                            "data-msg-required" => app_lang("field_required"),
                                        ));
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>

                            <?php if (!$license_error) {
                                //    echo anchor(get_uri("Updates/verify"), "Verify", array("class" => "btn btn-success ml10"));
                            } ?>

                            <?php echo form_close(); ?>
                        </div>

                        <div class="col-md-6">
                            <div class="b-l pl15">
                                <?php
                                if (get_setting("disable_installation") == "1") {
                                    echo "<p>If you want to use the license in this site, please enable the license.</p>";
                                    echo js_anchor("Enable License", array("class" => "btn btn-success mt10", "title" => "Enable License", "data-action-url" => get_uri("Updates/enable_installation"), 'data-reload-on-success' => true, "data-action" => "delete-confirmation", "id" => "enable-installation-btn", "data-bypass-submit" => "enableInstallationBypassSubmit"));
                                } else {
                                    if (!$license_error) {
                                        echo "<p><b>Move to another site?</b><br> To use this purchase code on another site, please disable the license on this site first.</p>";
                                        echo js_anchor("Disable License", array("class" => "btn btn-danger mt10", "title" => "Disable License", "data-action-url" => get_uri("Updates/disable_installation"), 'data-reload-on-success' => true, "data-action" => "delete-confirmation", "id" => "disable-installation-btn"));
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>



        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var startDownload = function() {
            var $link = $(".download-updates").first(),
                version = $link.attr("data-version"),
                salt = $link.attr("data-salt");

            if ($link.length) {
                $link.replaceWith("<p class='downloading downloading-" + version + "'><span class='download-loader spinning-btn spinning'></span> Downloading the version - <b>" + version + "</b>. Please wait...</p>");
                appAjaxRequest({
                    url: "<?php echo_uri("Updates/download_updates/"); ?>" + version,
                    dataType: "json",
                    success: function(response) {
                        if (response.success) {
                            $(".downloading").html("<a class='do-update' data-version='" + version + "' href='#'>Click here to Install the version - <b>" + version + "</b></a>").removeClass("downloading");
                            startDownload();
                        } else {
                            $(".downloading").html("<p>" + response.message + "</p>").removeClass("downloading").addClass("alert alert-danger");
                        }
                    }
                });
            }
        };
        startDownload();


        $('body').on('click', '.do-update', function() {
            var version = $(this).attr("data-version");
            var acknowledged = $(this).attr("data-acknowledged");
            if (!acknowledged) {
                acknowledged = 0;
            }
            $("#app-update-container").html("<h3><span class='download-loader-lg spinning-btn spinning'></span> Installing version - " + version + ". Please wait... </h3>");
            appAjaxRequest({
                url: "<?php echo_uri("Updates/do_update/"); ?>" + version + "/" + getFileHash(version) + "/" + acknowledged,
                dataType: "json",
                success: function(response) {
                    $("#app-update-container").html("");
                    if (response.response_type === "success") {
                        appAlert.success(response.message, {
                            container: "#app-update-container",
                            animate: false
                        });
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else if (response.response_type === "acknowledgement_required") {
                        appAlert.info(response.message, {
                            container: "#app-update-container",
                            animate: false
                        });
                        $("#app-update-container").append("<p><a class='do-update' data-acknowledged='1' data-version='" + version + "' href='#'>I understand, install the version - <b>" + version + "</b></a></p>");
                    } else {
                        appAlert.error(response.message, {
                            container: "#app-update-container",
                            animate: false
                        });
                    }

                }
            });
        });

        $("#item-purchase-code-form").appForm({
            isModal: false,
            onSuccess: function(result) {
                window.location.href = "<?php echo_uri('Updates/verify'); ?>";
            }
        });

        //modify the delete confirmation texts
        $("#disable-installation-btn").click(function() {
            $("#confirmationModal").find(".modal-dialog").css({
                "max-width": "510px"
            });
            $("#confirmationModalTitle").html("Disable License");
            $("#confirmDeleteButton").html("Yes, Disable");
            $("#confirmationModalContent .container-fluid").html("Disabling the license will deactivate functionalities of this site. Please note that each license can be disabled a maximum of 3 times. Are you sure you want to proceed with disabling this license?");
            feather.replace();
        });

        //modify the delete confirmation texts
        $("#enable-installation-btn").click(function() {
            $("#confirmationModal").find(".modal-dialog").css({
                "max-width": "510px"
            });
            $("#confirmationModalTitle").html("Enable License");
            $("#confirmDeleteButton").removeClass("btn-danger").addClass("btn-success").html("Yes, Enable");
            $("#confirmationModalContent .container-fluid").html("Please note that each license can be used on only one site. Are you sure you want to enable the license for this site?");
            feather.replace();
        });

    });

    function enableInstallationBypassSubmit() {
        window.location.href = "<?php echo_uri('Updates/verify'); ?>";
    }
</script>