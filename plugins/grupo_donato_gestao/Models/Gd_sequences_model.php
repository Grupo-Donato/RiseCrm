<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_sequences_model extends Gd_Model
{
    protected array $searchable_fields = ["document_type", "prefix"];

    public function __construct()
    {
        parent::__construct("gd_sequences");
    }

    /**
     * Localiza a sequência de um tipo de documento por unidade.
     */
    public function get_for(int $unit_id, string $document_type)
    {
        return $this->db->table($this->table)
            ->where("deleted", 0)
            ->where("unit_id", $unit_id)
            ->where("document_type", $document_type)
            ->get()
            ->getRow();
    }
}
