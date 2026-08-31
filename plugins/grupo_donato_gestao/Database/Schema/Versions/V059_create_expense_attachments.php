<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V059_create_expense_attachments extends SchemaVersion
{
    public function version(): string { return "059"; }
    public function description(): string { return "Cria anexos privados dos custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expense_attachments";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `expense_id` BIGINT UNSIGNED NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `stored_path` VARCHAR(255) NOT NULL,
            `stored_name` VARCHAR(190) NOT NULL,
            `mime_type` VARCHAR(120) NOT NULL,
            `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `sha256` CHAR(64) NOT NULL,
            `document_type` VARCHAR(30) NOT NULL DEFAULT 'other',
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_expense_attachment_expense` (`unit_id`,`expense_id`,`deleted`),
            UNIQUE KEY `uniq_expense_attachment_hash` (`unit_id`,`expense_id`,`sha256`)
        ) ENGINE=InnoDB");
    }
}
