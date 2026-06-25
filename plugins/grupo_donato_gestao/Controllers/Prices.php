<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\PricingService;

class Prices extends Gd_Controller
{
    private $model;
    private $lists;
    private PricingService $service;
    private int $unit_id;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_price_lists_view");
        $this->unit_id = (int) $this->active_unit_id();
        if (!$this->unit_id) { throw new \RuntimeException("No active unit."); }
        $this->model = $this->gd_model("Gd_prices_model");
        $this->lists = $this->gd_model("Gd_price_lists_model");
        $this->service = new PricingService($this->unit_id, $this->user_id(), $this->login_user);
    }

    private function list_or_404(int $list_id): object
    {
        $list = $this->lists->get_scoped($list_id, $this->unit_id);
        if (!$list) { show_404(); exit(); }
        return $list;
    }

    public function list_data()
    {
        $list_id = (int) $this->request->getPost("price_list_id");
        $this->list_or_404($list_id);
        $o = append_server_side_filtering_commmon_params([
            "unit_id" => $this->unit_id,
            "price_list_id" => $list_id,
            "product_id" => (int) $this->request->getPost("product_id") ?: null,
            "category_id" => (int) $this->request->getPost("category_id") ?: null,
            "product_type" => $this->request->getPost("product_type"),
            "resource_id" => (int) $this->request->getPost("resource_id") ?: null,
            "status" => $this->request->getPost("status"),
        ]);
        $r = $this->model->get_details($o);
        $rows = [];
        foreach ($r["data"] as $x) { $rows[] = $this->row($x); }
        $r["data"] = $rows;
        echo json_encode($r);
    }

    public function modal_form()
    {
        $this->access->require("gd_prices_manage");
        $list_id = (int) $this->request->getPost("price_list_id");
        $list = $this->list_or_404($list_id);
        $id = (int) $this->request->getPost("id");
        $row = $id ? $this->service->get($id) : null;
        if ($id && (!$row || (int) $row->price_list_id !== $list_id)) { return show_404(); }
        return $this->gd_view("pricing/price_modal", [
            "model_info" => $row ?: new \stdClass(),
            "price_list" => $list,
            "product_options" => $this->product_options(),
            "resource_options" => $this->resource_options(),
            "status_options" => $this->status_options(Constants::PRICE_STATUSES),
            "variant_options" => $row && $row->variant_id ? $this->variant_options((int) $row->product_id) : [["id" => "", "text" => "-"]],
        ]);
    }

    public function save()
    {
        $this->access->require("gd_prices_manage");
        try {
            $r = $this->service->save($this->input(), (int) $this->request->getPost("id"));
            $detail = $this->model->get_details(["unit_id" => $this->unit_id, "id" => $r["id"], "limit" => 1])["data"][0] ?? null;
            return $this->json_success(app_lang("record_saved"), ["id" => $r["id"], "data" => $detail ? $this->row($detail) : null]);
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    public function delete()
    {
        $this->access->require("gd_prices_manage");
        try {
            $this->service->delete((int) $this->request->getPost("id"));
            return $this->json_success(app_lang("record_deleted"));
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    /** Variações de um produto (para o dropdown do modal de preço). */
    public function variants()
    {
        $product_id = (int) $this->request->getPost("product_id");
        $product = $this->gd_model("Gd_products_model")->get_scoped($product_id, $this->unit_id);
        $data = [];
        if ($product) {
            foreach ($this->gd_model("Gd_product_variants_model")->get_details(["unit_id" => $this->unit_id, "product_id" => $product_id])->getResult() as $v) {
                if ((string) $v->status !== "active") { continue; }
                $data[] = ["id" => (int) $v->id, "text" => $v->code . " — " . $v->name];
            }
        }
        return $this->json_success("", ["variants" => $data]);
    }

    /** Ferramenta administrativa de teste de resolução de preço. */
    public function resolver()
    {
        return $this->gd_render("pricing/resolver", [
            "product_options" => $this->product_options(),
            "resource_options" => $this->resource_options(),
            "list_options" => $this->list_options(),
        ]);
    }

    /** Resolve o preço aplicável (somente leitura, sem efeito colateral). */
    public function resolve()
    {
        try {
            $result = $this->service->resolve([
                "price_list_id" => (int) $this->request->getPost("price_list_id") ?: null,
                "product_id" => (int) $this->request->getPost("product_id"),
                "variant_id" => (int) $this->request->getPost("variant_id") ?: null,
                "resource_id" => (int) $this->request->getPost("resource_id") ?: null,
                "quantity" => (string) ($this->request->getPost("quantity") ?: "1"),
                "reference_date" => (string) $this->request->getPost("reference_date"),
            ]);
            return $this->json_success("", ["result" => $result]);
        } catch (\Throwable $e) { return $this->fail($e); }
    }

    private function input(): array
    {
        return [
            "price_list_id" => $this->request->getPost("price_list_id"),
            "product_id" => $this->request->getPost("product_id"),
            "variant_id" => $this->request->getPost("variant_id"),
            "resource_id" => $this->request->getPost("resource_id"),
            "amount" => $this->request->getPost("amount"),
            "reference_cost" => $this->request->getPost("reference_cost"),
            "minimum_quantity" => $this->request->getPost("minimum_quantity"),
            "valid_from" => $this->request->getPost("valid_from"),
            "valid_until" => $this->request->getPost("valid_until"),
            "status" => $this->request->getPost("status"),
        ];
    }

    private function row($x): array
    {
        $actions = "";
        if ($this->access->can("gd_prices_manage")) {
            $actions = modal_anchor(get_uri("grupo_donato/pricing/prices/modal_form"), "<i data-feather='edit' class='icon-16'></i>", ["title" => app_lang("edit"), "data-post-id" => $x->id, "data-post-price_list_id" => $x->price_list_id])
                . js_anchor("<i data-feather='x' class='icon-16'></i>", ["title" => app_lang("delete"), "class" => "delete", "data-id" => $x->id, "data-action-url" => get_uri("grupo_donato/pricing/prices/delete"), "data-action" => "delete"]);
        }
        $validity = ($x->valid_from ? format_to_date($x->valid_from) : "∞") . " — " . ($x->valid_until ? format_to_date($x->valid_until) : "∞");
        return [
            $this->escape(($x->product_code ?? "") . " — " . ($x->product_name ?? "")),
            isset($x->variant_name) && $x->variant_name ? $this->escape($x->variant_name) : "-",
            isset($x->resource_name) && $x->resource_name ? $this->escape($x->resource_name) : "-",
            $this->escape(rtrim(rtrim((string) $x->minimum_quantity, "0"), ".")),
            "<span class='text-end d-block'>" . $this->escape(to_currency((float) $x->amount)) . "</span>",
            $x->reference_cost !== null ? $this->escape(to_currency((float) $x->reference_cost)) : "-",
            $validity,
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

    private function product_options(): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_products_model")->get_details(["unit_id" => $this->unit_id, "limit" => 100])["data"] as $p) {
            $list[] = ["id" => (int) $p->id, "text" => $p->code . " — " . $p->name];
        }
        return $list;
    }

    private function variant_options(int $product_id): array
    {
        $list = [["id" => "", "text" => "-"]];
        foreach ($this->gd_model("Gd_product_variants_model")->get_details(["unit_id" => $this->unit_id, "product_id" => $product_id])->getResult() as $v) {
            $list[] = ["id" => (int) $v->id, "text" => $v->code . " — " . $v->name];
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

    private function list_options(): array
    {
        $list = [["id" => "", "text" => app_lang("gd_default_price_list")]];
        foreach ($this->lists->get_details(["unit_id" => $this->unit_id])->getResult() as $l) {
            $list[] = ["id" => (int) $l->id, "text" => $l->code . " — " . $l->name];
        }
        return $list;
    }

    private function fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
