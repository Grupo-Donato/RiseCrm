<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php
            $tab_view['active_tab'] = "events";
            echo view("settings/tabs", $tab_view);
            ?>
        </div>

        <div class="col-sm-9 col-lg-10">
            <div class="card">

                <ul data-bs-toggle="ajax-tab" class="nav nav-tabs bg-white title scrollable-tabs" role="tablist">
                    <li><a role="presentation" data-bs-toggle="tab" href="javascript:;" data-bs-target="#event-settings-tab"> <?php echo app_lang('event_settings'); ?></a></li>
                    <li><a role="presentation" data-bs-toggle="tab" href="<?php echo_uri("settings/google_calendar"); ?>" data-bs-target="#google-calendar-tab">Google Calendar</a></li>
                </ul>

                <div class="tab-content">
                    <div role="tabpanel" class="tab-pane fade" id="event-settings-tab">
                        <?php echo form_open(get_uri("settings/save_event_settings"), array("id" => "event-settings-form", "class" => "general-form dashed-row", "role" => "form")); ?>

                        <div class="card-body">
                            <div class="form-group">
                                <div class="row">
                                    <label for="send_early_reminder_of_events_before" class=" col-md-3"><?php echo app_lang('send_early_reminder_before'); ?> <span class="help" data-bs-toggle="tooltip" title="<?php echo app_lang('cron_job_required') . " " . app_lang('send_early_reminder_of_events_before_help_message'); ?>"><i data-feather='help-circle' class="icon-16"></i></span></label>
                                    <div class=" col-md-9">
                                        <?php
                                        echo form_dropdown(
                                            "send_early_reminder_of_events_before",
                                            get_early_reminder_options_dropdown(),
                                            isset($reminder_info->reminder1) ? $reminder_info->reminder1 : "",
                                            "class='select2 mini'"
                                        );
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang('save'); ?></button>
                        </div>

                        <?php echo form_close(); ?>
                    </div>
                    <div role="tabpanel" class="tab-pane fade" id="google-calendar-tab"></div>

                </div>

            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $("#event-settings-form").appForm({
            isModal: false,
            onSuccess: function(result) {
                appAlert.success(result.message, {
                    duration: 10000
                });
            }
        });

        $('[data-bs-toggle="tooltip"]').tooltip();

        $("#event-settings-form .select2").select2();
    });
</script>