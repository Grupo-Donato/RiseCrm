<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;

class Business_areas extends Gd_Controller
{
    private $areas_model;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_business_areas_view");
        $this->areas_model = $this->gd_model("Gd_business_areas_model");
    }

    public function index()
    {
        return $this->gd_render("settings/business_areas", ["active_tab" => "business_areas"]);
    }

    public function modal_form()
    {
        $this->access->require("gd_business_areas_manage");
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = (int) $this->request->getPost("id");
        $view_data["model_info"] = $this->areas_model->get_one($id);
        if ($id && !$this->record_exists($view_data["model_info"])) {
            return show_404();
        }
        $view_data["units_dropdown"] = $this->units_dropdown();
        return $this->gd_view("settings/business_area_modal", $view_data);
    }

    public function save()
    {
        $this->access->require("gd_business_areas_manage");
        $this->validate_submitted_data([
            "id" => "numeric",
            "code" => "required|max_length[40]",
            "name" => "required|max_length[150]",
            "unit_id" => "numeric",
        ]);

        $id = (int) $this->request->getPost("id");
        $code = trim((string) $this->request->getPost("code"));
        $unit_id = $this->request->getPost("unit_id") ? (int) $this->request->getPost("unit_id") : null;
        $before_record = $id ? $this->areas_model->get_one($id) : null;
        if ($id && !$this->record_exists($before_record)) {
            return $this->json_error(app_lang("gd_record_not_found"));
        }
        if ($unit_id !== null && !$this->unit_context->user_can_access_unit($unit_id)) {
            return $this->json_error(app_lang("gd_invalid_unit"));
        }

        if ($this->areas_model->is_duplicate_code($code, $unit_id, $id)) {
            return $this->json_error(app_lang("gd_duplicate_code"));
        }

        $status = (string) $this->request->getPost("status");
        if (!Constants::isActivatableStatus($status)) {
            $status = Constants::STATUS_ACTIVE;
        }

        $data = [
            "unit_id" => $unit_id,
            "code" => $code,
            "name" => trim((string) $this->request->getPost("name")),
            "status" => $status,
            "updated_by" => $this->user_id(),
        ];
        if (!$id) {
            $data["created_by"] = $this->user_id();
        }

        $before = $id ? (array) $before_record : null;
        $save_id = $this->areas_model->ci_save($data, $id);
        if (!$save_id) {
            return $this->json_error(app_lang("error_occurred"));
        }

        $after = (array) $this->areas_model->get_one($save_id);
        $this->audit->log($id ? "update" : "create", "business_area", (int) $save_id, $before, $after, null, $unit_id);

        return $this->json_success(app_lang("record_saved"), [
            "id" => $save_id,
            "data" => $this->_row($save_id),
        ]);
    }

    public function delete()
    {
        $this->access->require("gd_business_areas_manage");
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int) $this->request->getPost("id");
        $area = $this->areas_model->get_one($id);
        if (!$this->record_exists($area)) {
            return $this->json_error(app_lang("error_occurred"));
        }

        if ($this->areas_model->delete($id)) {
            $this->audit->log("delete", "business_area", $id, (array) $area, null, null, $area->unit_id ? (int) $area->unit_id : null);
            return $this->json_success(app_lang("record_deleted"));
        }
        return $this->json_error(app_lang("record_cannot_be_deleted"));
    }

    public function list_data()
    {
        $rows = $this->areas_model->get_details()->getResult();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->_make_row($row);
        }
        echo json_encode(["data" => $result]);
    }

    private function _row($id)
    {
        return $this->_make_row($this->areas_model->get_details(["id" => $id])->getRow());
    }

    private function _make_row($data): array
    {
        $actions = "";
        if ($this->access->can("gd_business_areas_manage")) {
            $actions = modal_anchor(
                get_uri("grupo_donato/settings/business-areas/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            ) . js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("grupo_donato/settings/business-areas/delete"), "data-action" => "delete"]
            );
        }

        $status_class = $data->status === Constants::STATUS_ACTIVE ? "bg-success" : "bg-secondary";
        $status_text = $data->status === Constants::STATUS_ACTIVE ? app_lang("gd_status_active") : app_lang("gd_status_inactive");

        return [
            $this->escape($data->code),
            $this->escape($data->name),
            isset($data->unit_name) && $data->unit_name ? $this->escape($data->unit_name) : "<span class='text-muted'>" . app_lang("gd_global_scope") . "</span>",
            "<span class='badge $status_class'>$status_text</span>",
            $actions,
        ];
    }

    private function units_dropdown(): array
    {
        $dropdown = [["id" => "", "text" => app_lang("gd_global_scope")]];
        $units = $this->gd_model("Gd_units_model")->get_details(["where" => ["status" => Constants::STATUS_ACTIVE]])->getResult();
        foreach ($units as $unit) {
            $dropdown[] = ["id" => $unit->id, "text" => $unit->name];
        }
        return $dropdown;
    }
}
