<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Controla entrega do contrato sem misturar falha externa à matrícula. */
final class V073_create_online_contract_deliveries extends SchemaVersion
{
    public function version(): string { return "073"; }

    public function description(): string
    {
        return "Cria a fila idempotente de entrega do contrato por WhatsApp.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_online_contract_deliveries";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `contract_id` BIGINT UNSIGNED NOT NULL,
                `draft_id` BIGINT UNSIGNED NOT NULL,
                `student_id` BIGINT UNSIGNED NOT NULL,
                `recipient_phone` VARCHAR(30) NOT NULL,
                `provider_instance_id` INT(11) NULL,
                `conversation_id` BIGINT UNSIGNED NULL,
                `message_id` BIGINT UNSIGNED NULL,
                `external_message_id` VARCHAR(255) NULL,
                `status` VARCHAR(24) NOT NULL DEFAULT 'pending',
                `attempts` INT(11) NOT NULL DEFAULT 0,
                `last_error` TEXT NULL,
                `next_attempt_at` DATETIME NULL,
                `sent_at` DATETIME NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_online_delivery_contract` (`contract_id`),
                KEY `idx_online_delivery_status` (`status`, `next_attempt_at`),
                KEY `idx_online_delivery_student` (`student_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
