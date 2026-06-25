<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Base dos services do catálogo (Fase 2B). Reaproveita stamp/audit/valid_date/
 * assert_rise_id de CustomerDataService e adiciona validações de relação por
 * unidade compartilhadas (área de negócio, centro de resultado, categoria).
 */
abstract class CatalogDataService extends CustomerDataService
{
    /** Área de negócio opcional: se informada, ativa e da unidade (ou global). */
    protected function assert_area(?int $area_id): ?int
    {
        if (!$area_id) { return null; }
        $t = $this->db->prefixTable("gd_business_areas");
        $row = $this->db->table($t)->where("id", $area_id)->where("deleted", 0)->get(1)->getRow();
        if (!$row || (string) $row->status !== "active") { throw new \DomainException("gd_invalid_business_area"); }
        $area_unit = ($row->unit_id !== null && $row->unit_id !== "") ? (int) $row->unit_id : null;
        if ($area_unit !== null && $area_unit !== $this->unit_id) { throw new \DomainException("gd_invalid_business_area"); }
        return (int) $area_id;
    }

    /** Centro opcional: se informado, ativo, da unidade e compatível com a área. */
    protected function assert_cost_center(?int $cc_id, ?int $area_id): ?int
    {
        if (!$cc_id) { return null; }
        $t = $this->db->prefixTable("gd_cost_centers");
        $row = $this->db->table($t)->where("id", $cc_id)->where("deleted", 0)->get(1)->getRow();
        if (!$row || (string) $row->status !== "active") { throw new \DomainException("gd_invalid_cost_center"); }
        $cc_unit = ($row->unit_id !== null && $row->unit_id !== "") ? (int) $row->unit_id : null;
        if ($cc_unit !== null && $cc_unit !== $this->unit_id) { throw new \DomainException("gd_invalid_cost_center"); }
        $cc_area = ($row->business_area_id !== null && $row->business_area_id !== "") ? (int) $row->business_area_id : null;
        if ($area_id && $cc_area !== null && $cc_area !== $area_id) { throw new \DomainException("gd_incompatible_cost_center"); }
        return (int) $cc_id;
    }

    /** Categoria opcional: se informada, da unidade e não excluída. */
    protected function assert_category(?int $cat_id): ?int
    {
        if (!$cat_id) { return null; }
        $t = $this->db->prefixTable("gd_product_categories");
        $row = $this->db->table($t)->where("id", $cat_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_invalid_category"); }
        return (int) $cat_id;
    }

    /** Filtra um array de matches deixando apenas os "fortes" (exigem confirmação). */
    protected function strong_matches(array $matches): array
    {
        return array_values(array_filter($matches, static fn($m) => in_array($m["confidence"], ["exact", "high"], true)));
    }
}
