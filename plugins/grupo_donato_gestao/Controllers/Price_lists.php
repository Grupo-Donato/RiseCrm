<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\PriceListService;

class Price_lists extends Gd_Controller
{
    private $model;
    private PriceListService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_price_lists_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_price_lists_model");
        $this->service = new PriceListService($this->unit_id, $this->user_id(), $this->login_user);
    }

    public function index()
    {
        return $this->gd_render("pricing/lists_index", [
            "can_manage" => $this->access->can("gd_price_lists_manage"),
        ]);
    }

    public function view($id)
    {
        $row = $this->service->get((int) $id);
        if (!$row) { return show_404(); }
        return $this->gd_render("pricing/list_view", [
            "list" => $row,
            "can_manage_prices" => $this->access->can("gd_prices_manage"),
            "product_options" => $this->product_options(),
            "resource_options" => $this->resource_options(),
            "category_options" => $this->category_options(),
            "type_options" => $this->type_options(),
        ]);
    }

    public function list_data()
    {
        $rows = $this->model->get_details(["unit_id" => $this->unit_id])->getResult();
        $result = [];
        foreach ($rows as $x) { $result[] = $this->row($x); }
        echo json_encode(["data" => $result]);
    }

    public function modal_form()
    {
        $this->access->require("gd_price_lists_manage");
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && !$row) { return show_404(); }
        return $this->gd_view("pricing/list_modal", [
            "model_info" => $row ?: new \stdClass(),
            "statuses" => $this->status_options(Constants::PRICE_LIST_STATUSES),
        ]);
    }

    public function save()
    {
        $this->access->require("gd_price_lists_manage");
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
        $this->access->require("gd_price_lists_manage");
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
            "currency" => $this->request->getPost("currency"),
            "priority" => $this->request->getPost("priority"),
            "valid_from" => $this->request->getPost("valid_from"),
            "valid_until" => $this->request->getPost("valid_until"),
            "is_default" => $this->request->getPost("is_default"),
            "status" => $this->request->getPost("status"),
        ];
    }

    private function row($x): array
    {
        $actions = anchor(get_uri("grupo_donato/pricing/lists/view/" . $x->id), "<i data-feather='eye' class='icon-16'></i>", ["title" => app_lang("gd_view_prices")]);
        if ($this->access->can("gd_price_lists_manage")) {
            $actions .= modal_anchor(get_uri("grupo_donato/pricing/lists/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["title" => app_lang("edit"), "data-post-id" => $x->id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/pricing/lists/delete"), "data-action" => "delete"]);
        }
        $validity = ($x->valid_from ? format_to_date($x->valid_from) : "∞") . " — " . ($x->valid_until ? format_to_date($x->valid_until) : "∞");
        return [
            $this->escape($x->code),
            $this->escape($x->name),
            $this->escape($x->currency),
            $validity,
            (int) $x->priority,
            (int) $x->is_default ? "<span class='badge bg-primary'>" . app_lang("yes") . "</span>" : "",
            "<span class='badge " . ($x->status === "active" ? "bg-success" : "bg-secondary") . "'>" . app_lang("gd_status_" . $x->status) . "</span>",
            (int) ($x->price_count ?? 0),
            $actions,
        ];
    }

    private function status_options(array $statuses): array
    {
        $list = [];
        foreach ($statuses as $s) { $list[] = ["id" => $s, "text" => app_lang("gd_status_" . $s)]; }
        return $list;
    }

    private function product_options(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_products_model")->get_details(["unit_id" => $this->unit_id, "limit" => 100])["data"] as $p) {
            $list[] = ["id" => (int) $p->id, "text" => $p->code . " — " . $p->name];
        }
        return $list;
    }

    private function resource_options(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_resources_model")->get_details(["unit_id" => $this->unit_id, "limit" => 100])["data"] as $r) {
            $list[] = ["id" => (int) $r->id, "text" => $r->code . " — " . $r->name];
        }
        return $list;
    }

    private function category_options(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_product_categories_model")->get_details(["unit_id" => $this->unit_id, "status" => "active"])->getResult() as $c) {
            $list[] = ["id" => (int) $c->id, "text" => $c->name];
        }
        return $list;
    }

    private function type_options(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach (Constants::PRODUCT_TYPES_SELECTABLE as $t) { $list[] = ["id" => $t, "text" => app_lang("gd_product_type_" . $t)]; }
        return $list;
    }

    private function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
