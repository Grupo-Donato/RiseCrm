<?php

namespace App\Models;

class Estimate_requests_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = 'estimate_requests';
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $estimate_requests_table = $this->db->prefixTable('estimate_requests');
        $estimate_forms_table = $this->db->prefixTable('estimate_forms');
        $clients_table = $this->db->prefixTable('clients');
        $users_table = $this->db->prefixTable('users');

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $estimate_requests_table.id=$id";
        }

        $client_id = $this->_get_clean_value($options, "client_id");
        if ($client_id) {
            $where .= " AND $estimate_requests_table.client_id=$client_id";
        }

        $lead_id = $this->_get_clean_value($options, "lead_id");
        if ($lead_id) {
            $where .= " AND $estimate_requests_table.lead_id=$lead_id";
        }

        $assigned_to = $this->_get_clean_value($options, "assigned_to");
        if ($assigned_to) {
            $where .= " AND $estimate_requests_table.assigned_to=$assigned_to";
        }

        $status = $this->_get_clean_value($options, "status");
        if ($status) {
            $where .= " AND $estimate_requests_table.status='$status'";
        }

        $clients_only = $this->_get_clean_value($options, "clients_only");
        if ($clients_only) {
            $where .= " AND $estimate_requests_table.client_id IN(SELECT $clients_table.id FROM $clients_table WHERE $clients_table.deleted=0 AND $clients_table.is_lead=0)";
        }

        $show_own_client_estimates_user_id = $this->_get_clean_value($options, "show_own_client_estimates_user_id");
        if ($show_own_client_estimates_user_id) {
            $where .= " AND $clients_table.owner_id=$show_own_client_estimates_user_id AND $clients_table.is_lead=0";
        }

        $show_own_lead_estimates_user_id = $this->_get_clean_value($options, "show_own_lead_estimates_user_id");
        if ($show_own_lead_estimates_user_id) {
            $where .= " AND $clients_table.owner_id=$show_own_lead_estimates_user_id AND $clients_table.is_lead=1";
        }

        $show_own_clients_and_leads_estimates_user_id = $this->_get_clean_value($options, "show_own_clients_and_leads_estimates_user_id");
        if ($show_own_clients_and_leads_estimates_user_id) {
            $where .= " AND $clients_table.owner_id=$show_own_clients_and_leads_estimates_user_id";
        }

        $sql = "SELECT $estimate_requests_table.*, $clients_table.company_name, $estimate_forms_table.title AS form_title, $clients_table.is_lead,
              CONCAT($users_table.first_name, ' ',$users_table.last_name) AS assigned_to_user, $users_table.image as assigned_to_avatar, $clients_table.is_lead,
              CONCAT(created_by_table.first_name, ' ',created_by_table.last_name) AS created_by_name, created_by_table.image AS created_by_avatar, created_by_table.user_type AS created_by_user_type
        FROM $estimate_requests_table
        LEFT JOIN $clients_table ON $clients_table.id = $estimate_requests_table.client_id
        LEFT JOIN $users_table ON $users_table.id = $estimate_requests_table.assigned_to
        LEFT JOIN $users_table AS created_by_table ON created_by_table.id= $estimate_requests_table.created_by
        LEFT JOIN $estimate_forms_table ON $estimate_forms_table.id = $estimate_requests_table.estimate_form_id
        WHERE $estimate_requests_table.deleted=0 $where";

        return $this->db->query($sql);
    }

    function get_estimate_request_basic_info($estimate_request_id) {
        $estimate_requests_table = $this->db->prefixTable('estimate_requests');
        $clients_table = $this->db->prefixTable('clients');

        $sql = "SELECT $estimate_requests_table.id, $estimate_requests_table.created_by, $estimate_requests_table.assigned_to, $clients_table.owner_id AS client_owner_id, $clients_table.is_lead
                FROM $estimate_requests_table
                LEFT JOIN $clients_table ON $clients_table.id = $estimate_requests_table.client_id
                WHERE $estimate_requests_table.id=$estimate_request_id";

        return $this->db->query($sql)->getRow();
    }

    function get_total_estimate_request_count($client_id) {
        $estimate_requests_table = $this->db->prefixTable('estimate_requests');

        $sql = "SELECT COUNT($estimate_requests_table.id) AS total
                FROM $estimate_requests_table
                WHERE $estimate_requests_table.deleted=0 AND $estimate_requests_table.client_id=$client_id AND ($estimate_requests_table.status='new' OR $estimate_requests_table.status='processing')";

        return $this->db->query($sql)->getRow()->total;
    }
}
