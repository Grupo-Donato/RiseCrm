<div class="card">
    <div class="card-header fw-bold">
        <span class="inline-block mt-1">
            <i data-feather="file-plus" class="icon-16"></i> &nbsp;<?php echo app_lang("estimate_request_info"); ?>
        </span>

        <?php if ($show_actions) { ?>
            <div class="float-end">
                <div class="action-option" data-bs-toggle="dropdown" aria-expanded="true">
                    <i data-feather="more-horizontal" class="icon-16"></i>
                </div>
                <ul class="dropdown-menu" role="menu">
                    <?php
                    echo modal_anchor(get_uri("estimate_requests/edit_estimate_request_modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang('edit'), array("title" => app_lang('edit_estimate_request'), "data-post-view" => "details", "data-post-id" => $model_info->id, "class" => "dropdown-item"));

                    echo "<li role='presentation' class='dropdown-divider'></li>";

                    echo view("estimate_requests/estimate_request_status_options");
                    ?>
                </ul>
            </div>
        <?php } ?>
    </div>

    <div class="card-body">
        <ul class="list-group info-list">
            <li class="list-group-item">
                <span title="<?php echo app_lang("created_at"); ?>"><i data-feather="calendar" class="icon-16 mr5"></i> <?php echo format_to_datetime($model_info->created_at); ?></span>
            </li>

            <?php if ($show_client_info && $model_info->company_name) { ?>
                <li class="list-group-item">
                    <?php if ($model_info->is_lead) { ?>
                        <span title="<?php echo app_lang("lead"); ?>"><i data-feather="layers" class="icon-16 mr5"></i> <?php echo (anchor(get_uri("leads/view/" . $model_info->client_id), $model_info->company_name)); ?></span>
                    <?php } else { ?>
                        <span title="<?php echo app_lang("client"); ?>"><i data-feather="briefcase" class="icon-16 mr5"></i> <?php echo (anchor(get_uri("clients/view/" . $model_info->client_id), $model_info->company_name)); ?></span>
                    <?php } ?>
                </li>
            <?php } ?>

            <?php
            if ($login_user->user_type == "staff" && $estimates) {
                $estimate_lang = app_lang("estimate");
                if (count($estimates) > 1) {
                    $estimate_lang = app_lang("estimates");
                }
            ?>
                <li class="list-group-item">
                    <span><?php echo $estimate_lang . ": "; ?></span>

                    <?php
                    $last_estimate = end($estimates);
                    foreach ($estimates as $estimate) {
                        $seperation = ($estimate == $last_estimate) ? "" : ", ";
                        echo anchor(get_uri("estimates/view/" . $estimate->id), get_estimate_id($estimate->id)) . $seperation;
                    }
                    ?>
                </li>
            <?php } ?>
        </ul>
    </div>
</div>