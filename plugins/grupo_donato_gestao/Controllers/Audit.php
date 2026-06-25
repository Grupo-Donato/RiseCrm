<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

class Audit extends Gd_Controller
{
    private $audit_model;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_audit_view");
        $this->audit_model = $this->gd_model("Gd_audit_logs_model");
    }

    public function index()
    {
        return $this->gd_render("audit/index", ["active_tab" => "audit"]);
    }

    /** Listagem server-side (a auditoria pode crescer muito). */
    public function list_data()
    {
        $options = [
            "entity_type" => $this->request->getPost("entity_type"),
            "action" => $this->request->getPost("action"),
        ];
        $options = append_server_side_filtering_commmon_params($options);
        $allowed_order = [
            "id" => "id",
            "created_at" => "created_at",
            "action" => "action",
            "entity_type" => "entity_type",
        ];
        $requested_order = (string) get_array_value($options, "order_by");
        $options["order_by"] = $allowed_order[$requested_order] ?? "id";

        $result = $this->audit_model->get_details($options);

        if (get_array_value($options, "server_side")) {
            $list = get_array_value($result, "data");
        } else {
            $list = $result->getResult();
            $result = [];
        }

        $rows = [];
        foreach ($list as $data) {
            $rows[] = $this->_make_row($data);
        }
        $result["data"] = $rows;

        echo json_encode($result);
    }

    public function view()
    {
        $this->validate_submitted_data(["id" => "required|numeric"]);
        $id = (int) $this->request->getPost("id");
        $view_data["model_info"] = $this->audit_model->get_one($id);
        if (!$this->record_exists($view_data["model_info"])) {
            return show_404();
        }
        return $this->gd_view("audit/view_modal", $view_data);
    }

    private function _make_row($data): array
    {
        $actor = trim(($data->first_name ?? "") . " " . ($data->last_name ?? ""));
        if (!$actor) {
            $actor = $data->actor_type ?: "system";
        }

        $when = $data->created_at ? format_to_datetime($data->created_at) : "";

        $view = modal_anchor(
            get_uri("grupo_donato/audit/view"),
            "<i data-feather='eye' class='icon-16'></i>",
            ["class" => "edit", "title" => app_lang("gd_view_details"), "data-post-id" => $data->id]
        );

        return [
            $data->id,
            $when,
            $this->escape($actor),
            "<span class='badge bg-info'>" . htmlspecialchars((string) $data->action) . "</span>",
            htmlspecialchars((string) $data->entity_type) . ($data->entity_id ? " #" . $data->entity_id : ""),
            htmlspecialchars((string) $data->request_id),
            $view,
        ];
    }
}
