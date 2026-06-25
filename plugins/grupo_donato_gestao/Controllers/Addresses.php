<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\AddressService;
use grupo_donato_gestao\Services\SettingsService;

class Addresses extends Gd_Controller
{
    private int $unit_id;
    private $model;
    private AddressService $service;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_customer_accounts_view");
        $this->unit_id = (int) $this->active_unit_id();
        $this->model = $this->gd_model("Gd_addresses_model");
        $this->service = new AddressService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function list_data(): void
    {
        $result = $this->model->get_details([
            "unit_id" => $this->unit_id,
            "account_id" => (int) $this->request->getPost("account_id"),
            "limit" => (int) ($this->request->getPost("limit") ?: 25),
            "skip" => (int) $this->request->getPost("skip"),
        ]);
        $rows = [];
        foreach ($result["data"] as $row) {
            $summary = implode(", ", array_filter([$row->street, $row->number, $row->city, $row->state]));
            $actions = "";
            if ($this->access->can("gd_addresses_manage")) {
                $actions = modal_anchor(get_uri("grupo_donato/addresses/modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                    "data-post-id" => $row->id,
                    "data-post-account_id" => $row->account_id,
                    "title" => app_lang("edit"),
                ]);
            }
            $rows[] = [app_lang("gd_address_type_" . $row->address_type), $this->escape($summary), $this->escape($row->postal_code), $row->is_primary ? app_lang("yes") : "", app_lang("gd_status_" . $row->status), $actions];
        }
        $result["data"] = $rows;
        echo json_encode($result);
    }

    public function modal_form()
    {
        $this->access->require("gd_addresses_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->model->get_scoped($id, $this->unit_id) : null;
        if ($id && !$row) {
            return show_404();
        }
        $types = [];
        foreach (Constants::ADDRESS_TYPES as $type) {
            $types[] = ["id" => $type, "text" => app_lang("gd_address_type_" . $type)];
        }
        if (!$row) {
            $row = new \stdClass();
            $row->country = (string) (new SettingsService())->get("default_country", null, "");
        }
        return $this->gd_view("addresses/modal_form", [
            "model_info" => $row,
            "account_id" => (int) ($this->request->getPost("account_id") ?: ($row->account_id ?? 0)),
            "types" => $types,
        ]);
    }

    public function save(): void
    {
        $this->access->require("gd_addresses_manage");
        try {
            $fields = ["account_id", "address_type", "postal_code", "street", "number", "complement", "district", "city", "state", "country", "is_primary", "status"];
            $data = [];
            foreach ($fields as $field) {
                $data[$field] = $this->request->getPost($field);
            }
            $id = $this->service->save($data, (int) $this->request->getPost("id"), (bool) $this->request->getPost("duplicate_override"));
            $this->json_success(app_lang("record_saved"), ["id" => $id]);
        } catch (\Throwable $e) {
            $this->error($e);
        }
    }

    public function delete(): void
    {
        $this->access->require("gd_addresses_manage");
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
        $extra = $key === "gd_duplicate_address" ? ["duplicate_confirmation_required" => true] : [];
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"), $extra);
    }
}
