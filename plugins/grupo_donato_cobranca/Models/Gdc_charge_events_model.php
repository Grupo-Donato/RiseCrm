<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Models;

use App\Models\Crud_model;

final class Gdc_charge_events_model extends Crud_model
{
    public function __construct()
    {
        $this->table = 'gdc_charge_events';
        parent::__construct($this->table);
    }

    public function add(array $data): int
    {
        return (int) $this->ci_save($data, 0);
    }
}
