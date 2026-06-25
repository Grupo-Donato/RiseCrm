<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ResourceService;
use grupo_donato_gestao\Services\TemporalService;

class Resources extends Gd_Controller
{
    private $model;
    private ResourceService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_resources_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_resources_model");
        $this->service = new ResourceService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("resources/index", [
            "can_manage" => $this->access->can("gd_resources_manage"),
            "type_options" => $this->type_options(),
        ]);
    }

    public function view($id)
    {
        $row = $this->service->get((int) $id);
        if (!$row) { return show_404(); }
        $detail = $this->model->get_details(["unit_id" => $this->unit_id, "id" => (int) $row->id, "limit" => 1])["data"][0] ?? $row;
        $can_audit = $this->access->can("gd_audit_view");
        $audits = $can_audit ? $this->gd_model("Gd_audit_logs_model")->get_details(["unit_id" => $this->unit_id, "entity_type" => "resource", "entity_id" => (int) $row->id, "limit" => 8])->getResult() : [];
        return $this->gd_render("resources/view", [
            "resource" => $detail,
            "prices" => $this->gd_model("Gd_prices_model")->active_for_resource((int) $row->id, $this->unit_id),
            "audits" => $audits,
            "can_manage" => $this->access->can("gd_resources_manage"),
            "can_audit" => $can_audit,
            "can_calendar" => $this->access->can("gd_calendar_view"),
            "can_availability_manage" => $this->access->can("gd_resource_availability_manage"),
            "can_blocks_manage" => $this->access->can("gd_resource_blocks_manage"),
            "timezone" => (new TemporalService($this->unit_id))->timezoneName(),
        ]);
    }

    public function list_data()
    {
        $o = append_server_side_filtering_commmon_params([
            "unit_id" => $this->unit_id,
            "resource_type" => $this->request->getPost("resource_type"),
            "is_active" => $this->request->getPost("is_active"),
        ]);
        $r = $this->model->get_details($o);
        $rows = [];
        foreach ($r["data"] as $x) { $rows[] = $this->row($x); }
        $r["data"] = $rows;
        echo json_encode($r);
    }

    public function modal_form()
    {
        $this->access->require("gd_resources_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && !$row) { return show_404(); }
        return $this->gd_view("resources/modal_form", [
            "model_info" => $row ?: new \stdClass(),
            "type_options" => $this->type_options(),
            "areas" => $this->areas_dropdown(),
            "cost_centers" => $this->cost_centers_dropdown(),
        ]);
    }

    public function save()
    {
        $this->access->require("gd_resources_manage");
        try {
            $r = $this->service->save($this->input(), (int) $this->request->getPost("id"), (bool) $this->request->getPost("duplicate_override"));
            if (empty($r["saved"])) {
                return $this->json_error(app_lang("gd_duplicate_confirmation_required"), ["duplicate_confirmation_required" => true, "duplicates" => $r["duplicates"]]);
            }
            $detail = $this->model->get_details(["unit_id" => $this->unit_id, "id" => $r["id"], "limit" => 1])["data"][0] ?? null;
            return $this->json_success(app_lang("record_saved"), ["id" => $r["id"], "data" => $detail ? $this->row($detail) : null]);
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    public function delete()
    {
        $this->access->require("gd_resources_manage");
        try {
            $this->service->delete((int) $this->request->getPost("id"));
            return $this->json_success(app_lang("record_deleted"));
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    private function input(): array
    {
        return [
            "code" => $this->request->getPost("code"),
            "name" => $this->request->getPost("name"),
            "resource_type" => $this->request->getPost("resource_type"),
            "description" => $this->request->getPost("description"),
            "capacity" => $this->request->getPost("capacity"),
            "business_area_id" => $this->request->getPost("business_area_id"),
            "cost_center_id" => $this->request->getPost("cost_center_id"),
            "is_bookable" => $this->request->getPost("is_bookable"),
            "is_active" => $this->request->getPost("is_active"),
            "sort_order" => $this->request->getPost("sort_order"),
            "metadata" => $this->request->getPost("metadata"),
        ];
    }

    private function row($x): array
    {
        $actions = anchor(get_uri("grupo_donato/resources/view/" . $x->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        if ($this->access->can("gd_resources_manage")) {
            $actions .= modal_anchor(get_uri("grupo_donato/resources/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["title" => app_lang("edit"), "data-post-id" => $x->id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/resources/delete"), "data-action" => "delete"]);
        }
        return [
            $this->escape($x->code),
            $this->escape($x->name),
            app_lang("gd_resource_type_" . $x->resource_type),
            isset($x->business_area_name) && $x->business_area_name ? $this->escape($x->business_area_name) : "-",
            isset($x->cost_center_name) && $x->cost_center_name ? $this->escape($x->cost_center_name) : "-",
            $x->capacity !== null ? (int) $x->capacity : "-",
            "<span class='badge " . ((int) $x->is_bookable ? "bg-info" : "bg-secondary") . "'>" . app_lang((int) $x->is_bookable ? "yes" : "no") . "</span>",
            "<span class='badge " . ((int) $x->is_active ? "bg-success" : "bg-secondary") . "'>" . app_lang((int) $x->is_active ? "gd_status_active" : "gd_status_inactive") . "</span>",
            $x->updated_at ? format_to_datetime($x->updated_at) : "",
            $actions,
        ];
    }

    private function type_options(): array
    {
        $list = [];
        foreach (Constants::RESOURCE_TYPES as $t) { $list[] = ["id" => $t, "text" => app_lang("gd_resource_type_" . $t)]; }
        return $list;
    }

    private function areas_dropdown(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_business_areas_model")->get_details(["status" => Constants::STATUS_ACTIVE])->getResult() as $a) {
            if ($a->unit_id !== null && (int) $a->unit_id !== $this->unit_id) { continue; }
            $list[] = ["id" => (int) $a->id, "text" => $a->name];
        }
        return $list;
    }

    private function cost_centers_dropdown(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_cost_centers_model")->get_details()->getResult() as $cc) {
            if ($cc->unit_id !== null && (int) $cc->unit_id !== $this->unit_id) { continue; }
            if ((string) $cc->status !== Constants::STATUS_ACTIVE) { continue; }
            $list[] = ["id" => (int) $cc->id, "text" => $cc->name, "area" => (int) ($cc->business_area_id ?? 0)];
        }
        return $list;
    }

    private function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
