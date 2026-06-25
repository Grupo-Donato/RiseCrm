<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

abstract class ResourceTemporalService extends CustomerDataService
{
    protected TemporalService $time;
    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->time = new TemporalService($unit_id);
    }

    protected function assertResource(int $resource_id): object
    {
        $table = $this->db->prefixTable("gd_resources");
        $row = $this->db->table($table)->where("id", $resource_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_invalid_resource"); }
        return $row;
    }

    /** @return array{0:string,1:string} */
    protected function interval(array $input): array
    {
        if (trim((string) ($input["starts_at_local"] ?? "")) !== "") {
            $start = $this->time->localStringToUtc((string) $input["starts_at_local"]);
            $end = $this->time->localStringToUtc((string) ($input["ends_at_local"] ?? ""));
        } else {
            $start = (string) ($input["starts_at_utc"] ?? "");
            $end = (string) ($input["ends_at_utc"] ?? "");
        }
        return $this->time->validateRange($start, $end);
    }

    protected function acquireLock(string $scope): string
    {
        $name = substr("gd:" . $scope . ":" . $this->unit_id, 0, 64);
        $row = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$name])->getRow();
        if (!$row || (int) $row->l !== 1) { throw new \RuntimeException("gd_lock_unavailable"); }
        return $name;
    }
    protected function releaseLock(string $name): void { $this->db->query("SELECT RELEASE_LOCK(?)", [$name]); }

    protected function exactExists(string $table, int $resource_id, string $type_field, string $type, string $start, string $end, int $exclude_id): bool
    {
        $b = $this->db->table($this->db->prefixTable($table))->where("unit_id",$this->unit_id)->where("resource_id",$resource_id)
            ->where($type_field,$type)->where("starts_at_utc",$start)->where("ends_at_utc",$end)->where("status","active")->where("deleted",0);
        if ($exclude_id) { $b->where("id !=", $exclude_id); }
        return $b->countAllResults() > 0;
    }

    protected function overlaps(string $table, int $resource_id, string $start, string $end, int $exclude_id, ?string $type_field = null, ?string $type = null): array
    {
        $b = $this->db->table($this->db->prefixTable($table))->select("id,title,starts_at_utc,ends_at_utc")
            ->where("unit_id",$this->unit_id)->where("resource_id",$resource_id)->where("status","active")->where("deleted",0)
            ->where("starts_at_utc <",$end)->where("ends_at_utc >",$start);
        if ($exclude_id) { $b->where("id !=",$exclude_id); }
        if ($type_field !== null) { $b->where($type_field,$type); }
        return $b->orderBy("starts_at_utc")->get()->getResultArray();
    }
}
