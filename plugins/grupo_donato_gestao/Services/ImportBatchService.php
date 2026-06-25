<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\Import\AbstractImporter;
use grupo_donato_gestao\Services\Import\CashImporter;
use grupo_donato_gestao\Services\Import\CourtRentersImporter;
use grupo_donato_gestao\Services\Import\SchoolPaymentsImporter;

/**
 * Orquestra a importação assistida (Fase 6): upload+hash, preview sem persistência,
 * mapeamento, validação (linhas+inconsistências sem escrita de domínio), confirmação
 * (escreve via services existentes, com rastreabilidade) e reprocessamento de falhas.
 * Nada é importado antes da confirmação. Tudo é auditado.
 */
final class ImportBatchService extends CustomerDataService
{
    private $batches;
    private $rows;
    private $issues;
    private $links;
    private ImportFileService $files;
    private ?object $login_user;

    private const PREVIEW_LIMIT = 15;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->batches = model("grupo_donato_gestao\\Models\\Gd_import_batches_model");
        $this->rows = model("grupo_donato_gestao\\Models\\Gd_import_rows_model");
        $this->issues = model("grupo_donato_gestao\\Models\\Gd_import_issues_model");
        $this->links = model("grupo_donato_gestao\\Models\\Gd_import_links_model");
        $this->files = new ImportFileService();
        $this->login_user = $login_user;
    }

    public function importerFor(string $type): AbstractImporter
    {
        switch ($type) {
            case "school_payments": return new SchoolPaymentsImporter($this->unit_id, $this->actor_id, $this->login_user);
            case "cash": return new CashImporter($this->unit_id, $this->actor_id, $this->login_user);
            case "court_renters": return new CourtRentersImporter($this->unit_id, $this->actor_id, $this->login_user);
        }
        throw new \DomainException("gd_import_invalid_type");
    }

    /** Cria o lote: hash, dedupe, armazenamento e mapeamento automático. Retorna preview. */
    public function createBatch(array $in): array
    {
        $type = (string) ($in["import_type"] ?? "");
        if (!Constants::isImportType($type)) { throw new \DomainException("gd_import_invalid_type"); }
        $path = (string) ($in["file_path"] ?? "");
        if (!is_file($path)) { throw new \DomainException("gd_import_file_unreadable"); }
        $original = mb_substr(DataNormalizationService::text($in["original_filename"] ?? basename($path)), 0, 255);
        $hash = $this->files->hashFile($path);
        if ($hash === "") { throw new \DomainException("gd_import_file_unreadable"); }
        if (empty($in["override"]) && $this->batches->imported_with_hash($this->unit_id, $hash)) { throw new \DomainException("gd_import_duplicate_file"); }

        $parsed = $this->files->read($path);
        $importer = $this->importerFor($type);
        $map = $this->files->autoMap($parsed["header"], $importer->headerDefs());
        $stored = $this->files->store($path, $original, $this->unit_id, $hash);

        $sequence = new SequenceService();
        $sequence->ensure($this->unit_id, "import_batch", "IMP-" . gmdate("Y") . "-", 6, true);
        $number = $sequence->next($this->unit_id, "import_batch");
        $data = $this->stamp([
            "unit_id" => $this->unit_id, "batch_number" => $number, "import_type" => $type,
            "original_filename" => $original, "stored_path" => $stored["stored_path"], "file_hash" => $hash, "file_size" => $stored["size"],
            "status" => "previewed", "mapping" => json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "row_count" => count($parsed["rows"]), "imported_count" => 0, "issue_count" => 0,
            "metadata" => json_encode(["header" => $parsed["header"]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "lock_version" => 1, "deleted" => 0,
        ], true);
        $id = (int) $this->batches->ci_save($data);
        if ($id <= 0) { throw new \RuntimeException("import batch insert"); }
        $this->audit_change("import_created", "import_batch", $id, null, ["batch_number" => $number, "import_type" => $type, "file_hash" => $hash, "row_count" => count($parsed["rows"])]);
        return ["id" => $id, "batch_number" => $number, "header" => $parsed["header"], "mapping" => $map, "sample" => $this->sample($importer, $parsed, $map)];
    }

    /** Pré-visualização sem persistência de domínio nem de linhas. */
    public function preview(int $id): array
    {
        $batch = $this->scoped($id);
        $parsed = $this->files->read((string) $batch->stored_path);
        $importer = $this->importerFor((string) $batch->import_type);
        $map = (array) (json_decode((string) $batch->mapping, true) ?: []);
        return ["header" => $parsed["header"], "mapping" => $map, "sample" => $this->sample($importer, $parsed, $map)];
    }

    public function saveMapping(int $id, array $map): array
    {
        $batch = $this->scoped($id);
        if (in_array((string) $batch->status, ["imported", "archived"], true)) { throw new \DomainException("gd_import_not_editable"); }
        $clean = [];
        foreach ($map as $field => $index) { if (preg_match('/^[a-z_]+$/', (string) $field) && preg_match('/^\d+$/', (string) $index)) { $clean[(string) $field] = (int) $index; } }
        $this->batches->ci_save($this->stamp(["mapping" => json_encode($clean, JSON_UNESCAPED_SLASHES), "status" => "previewed"], false), $id);
        $this->audit_change("import_mapping", "import_batch", $id, null, ["mapping" => $clean]);
        return ["id" => $id, "mapping" => $clean];
    }

    /** Valida e persiste linhas + inconsistências. NENHUMA escrita de domínio. */
    public function validate(int $id): array
    {
        $batch = $this->scoped($id);
        $parsed = $this->files->read((string) $batch->stored_path);
        $importer = $this->importerFor((string) $batch->import_type);
        $map = (array) (json_decode((string) $batch->mapping, true) ?: []);

        $importedKeys = [];
        foreach ($this->rows->for_batch($id, $this->unit_id, ["imported"]) as $row) { $importedKeys[(string) $row->source_key] = true; }
        // Regenera linhas não importadas e supersede inconsistências anteriores.
        $this->db->table($this->db->prefixTable("gd_import_rows"))->where("batch_id", $id)->where("unit_id", $this->unit_id)->where("status !=", "imported")->where("deleted", 0)->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s")]);
        $this->issues->supersede_batch($id, $this->unit_id);

        $issueCount = 0; $counts = [];
        foreach ($parsed["rows"] as $i => $raw) {
            $number = $i + 1;
            $assoc = $this->files->applyMapping($raw, $map);
            $result = $importer->validateRow($assoc);
            $sourceKey = (string) $result["source_key"];
            if (isset($importedKeys[$sourceKey])) { continue; }
            $status = (string) $result["status"];
            if ($this->alreadyImported($sourceKey, $importer->primaryTargetTypes())) { $status = "imported"; }
            $rowData = $this->stamp([
                "unit_id" => $this->unit_id, "batch_id" => $id, "row_number" => $number, "source_key" => $sourceKey,
                "raw_data" => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                "normalized_data" => json_encode($result["normalized"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                "status" => $status, "note" => null, "deleted" => 0,
            ], true);
            $rowId = (int) $this->rows->ci_save($rowData);
            foreach ((array) $result["issues"] as $issue) { $this->recordIssue($id, $rowId, $number, $issue); $issueCount++; }
            $counts[$status] = ($counts[$status] ?? 0) + 1;
        }
        $this->batches->ci_save($this->stamp(["status" => "validated", "row_count" => count($parsed["rows"]), "issue_count" => $issueCount], false), $id);
        $this->audit_change("import_validated", "import_batch", $id, null, ["counts" => $counts, "issues" => $issueCount]);
        return ["id" => $id, "counts" => $counts, "issue_count" => $issueCount];
    }

    /** Confirma: importa as linhas válidas via services de domínio, com rastreabilidade. */
    public function confirm(int $id): array
    {
        $lock = new ImportLockService();
        try {
            $lock->acquire($this->unit_id, (string) $id);
            $batch = $this->scoped($id);
            if (!in_array((string) $batch->status, ["validated", "partially_imported"], true)) { throw new \DomainException("gd_import_not_confirmable"); }
            $importer = $this->importerFor((string) $batch->import_type);
            $rows = $this->rows->for_batch($id, $this->unit_id, ["valid"]);
            $imported = 0; $failed = 0;
            foreach ($rows as $row) {
                $normalized = (array) (json_decode((string) $row->normalized_data, true) ?: []);
                $ok = false; $error = "";
                $this->db->transBegin();
                try {
                    $result = $importer->importRow($normalized, (string) $row->source_key, (int) $row->row_number, (int) $row->id);
                    foreach ((array) ($result["links"] ?? []) as $link) { $this->recordLink($id, (int) $row->id, (int) $row->row_number, $link); }
                    if ($this->db->transCommit() === false) { throw new \RuntimeException("save_failed"); }
                    $ok = true;
                } catch (\Throwable $e) { $this->db->transRollback(); $error = $e->getMessage(); }
                if ($ok) {
                    $this->rows->ci_save($this->stamp(["status" => "imported"], false), (int) $row->id);
                    $imported++;
                } else {
                    $this->rows->ci_save($this->stamp(["status" => "invalid", "note" => mb_substr($error, 0, 255)], false), (int) $row->id);
                    $this->recordIssue($id, (int) $row->id, (int) $row->row_number, $this->issueArray("import_error", "error", "gd_import_issue_import_error", ["error" => $error]));
                    $failed++;
                }
            }
            $remaining = (int) $this->db->table($this->db->prefixTable("gd_import_rows"))->where("batch_id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->whereIn("status", ["valid", "invalid", "needs_review"])->countAllResults();
            $totalImported = (int) $this->db->table($this->db->prefixTable("gd_import_rows"))->where("batch_id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "imported")->countAllResults();
            $status = $remaining === 0 ? "imported" : "partially_imported";
            $this->batches->ci_save($this->stamp(["status" => $status, "imported_count" => $totalImported, "confirmed_at" => gmdate("Y-m-d H:i:s"), "confirmed_by" => $this->actor_id ?: null], false), $id);
            $this->audit_change("import_confirmed", "import_batch", $id, null, ["imported" => $imported, "failed" => $failed, "status" => $status]);
            return ["id" => $id, "imported" => $imported, "failed" => $failed, "status" => $status];
        } finally {
            $lock->release();
        }
    }

    /** Revalida apenas as linhas não importadas e reimporta as que ficaram válidas. */
    public function reprocess(int $id): array
    {
        $this->validate($id);
        return $this->confirm($id);
    }

    /* ---------------- leitura ---------------- */

    public function get(int $id): ?object
    {
        $batch = $this->batches->get_scoped($id, $this->unit_id);
        if (!$batch) { return null; }
        $batch->rows = $this->rows->for_batch($id, $this->unit_id);
        $batch->row_status = $this->rows->count_by_status($id, $this->unit_id);
        $batch->issues = $this->issues->for_batch($id, $this->unit_id);
        $batch->links = $this->links->for_batch($id, $this->unit_id);
        return $batch;
    }

    public function listPage(array $o): array
    {
        $t = $this->db->prefixTable("gd_import_batches");
        $base = function () use ($o, $t) {
            $q = $this->db->table($t)->where("unit_id", $this->unit_id)->where("deleted", 0);
            if ($v = trim((string) ($o["import_type"] ?? ""))) { $q->where("import_type", $v); }
            if ($v = trim((string) ($o["status"] ?? ""))) { $q->where("status", $v); }
            if ($v = trim((string) ($o["search_by"] ?? ""))) { $q->groupStart()->like("batch_number", $v)->orLike("original_filename", $v)->groupEnd(); }
            return $q;
        };
        $total = $this->db->table($t)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults();
        $filtered = (int) $base()->countAllResults(false);
        $rows = $base()->orderBy("id", "DESC")->limit(max(1, min(100, (int) ($o["limit"] ?? 25))), max(0, (int) ($o["skip"] ?? 0)))->get()->getResult();
        return ["data" => $rows, "recordsTotal" => $total, "recordsFiltered" => $filtered];
    }

    /* ---------------- internos ---------------- */

    private function sample(AbstractImporter $importer, array $parsed, array $map): array
    {
        $out = [];
        foreach (array_slice($parsed["rows"], 0, self::PREVIEW_LIMIT) as $i => $raw) {
            $assoc = $this->files->applyMapping($raw, $map);
            $result = $importer->validateRow($assoc);
            $out[] = ["row_number" => $i + 1, "status" => $result["status"], "normalized" => $result["normalized"], "issues" => $result["issues"]];
        }
        return $out;
    }

    private function alreadyImported(string $sourceKey, array $types): bool
    {
        foreach ($types as $type) { if ($this->links->target_for_source($this->unit_id, $sourceKey, $type)) { return true; } }
        return false;
    }

    private function recordIssue(int $batchId, ?int $rowId, ?int $rowNumber, array $issue): void
    {
        $this->issues->ci_save($this->stamp([
            "unit_id" => $this->unit_id, "batch_id" => $batchId, "row_id" => $rowId, "row_number" => $rowNumber,
            "issue_type" => (string) $issue["issue_type"], "severity" => (string) $issue["severity"],
            "message" => mb_substr((string) ($issue["message"] ?? ""), 0, 255),
            "context" => json_encode(DataPrivacyService::forAudit((array) ($issue["context"] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "deleted" => 0,
        ], true));
    }

    private function issueArray(string $type, string $severity, string $message, array $context = []): array
    {
        return ["issue_type" => $type, "severity" => $severity, "message" => $message, "context" => $context];
    }

    private function recordLink(int $batchId, int $rowId, int $rowNumber, array $link): void
    {
        if (!Constants::isImportTargetType((string) $link["target_type"])) { return; }
        try {
            $this->links->ci_save($this->stamp([
                "unit_id" => $this->unit_id, "batch_id" => $batchId, "row_id" => $rowId, "row_number" => $rowNumber,
                "source_key" => (string) $link["source_key"], "target_type" => (string) $link["target_type"], "target_id" => (int) $link["target_id"], "deleted" => 0,
            ], true));
        } catch (\Throwable $e) {
            if (!str_contains($e->getMessage(), "uniq_batch_row_target")) { throw $e; }
        }
    }

    private function scoped(int $id): object
    {
        $batch = $this->batches->get_scoped($id, $this->unit_id);
        if (!$batch) { throw new \DomainException("gd_import_not_found"); }
        return $batch;
    }
}
