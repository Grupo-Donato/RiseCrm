<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class ImportLockService
{
    private $db;
    private ?string $held = null;

    public function __construct() { $this->db = db_connect(); }

    public function acquire(int $unit_id, string $batch_key, int $timeout = 20): void
    {
        $raw = "gd:import:$unit_id:$batch_key";
        $name = strlen($raw) <= 64 ? $raw : "gd:import:" . hash("sha256", $raw);
        $row = $this->db->query("SELECT GET_LOCK(?, ?) AS l", [$name, $timeout])->getRow();
        if (!$row || (int) $row->l !== 1) { throw new \DomainException("gd_import_lock_unavailable"); }
        $this->held = $name;
    }

    public function release(): void
    {
        if ($this->held === null) { return; }
        try { $this->db->query("SELECT RELEASE_LOCK(?)", [$this->held]); }
        catch (\Throwable $e) { log_message("error", "GD import lock release: " . $e->getMessage()); }
        $this->held = null;
    }
}
