<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ProductCategoryService;

class Product_categories extends Gd_Controller
{
    private $model;
    private ProductCategoryService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_catalog_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_product_categories_model");
        $this->service = new ProductCategoryService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("catalog/categories_index", [
            "can_manage" => $this->access->can("gd_product_categories_manage"),
        ]);
    }

    public function list_data()
    {
        $rows = $this->model->get_details(["unit_id" => $this->unit_id])->getResult();
        $result = [];
        foreach ($rows as $row) { $result[] = $this->row($row); }
        echo json_encode(["data" => $result]);
    }

    public function modal_form()
    {
        $this->access->require("gd_product_categories_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && !$row) { return show_404(); }
        return $this->gd_view("catalog/category_modal", [
            "model_info" => $row ?: new \stdClass(),
            "parents" => $this->parents_dropdown($id),
            "statuses" => $this->status_options(Constants::PRODUCT_CATEGORY_STATUSES),
        ]);
    }

    public function save()
    {
        $this->access->require("gd_product_categories_manage");
        try {
            $r = $this->service->save($this->input(), (int) $this->request->getPost("id"), (bool) $this->request->getPost("duplicate_override"));
            if (empty($r["saved"])) {
                return $this->json_error(app_lang("gd_duplicate_confirmation_required"), ["duplicate_confirmation_required" => true, "duplicates" => $r["duplicates"]]);
            }
            return $this->json_success(app_lang("record_saved"), ["id" => $r["id"], "data" => $this->row($this->model->get_details(["id" => $r["id"]])->getRow())]);
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    public function delete()
    {
        $this->access->require("gd_product_categories_manage");
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
            "parent_id" => $this->request->getPost("parent_id"),
            "sort_order" => $this->request->getPost("sort_order"),
            "status" => $this->request->getPost("status"),
        ];
    }

    private function row($x): array
    {
        $actions = "";
        if ($this->access->can("gd_product_categories_manage")) {
            $actions = modal_anchor(get_uri("grupo_donato/catalog/categories/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["class" => "edit", "title" => app_lang("edit"), "data-post-id" => $x->id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/catalog/categories/delete"), "data-action" => "delete"]);
        }
        return [
            $this->escape($x->code),
            $this->escape($x->name),
            isset($x->parent_name) && $x->parent_name ? $this->escape($x->parent_name) : "-",
            (int) $x->sort_order,
            "<span class='badge " . ($x->status === "active" ? "bg-success" : "bg-secondary") . "'>" . app_lang("gd_status_" . $x->status) . "</span>",
            $actions,
        ];
    }

    private function parents_dropdown(int $exclude_id): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->model->get_details(["unit_id" => $this->unit_id, "status" => "active"])->getResult() as $cat) {
            if ($exclude_id && (int) $cat->id === $exclude_id) { continue; }
            $list[] = ["id" => (int) $cat->id, "text" => $cat->name];
        }
        return $list;
    }

    private function status_options(array $statuses): array
    {
        $list = [];
        foreach ($statuses as $s) { $list[] = ["id" => $s, "text" => app_lang("gd_status_" . $s)]; }
        return $list;
    }

    private function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
