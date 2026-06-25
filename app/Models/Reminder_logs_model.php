<?php

namespace App\Models;

class Reminder_logs_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = 'reminder_logs';
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $reminder_logs_table = $this->db->prefixTable('reminder_logs');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $reminder_logs_table.id=$id";
        }

        $notification_status = $this->_get_clean_value($options, "notification_status");
        if ($notification_status) {
            $where .= " AND $reminder_logs_table.notification_status='$notification_status'";
        }

        $reminder_start_date_time = $this->_get_clean_value($options, "reminder_start_date_time");
        if ($reminder_start_date_time) {

            $reminder_end_date_time = $this->_get_clean_value($options, "reminder_end_date_time");
            if ($reminder_end_date_time) {
                $where .= " AND CONCAT($reminder_logs_table.reminder_date, ' ', $reminder_logs_table.reminder_time) BETWEEN '$reminder_start_date_time' AND '$reminder_end_date_time' ";
            } else {
                $where .= " AND CONCAT($reminder_logs_table.reminder_date, ' ', $reminder_logs_table.reminder_time)<='$reminder_start_date_time' ";
            }
        }

        $contexts = $this->_get_clean_value($options, "contexts");

        if ($contexts && is_array($contexts)) {
            $contexts = implode(",", $contexts); //prepare comma separated value
            $where .= " AND FIND_IN_SET($reminder_logs_table.context, '$contexts')";
        }

        $sql = "SELECT $reminder_logs_table.*
                FROM $reminder_logs_table
                WHERE $reminder_logs_table.deleted=0 $where";

        return $this->db->query($sql);
    }

    function delete_logs_of_this_context($context, $context_id) {
        $reminder_logs_table = $this->db->prefixTable('reminder_logs');
        $context = $this->_get_clean_value($context);
        $context_id = $this->_get_clean_value($context_id);
        if (!$context || !$context_id) {
            return;
        }

        $sql = "DELETE FROM $reminder_logs_table WHERE $reminder_logs_table.context='$context' AND $reminder_logs_table.context_id=$context_id";
        $this->db->query($sql);
    }
}
