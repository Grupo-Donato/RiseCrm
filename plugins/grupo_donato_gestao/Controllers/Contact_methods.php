<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ContactMethodService;
use grupo_donato_gestao\Services\DataPrivacyService;

class Contact_methods extends Gd_Controller
{
    private int $unit_id;
    private $model;
    private ContactMethodService $service;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_people_view");
        $this->unit_id = (int) $this->active_unit_id();
        $this->model = $this->gd_model("Gd_contact_methods_model");
        $this->service = new ContactMethodService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function list_data(): void
    {
        $result = $this->model->get_details([
            "unit_id" => $this->unit_id,
            "person_id" => (int) $this->request->getPost("person_id"),
            "limit" => (int) ($this->request->getPost("limit") ?: 25),
            "skip" => (int) $this->request->getPost("skip"),
        ]);
        $rows = [];
        foreach ($result["data"] as $row) {
            $masked = $row->contact_type === "email" ? DataPrivacyService::maskEmail($row->value) : DataPrivacyService::maskPhone($row->value);
            $actions = "";
            if ($this->access->can("gd_contacts_manage")) {
                $actions = modal_anchor(get_uri("grupo_donato/contacts/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                    "data-post-id" => $row->id,
                    "data-post-person_id" => $row->person_id,
                    "title" => app_lang("edit"),
                ]);
            }
            $rows[] = [app_lang("gd_contact_type_" . $row->contact_type), $this->escape($row->label), $this->escape($masked), $row->is_primary ? app_lang("yes") : "", app_lang("gd_status_" . $row->status), $actions];
        }
        $result["data"] = $rows;
        echo json_encode($result);
    }

    public function modal_form()
    {
        $this->access->require("gd_contacts_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->model->get_scoped($id, $this->unit_id) : null;
        if ($id && !$row) {
            return show_404();
        }
        $types = [];
        foreach (Constants::CONTACT_TYPES as $type) {
            $types[] = ["id" => $type, "text" => app_lang("gd_contact_type_" . $type)];
        }
        return $this->gd_view("contacts/modal_form", [
            "model_info" => $row ?: new \stdClass(),
            "person_id" => (int) ($this->request->getPost("person_id") ?: ($row->person_id ?? 0)),
            "types" => $types,
        ]);
    }

    public function save(): void
    {
        $this->access->require("gd_contacts_manage");
        try {
            $id = $this->service->save([
                "person_id" => $this->request->getPost("person_id"),
                "contact_type" => $this->request->getPost("contact_type"),
                "label" => $this->request->getPost("label"),
                "value" => $this->request->getPost("value"),
                "is_primary" => $this->request->getPost("is_primary"),
                "receives_notifications" => $this->request->getPost("receives_notifications"),
                "status" => $this->request->getPost("status") ?: "active",
            ], (int) $this->request->getPost("id"));
            $this->json_success(app_lang("record_saved"), ["id" => $id]);
        } catch (\Throwable $e) {
            $this->error($e);
        }
    }

    public function delete(): void
    {
        $this->access->require("gd_contacts_manage");
        try {
            $this->service->delete((int) $this->request->getPost("id"), (string) $this->request->getPost("reason"));
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
