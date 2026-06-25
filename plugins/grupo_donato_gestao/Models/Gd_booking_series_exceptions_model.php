<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_booking_series_exceptions_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_booking_series_exceptions"); }
    public function add(array $data): int { $this->db->table($this->table)->insert($data); return (int) $this->db->insertID(); }
    public function for_series(int $series_id, int $unit_id, int $limit = 500): array { return $this->db->table($this->table)->where("series_id", $series_id)->where("unit_id", $unit_id)->orderBy("id", "DESC")->limit(max(1, min(1000, $limit)))->get()->getResult(); }
    public function ci_save(&$data = [], $id = 0) { throw new \LogicException("Series exceptions are append-only; use add()."); }
    public function update_where($data = [], $where = []) { throw new \LogicException("Series exceptions cannot be updated."); }
    public function delete($id = 0, $undo = false) { throw new \LogicException("Series exceptions cannot be deleted."); }
    public function delete_permanently($id = 0) { throw new \LogicException("Series exceptions cannot be deleted."); }
}
