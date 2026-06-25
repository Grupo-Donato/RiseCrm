<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

/**
 * Acesso tipado às configurações do plugin (tabela gd_settings).
 *
 * Segredos: nesta fase o plugin NÃO armazena segredos (não há mecanismo de
 * criptografia seguro disponível no core — a chave é curta e hard-coded). Tentar
 * gravar um valor secreto é recusado e registrado (ver docs/security-foundation.md).
 */
class SettingsService
{
    private $model;
    private $db;
    /** @var array<string,mixed> */
    private static array $cache = [];

    public function __construct()
    {
        $this->model = model("grupo_donato_gestao\\Models\\Gd_settings_model");
        $this->db = db_connect();
    }

    private function cache_key(string $key, ?int $unit_id): string
    {
        return ($unit_id === null ? "g" : (string) $unit_id) . ":" . $key;
    }

    /**
     * @return mixed valor convertido conforme value_type, ou $default.
     */
    public function get(string $key, ?int $unit_id = null, $default = null)
    {
        $ck = $this->cache_key($key, $unit_id);
        if (array_key_exists($ck, self::$cache)) {
            return self::$cache[$ck];
        }

        $row = $this->model->get_by_key($key, $unit_id);
        if (!$row) {
            return $default;
        }

        $value = $this->cast_from_storage($row->value, $row->value_type);
        self::$cache[$ck] = $value;
        return $value;
    }

    /**
     * Persiste uma configuração não-secreta.
     *
     * @return bool false se for tentativa de gravar segredo (recusado) ou erro.
     */
    public function set(string $key, $value, ?int $unit_id = null, string $type = "string", bool $is_secret = false, int $actor_id = 0): bool
    {
        if ($is_secret) {
            log_message("warning", "GD SettingsService: tentativa de gravar segredo recusada (sem cripto segura nesta fase).");
            return false;
        }

        $key = trim($key);
        if (!preg_match('/^[a-z0-9_.-]{1,120}$/', $key)) {
            throw new \InvalidArgumentException("Invalid setting key.");
        }
        if (!in_array($type, ["string", "bool", "int", "json"], true)) {
            throw new \InvalidArgumentException("Invalid setting type.");
        }
        if ($unit_id !== null && !$this->unit_exists($unit_id)) {
            throw new \InvalidArgumentException("Invalid setting unit.");
        }

        $stored = $this->cast_to_storage($value, $type);
        $lock_name = "gd_setting_" . substr(hash("sha256", ($unit_id ?? "global") . ":" . $key), 0, 40);
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$lock_name])->getRow();
        if (!$lock || (int) $lock->l !== 1) {
            throw new \RuntimeException("Could not acquire setting lock.");
        }

        try {
            $existing = $this->model->get_by_key($key, $unit_id);
            $data = [
                "unit_id" => $unit_id,
                "key" => $key,
                "value" => $stored,
                "value_type" => $type,
                "is_secret" => 0,
            ];
            if ($actor_id) {
                $data["updated_by"] = $actor_id;
                if (!$existing) {
                    $data["created_by"] = $actor_id;
                }
            }

            $id = $existing ? $existing->id : 0;
            $save_id = $this->model->ci_save($data, $id);

            if ($save_id) {
                self::$cache[$this->cache_key($key, $unit_id)] = $this->cast_from_storage($stored, $type);
                return true;
            }
            return false;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }
    }

    private function cast_to_storage($value, string $type): string
    {
        return match ($type) {
            "bool" => $value ? "1" : "0",
            "int" => (string) (int) $value,
            "json" => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            default => (string) $value,
        };
    }

    private function cast_from_storage($value, string $type)
    {
        return match ($type) {
            "bool" => (bool) $value,
            "int" => (int) $value,
            "json" => json_decode((string) $value, true),
            default => (string) $value,
        };
    }

    private function unit_exists(int $unit_id): bool
    {
        if ($unit_id <= 0) {
            return false;
        }
        $table = $this->db->prefixTable("gd_units");
        return $this->db->table($table)
            ->where("id", $unit_id)
            ->where("deleted", 0)
            ->where("status", "active")
            ->countAllResults() === 1;
    }
}
