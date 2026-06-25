<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

/**
 * Controle de versões aplicadas pelo SchemaRunner.
 *
 * Tabela append-only de controle (sem `deleted`). As escritas são feitas pelo
 * SchemaRunner via SQL bruto; este model serve para leitura (ex.: dashboard).
 */
class Gd_schema_versions_model extends Gd_Model
{
    public function __construct()
    {
        parent::__construct("gd_schema_versions");
    }

    /** @return \CodeIgniter\Database\ResultInterface */
    public function get_all_ordered()
    {
        return $this->db->table($this->table)->orderBy("version", "ASC")->get();
    }

    public function get_applied_version(): string
    {
        $row = $this->db->table($this->table)
            ->select("version")
            ->where("status", "completed")
            ->orderBy("version", "DESC")
            ->get(1)
            ->getRow();
        return $row ? (string) $row->version : "";
    }

    public function count_by_status(string $status): int
    {
        return $this->db->table($this->table)->where("status", $status)->countAllResults();
    }

    public function has_failed(): bool
    {
        return $this->count_by_status("failed") > 0;
    }
}
