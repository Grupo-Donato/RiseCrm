<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

/** Read model for the event list; writes remain in AcademyEventService. */
final class Gd_academy_events_model extends Gd_Model
{
    protected array $searchable_fields = ["name", "event_type", "location", "organizer"];

    public function __construct()
    {
        parent::__construct("gd_academy_events");
    }

    public function get_scoped(int $id, int $unit_id): ?object
    {
        return $this->db->table($this->table)
            ->where("id", $id)
            ->where("unit_id", $unit_id)
            ->where("deleted", 0)
            ->get(1)
            ->getRow();
    }

    public function list_page(int $unit_id, array $filters = []): array
    {
        $builder = $this->db->table($this->table)
            ->where("unit_id", $unit_id)
            ->where("deleted", 0);
        if (!empty($filters["status"])) {
            $builder->where("status", $filters["status"]);
        }
        if (!empty($filters["event_type"])) {
            $builder->where("event_type", $filters["event_type"]);
        }
        if (!empty($filters["date_from"])) {
            $builder->where("starts_on >=", $filters["date_from"]);
        }
        if (!empty($filters["date_to"])) {
            $builder->where("starts_on <=", $filters["date_to"]);
        }
        if (!empty($filters["search_by"])) {
            $builder->groupStart()
                ->like("name", $filters["search_by"])
                ->orLike("location", $filters["search_by"])
                ->orLike("organizer", $filters["search_by"])
                ->groupEnd();
        }
        $total = $this->db->table($this->table)->where("unit_id", $unit_id)->where("deleted", 0)->countAllResults();
        $filtered = $builder->countAllResults(false);
        $rows = $builder->orderBy("starts_on", "ASC")->orderBy("id", "ASC")
            ->limit(max(1, min(100, (int) ($filters["limit"] ?? 50))), max(0, (int) ($filters["skip"] ?? 0)))
            ->get()->getResult();
        return ["data" => $rows, "recordsTotal" => $total, "recordsFiltered" => $filtered];
    }
}
