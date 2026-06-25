<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;

class Cost_centers extends Gd_Controller
{
    private $cc_model;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_cost_centers_view");
        $this->cc_model = $this->gd_model("Gd_cost_centers_model");
    }

    public function index()
    {
        return $this->gd_render("settings/cost_centers", ["active_tab" => "cost_centers"]);
    }

    public function modal_form()
    {
        $this->access->require("gd_cost_centers_manage");
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = (int) $this->request->getPost("id");
        $view_data["model_info"] = $this->cc_model->get_one($id);
        if ($id && !$this->record_exists($view_data["model_info"])) {
            return show_404();
        }
        $view_data["units_dropdown"] = $this->units_dropdown();
        $view_data["areas_dropdown"] = $this->areas_dropdown();
        $view_data["type_dropdown"] = $this->type_dropdown();
        return $this->gd_view("settings/cost_center_modal", $view_data);
    }

    public function save()
    {
        $this->access->require("gd_cost_centers_manage");
        $this->validate_submitted_data([
            "id" => "numeric",
            "code" => "required|max_length[40]",
            "name" => "required|max_length[150]",
            "unit_id" => "required|numeric",
            "business_area_id" => "numeric",
        ]);

        $id = (int) $this->request->getPost("id");
        $code = trim((string) $this->request->getPost("code"));
        $unit_id = (int) $this->request->getPost("unit_id");
        $area_id = $this->request->getPost("business_area_id") ? (int) $this->request->getPost("business_area_id") : null;
        $before_record = $id ? $this->cc_model->get_one($id) : null;

        if ($id && !$this->record_exists($before_record)) {
            return $this->json_error(app_lang("gd_record_not_found"));
        }
        if (!$this->unit_context->user_can_access_unit($unit_id)) {
            return $this->json_error(app_lang("gd_invalid_unit"));
        }
        if ($area_id !== null) {
            $area = $this->gd_model("Gd_business_areas_model")->get_one($area_id);
            $area_unit = $this->record_exists($area) && $area->unit_id !== null && $area->unit_id !== "" ? (int) $area->unit_id : null;
            if (!$this->record_exists($area) || (string) $area->status !== Constants::STATUS_ACTIVE || ($area_unit !== null && $area_unit !== $unit_id)) {
                return $this->json_error(app_lang("gd_invalid_business_area"));
            }
        }

        if ($this->cc_model->is_duplicate_code($code, $unit_id, $id)) {
            return $this->json_error(app_lang("gd_duplicate_code"));
        }

        $type = (string) $this->request->getPost("type");
        if (!Constants::isCostCenterType($type)) {
            $type = "mixed";
        }

        $status = (string) $this->request->getPost("status");
        if (!Constants::isActivatableStatus($status)) {
            $status = Constants::STATUS_ACTIVE;
        }

        $data = [
            "unit_id" => $unit_id,
            "business_area_id" => $area_id,
            "code" => $code,
            "name" => trim((string) $this->request->getPost("name")),
            "type" => $type,
            "status" => $status,
            "updated_by" => $this->user_id(),
        ];
        if (!$id) {
            $data["created_by"] = $this->user_id();
        }

        $before = $id ? (array) $before_record : null;
        $save_id = $this->cc_model->ci_save($data, $id);
        if (!$save_id) {
            return $this->json_error(app_lang("error_occurred"));
        }

        $after = (array) $this->cc_model->get_one($save_id);
        $this->audit->log($id ? "update" : "create", "cost_center", (int) $save_id, $before, $after, null, $unit_id);

        return $this->json_success(app_lang("record_saved"), [
            "id" => $save_id,
            "data" => $this->_row($save_id),
        ]);
    }

    public function delete()
    {
        $this->access->require("gd_cost_centers_manage");
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int) $this->request->getPost("id");
        $cc = $this->cc_model->get_one($id);
        if (!$this->record_exists($cc)) {
            return $this->json_error(app_lang("error_occurred"));
        }

        if ($this->cc_model->delete($id)) {
            $this->audit->log("delete", "cost_center", $id, (array) $cc, null, null, (int) $cc->unit_id);
            return $this->json_success(app_lang("record_deleted"));
        }
        return $this->json_error(app_lang("record_cannot_be_deleted"));
    }

    public function list_data()
    {
        $rows = $this->cc_model->get_details()->getResult();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->_make_row($row);
        }
        echo json_encode(["data" => $result]);
    }

    private function _row($id)
    {
        return $this->_make_row($this->cc_model->get_details(["id" => $id])->getRow());
    }

    private function _make_row($data): array
    {
        $actions = "";
        if ($this->access->can("gd_cost_centers_manage")) {
            $actions = modal_anchor(
                get_uri("grupo_donato/settings/cost-centers/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            ) . js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("grupo_donato/settings/cost-centers/delete"), "data-action" => "delete"]
            );
        }

        $status_class = $data->status === Constants::STATUS_ACTIVE ? "bg-success" : "bg-secondary";
        $status_text = $data->status === Constants::STATUS_ACTIVE ? app_lang("gd_status_active") : app_lang("gd_status_inactive");

        return [
            $this->escape($data->code),
            $this->escape($data->name),
            isset($data->unit_name) ? $this->escape($data->unit_name) : "",
            isset($data->business_area_name) && $data->business_area_name ? $this->escape($data->business_area_name) : "-",
            app_lang("gd_cc_type_" . $data->type),
            "<span class='badge $status_class'>$status_text</span>",
            $actions,
        ];
    }

    private function units_dropdown(): array
    {
        $dropdown = [];
        $units = $this->gd_model("Gd_units_model")->get_details(["where" => ["status" => Constants::STATUS_ACTIVE]])->getResult();
        foreach ($units as $unit) {
            $dropdown[] = ["id" => $unit->id, "text" => $unit->name];
        }
        return $dropdown;
    }

    private function areas_dropdown(): array
    {
        $dropdown = [["id" => "", "text" => "-"]];
        $areas = $this->gd_model("Gd_business_areas_model")->get_details(["status" => Constants::STATUS_ACTIVE])->getResult();
        foreach ($areas as $area) {
            $dropdown[] = ["id" => $area->id, "text" => $area->name];
        }
        return $dropdown;
    }

    private function type_dropdown(): array
    {
        $list = [];
        foreach (Constants::COST_CENTER_TYPES as $type) {
            $list[] = ["id" => $type, "text" => app_lang("gd_cc_type_" . $type)];
        }
        return $list;
    }
}
