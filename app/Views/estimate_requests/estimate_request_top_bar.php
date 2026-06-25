<div id="estimate-request-top-bar" class="details-view-status-section mb20">
    <div class="page-title no-bg clearfix mb5 no-border">
        <h1 class="pl0">
            <span><i data-feather="file-plus" class='icon'></i></span>
            <?php echo get_estimate_request_id($model_info->id); ?>
        </h1>

        <div class="title-button-group mr0">
            <?php
            if ($show_actions) {
                echo modal_anchor(get_uri("estimates/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_estimate'), array("title" => app_lang('add_estimate'), "data-post-estimate_request_id" => $model_info->id, "data-post-client_id" => $model_info->client_id, "class" => "btn btn-default"));

                if ($model_info->status === "new") {
                    echo ajax_anchor(get_uri("estimate_requests/change_estimate_request_status/$model_info->id/processing"), "<i data-feather='refresh-cw' class='icon-16'></i> " . app_lang('mark_as_processing'), array("class" => "btn btn-primary", "title" => app_lang('mark_as_processing'), "data-reload-on-success" => "1"));
                } else if ($model_info->status === "processing") {
                    echo ajax_anchor(get_uri("estimate_requests/change_estimate_request_status/$model_info->id/estimated"), "<i data-feather='check-circle' class='icon-16'></i> " . app_lang('mark_as_estimated'), array("class" => "btn btn-primary", "title" => app_lang('mark_as_estimated'), "data-reload-on-success" => "1"));
                }
            }
            ?>
        </div>
    </div>

    <?php
    echo $status;

    if ($show_assignee) {
        $image_url = get_avatar($model_info->assigned_to_avatar);
        $assigned_to_avatar = "<span class='avatar avatar-xxs mr5'><img id='estimate-request-assigned-to-avatar' src='$image_url' alt='...'></span>";

        echo js_anchor(
            $model_info->assigned_to_user ? $assigned_to_avatar . "<span class='hidden-sm'>" . $model_info->assigned_to_user . "</span>" : "<span class='text-off'>" . app_lang("add") . " " . app_lang("assignee") . "<span>",
            array(
                'title' => app_lang("assigned_to"),
                "class" => "estimate-request-assigned-to ml5",
                "data-id" => $model_info->id,
                "data-value" => $model_info->assigned_to,
                "data-act" => "estimate-request-modifier",
                "data-modifier-group" => "estimate_request_info",
                "data-field" => "assigned_to",
                "data-action-url" => get_uri("estimate_requests/update_estimate_request_info/$model_info->id/assigned_to")
            )
        );
    }

    $created_at = format_since_then($model_info->created_at, false);
    echo "<span class='badge rounded-pill large bg-transparent hidden-xs ml10' title='" . format_to_datetime($model_info->created_at) . "'>" . $created_at . "</span>";
    ?>
</div>