<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Gerador de números de documento seguro contra concorrência.
 *
 * Usa transação + SELECT ... FOR UPDATE sobre a linha da sequência, garantindo
 * que duas requisições simultâneas nunca obtenham o mesmo número.
 * (Nenhum documento operacional é gerado nesta fase — apenas a infraestrutura.)
 */
class SequenceService
{
    private $db;
    private string $table;
    private string $units_table;

    public function __construct()
    {
        $this->db = db_connect();
        $this->table = $this->db->prefixTable("gd_sequences");
        $this->units_table = $this->db->prefixTable("gd_units");
    }

    /**
     * Cria/garante a definição de uma sequência (idempotente).
     */
    public function ensure(int $unit_id, string $document_type, string $prefix = "", int $padding = 0, bool $yearly_reset = false): void
    {
        $this->validate_definition($unit_id, $document_type, $prefix, $padding);

        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
        // INSERT IGNORE + UNIQUE(unit_id, document_type) closes the first-use race:
        // concurrent creators converge on the same row instead of one failing.
        $this->db->query(
            "INSERT IGNORE INTO `{$this->table}` "
            . "(unit_id, document_type, prefix, current_value, padding, yearly_reset, last_reset_year, deleted, created_at, updated_at) "
            . "VALUES (?, ?, ?, 0, ?, ?, NULL, 0, ?, ?)",
            [$unit_id, $document_type, $prefix, $padding, $yearly_reset ? 1 : 0, $now, $now]
        );

        $existing = $this->db->table($this->table)
            ->where("unit_id", $unit_id)
            ->where("document_type", $document_type)
            ->where("deleted", 0)
            ->get(1)->getRow();
        if (!$existing) {
            throw new \RuntimeException("Could not create or locate sequence definition.");
        }
    }

    /**
     * Retorna o próximo número formatado (prefixo + valor com padding).
     *
     * @throws \RuntimeException se a sequência não puder ser obtida
     */
    public function next(int $unit_id, string $document_type): string
    {
        $row = $this->next_raw($unit_id, $document_type);
        $prefix = (string) ($row["prefix"] ?? "");
        $padding = (int) ($row["padding"] ?? 0);
        $value = (int) $row["current_value"];

        $number = $padding > 0 ? str_pad((string) $value, $padding, "0", STR_PAD_LEFT) : (string) $value;
        return $prefix . $number;
    }

    /**
     * Incremento atômico. Retorna a linha atualizada (com current_value já novo).
     *
     * @return array<string,mixed>
     */
    public function next_raw(int $unit_id, string $document_type): array
    {
        $this->ensure($unit_id, $document_type);

        if ($this->db->transBegin() === false) {
            throw new \RuntimeException("Could not start sequence transaction.");
        }
        try {
            // trava a linha da sequência
            $locked = $this->db->query(
                "SELECT * FROM `{$this->table}` WHERE unit_id = ? AND document_type = ? AND deleted = 0 LIMIT 1 FOR UPDATE",
                [$unit_id, $document_type]
            )->getRow();

            if (!$locked) {
                throw new \RuntimeException("Sequence not found after ensure().");
            }

            $current_year = (int) gmdate("Y");
            $value = (int) $locked->current_value;

            if ((int) $locked->yearly_reset === 1 && (int) $locked->last_reset_year !== $current_year) {
                $value = 0;
            }

            $value++;

            $update = [
                "current_value" => $value,
                "updated_at" => function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s"),
            ];
            if ((int) $locked->yearly_reset === 1) {
                $update["last_reset_year"] = $current_year;
            }

            $updated = $this->db->table($this->table)
                ->where("unit_id", $unit_id)
                ->where("document_type", $document_type)
                ->where("deleted", 0)
                ->update($update);

            if (!$updated || $this->db->transStatus() === false) {
                throw new \RuntimeException("Could not persist sequence increment.");
            }

            if ($this->db->transCommit() === false) {
                throw new \RuntimeException("Could not commit sequence increment.");
            }

            return [
                "current_value" => $value,
                "prefix" => $locked->prefix,
                "padding" => (int) $locked->padding,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message("error", "GD SequenceService: falha ao obter próximo número: " . $e->getMessage());
            throw new \RuntimeException("Could not get next sequence number.", 0, $e);
        }
    }

    private function validate_definition(int $unit_id, string $document_type, string $prefix, int $padding): void
    {
        if ($unit_id <= 0 || $document_type === "" || mb_strlen($document_type) > 40) {
            throw new \InvalidArgumentException("Invalid sequence unit or document type.");
        }
        if (mb_strlen($prefix) > 20 || $padding < 0 || $padding > 20) {
            throw new \InvalidArgumentException("Invalid sequence prefix or padding.");
        }

        $unit = $this->db->table($this->units_table)
            ->select("id")
            ->where("id", $unit_id)
            ->where("deleted", 0)
            ->where("status", "active")
            ->get(1)->getRow();
        if (!$unit) {
            throw new \InvalidArgumentException("Sequence unit is inactive, missing, or inaccessible.");
        }
    }
}
