<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Seeds;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\SettingsService;

/**
 * Seeds da fundação — idempotentes e SEM dados comerciais fictícios.
 *
 * Cria: a unidade padrão (apenas nome genérico + timezone do app), as áreas de
 * negócio iniciais (códigos estáveis) e configurações técnicas seguras.
 */
class FoundationSeeder
{
    private $units_model;
    private $areas_model;
    private SettingsService $settings;
    private int $actor_id;

    /** Áreas de negócio iniciais: code => nome de exibição padrão. */
    private const BUSINESS_AREAS = [
        "school" => "Escola",
        "court_rental" => "Locação de Quadras",
        "events" => "Eventos e Festas",
        "bar" => "Bar",
        "store" => "Loja / Produtos",
        "sports_events" => "Campeonatos",
        "personal" => "Personal",
    ];

    public function __construct(int $actor_id = 0)
    {
        $this->units_model = model("grupo_donato_gestao\\Models\\Gd_units_model");
        $this->areas_model = model("grupo_donato_gestao\\Models\\Gd_business_areas_model");
        $this->settings = new SettingsService();
        $this->actor_id = $actor_id;
    }

    public function run(): void
    {
        $this->seed_default_unit();
        $this->seed_business_areas();
        $this->seed_settings();
    }

    private function seed_default_unit(): void
    {
        if ($this->units_model->count_active() > 0) {
            return; // já existe alguma unidade
        }

        $timezone = function_exists("get_setting") ? (string) get_setting("timezone") : "";

        $data = [
            "name" => "Unidade Principal",
            "legal_name" => null,
            "document" => null,
            "timezone" => $timezone ?: "America/Sao_Paulo",
            "status" => Constants::STATUS_ACTIVE,
            "is_default" => 1,
            "deleted" => 0,
        ];
        if ($this->actor_id) {
            $data["created_by"] = $this->actor_id;
            $data["updated_by"] = $this->actor_id;
        }

        $this->units_model->ci_save($data);
    }

    private function seed_business_areas(): void
    {
        foreach (self::BUSINESS_AREAS as $code => $name) {
            if ($this->areas_model->is_duplicate_code($code, null)) {
                continue;
            }
            $data = [
                "unit_id" => null,
                "code" => $code,
                "name" => $name,
                "status" => Constants::STATUS_ACTIVE,
                "deleted" => 0,
            ];
            if ($this->actor_id) {
                $data["created_by"] = $this->actor_id;
                $data["updated_by"] = $this->actor_id;
            }
            $this->areas_model->ci_save($data);
        }
    }

    private function seed_settings(): void
    {
        $this->settings->set("plugin_version", Constants::PLUGIN_VERSION, null, "string", false, $this->actor_id);
        if ($this->settings->get("installed_at") === null) {
            $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
            $this->settings->set("installed_at", $now, null, "string", false, $this->actor_id);
        }
    }
}
