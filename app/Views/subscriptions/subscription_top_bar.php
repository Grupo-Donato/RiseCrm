<div id="subscription-top-bar" class="details-view-status-section mb20">
    <div class="page-title no-bg clearfix no-border">
        <h1 class="pl0">
            <span><i data-feather="repeat" class='icon'></i></span>
            <?php echo get_subscription_id($subscription_info->id) . ": " . $subscription_info->title; ?>
        </h1>

        <div class="title-button-group mr0 hidden-xs">
            <?php
            if ($subscription_info->type === "stripe") {
                if ($subscription_status !== "cancelled" && $subscription_status !== "active" && !$subscription_info->stripe_subscription_id && get_setting("enable_stripe_subscription")) {
                    echo modal_anchor(get_uri("subscriptions/activate_as_stripe_subscription_modal_form/" . $subscription_info->id), "<i data-feather='credit-card' class='icon-16'></i> " . app_lang('activate'), array("title" => app_lang('activate_as_stripe_subscription'), "data-post-id" => $subscription_info->id, "class" => "btn btn-primary"));
                }
            } else {
                if ($subscription_status == "draft" && $subscription_status !== "cancelled" && $subscription_info->type === "app") {
                    echo modal_anchor(get_uri("subscriptions/activate_as_internal_subscription_modal_form/" . $subscription_info->id), "<i data-feather='check' class='icon-16'></i> " . app_lang('activate'), array("title" => app_lang("activate_as_internal_subscription"), "data-post-id" => $subscription_info->id, "class" => "btn btn-primary"));
                }
            }
            ?>
        </div>
    </div>

    <?php
    echo $subscription_status_label;

    $subscription_labels = make_labels_view_data($subscription_info->labels_list, false, true, "rounded-pill");

    $labels = $can_edit_subscriptions ? "<span class='text-off ml10 mr10'>" . app_lang("add") . " " . app_lang("label") . "<span>" : "";

    if (isset($subscription_labels) && $subscription_labels) {
        $labels = $subscription_labels;
    }

    if ($can_edit_subscriptions) {
        echo js_anchor($labels, array(
            'title' => "",
            "class" => "mr5",
            "data-id" => $subscription_info->id,
            "data-value" => $subscription_info->labels,
            "data-act" => "subscription-modifier",
            "data-modifier-group" => "subscription_info",
            "data-field" => "labels",
            "data-multiple-tags" => "1",
            "data-action-url" => get_uri("subscriptions/update_subscription_info/$subscription_info->id/labels")
        ));
    } else {
        echo $labels;
    }

    if ($subscription_info->status == "active" && $subscription_info->next_recurring_date) {
        echo "<span class='badge rounded-pill large text-default b-a' title='" . app_lang("next_billing_date") . "'> <i data-feather='refresh-cw' class='icon-14 text-off '></i>" . format_to_date($subscription_info->next_recurring_date, false) . "</span>";
    }
    ?>
</div>