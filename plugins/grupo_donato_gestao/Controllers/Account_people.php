<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\AccountPersonService;

class Account_people extends Gd_Controller
{
    private int $unit_id;
    private $model;
    private AccountPersonService $service;

    public function __construct()
    {
        parent::__construct();
        $this->access->require_any(["gd_customer_accounts_view", "gd_people_view"]);
        $this->unit_id = (int) $this->active_unit_id();
        $this->model = $this->gd_model("Gd_account_people_model");
        $this->service = new AccountPersonService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function list_data(): void
    {
        $person_id = (int) $this->request->getPost("person_id");
        $result = $this->model->get_details([
            "unit_id" => $this->unit_id,
            "account_id" => (int) $this->request->getPost("account_id"),
            "person_id" => $person_id,
            "limit" => (int) ($this->request->getPost("limit") ?: 25),
            "skip" => (int) $this->request->getPost("skip"),
        ]);
        $rows = [];
        foreach ($result["data"] as $row) {
            $actions = "";
            if ($this->access->can("gd_customer_relations_manage")) {
                $actions = modal_anchor(get_uri("grupo_donato/account-people/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                    "data-post-id" => $row->id,
                    "data-post-account_id" => $row->account_id,
                    "data-post-person_id" => $row->person_id,
                    "title" => app_lang("edit"),
                ]);
            }
            $rows[] = [
                $this->escape($person_id ? $row->display_name : $row->full_name),
                app_lang("gd_role_" . $row->role),
                $row->is_primary ? app_lang("yes") : "",
                $row->is_financial_responsible ? app_lang("yes") : "",
                app_lang("gd_status_" . $row->status),
                $actions,
            ];
        }
        $result["data"] = $rows;
        echo json_encode($result);
    }

    public function modal_form()
    {
        $this->access->require("gd_customer_relations_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->model->get_scoped($id, $this->unit_id) : null;
        if ($id && !$row) {
            return show_404();
        }
        $account_id = (int) ($this->request->getPost("account_id") ?: ($row->account_id ?? 0));
        $person_id = (int) ($this->request->getPost("person_id") ?: ($row->person_id ?? 0));
        $people = $this->gd_model("Gd_people_model")->get_details(["unit_id" => $this->unit_id, "status" => "active", "limit" => 100])["data"];
        $accounts = $this->gd_model("Gd_customer_accounts_model")->get_details(["unit_id" => $this->unit_id, "status" => "active", "limit" => 100])["data"];
        $roles = [];
        foreach (Constants::ACCOUNT_PERSON_ROLES as $role) {
            $roles[] = ["id" => $role, "text" => app_lang("gd_role_" . $role)];
        }
        return $this->gd_view("relations/modal_form", [
            "model_info" => $row ?: new \stdClass(),
            "account_id" => $account_id,
            "person_id" => $person_id,
            "people" => $people,
            "accounts" => $accounts,
            "roles" => $roles,
        ]);
    }

    public function save(): void
    {
        $this->access->require("gd_customer_relations_manage");
        try {
            $id = $this->service->save([
                "account_id" => $this->request->getPost("account_id"),
                "person_id" => $this->request->getPost("person_id"),
                "role" => $this->request->getPost("role"),
                "is_primary" => $this->request->getPost("is_primary"),
                "is_financial_responsible" => $this->request->getPost("is_financial_responsible"),
                "receives_notifications" => $this->request->getPost("receives_notifications"),
                "status" => $this->request->getPost("status") ?: "active",
                "start_date" => $this->request->getPost("start_date"),
                "end_date" => $this->request->getPost("end_date"),
                "notes" => $this->request->getPost("notes"),
            ], (int) $this->request->getPost("id"));
            $this->json_success(app_lang("record_saved"), ["id" => $id]);
        } catch (\Throwable $e) {
            $this->error($e);
        }
    }

    public function delete(): void
    {
        $this->access->require("gd_customer_relations_manage");
        try {
            $this->service->end((int) $this->request->getPost("id"), (string) $this->request->getPost("reason"));
            $this->json_success(app_lang("record_deleted"));
        } catch (\Throwable $e) {
            $this->error($e);
        }
    }

    private function error(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
