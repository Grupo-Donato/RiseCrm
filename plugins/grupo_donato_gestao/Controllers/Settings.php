<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\SettingsService;

class Settings extends Gd_Controller
{
    private SettingsService $settings;

    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_settings_view");
        $this->settings = new SettingsService();
    }

    public function index()
    {
        return $this->general();
    }

    public function general()
    {
        $view_data = [
            "active_tab" => "general",
            "active_unit" => $this->unit_context->get_active_unit(),
            "units_dropdown" => $this->units_dropdown(),
            "document_prefix" => (string) $this->settings->get("document_prefix", null, ""),
            "default_country" => (string) $this->settings->get("default_country", null, ""),
            "can_manage" => $this->access->can("gd_settings_manage"),
            // Hub de configurações (cartões para telas existentes)
            "can_units" => $this->access->can("gd_units_view"),
            "can_areas" => $this->access->can("gd_business_areas_view"),
            "can_centers" => $this->access->can("gd_cost_centers_view"),
            "can_catalog" => $this->access->can("gd_catalog_view"),
            "can_resources" => $this->access->can("gd_resources_view"),
            "can_pricing" => $this->access->can("gd_price_lists_view"),
            "can_audit" => $this->access->can("gd_audit_view"),
            "is_admin" => !empty($this->login_user->is_admin),
            // Informações do sistema
            "plugin_version" => Constants::PLUGIN_VERSION,
            "schema_applied" => (string) ($this->gd_model("Gd_schema_versions_model")->get_applied_version() ?: "-"),
            "schema_target" => Constants::SCHEMA_TARGET,
        ];
        return $this->gd_render("settings/general", $view_data);
    }

    public function save_general()
    {
        $this->access->require("gd_settings_manage");
        $this->validate_submitted_data([
            "active_unit_id" => "numeric",
            "document_prefix" => "max_length[20]",
            "default_country" => "max_length[80]",
        ]);

        $changed = [];
        $before = [
            "active_unit_id" => $this->active_unit_id(),
            "document_prefix" => (string) $this->settings->get("document_prefix", null, ""),
            "default_country" => (string) $this->settings->get("default_country", null, ""),
        ];

        $unit_id = (int) $this->request->getPost("active_unit_id");
        if ($unit_id) {
            if (!$this->unit_context->set_active_unit($unit_id)) {
                return $this->json_error(app_lang("gd_invalid_unit"));
            }
            $changed["active_unit_id"] = $unit_id;
        }

        // valor seguro (não secreto)
        $prefix = strip_tags(trim((string) $this->request->getPost("document_prefix")));
        if (!$this->settings->set("document_prefix", $prefix, null, "string", false, $this->user_id())) {
            return $this->json_error(app_lang("error_occurred"));
        }
        $changed["document_prefix"] = $prefix;

        $default_country = strip_tags(trim((string) $this->request->getPost("default_country")));
        if (!$this->settings->set("default_country", $default_country, null, "string", false, $this->user_id())) {
            return $this->json_error(app_lang("error_occurred"));
        }
        $changed["default_country"] = $default_country;

        $this->audit->log("update", "settings", null, $before, $changed);

        return $this->json_success(app_lang("settings_updated"));
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
}
