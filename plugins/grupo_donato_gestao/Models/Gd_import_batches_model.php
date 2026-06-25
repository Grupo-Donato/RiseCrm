<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_import_batches_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_import_batches"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    public function optimistic_update(int $id, int $unit_id, int $lock_version, array $data): bool
    {
        $data["lock_version"] = $lock_version + 1;
        $data["updated_at"] = gmdate("Y-m-d H:i:s");
        $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("deleted", 0)->where("lock_version", $lock_version)->update($data);
        return $this->db->affectedRows() === 1;
    }

    /** Lote já importado com o mesmo hash (para impedir reimport silencioso). */
    public function imported_with_hash(int $unit_id, string $hash): ?object
    {
        return $this->db->table($this->table)->where("unit_id", $unit_id)->where("file_hash", $hash)->whereIn("status", ["imported", "partially_imported"])->where("deleted", 0)->get(1)->getRow();
    }
}
