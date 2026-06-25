<?php echo view('includes/intl_tel_input_js'); ?>
<?php echo form_open(get_uri("settings/save_ui_options_settings"), array("id" => "ui-options-form", "class" => "general-form dashed-row", "role" => "form")); ?>

<div class="card-body">

    <?php if (get_setting("disable_html_input")) { ?>
        <!--flag the enable_rich_text_editor as disabled, when the disable_html_input is enabled-->
        <input type="hidden" name="enable_rich_text_editor" value="0" />
    <?php } else { ?>
        <div class="form-group form-switch">
            <div class="row">
                <label for="enable_rich_text_editor" class=" col-md-3"><?php echo app_lang('enable_rich_text_editor'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_checkbox("enable_rich_text_editor", "1", get_setting("enable_rich_text_editor") ? true : false, "id='enable_rich_text_editor' class='form-check-input'");
                    ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="form-group form-switch">
        <div class="row">
            <label for="enable_audio_recording" class=" col-md-3">
                <?php echo app_lang('enable_audio_recording'); ?>
                <span class="help" data-container="body" data-bs-toggle="tooltip" title="<?php echo app_lang('https_required') ?>"><i data-feather="help-circle" class="icon-16"></i></span>
            </label>
            <div class="col-md-9">
                <?php
                echo form_checkbox("enable_audio_recording", "1", get_setting("enable_audio_recording") ? true : false, "id='enable_audio_recording' class='form-check-input'");
                ?>
            </div>
        </div>
    </div>

    <div class="form-group form-switch">
        <div class="row">
            <label for="show_theme_color_changer" class=" col-md-3"><?php echo app_lang('show_theme_color_changer'); ?></label>
            <div class="col-md-9">
                <?php
                echo form_checkbox("show_theme_color_changer", "yes", get_setting("show_theme_color_changer") ? true : false, "id='show_theme_color_changer' class='form-check-input'");
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="rows_per_page" class=" col-md-3"><?php echo app_lang('rows_per_page'); ?></label>
            <div class="col-md-9">
                <?php
                echo form_dropdown(
                    "rows_per_page",
                    array(
                        "10" => "10",
                        "25" => "25",
                        "50" => "50",
                        "100" => "100",
                    ),
                    get_setting('rows_per_page'),
                    "class='select2 mini'"
                );
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="scrollbar" class=" col-md-3"><?php echo app_lang('scrollbar'); ?></label>
            <div class="col-md-9">
                <?php
                echo form_dropdown(
                    "scrollbar",
                    array(
                        "native" => "Native",
                        "jquery" => "jQuery"

                    ),
                    get_setting('scrollbar'),
                    "class='select2 mini'"
                );
                ?>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="row">
            <label for="filter_bar" class=" col-md-3"><?php echo app_lang('filter_bar'); ?></label>
            <div class="col-md-9">
                <?php
                echo form_dropdown(
                    "filter_bar",
                    array(
                        "" => app_lang("keep_filter_bar_collapsed"),
                        "expanded_until_saved_filter_selected" => app_lang("keep_filter_bar_expanded_until_any_saved_filter_is_selected"),
                        "always_expanded" => app_lang("keep_filter_bar_always_expanded")
                    ),
                    get_setting('filter_bar'),
                    "class='select2'"
                );
                ?>
            </div>
        </div>
    </div>

    <?php if (get_setting("module_chat")) { ?>
        <div class="form-group form-switch">
            <div class="row">
                <label for="hide_chat_icon" class=" col-md-3"><?php echo app_lang('hide_chat_icon'); ?></label>
                <div class="col-md-9">
                    <?php
                    echo form_checkbox("hide_chat_icon", "yes", get_setting("hide_chat_icon") ? true : false, "id='hide_chat_icon' class='form-check-input'");
                    ?>
                </div>
            </div>
        </div>
    <?php } ?>

    <div class="form-group">
        <div class="row">
            <label for="phone_input_default_country" class=" col-md-3"><?php echo app_lang('phone_input_default_country'); ?></label>
            <div class="col-md-9">
                <?php
                echo form_dropdown(
                    "phone_input_default_country",
                    array(),
                    "",
                    "class='select2' id='phone_input_default_country' data-selected='" . get_setting("phone_input_default_country") . "'"
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

<script type="text/javascript">
    $(document).ready(function() {
        $("#ui-options-form .select2").select2();

        $("#ui-options-form").appForm({
            isModal: false,
            onSuccess: function(result) {
                if (result.success) {
                    appAlert.success(result.message, {
                        duration: 10000
                    });
                } else {
                    appAlert.error(result.message);
                }
            }
        });

        $('[data-bs-toggle="tooltip"]').tooltip();

        // Populate the country dropdown
        var countryData = window.intlTelInput.getCountryData();
        var $countryDropdown = $("#phone_input_default_country");

        var savedCountry = $countryDropdown.data("selected") || "us";

        $countryDropdown.empty();

        $.each(countryData, function(index, country) {
            var isSelected = savedCountry === country.iso2 ? 'selected' : '';
            var option = `<option value="${country.iso2}" ${isSelected}>${country.name}</option>`;
            $countryDropdown.append(option);
        });

        $countryDropdown.select2();
    });
</script>