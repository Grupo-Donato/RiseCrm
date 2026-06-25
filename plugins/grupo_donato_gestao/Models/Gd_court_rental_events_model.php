<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_court_rental_events_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_court_rental_events"); }
    public function add(array $data): int { $this->db->table($this->table)->insert($data); return (int) $this->db->insertID(); }
    public function for_rental(int $rental_id, int $unit_id, int $limit = 200): array { return $this->db->table($this->table)->where("rental_id", $rental_id)->where("unit_id", $unit_id)->orderBy("id", "DESC")->limit(max(1, min(500, $limit)))->get()->getResult(); }
    public function ci_save(&$data = [], $id = 0) { throw new \LogicException("Court rental events are append-only; use add()."); }
    public function update_where($data = [], $where = []) { throw new \LogicException("Court rental events cannot be updated."); }
    public function delete($id = 0, $undo = false) { throw new \LogicException("Court rental events cannot be deleted."); }
    public function delete_permanently($id = 0) { throw new \LogicException("Court rental events cannot be deleted."); }
}
