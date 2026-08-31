<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_booking_series_exceptions_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_booking_series_exceptions"); }
    public function add(array $data): int { $this->db->table($this->table)->insert($data); return (int) $this->db->insertID(); }
    public function for_series(int $series_id, int $unit_id, int $limit = 500): array { return $this->db->table($this->table)->where("series_id", $series_id)->where("unit_id", $unit_id)->orderBy("id", "DESC")->limit(max(1, min(1000, $limit)))->get()->getResult(); }
    public function active_reschedule(int $series_id, string $occurrence_key, int $unit_id): ?object
    {
        return $this->db->table($this->table)
            ->where("unit_id", $unit_id)->where("series_id", $series_id)
            ->where("occurrence_key", $occurrence_key)->where("exception_type", "reschedule")
            ->where("status", "active")->orderBy("revision", "DESC")->get(1)->getRow();
    }
    public function next_reschedule_revision(int $series_id, string $occurrence_key, int $unit_id): int
    {
        $row = $this->db->table($this->table)->selectMax("revision", "revision")
            ->where("unit_id", $unit_id)->where("series_id", $series_id)
            ->where("occurrence_key", $occurrence_key)->where("exception_type", "reschedule")
            ->get(1)->getRow();
        return max(1, ((int) ($row->revision ?? 0)) + 1);
    }
    public function mark_reverted(int $id, int $series_id, int $unit_id, int $actor_id, ?string $reason = null): bool
    {
        $data = ["status" => "reverted", "reverted_at" => gmdate("Y-m-d H:i:s"), "reverted_by" => $actor_id ?: null, "reversal_reason" => $reason, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $actor_id ?: null];
        $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id)->where("series_id", $series_id)->where("exception_type", "reschedule")->where("status", "active")->update($data);
        return $this->db->affectedRows() === 1;
    }
    public function ci_save(&$data = [], $id = 0) { throw new \LogicException("Series exceptions are append-only; use add()."); }
    public function update_where($data = [], $where = []) { throw new \LogicException("Series exceptions cannot be updated."); }
    public function delete($id = 0, $undo = false) { throw new \LogicException("Series exceptions cannot be deleted."); }
    public function delete_permanently($id = 0) { throw new \LogicException("Series exceptions cannot be deleted."); }
}
