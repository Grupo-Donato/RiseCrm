<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

/** Upload privado com MIME real, nome aleatório e download autenticado. */
final class CostAttachmentService extends CustomerDataService
{
    private string $table;
    private const MAX_BYTES = 15728640;
    private const TYPES = ["pdf" => "application/pdf", "jpg" => "image/jpeg", "jpeg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null) { parent::__construct($unit_id, $actor_id, $login_user); $this->table = $this->db->prefixTable("gd_expense_attachments"); }
    public function upload(int $expense_id, UploadedFile $file, string $document_type = "other"): array
    {
        $expense = $this->db->table($this->db->prefixTable("gd_expenses"))->where("id", $expense_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        if (!$expense) throw new \DomainException("gd_record_not_found");
        if (!$file->isValid() || $file->getError() !== UPLOAD_ERR_OK) throw new \DomainException("gd_attachment_invalid");
        $source = $file->getTempName(); $size = max((int) $file->getSize(), (int) (@filesize($source) ?: 0)); if ($size <= 0 || $size > self::MAX_BYTES) throw new \DomainException("gd_attachment_invalid");
        $mime = strtolower((string) $file->getMimeType()); $extension = strtolower((string) ($file->getClientExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION))); if (!isset(self::TYPES[$extension]) || self::TYPES[$extension] !== $mime) throw new \DomainException("gd_attachment_invalid");
        if ($extension === "jpeg") $extension = "jpg";
        $hash = hash_file("sha256", $source) ?: ""; $existing = $this->db->table($this->table)->where("unit_id", $this->unit_id)->where("expense_id", $expense_id)->where("sha256", $hash)->where("deleted", 0)->get(1)->getRow(); if ($existing) return ["id" => (int) $existing->id, "duplicate" => true, "data" => $existing];
        $safe_original = trim((string) preg_replace('/[^\pL\pN._ -]+/u', "_", $file->getClientName())); if ($safe_original === "") $safe_original = "document." . $extension;
        try { $token = bin2hex(random_bytes(16)); } catch (\Throwable $e) { throw new \DomainException("gd_attachment_invalid"); }
        $relative = "uploads/gd_costs/{$this->unit_id}/{$expense_id}/{$token}.{$extension}"; $absolute = rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative); $dir = dirname($absolute); if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new \DomainException("gd_attachment_storage_failed");
        if (is_uploaded_file($source)) { if (!@move_uploaded_file($source, $absolute)) throw new \DomainException("gd_attachment_storage_failed"); } elseif (!@copy($source, $absolute)) throw new \DomainException("gd_attachment_storage_failed"); @chmod($absolute, 0640);
        $now = gmdate("Y-m-d H:i:s"); $this->db->table($this->table)->insert(["unit_id" => $this->unit_id, "expense_id" => $expense_id, "original_name" => mb_substr($safe_original, 0, 255), "stored_path" => $relative, "stored_name" => basename($absolute), "mime_type" => $mime, "file_size" => $size, "sha256" => $hash, "document_type" => preg_match('/^[a-z_]{1,30}$/', $document_type) ? $document_type : "other", "created_at" => $now, "created_by" => $this->actor_id ?: null, "deleted" => 0]); $id = (int) $this->db->insertID(); if ($id <= 0) throw new \RuntimeException("gd_save_failed"); $row = $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->get(1)->getRow(); $this->audit_change("attachment_upload", "cost", $expense_id, null, $row ? (array) $row : null); return ["id" => $id, "data" => $row];
    }
    public function remove(int $id): void { $row = $this->get($id); if (!$row) throw new \DomainException("gd_record_not_found"); $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->update(["deleted" => 1]); $this->audit_change("attachment_delete", "cost", (int) $row->expense_id, (array) $row, null); }
    public function get(int $id): ?object { return $this->db->table($this->table)->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow(); }
    public function absolute_path(object $row): ?string { $relative = (string) $row->stored_path; $prefix = "uploads/gd_costs/{$this->unit_id}/"; if (!str_starts_with($relative, $prefix) || str_contains($relative, "..")) return null; $root = realpath(rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "gd_costs" . DIRECTORY_SEPARATOR . $this->unit_id); $candidate = realpath(rtrim(WRITEPATH, "/\\") . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relative)); if (!$root || !$candidate || !is_file($candidate)) return null; return str_starts_with($candidate, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) ? $candidate : null; }
}
