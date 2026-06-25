<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Seeds;

use grupo_donato_gestao\Config\Constants;

/**
 * Seeds do catálogo (Fase 2B) — idempotentes e SEM dados comerciais fictícios.
 *
 * Cria apenas:
 *  - os recursos físicos REAIS Q2–Q6 (quadras), sem preço/capacidade/área;
 *  - uma tabela de preço padrão vazia por unidade (DEFAULT), necessária para a
 *    resolução mínima de preço.
 *
 * Nada de produtos, categorias, valores, bar, uniformes ou mensalidades.
 */
class CatalogSeeder
{
    private $db;
    private $units_model;
    private int $actor_id;

    /** Quadras reais de infraestrutura: code => nome. */
    private const COURTS = [
        "Q2" => "Quadra Q2",
        "Q3" => "Quadra Q3",
        "Q4" => "Quadra Q4",
        "Q5" => "Quadra Q5",
        "Q6" => "Quadra Q6",
    ];

    public function __construct(int $actor_id = 0)
    {
        $this->db = db_connect();
        $this->units_model = model("grupo_donato_gestao\\Models\\Gd_units_model");
        $this->actor_id = $actor_id;
    }

    public function run(): void
    {
        $unit = $this->units_model->get_default();
        if (!$unit) { return; } // sem unidade padrão → nada a semear
        $unit_id = (int) $unit->id;

        $this->seed_courts($unit_id);
        $this->seed_default_price_list($unit_id);
    }

    private function seed_courts(int $unit_id): void
    {
        $table = $this->db->prefixTable("gd_resources");
        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");

        foreach (self::COURTS as $code => $name) {
            $exists = $this->db->table($table)
                ->where("unit_id", $unit_id)->where("code", $code)->where("deleted", 0)
                ->countAllResults();
            if ($exists > 0) { continue; } // não duplica nem sobrescreve edição posterior

            $this->db->table($table)->insert([
                "unit_id" => $unit_id,
                "business_area_id" => null,
                "cost_center_id" => null,
                "code" => $code,
                "name" => $name,
                "resource_type" => "court",
                "description" => null,
                "capacity" => null,
                "is_bookable" => 1,
                "is_active" => 1,
                "sort_order" => 0,
                "metadata" => null,
                "deleted" => 0,
                "created_at" => $now,
                "updated_at" => $now,
                "created_by" => $this->actor_id ?: null,
                "updated_by" => $this->actor_id ?: null,
            ]);
        }
    }

    private function seed_default_price_list(int $unit_id): void
    {
        $table = $this->db->prefixTable("gd_price_lists");
        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");

        $exists = $this->db->table($table)
            ->where("unit_id", $unit_id)->where("code", "DEFAULT")->where("deleted", 0)
            ->countAllResults();
        if ($exists > 0) { return; }

        // Se já houver outra padrão ativa, não cria conflito de índice único.
        $has_default = $this->db->table($table)
            ->where("unit_id", $unit_id)->where("deleted", 0)->where("status", "active")->where("is_default", 1)
            ->countAllResults();

        $this->db->table($table)->insert([
            "unit_id" => $unit_id,
            "code" => "DEFAULT",
            "name" => "Preço padrão",
            "description" => null,
            "currency" => Constants::DEFAULT_CURRENCY,
            "priority" => 0,
            "valid_from" => null,
            "valid_until" => null,
            "is_default" => $has_default > 0 ? 0 : 1,
            "status" => "active",
            "deleted" => 0,
            "created_at" => $now,
            "updated_at" => $now,
            "created_by" => $this->actor_id ?: null,
            "updated_by" => $this->actor_id ?: null,
        ]);
    }
}
