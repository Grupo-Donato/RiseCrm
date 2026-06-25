<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema;

use grupo_donato_gestao\Config\Constants;
use CodeIgniter\Database\BaseConnection;

/**
 * Executor de versões de schema do plugin.
 *
 * O Rise NÃO usa migrations nativas do CI4 para plugins (verificado). Este
 * runner aplica versões pequenas e idempotentes, registra status em
 * `gd_schema_versions`, usa lock (GET_LOCK) contra execução concorrente,
 * interrompe na primeira falha (sem marcar posteriores como concluídas) e é
 * seguro para reexecutar após correção.
 */
class SchemaRunner
{
    private BaseConnection $db;
    private string $prefix;
    /** @var SchemaVersion[] */
    private array $versions = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
        $this->prefix = $this->db->getPrefix();
        $this->versions = $this->load_versions();
        $loaded = array_map(fn(SchemaVersion $version) => $version->version(), $this->versions);
        if (count($loaded) !== count(array_unique($loaded)) || !in_array(Constants::SCHEMA_TARGET, $loaded, true)) {
            throw new \RuntimeException("Invalid schema version catalog.");
        }
    }

    /**
     * @return SchemaVersion[]
     */
    private function load_versions(): array
    {
        $dir = __DIR__ . "/Versions";
        $files = glob($dir . "/V*.php") ?: [];
        sort($files);

        $list = [];
        foreach ($files as $file) {
            $short = basename($file, ".php");
            $class = "grupo_donato_gestao\\Database\\Schema\\Versions\\" . $short;
            if (!class_exists($class)) {
                require_once $file;
            }
            if (class_exists($class)) {
                $instance = new $class();
                if ($instance instanceof SchemaVersion && strcmp($instance->version(), Constants::SCHEMA_TARGET) <= 0) {
                    $list[] = $instance;
                }
            }
        }

        usort($list, fn(SchemaVersion $a, SchemaVersion $b) => strcmp($a->version(), $b->version()));
        return $list;
    }

    private function versions_table(): string
    {
        return $this->prefix . "gd_schema_versions";
    }

    /**
     * Executa as versões pendentes.
     *
     * @return array{ran: array<string>, failed: ?string, skipped_lock: bool}
     */
    public function run(): array
    {
        $result = ["ran" => [], "failed" => null, "skipped_lock" => false];

        $database = (string) ($this->db->query("SELECT DATABASE() AS db")->getRow()->db ?? "default");
        $lock_name = substr("gd_schema:" . $database . ":" . $this->prefix, 0, 64);
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS l", [$lock_name])->getRow();
        if (!$lock || (int) $lock->l !== 1) {
            $result["skipped_lock"] = true;
            log_message("notice", "GD SchemaRunner: lock ocupado, execução ignorada.");
            return $result;
        }

        try {
            $this->ensure_versions_table();
            $applied = $this->applied_status_map();

            foreach ($this->versions as $version) {
                $ver = $version->version();
                if (($applied[$ver] ?? "") === Constants::SCHEMA_STATUS_COMPLETED) {
                    try {
                        // Reconcile non-destructive columns/indexes added to an
                        // already released version. Version up() must be idempotent.
                        $version->up($this->db, $this->prefix);
                    } catch (\Throwable $e) {
                        $message = $this->sanitize_error($e->getMessage());
                        $this->mark($ver, $version->description(), Constants::SCHEMA_STATUS_FAILED, $message);
                        log_message("error", "GD SchemaRunner: falha ao reconciliar versão {$ver}: {$message}");
                        $result["failed"] = $ver;
                        break;
                    }
                    continue;
                }

                $this->mark($ver, $version->description(), Constants::SCHEMA_STATUS_RUNNING);
                try {
                    $version->up($this->db, $this->prefix);
                    $this->mark($ver, $version->description(), Constants::SCHEMA_STATUS_COMPLETED, null, true);
                    $result["ran"][] = $ver;
                    log_message("notice", "GD SchemaRunner: versão {$ver} aplicada.");
                } catch (\Throwable $e) {
                    $message = $this->sanitize_error($e->getMessage());
                    $this->mark($ver, $version->description(), Constants::SCHEMA_STATUS_FAILED, $message);
                    log_message("error", "GD SchemaRunner: falha na versão {$ver}: {$message}");
                    $result["failed"] = $ver;
                    break; // não aplica versões posteriores
                }
            }

            $this->persist_applied_version();
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lock_name]);
        }

        return $result;
    }

    /** Garante a existência da tabela de controle (bootstrap da V001). */
    private function ensure_versions_table(): void
    {
        if ($this->db->tableExists($this->versions_table()) || empty($this->versions)) {
            return;
        }
        // a primeira versão é responsável por criar gd_schema_versions
        $this->versions[0]->up($this->db, $this->prefix);
    }

    /** @return array<string,string> version => status */
    private function applied_status_map(): array
    {
        $map = [];
        $rows = $this->db->table($this->versions_table())->select("version, status")->get()->getResult();
        foreach ($rows as $row) {
            $map[(string) $row->version] = (string) $row->status;
        }
        return $map;
    }

    private function mark(string $version, string $description, string $status, ?string $error = null, bool $finished = false): void
    {
        $table = $this->versions_table();
        $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");

        $existing = $this->db->table($table)->where("version", $version)->get(1)->getRow();

        if ($existing) {
            $update = ["status" => $status, "description" => $description, "error_message" => $error];
            if ($status === Constants::SCHEMA_STATUS_RUNNING) {
                $update["started_at"] = $now;
                $update["finished_at"] = null;
            }
            if ($finished) {
                $update["finished_at"] = $now;
                $update["error_message"] = null;
            }
            $this->db->table($table)->where("version", $version)->update($update);
        } else {
            $this->db->table($table)->insert([
                "version" => $version,
                "description" => $description,
                "status" => $status,
                "started_at" => $now,
                "finished_at" => $finished ? $now : null,
                "error_message" => $error,
                "created_at" => $now,
            ]);
        }
    }

    private function persist_applied_version(): void
    {
        $applied = "";
        foreach ($this->applied_status_map() as $version => $status) {
            if ($status === Constants::SCHEMA_STATUS_COMPLETED && strcmp($version, $applied) > 0) {
                $applied = $version;
            }
        }

        // marcador em disco (leitura barata por request, sem consultar o banco)
        $this->write_marker($applied);

        // cache também em gd_settings, se a tabela já existir
        $settings_table = $this->prefix . "gd_settings";
        if ($applied && $this->db->tableExists($settings_table)) {
            $now = function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
            $existing = $this->db->table($settings_table)
                ->where("`key`", Constants::SETTING_SCHEMA_VERSION)
                ->where("unit_id IS NULL", null, false)
                ->get(1)->getRow();
            if ($existing) {
                $this->db->table($settings_table)->where("id", $existing->id)
                    ->update(["value" => $applied, "updated_at" => $now]);
            } else {
                $this->db->table($settings_table)->insert([
                    "unit_id" => null,
                    "key" => Constants::SETTING_SCHEMA_VERSION,
                    "value" => $applied,
                    "value_type" => "string",
                    "is_secret" => 0,
                    "deleted" => 0,
                    "created_at" => $now,
                    "updated_at" => $now,
                ]);
            }
        }
    }

    public static function marker_path(): string
    {
        $base = defined("WRITEPATH") ? WRITEPATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        return rtrim($base, "/\\") . DIRECTORY_SEPARATOR . Constants::SCHEMA_MARKER_FILE;
    }

    private function write_marker(string $applied): void
    {
        try {
            @file_put_contents(self::marker_path(), $applied);
        } catch (\Throwable $e) {
            log_message("error", "GD SchemaRunner: não foi possível gravar o marcador: " . $e->getMessage());
        }
    }

    /** Remove ruído e evita vazar segredos na mensagem de erro persistida. */
    private function sanitize_error(string $message): string
    {
        $message = preg_replace('/\s+/', " ", $message) ?? $message;
        foreach (Constants::SENSITIVE_KEYS as $needle) {
            $message = str_ireplace($needle, "***", $message);
        }
        return mb_substr(trim($message), 0, 480);
    }
}
