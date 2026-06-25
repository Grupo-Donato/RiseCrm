<?php

namespace App\Models;

class Reminder_settings_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = 'reminder_settings';
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $reminder_settings_table = $this->db->prefixTable('reminder_settings');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $reminder_settings_table.id=$id";
        }

        $context = $this->_get_clean_value($options, "context");
        if ($context) {
            $where .= " AND $reminder_settings_table.context='$context'";
        }

        $reminder_event = $this->_get_clean_value($options, "reminder_event");
        if ($reminder_event) {
            $where .= " AND $reminder_settings_table.reminder_event='$reminder_event'";
        }

        $user_id = $this->_get_clean_value($options, "user_id");
        if ($user_id) {
            $where .= " AND $reminder_settings_table.user_id='$user_id'";
        }

        $sql = "SELECT $reminder_settings_table.*
                FROM $reminder_settings_table
                WHERE $reminder_settings_table.deleted=0 $where";
        return $this->db->query($sql);
    }

    function get_reminders_by_context($context) {
        $reminder_settings_table = $this->db->prefixTable('reminder_settings');

        $context = $this->_get_clean_value($context);

        $sql = "SELECT $reminder_settings_table.*
                FROM $reminder_settings_table
                WHERE $reminder_settings_table.context = '$context' AND $reminder_settings_table.deleted = 0";

        return $this->db->query($sql, [$context])->getResult();
    }
}
