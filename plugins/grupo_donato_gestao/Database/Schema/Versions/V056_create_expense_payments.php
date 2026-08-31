<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

final class V056_create_expense_payments extends SchemaVersion
{
    public function version(): string { return "056"; }
    public function description(): string { return "Cria o ledger de pagamentos de custos."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_expense_payments";
        $this->ensureTable($db, $table, "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `expense_id` BIGINT UNSIGNED NOT NULL,
            `payment_number` VARCHAR(40) NOT NULL,
            `financial_account_id` BIGINT UNSIGNED NULL,
            `payment_date` DATE NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `payment_method` VARCHAR(30) NOT NULL,
            `external_reference` VARCHAR(190) NULL,
            `idempotency_key` VARCHAR(140) NULL,
            `cash_movement_id` BIGINT UNSIGNED NULL,
            `reversal_cash_movement_id` BIGINT UNSIGNED NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed',
            `reversed_at` DATETIME NULL,
            `reversed_by` BIGINT UNSIGNED NULL,
            `reversal_reason` TEXT NULL,
            `legacy_expense_id` BIGINT UNSIGNED NULL,
            `legacy_cash_movement_id` BIGINT UNSIGNED NULL,
            `notes` TEXT NULL,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_expense_payment_number` (`unit_id`,`payment_number`),
            UNIQUE KEY `uniq_expense_payment_idempotency` (`unit_id`,`idempotency_key`),
            UNIQUE KEY `uniq_expense_payment_legacy` (`unit_id`,`legacy_expense_id`),
            KEY `idx_expense_payment_expense` (`unit_id`,`expense_id`,`status`,`deleted`),
            KEY `idx_expense_payment_date` (`unit_id`,`payment_date`,`status`,`deleted`)
        ) ENGINE=InnoDB");
    }
}
