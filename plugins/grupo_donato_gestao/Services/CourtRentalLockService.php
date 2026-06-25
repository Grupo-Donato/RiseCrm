<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class CourtRentalLockService
{
    private $db;
    private ?string $held = null;

    public function __construct() { $this->db = db_connect(); }

    public function acquire(int $unit_id, string $rental_key, int $timeout = 15): void
    {
        $raw = "gd:court_rental:$unit_id:$rental_key";
        $name = strlen($raw) <= 64 ? $raw : "gd:court_rental:" . hash("sha256", $raw);
        $row = $this->db->query("SELECT GET_LOCK(?, ?) AS l", [$name, $timeout])->getRow();
        if (!$row || (int) $row->l !== 1) { throw new \DomainException("gd_court_rental_lock_unavailable"); }
        $this->held = $name;
    }

    public function release(): void
    {
        if ($this->held === null) { return; }
        try { $this->db->query("SELECT RELEASE_LOCK(?)", [$this->held]); }
        catch (\Throwable $e) { log_message("error", "GD court rental lock release: " . $e->getMessage()); }
        $this->held = null;
    }
}
