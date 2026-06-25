<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;

class Units extends Gd_Controller
{
    private $units_model;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_units_view");
        $this->units_model = $this->gd_model("Gd_units_model");
    }

    public function index()
    {
        return $this->gd_render("settings/units", ["active_tab" => "units"]);
    }

    public function modal_form()
    {
        $this->access->require("gd_units_manage");
        $this->validate_submitted_data(["id" => "numeric"]);

        $id = (int) $this->request->getPost("id");
        $view_data["model_info"] = $this->units_model->get_one($id);
        if ($id && !$this->record_exists($view_data["model_info"])) {
            return show_404();
        }
        $view_data["status_dropdown"] = $this->status_dropdown();
        return $this->gd_view("settings/unit_modal", $view_data);
    }

    public function save()
    {
        $this->access->require("gd_units_manage");
        $this->validate_submitted_data([
            "id" => "numeric",
            "name" => "required|max_length[150]",
            "legal_name" => "max_length[190]",
            "document" => "max_length[20]",
            "timezone" => "max_length[64]",
        ]);

        $id = (int) $this->request->getPost("id");
        $name = trim((string) $this->request->getPost("name"));
        $before_record = $id ? $this->units_model->get_one($id) : null;
        if ($id && !$this->record_exists($before_record)) {
            return $this->json_error(app_lang("gd_record_not_found"));
        }

        if ($this->units_model->is_duplicate_name($name, $id)) {
            return $this->json_error(app_lang("gd_duplicate_name"));
        }

        $status = (string) $this->request->getPost("status");
        if (!Constants::isActivatableStatus($status)) {
            $status = Constants::STATUS_ACTIVE;
        }

        $timezone = trim((string) $this->request->getPost("timezone"));
        if ($timezone !== "") {
            try {
                new \DateTimeZone($timezone);
            } catch (\Throwable $e) {
                return $this->json_error(app_lang("gd_invalid_timezone"));
            }
        }

        $requested_default = $this->request->getPost("is_default") ? 1 : 0;
        if ($this->record_exists($before_record) && (int) $before_record->is_default === 1) {
            $requested_default = 1;
            if ($status !== Constants::STATUS_ACTIVE) {
                return $this->json_error(app_lang("gd_cannot_deactivate_default_unit"));
            }
        }

        // whitelist explícito (sem mass-assignment)
        $data = [
            "name" => $name,
            "legal_name" => $this->_get_post_data("legal_name", "text"),
            "document" => $this->_get_post_data("document", "text"),
            "timezone" => $timezone,
            "status" => $status,
            "is_default" => $requested_default,
            "updated_by" => $this->user_id(),
        ];
        if (!$id) {
            $data["created_by"] = $this->user_id();
        }

        $before = $id ? (array) $before_record : null;
        $save_id = $this->units_model->ci_save($data, $id);

        if (!$save_id) {
            return $this->json_error(app_lang("error_occurred"));
        }

        // garante unicidade do padrão
        if ($data["is_default"]) {
            $this->units_model->mark_as_default((int) $save_id);
        }

        $after = (array) $this->units_model->get_one($save_id);
        $this->audit->log($id ? "update" : "create", "unit", (int) $save_id, $before, $after);

        return $this->json_success(app_lang("record_saved"), [
            "id" => $save_id,
            "data" => $this->_row($save_id),
        ]);
    }

    public function delete()
    {
        $this->access->require("gd_units_manage");
        $this->validate_submitted_data(["id" => "required|numeric"]);

        $id = (int) $this->request->getPost("id");
        $unit = $this->units_model->get_one($id);

        if (!$this->record_exists($unit)) {
            return $this->json_error(app_lang("error_occurred"));
        }
        if ((int) $unit->is_default === 1) {
            return $this->json_error(app_lang("gd_cannot_delete_default_unit"));
        }

        if ($this->units_model->delete($id)) {
            $this->audit->log("delete", "unit", $id, (array) $unit, null);
            return $this->json_success(app_lang("record_deleted"));
        }
        return $this->json_error(app_lang("record_cannot_be_deleted"));
    }

    public function list_data()
    {
        $rows = $this->units_model->get_details()->getResult();
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->_make_row($row);
        }
        echo json_encode(["data" => $result]);
    }

    private function _row($id)
    {
        return $this->_make_row($this->units_model->get_details(["id" => $id])->getRow());
    }

    private function _make_row($data): array
    {
        $default_badge = (int) $data->is_default === 1
            ? "<span class='badge bg-primary'>" . app_lang("gd_default") . "</span>"
            : "";

        $actions = "";
        if ($this->access->can("gd_units_manage")) {
            $actions = modal_anchor(
                get_uri("grupo_donato/settings/units/modal_form"),
                "<i data-feather='edit' class='icon-16'></i>",
                ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $data->id]
            ) . js_anchor(
                "<i data-feather='x' class='icon-16'></i>",
                ["title" => app_lang("delete"), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("grupo_donato/settings/units/delete"), "data-action" => "delete"]
            );
        }

        return [
            $this->escape($data->name) . " " . $default_badge,
            $this->escape($data->legal_name),
            $this->escape($data->timezone),
            $this->status_label((string) $data->status),
            $actions,
        ];
    }

    private function status_dropdown(): array
    {
        return [
            ["id" => Constants::STATUS_ACTIVE, "text" => app_lang("gd_status_active")],
            ["id" => Constants::STATUS_INACTIVE, "text" => app_lang("gd_status_inactive")],
        ];
    }

    private function status_label(string $status): string
    {
        $class = $status === Constants::STATUS_ACTIVE ? "bg-success" : "bg-secondary";
        $text = $status === Constants::STATUS_ACTIVE ? app_lang("gd_status_active") : app_lang("gd_status_inactive");
        return "<span class='badge $class'>$text</span>";
    }
}
