<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_import_issues_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_import_issues"); }

    /** @return array<object> */
    public function for_batch(int $batch_id, int $unit_id): array
    {
        return $this->db->table($this->table)->where("batch_id", $batch_id)->where("unit_id", $unit_id)->where("deleted", 0)->orderBy("row_number", "ASC")->orderBy("id", "ASC")->get()->getResult();
    }

    /** Marca como superados (soft delete) os issues de um lote antes de revalidar. */
    public function supersede_batch(int $batch_id, int $unit_id): void
    {
        $this->db->table($this->table)->where("batch_id", $batch_id)->where("unit_id", $unit_id)->where("deleted", 0)->update(["deleted" => 1]);
    }
}
