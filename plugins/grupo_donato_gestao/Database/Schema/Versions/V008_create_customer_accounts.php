<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V008_create_customer_accounts extends SchemaVersion
{
    public function version(): string { return "008"; }
    public function description(): string { return "Cria contas universais de clientes."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_customer_accounts";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `account_type` VARCHAR(30) NOT NULL,
                `display_name` VARCHAR(190) NOT NULL,
                `normalized_name` VARCHAR(190) NOT NULL,
                `legal_name` VARCHAR(190) NULL,
                `trade_name` VARCHAR(190) NULL,
                `document_type` VARCHAR(20) NOT NULL DEFAULT 'none',
                `document_number` VARCHAR(40) NULL,
                `document_number_normalized` VARCHAR(40) NULL,
                `email` VARCHAR(190) NULL,
                `email_normalized` VARCHAR(190) NULL,
                `phone` VARCHAR(40) NULL,
                `phone_normalized` VARCHAR(40) NULL,
                `whatsapp` VARCHAR(40) NULL,
                `whatsapp_normalized` VARCHAR(40) NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `rise_client_id` BIGINT UNSIGNED NULL,
                `notes` TEXT NULL,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_unit` (`unit_id`),
                KEY `idx_type` (`account_type`),
                KEY `idx_status` (`status`),
                KEY `idx_name` (`normalized_name`),
                KEY `idx_document` (`document_number_normalized`),
                KEY `idx_email` (`email_normalized`),
                KEY `idx_phone` (`phone_normalized`),
                KEY `idx_rise_client` (`rise_client_id`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
