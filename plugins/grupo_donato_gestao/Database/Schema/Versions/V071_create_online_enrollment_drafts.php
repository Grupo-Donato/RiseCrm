<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Guarda o formulário público enquanto o contrato ainda não foi efetivado. */
final class V071_create_online_enrollment_drafts extends SchemaVersion
{
    public function version(): string { return "071"; }

    public function description(): string
    {
        return "Cria rascunhos idempotentes da matrícula online.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_online_enrollment_drafts";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` INT(11) NOT NULL,
                `public_token_hash` CHAR(64) NOT NULL,
                `public_token` VARCHAR(96) NULL,
                `idempotency_key` CHAR(64) NULL,
                `payload_hash` CHAR(64) NOT NULL,
                `status` VARCHAR(32) NOT NULL DEFAULT 'contract_pending',
                `payload_json` LONGTEXT NOT NULL,
                `contract_version` VARCHAR(80) NOT NULL,
                `accepted_at` DATETIME NULL,
                `accepted_ip` VARCHAR(45) NULL,
                `accepted_user_agent` VARCHAR(500) NULL,
                `signed_at` DATETIME NULL,
                `signature_path` VARCHAR(500) NULL,
                `signature_sha256` CHAR(64) NULL,
                `student_id` BIGINT UNSIGNED NULL,
                `contract_id` BIGINT UNSIGNED NULL,
                `completed_at` DATETIME NULL,
                `expires_at` DATETIME NOT NULL,
                `last_error` TEXT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_online_draft_token` (`public_token_hash`),
                UNIQUE KEY `uniq_online_draft_idempotency` (`idempotency_key`),
                KEY `idx_online_draft_payload` (`unit_id`, `payload_hash`),
                KEY `idx_online_draft_status` (`status`, `expires_at`),
                KEY `idx_online_draft_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        // Mantém o token reversível apenas dentro do banco privado para que
        // uma requisição repetida possa recuperar a mesma resposta idempotente
        // mesmo quando a primeira resposta se perdeu na rede. A URL continua
        // sendo protegida pelo hash usado nas consultas públicas.
        $this->ensureColumn($db, $table, "public_token", "VARCHAR(96) NULL AFTER `public_token_hash`");
    }
}
