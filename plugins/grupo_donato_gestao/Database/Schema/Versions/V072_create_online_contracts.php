<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Mantém a versão e a evidência do contrato aceito. */
final class V072_create_online_contracts extends SchemaVersion
{
    public function version(): string { return "072"; }

    public function description(): string
    {
        return "Cria o arquivo lógico de contratos da matrícula online.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_online_contracts";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `draft_id` BIGINT UNSIGNED NOT NULL,
                `unit_id` INT(11) NOT NULL,
                `student_id` BIGINT UNSIGNED NOT NULL,
                `contract_number` VARCHAR(80) NOT NULL,
                `version` VARCHAR(80) NOT NULL,
                `content_html` LONGTEXT NOT NULL,
                `html_path` VARCHAR(500) NOT NULL,
                `pdf_path` VARCHAR(500) NOT NULL,
                `signature_path` VARCHAR(500) NOT NULL,
                `pdf_sha256` CHAR(64) NOT NULL,
                `accepted_at` DATETIME NOT NULL,
                `signed_at` DATETIME NOT NULL,
                `acceptance_ip` VARCHAR(45) NULL,
                `acceptance_user_agent` VARCHAR(500) NULL,
                `generated_at` DATETIME NOT NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'signed',
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_online_contract_draft` (`draft_id`),
                UNIQUE KEY `uniq_online_contract_number` (`contract_number`),
                KEY `idx_online_contract_student` (`student_id`),
                KEY `idx_online_contract_hash` (`pdf_sha256`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
