<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_import_rows_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_import_rows"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /** @return array<object> */
    public function for_batch(int $batch_id, int $unit_id, array $statuses = []): array
    {
        $builder = $this->db->table($this->table)->where("batch_id", $batch_id)->where("unit_id", $unit_id)->where("deleted", 0);
        if ($statuses) { $builder->whereIn("status", $statuses); }
        return $builder->orderBy("row_number", "ASC")->get()->getResult();
    }

    public function count_by_status(int $batch_id, int $unit_id): array
    {
        $rows = $this->db->table($this->table)->select("status, COUNT(*) c", false)->where("batch_id", $batch_id)->where("unit_id", $unit_id)->where("deleted", 0)->groupBy("status")->get()->getResult();
        $out = [];
        foreach ($rows as $r) { $out[(string) $r->status] = (int) $r->c; }
        return $out;
    }
}
