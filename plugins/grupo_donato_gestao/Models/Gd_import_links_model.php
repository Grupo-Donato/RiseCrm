<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_import_links_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_import_links"); }

    /** @return array<object> */
    public function for_batch(int $batch_id, int $unit_id): array
    {
        return $this->db->table($this->table)->where("batch_id", $batch_id)->where("unit_id", $unit_id)->where("deleted", 0)->orderBy("row_number", "ASC")->orderBy("id", "ASC")->get()->getResult();
    }

    /** Alvo já criado para uma chave lógica (dedupe de reimport). */
    public function target_for_source(int $unit_id, string $source_key, string $target_type): ?object
    {
        return $this->db->table($this->table)->where("unit_id", $unit_id)->where("source_key", $source_key)->where("target_type", $target_type)->where("deleted", 0)->get(1)->getRow();
    }
}
