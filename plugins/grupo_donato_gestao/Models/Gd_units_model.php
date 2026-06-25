<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_units_model extends Gd_Model
{
    protected array $searchable_fields = ["name", "legal_name", "document"];

    public function __construct()
    {
        parent::__construct("gd_units");
    }

    public function get_default()
    {
        return $this->db->table($this->table)
            ->where("deleted", 0)
            ->where("is_default", 1)
            ->get()
            ->getRow();
    }

    /** Verifica duplicidade de nome (case-insensitive), opcionalmente excluindo um id. */
    public function is_duplicate_name(string $name, int $exclude_id = 0): bool
    {
        $builder = $this->db->table($this->table)
            ->where("deleted", 0)
            ->where("LOWER(name)", strtolower(trim($name)));
        if ($exclude_id) {
            $builder->where("id !=", $exclude_id);
        }
        return $builder->countAllResults() > 0;
    }

    /** Garante uma única unidade marcada como padrão. */
    public function mark_as_default(int $id): void
    {
        $lock = $this->db->query("SELECT GET_LOCK('gd_units_default', 5) AS l")->getRow();
        if (!$lock || (int) $lock->l !== 1) {
            throw new \RuntimeException("Could not acquire default-unit lock.");
        }
        try {
            $this->db->transStart();
            $this->db->table($this->table)->where("id !=", $id)->update(["is_default" => 0]);
            $this->db->table($this->table)->where("id", $id)->where("deleted", 0)->update(["is_default" => 1]);
            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException("Could not set the default unit.");
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('gd_units_default')");
        }
    }

    public function count_active(): int
    {
        return $this->db->table($this->table)->where("deleted", 0)->countAllResults();
    }
}
