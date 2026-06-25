<?php

namespace App\Libraries;

use App\Controllers\App_Controller;

class Reminders {

    private $ci;
    private $today = null;

    public function __construct() {
        $this->ci = new App_Controller();
        $this->today = get_today_date();
    }

    function create_reminder_logs($context) {
        $reminders_info = $this->ci->Reminder_settings_model->get_reminders_by_context($context);

        $weekly_dates = [];
        $monthly_dates = [];
        $yearly_dates = [];

        foreach ($reminders_info as $reminder_info) {
            if ($reminder_info->reminder_event == "subscription_weekly_reminder") {
                if ($reminder_info->reminder1) {
                    $weekly_dates[] = add_period_to_date($this->today, $reminder_info->reminder1, "days");
                }
                if ($reminder_info->reminder2) {
                    $weekly_dates[] = add_period_to_date($this->today, $reminder_info->reminder2, "days");
                }
            } else if ($reminder_info->reminder_event == "subscription_monthly_reminder") {
                if ($reminder_info->reminder1) {
                    $monthly_dates[] = add_period_to_date($this->today, $reminder_info->reminder1, "days");
                }
                if ($reminder_info->reminder2) {
                    $monthly_dates[] = add_period_to_date($this->today, $reminder_info->reminder2, "days");
                }
            } else if ($reminder_info->reminder_event == "subscription_yearly_reminder") {
                if ($reminder_info->reminder1) {
                    $yearly_dates[] = add_period_to_date($this->today, $reminder_info->reminder1, "days");
                }
                if ($reminder_info->reminder2) {
                    $yearly_dates[] = add_period_to_date($this->today, $reminder_info->reminder2, "days");
                }
            }
        }

        $reminders = array();

        if ($context == "subscription") {
            $reminders = $this->ci->Subscriptions_model->get_subscriptions_to_send_reminder(array(
                "status" => "active",
                "weekly_dates" => implode(',', $weekly_dates),
                "monthly_dates" => implode(',', $monthly_dates),
                "yearly_dates" => implode(',', $yearly_dates),
                "exclude_reminder_date" => $this->today
            ))->getResult();
        }

        foreach ($reminders as $reminder) {
            $data = array(
                "context" => $context,
                "context_id" => $reminder->id,
                "reminder_date" => $this->today,
            );

            if ($context == "subscription") {
                $data["reminder_event"] = "subscription_renewal_reminder";
            }

            $this->ci->Reminder_logs_model->ci_save($data);
        }
    }

    function send_available_reminders() {

        // send subscription reminders differently
        $available_reminders = $this->ci->Reminder_logs_model->get_details(array("notification_status" => "draft", "contexts" => array("subscription")))->getResult();

        foreach ($available_reminders as $available_reminder) {
            // Create dynamic key based on the context
            $context_key = $available_reminder->context . "_id";
            $notification_data = array($context_key => $available_reminder->context_id, "reminder_log_id" => $available_reminder->id);

            log_notification($available_reminder->reminder_event, $notification_data, "0");
        }

        // send other reminders
        $reminder_end_date_time = get_my_local_time();
        $reminder_start_date_time = subtract_period_from_date($reminder_end_date_time, 15, "minutes", "Y-m-d H:i:s");

        $available_reminders = $this->ci->Reminder_logs_model->get_details(array("notification_status" => "draft", "contexts" => array("event", "reminder"), "reminder_start_date_time" => $reminder_start_date_time, "reminder_end_date_time" => $reminder_end_date_time))->getResult();

        foreach ($available_reminders as $available_reminder) {
            // Create dynamic key based on the context
            $context_key = $available_reminder->context . "_id";
            $notification_data = array($context_key => $available_reminder->context_id, "reminder_log_id" => $available_reminder->id, "notify_to" => $available_reminder->notify_to);

            log_notification($available_reminder->reminder_event, $notification_data, "0");

            $this->_create_logs_for_next_recurring_event($available_reminder);
        }
    }

    private function _create_logs_for_next_recurring_event($available_reminder) {
        // check if there is any future recurring event or reminder
        if (!($available_reminder->context == "event" || $available_reminder->context == "reminder")) {
            return false;
        }

        $event_info = $this->ci->Events_model->get_one($available_reminder->context_id);
        if (!($event_info->recurring && $event_info->recurring_dates)) {
            return false;
        }

        $recurring_dates = explode(',', $event_info->recurring_dates);

        foreach ($recurring_dates as $recurring_date) {
            if ($recurring_date > $this->today) {

                $new_reminder_log_data = (array) $available_reminder;
                unset($new_reminder_log_data["id"]);
                $new_reminder_log_data["reminder_date"] = $recurring_date;

                $this->ci->Reminder_logs_model->ci_save($new_reminder_log_data);

                break;
            }
        }
    }

    function create_or_update_early_reminder_of_events($event_info, $old_event_info) {
        // if the event is not modified, do not create or update the early reminder
        if (
            $old_event_info->id &&
            $old_event_info->start_date == $event_info->start_date &&
            $old_event_info->start_time == $event_info->start_time &&
            $old_event_info->share_with == $event_info->share_with
        ) {
            return;
        }

        // prepare the settings separately to reduce server cost
        $global_reminder_setting = "";
        $user_wise_reminder_setting = array();
        $reminder_settings = $this->ci->Reminder_settings_model->get_reminders_by_context($event_info->type);

        foreach ($reminder_settings as $reminder_setting) {

            if ($reminder_setting->user_id) {
                $user_wise_reminder_setting[$reminder_setting->user_id] = $reminder_setting->reminder1;
            } else {
                $global_reminder_setting = $reminder_setting->reminder1;
            }
        }

        // delete the existing reminders first
        $this->ci->Reminder_logs_model->delete_logs_of_this_context($event_info->type, $event_info->id);

        $event_time = $event_info->start_date . " " . $event_info->start_time;
        $reminders = array();

        // Get the appropriate user IDs to process
        $shared_users = $this->ci->Events_model->get_share_with_users_of_event($event_info, false, true);
        $user_ids = $shared_users ? array_column($shared_users->getResult(), 'id') : [$event_info->created_by];

        foreach ($user_ids as $user_id) {
            // Get reminder setting - first check user-specific, then global
            $reminder_setting = get_array_value($user_wise_reminder_setting, $user_id) ? get_array_value($user_wise_reminder_setting, $user_id) : $global_reminder_setting;

            if (!$reminder_setting) {
                if (!$shared_users) {
                    // If no shared users and no reminder setting, exit early
                    return;
                }
                continue;
            }

            // Calculate reminder time based on the setting
            $reminder_time = $this->_prepare_reminder_time_based_on_setting($event_time, $reminder_setting);
            $reminders[$reminder_time][] = $user_id;
        }

        foreach ($reminders as $reminder_time => $user_ids) {

            $reminder_time = explode(" ", $reminder_time);
            $reminder_logs_data = array(
                "context" => $event_info->type,
                "context_id" => $event_info->id,
                "reminder_date" => get_array_value($reminder_time, 0),
                "reminder_time" => get_array_value($reminder_time, 1),
                "notify_to" => implode(',', $user_ids),
                "reminder_event" => "upcoming_" . $event_info->type
            );

            $this->ci->Reminder_logs_model->ci_save($reminder_logs_data);
        }
    }

    private function _prepare_reminder_time_based_on_setting($event_time, $reminder_setting) {
        $explode_early_reminder_of_events_before = explode('_', $reminder_setting);
        $no_of = get_array_value($explode_early_reminder_of_events_before, 0);
        $period_type = get_array_value($explode_early_reminder_of_events_before, 1);

        $reminder_time = subtract_period_from_date($event_time, $no_of, $period_type, "Y-m-d H:i:s");
        return $reminder_time;
    }

    function save_early_reminder_data($value, $context, $user_id) {
        $data = array(
            "context" => $context,
            "reminder_event" => "early_reminder",
            "reminder1" => $value,
            "type" => "user",
            "user_id" => $user_id
        );

        $reminder_info = $this->ci->Reminder_settings_model->get_details(array("context" => $context, "reminder_event" => "early_reminder", "user_id" => $user_id))->getRow();

        if ($reminder_info) {
            $this->ci->Reminder_settings_model->ci_save($data, $reminder_info->id);
        } else if ($value) {
            $this->ci->Reminder_settings_model->ci_save($data);
        }
    }
}
