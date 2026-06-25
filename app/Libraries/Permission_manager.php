<?php

namespace App\Libraries;

class Permission_manager {

    private $ci = null;
    private $permissions = array();
    private $client_permissions = "";

    public function __construct($security_controller_instance) {
        $this->ci = $security_controller_instance;
        if (!$this->ci || !$this->login_user_id()) {
            return false;
        }

        $this->permissions = $this->ci->login_user->permissions ? $this->ci->login_user->permissions : array();
        $this->client_permissions = isset($this->ci->login_user->client_permissions) ? $this->ci->login_user->client_permissions : ""; // keep it as string since it'll be needed like this later, also it's not rebuilt from security controller
    }

    function get_permission($permission_name = "") {
        if ($permission_name) {
            return get_array_value($this->permissions, $permission_name);
        }
        return $this->permissions;
    }

    function is_admin() {
        return $this->ci->login_user->is_admin;
    }

    function is_team_member() {
        return $this->ci->login_user->user_type == "staff";
    }

    function is_client() {
        return $this->ci->login_user->user_type == "client";
    }

    function login_user_id() {
        return isset($this->ci->login_user->id) ? $this->ci->login_user->id : null;
    }

    function login_client_id() {
        return isset($this->ci->login_user->client_id) ? $this->ci->login_user->client_id : null;
    }

    function is_active_module($module_name) {
        return get_setting($module_name) == "1";
    }

    function can_manage_invoices() {

        if (!$this->is_active_module("module_invoice")) {
            return false;
        }

        if ($this->is_admin()) {
            return true;
        }

        if ($this->is_team_member()) {
            $invoice_permission = get_array_value($this->permissions, "invoice");

            return in_array($invoice_permission, [
                "all",
                "manage_own_client_invoices",
                "manage_own_client_invoices_except_delete",
                "manage_only_own_created_invoices",
                "manage_only_own_created_invoices_except_delete"
            ]);
        }
    }

    function can_manage_items() {
        return $this->can_manage_invoices() || $this->can_manage_estimates();
    }

    function can_manage_clients($client_id = 0) {

        if ($this->is_admin()) {
            return true;
        }

        if ($this->is_team_member()) {
            $client_permission = get_array_value($this->permissions, "client");

            // can manager all clients. Id wise permission is not required
            if ($client_permission == "all") {
                return true;
            }

            if ($client_id && ($client_permission == "own" || $client_permission == "specific")) {
                if (!is_numeric($client_id)) {
                    return false; // invalid client id
                }

                $client_info = $this->ci->Clients_model->get_one($client_id);
                if (!$client_info) {
                    return false; // client not found
                }

                //can manage own
                if ($client_permission === "own" && ($client_info->created_by == $this->login_user_id() || $client_info->owner_id == $this->login_user_id() || in_array($this->login_user_id(), explode(',', $client_info->managers)))) {
                    return true;
                }

                //can manage specific client groups
                $client_specific_permission = get_array_value($this->permissions, "client_specific");
                if ($client_permission == "specific" && $client_specific_permission && $client_info->group_ids) {
                    if (array_intersect(explode(',', $client_specific_permission), explode(',', $client_info->group_ids))) {
                        return true;
                    }
                }
            }

            //since the client id is not provided and has some permissions, this can be allowed for client insert
            if (!$client_id && ($client_permission == "own" || $client_permission == "specific")) {
                return true;
            }
        }

        if ($this->is_client() && $this->ci->login_user->client_id == $client_id) {
            return true;
        }
    }

    function can_view_clients($client_id = 0) {
        if ($this->is_team_member()) {
            //if team member has readonly access,  then can view client
            $client_permission = get_array_value($this->permissions, "client");
            if ($client_permission == "read_only") {
                return true;
            }
        }

        // if the user has client manage permission, then can view client
        return $this->can_manage_clients($client_id);
    }

    function get_allowed_client_group_ids_array() {

        if ($this->is_team_member()) {
            $client_permission = get_array_value($this->permissions, "client");

            if ($client_permission == "specific") {
                $client_specific = get_array_value($this->permissions, "client_specific");
                if ($client_specific) {
                    return explode(',', $client_specific);
                }
            }
        }
    }

    function get_own_clients_only_user_id() {
        if ($this->is_team_member()) {
            $client_permission = get_array_value($this->permissions, "client");
            if ($client_permission == "own") {
                return $this->login_user_id();
            }
        }
    }

    function get_own_leads_only_user_id() {
        if ($this->is_team_member()) {
            $client_permission = get_array_value($this->permissions, "lead");
            if ($client_permission == "own") {
                return $this->login_user_id();
            }
        }
    }

    function can_manage_leads($lead_id = 0) {
        if ($this->is_admin()) {
            return true;
        }

        if ($this->is_team_member()) {
            $lead_permission = get_array_value($this->permissions, "lead");
            if ($lead_permission == "all") {
                return true;
            }

            if ($lead_id && $lead_permission == "own") {
                if (!is_numeric($lead_id)) {
                    return false; // invalid lead id
                }

                $lead_info = $this->ci->Clients_model->get_one($lead_id);
                if (!$lead_info) {
                    return false; // lead not found
                }

                if ($lead_info->id && $lead_info->owner_id == $this->login_user_id()) {
                    return true;
                }
            }

            //since the lead id is not provided and has some permissions, this can be allowed for lead insert
            if (!$lead_id && $lead_permission == "own") {
                return true;
            }
        }
    }

    function can_view_leads($lead_id = 0) {
        // if the user has lead manage permission, then can view lead        
        return $this->can_manage_leads($lead_id);
    }

    function can_view_estimates($estimate_id = 0, $client_id = 0) {
        if (!$this->is_active_module("module_estimate")) {
            return false;
        }

        $permission = get_array_value($this->ci->login_user->permissions, "estimate");

        if ($this->is_team_member()) {
            if ($this->is_admin()) {
                return true;
            }

            if (!$estimate_id) {
                if (!empty($permission)) {
                    return true;
                }

                return false;
            }

            $estimate_info = $this->ci->Estimates_model->get_estimate_basic_info($estimate_id);
            if (!$estimate_info || !$estimate_info->id) {
                return false;
            }

            if ($permission == "all" || $permission == "view_all") {
                return true;
            }

            if ($permission == "manage_own_clients_and_leads_estimates" || $permission == "view_own_clients_and_leads_estimates") {
                if ($this->login_user_id() == $estimate_info->client_owner_id) {
                    return true;
                }
            }

            if (!$estimate_info->is_lead && $this->login_user_id() == $estimate_info->client_owner_id && ($permission == "manage_own_clients_estimates" || $permission == "view_own_clients_estimates")) {
                return true;
            }

            if ($estimate_info->is_lead && $this->login_user_id() == $estimate_info->client_owner_id && ($permission == "manage_own_leads_estimates" || $permission == "view_own_leads_estimates")) {
                return true;
            }

            if ($permission == "manage_own_created_estimates" && $estimate_info->created_by == $this->login_user_id()) {
                return true;
            }
        } else {
            if ($this->login_client_id() === $client_id && $this->ci->can_client_access("estimate")) {
                return true;
            }
        }

        return false;
    }

    function can_manage_estimates($estimate_id = 0, $check_client = false) {
        if (!$this->is_active_module("module_estimate")) {
            return false;
        }

        $permission = get_array_value($this->ci->login_user->permissions, "estimate");

        if ($this->is_admin()) {
            return true;
        }

        if (!$estimate_id) {
            if ($permission == "all" || $permission == "manage_own_created_estimates" || $permission == "manage_own_clients_estimates" || $permission == "manage_own_leads_estimates" || $permission == "manage_own_clients_and_leads_estimates") {
                return true;
            }

            return false;
        }

        if ($estimate_id) {
            $estimate_info = $this->ci->Estimates_model->get_estimate_basic_info($estimate_id);
            if (!$estimate_info || !$estimate_info->id) {
                return false;
            }

            if ($check_client && $this->is_client() && $estimate_info->client_id === $this->login_client_id() && $this->ci->can_client_access("estimate")) {
                return true;
            }

            if ($permission == "all") {
                return true;
            }

            if ($permission == "manage_own_created_estimates" && $estimate_info->created_by == $this->login_user_id()) {
                return true;
            }

            if (!$estimate_info->is_lead && $this->login_user_id() == $estimate_info->client_owner_id && $permission == "manage_own_clients_estimates") {
                return true;
            }

            if ($estimate_info->is_lead && $this->login_user_id() == $estimate_info->client_owner_id && $permission == "manage_own_leads_estimates") {
                return true;
            }

            if ($this->login_user_id() == $estimate_info->client_owner_id && $permission == "manage_own_clients_and_leads_estimates") {
                return true;
            }
        }

        return false;
    }

    function can_view_proposals($proposal_id = 0, $check_client = false) {
        if (!$this->is_active_module("module_proposal")) {
            return false;
        }

        $permission = get_array_value($this->ci->login_user->permissions, "proposal");

        if ($this->is_admin()) {
            return true;
        }

        if (!$proposal_id) {
            if (!empty($permission)) {
                return true;
            }

            return false;
        }

        if ($proposal_id) {
            $proposal_info = $this->ci->Proposals_model->get_proposal_basic_info($proposal_id);

            if ($check_client && $this->is_client() && $proposal_info->client_id === $this->login_client_id() && $this->ci->can_client_access("proposal")) {
                return true;
            }

            if ($permission == "all" || $permission == "view_all") {
                return true;
            }

            if ($permission == "manage_own_clients_and_leads_proposals" || $permission == "view_own_clients_and_leads_proposals") {
                if ($this->login_user_id() == $proposal_info->client_owner_id) {
                    return true;
                }
            }

            if (!$proposal_info->is_lead && $this->login_user_id() == $proposal_info->client_owner_id && ($permission == "manage_own_clients_proposals" || $permission == "view_own_clients_proposals")) {
                return true;
            }

            if ($proposal_info->is_lead && $this->login_user_id() == $proposal_info->client_owner_id && ($permission == "manage_own_leads_proposals" || $permission == "view_own_leads_proposals")) {
                return true;
            }

            if ($permission == "manage_own_created_proposals" && $proposal_info->created_by == $this->login_user_id()) {
                return true;
            }
        }

        return false;
    }

    function can_manage_proposals($proposal_id = 0, $check_client = false) {
        if (!$this->is_active_module("module_proposal")) {
            return false;
        }

        $permission = get_array_value($this->ci->login_user->permissions, "proposal");

        if ($this->is_admin()) {
            return true;
        }

        if (!$proposal_id) {
            if ($permission == "all" || $permission == "manage_own_created_proposals" || $permission == "manage_own_clients_proposals" || $permission == "manage_own_leads_proposals" || $permission == "manage_own_clients_and_leads_proposals") {
                return true;
            }

            return false;
        }

        if ($proposal_id) {
            $proposal_info = $this->ci->Proposals_model->get_proposal_basic_info($proposal_id);

            if ($proposal_info && $check_client && $this->is_client() && $proposal_info->client_id === $this->login_client_id() && $this->ci->can_client_access("proposal")) {
                return true;
            }

            if ($permission == "all") {
                return true;
            }

            if ($permission == "manage_own_clients_and_leads_proposals") {
                if ($this->login_user_id() == $proposal_info->client_owner_id) {
                    return true;
                }
            }

            if ($proposal_info && !$proposal_info->is_lead && $this->login_user_id() == $proposal_info->client_owner_id && $permission == "manage_own_clients_proposals") {
                return true;
            }

            if ($proposal_info && $proposal_info->is_lead && $this->login_user_id() == $proposal_info->client_owner_id && $permission == "manage_own_leads_proposals") {
                return true;
            }

            if ($permission == "manage_own_created_proposals" && $proposal_info->created_by == $this->login_user_id()) {
                return true;
            }
        }

        return false;
    }

    function can_manage_tickets($ticket_id = 0) {

        if (!$this->is_active_module("module_ticket")) {
            return false;
        }

        if ($this->is_admin()) {
            return true;
        }

        if ($this->is_team_member()) {

            $ticket_permission = get_array_value($this->permissions, "ticket");

            if ($ticket_permission == "all") {
                // can manage all tickets
                // ID-wise permission is not required
                return true;
            }

            if ($ticket_id && ($ticket_permission == "assigned_only" || $ticket_permission == "specific")) {

                $ticket_info = $this->_get_ticket_info($ticket_id);
                if (!$ticket_info) {
                    return false; // ticket not found
                }

                // can manage assigned tickets only
                if ($ticket_permission === "assigned_only" && $ticket_info->assigned_to == $this->login_user_id()) {
                    return true;
                }

                //can manage specific ticket types
                $ticket_specific_permission = get_array_value($ticket_permission, "ticket_specific");
                if ($ticket_permission == "specific" && $ticket_specific_permission && $ticket_info->ticket_type_id) {

                    $allowed_ticket_types = explode(',', $ticket_specific_permission);
                    if (in_array($ticket_info->ticket_type_id, $allowed_ticket_types)) {
                        return true;
                    }
                }
            }

            if (!$ticket_id && $ticket_permission) {
                // since the ticket id is not provided and has some permissions, this can be allowed for ticket insert
                return true;
            }
        }

        if ($this->is_client()) {

            // either ticket is provided or not, client should have access to ticket
            if (!can_client_access($this->client_permissions, "ticket")) {
                return false;
            }

            // if ticket id is not provided, previous check is enough
            if (!$ticket_id) {
                return true;
            }

            if ($ticket_id) {

                $ticket_info = $this->_get_ticket_info($ticket_id);
                if (!$ticket_info) {
                    return false; // ticket not found
                }

                return $ticket_info->client_id === $this->login_client_id();
            }
        }
    }

    private function _get_ticket_info($ticket_id) {
        if (!is_numeric($ticket_id)) {
            return false; // invalid ticket id
        }

        return $this->ci->Tickets_model->get_one($ticket_id);
    }
}
