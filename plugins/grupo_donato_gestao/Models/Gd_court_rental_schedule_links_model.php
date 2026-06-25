<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_court_rental_schedule_links_model extends Gd_Model
{
    public function __construct() { parent::__construct("gd_court_rental_schedule_links"); }

    public function get_scoped(int $id, int $unit_id, bool $include_deleted = false): ?object
    {
        $builder = $this->db->table($this->table)->where("id", $id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->get(1)->getRow();
    }

    /** @return array<object> */
    public function for_rental(int $rental_id, int $unit_id, bool $include_deleted = false): array
    {
        $builder = $this->db->table($this->table)->where("rental_id", $rental_id)->where("unit_id", $unit_id);
        if (!$include_deleted) { $builder->where("deleted", 0); }
        return $builder->orderBy("id", "ASC")->get()->getResult();
    }

    /** Vínculo ativo (não histórico, não excluído) de uma reserva, se houver. */
    public function active_for_booking(int $booking_id, int $unit_id): ?object
    {
        return $this->db->table($this->table)->where("unit_id", $unit_id)->where("active_booking_guard", $booking_id)->where("deleted", 0)->get(1)->getRow();
    }

    /** Vínculo ativo (não histórico, não excluído) de uma série, se houver. */
    public function active_for_series(int $series_id, int $unit_id): ?object
    {
        return $this->db->table($this->table)->where("unit_id", $unit_id)->where("active_series_guard", $series_id)->where("deleted", 0)->get(1)->getRow();
    }
}
