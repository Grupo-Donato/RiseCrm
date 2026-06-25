<?php $view_type = isset($view_type) && $view_type == "client" ? "client" : ""; ?>

<div class="card">
    <div class="box p15">
        <?php echo modal_anchor(get_uri("dashboard/custom_widget_modal_form/0/" . $view_type), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_widget'), array("class" => "btn btn-default col-md-12 block", "title" => app_lang('add_widget'))); ?>
    </div>

    <div class="add-column-panel js-widget-container p15 pt0" id="add-column-panel-1000000">
        <?php
        if ($widgets) {
            echo $widgets;
        }
        ?>
    </div>
</div>