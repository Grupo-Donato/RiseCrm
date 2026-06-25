<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Models;

use App\Models\Crud_model;

abstract class Gdc_Model extends Crud_model
{
    public function __construct(string $table)
    {
        $this->table = $table;
        parent::__construct($table);
    }

    public function getScoped(int $id, int $unitId): ?object
    {
        return $this->db->table($this->table)
            ->where('id', $id)
            ->where('unit_id', $unitId)
            ->where('deleted', 0)
            ->get(1)->getRow();
    }
}
