<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ProductService;

class Products extends Gd_Controller
{
    private $model;
    private ProductService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_catalog_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_products_model");
        $this->service = new ProductService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("catalog/products_index", [
            "can_manage" => $this->access->can("gd_products_manage"),
            "can_categories" => $this->access->can("gd_product_categories_manage"),
            "type_options" => $this->type_options(),
            "category_options" => $this->categories_dropdown(),
            "area_options" => $this->areas_dropdown(),
            "status_options" => $this->status_options(Constants::PRODUCT_STATUSES),
        ]);
    }

    public function view($id)
    {
        $row = $this->service->get((int) $id);
        if (!$row) { return show_404(); }
        $detail = $this->model->get_details(["unit_id" => $this->unit_id, "id" => (int) $row->id, "limit" => 1])["data"][0] ?? $row;
        $can_audit = $this->access->can("gd_audit_view");
        $audits = $can_audit ? $this->gd_model("Gd_audit_logs_model")->get_details(["unit_id" => $this->unit_id, "entity_type" => "product", "entity_id" => (int) $row->id, "limit" => 8])->getResult() : [];
        return $this->gd_render("catalog/product_view", [
            "product" => $detail,
            "variants" => $this->gd_model("Gd_product_variants_model")->get_details(["unit_id" => $this->unit_id, "product_id" => (int) $row->id])->getResult(),
            "prices" => $this->gd_model("Gd_prices_model")->active_for_product((int) $row->id, $this->unit_id),
            "audits" => $audits,
            "can_manage" => $this->access->can("gd_products_manage"),
            "can_audit" => $can_audit,
        ]);
    }

    public function list_data()
    {
        $o = append_server_side_filtering_commmon_params([
            "unit_id" => $this->unit_id,
            "product_type" => $this->request->getPost("product_type"),
            "status" => $this->request->getPost("status"),
            "category_id" => (int) $this->request->getPost("category_id") ?: null,
            "business_area_id" => (int) $this->request->getPost("business_area_id") ?: null,
        ]);
        $r = $this->model->get_details($o);
        $rows = [];
        foreach ($r["data"] as $x) { $rows[] = $this->row($x); }
        $r["data"] = $rows;
        echo json_encode($r);
    }

    public function modal_form()
    {
        $this->access->require("gd_products_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && !$row) { return show_404(); }
        return $this->gd_view("catalog/product_modal", [
            "model_info" => $row ?: new \stdClass(),
            "type_options" => $this->type_options(),
            "billing_options" => $this->simple_options(Constants::BILLING_MODES, "gd_billing_mode_"),
            "uom_options" => $this->simple_options(Constants::UNITS_OF_MEASURE, "gd_uom_"),
            "status_options" => $this->status_options(Constants::PRODUCT_STATUSES),
            "category_options" => $this->categories_dropdown(),
            "area_options" => $this->areas_dropdown(),
            "cost_center_options" => $this->cost_centers_dropdown(),
        ]);
    }

    public function save()
    {
        $this->access->require("gd_products_manage");
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
        $this->access->require("gd_products_manage");
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
            "description" => $this->request->getPost("description"),
            "product_type" => $this->request->getPost("product_type"),
            "billing_mode" => $this->request->getPost("billing_mode"),
            "unit_of_measure" => $this->request->getPost("unit_of_measure"),
            "category_id" => $this->request->getPost("category_id"),
            "business_area_id" => $this->request->getPost("business_area_id"),
            "default_cost_center_id" => $this->request->getPost("default_cost_center_id"),
            "track_stock" => $this->request->getPost("track_stock"),
            "allows_variants" => $this->request->getPost("allows_variants"),
            "allows_discount" => $this->request->getPost("allows_discount"),
            "requires_resource" => $this->request->getPost("requires_resource"),
            "status" => $this->request->getPost("status"),
            "rise_item_id" => $this->request->getPost("rise_item_id"),
            "metadata" => $this->request->getPost("metadata"),
        ];
    }

    private function row($x): array
    {
        $actions = anchor(get_uri("grupo_donato/catalog/products/view/" . $x->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_details")]);
        if ($this->access->can("gd_products_manage")) {
            $actions .= modal_anchor(get_uri("grupo_donato/catalog/products/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["title" => app_lang("edit"), "data-post-id" => $x->id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/catalog/products/delete"), "data-action" => "delete"]);
        }
        $status_class = $x->status === "active" ? "bg-success" : ($x->status === "draft" ? "bg-warning" : "bg-secondary");
        return [
            $this->escape($x->code),
            $this->escape($x->name),
            app_lang("gd_product_type_" . $x->product_type),
            isset($x->category_name) && $x->category_name ? $this->escape($x->category_name) : "-",
            isset($x->business_area_name) && $x->business_area_name ? $this->escape($x->business_area_name) : "-",
            app_lang("gd_billing_mode_" . $x->billing_mode),
            app_lang("gd_uom_" . $x->unit_of_measure),
            (int) ($x->variant_count ?? 0),
            "<span class='badge $status_class'>" . app_lang("gd_status_" . $x->status) . "</span>",
            $x->updated_at ? format_to_datetime($x->updated_at) : "",
            $actions,
        ];
    }

    private function type_options(): array { return $this->simple_options(Constants::PRODUCT_TYPES_SELECTABLE, "gd_product_type_"); }

    private function simple_options(array $values, string $prefix): array
    {
        $list = [];
        foreach ($values as $v) { $list[] = ["id" => $v, "text" => app_lang($prefix . $v)]; }
        return $list;
    }

    private function status_options(array $statuses): array
    {
        $list = [];
        foreach ($statuses as $s) { $list[] = ["id" => $s, "text" => app_lang("gd_status_" . $s)]; }
        return $list;
    }

    private function categories_dropdown(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_product_categories_model")->get_details(["unit_id" => $this->unit_id, "status" => "active"])->getResult() as $c) {
            $list[] = ["id" => (int) $c->id, "text" => $c->name];
        }
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
            $list[] = ["id" => (int) $cc->id, "text" => $cc->name];
        }
        return $list;
    }

    private function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
