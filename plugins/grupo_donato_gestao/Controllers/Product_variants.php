<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\ProductVariantService;

class Product_variants extends Gd_Controller
{
    private $model;
    private $products;
    private ProductVariantService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_catalog_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_product_variants_model");
        $this->products = $this->gd_model("Gd_products_model");
        $this->service = new ProductVariantService($this->unit_id, $this->user_id(), $this->login_user);
    }

    /** Confirma que o produto pertence à unidade ativa (evita IDOR). */
    private function product_or_404(int $product_id): object
    {
        $product = $this->products->get_scoped($product_id, $this->unit_id);
        if (!$product) { show_404(); exit(); }
        return $product;
    }

    public function list_data()
    {
        $product_id = (int) $this->request->getPost("product_id");
        $this->product_or_404($product_id);
        $rows = $this->model->get_details(["unit_id" => $this->unit_id, "product_id" => $product_id])->getResult();
        $result = [];
        foreach ($rows as $x) { $result[] = $this->row($x); }
        echo json_encode(["data" => $result, "recordsTotal" => count($result), "recordsFiltered" => count($result)]);
    }

    public function modal_form()
    {
        $this->access->require("gd_products_manage");
        $product_id = (int) $this->request->getPost("product_id");
        $this->product_or_404($product_id);
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && (!$row || (int) $row->product_id !== $product_id)) { return show_404(); }
        return $this->gd_view("catalog/variant_modal", [
            "model_info" => $row ?: new \stdClass(),
            "product_id" => $product_id,
            "statuses" => $this->status_options(Constants::VARIANT_STATUSES),
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
            return $this->json_success(app_lang("record_saved"), ["id" => $r["id"], "data" => $this->row($this->service->get($r["id"]))]);
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
            "product_id" => $this->request->getPost("product_id"),
            "code" => $this->request->getPost("code"),
            "name" => $this->request->getPost("name"),
            "barcode" => $this->request->getPost("barcode"),
            "attributes" => $this->request->getPost("attributes"),
            "is_default" => $this->request->getPost("is_default"),
            "sort_order" => $this->request->getPost("sort_order"),
            "status" => $this->request->getPost("status"),
        ];
    }

    private function row($x): array
    {
        $actions = "";
        if ($this->access->can("gd_products_manage")) {
            $actions = modal_anchor(get_uri("grupo_donato/catalog/variants/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["title" => app_lang("edit"), "data-post-id" => $x->id, "data-post-product_id" => $x->product_id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/catalog/variants/delete"), "data-action" => "delete"]);
        }
        return [
            $this->escape($x->code),
            $this->escape($x->name),
            $x->barcode ? $this->escape($x->barcode) : "-",
            $x->attributes ? "<code>" . $this->escape($x->attributes) . "</code>" : "-",
            (int) $x->is_default ? "<span class='badge bg-primary'>" . app_lang("yes") . "</span>" : "",
            "<span class='badge " . ($x->status === "active" ? "bg-success" : "bg-secondary") . "'>" . app_lang("gd_status_" . $x->status) . "</span>",
            $actions,
        ];
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
