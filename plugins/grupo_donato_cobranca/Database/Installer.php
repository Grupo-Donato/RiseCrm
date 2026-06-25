<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Database;

use grupo_donato_cobranca\Config\Constants;

final class Installer
{
    public function install(): void
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        $schema = $prefix . 'gdc_schema_versions';
        $settings = $prefix . 'gdc_settings';
        $charges = $prefix . 'gdc_charges';
        $subscriptions = $prefix . 'gdc_subscriptions';
        $methods = $prefix . 'gdc_payment_methods';
        $events = $prefix . 'gdc_charge_events';

        $db->query("CREATE TABLE IF NOT EXISTS `$schema` (
            `version` VARCHAR(10) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `applied_at` DATETIME NOT NULL,
            PRIMARY KEY (`version`)
        ) ENGINE=InnoDB");

        $db->query("CREATE TABLE IF NOT EXISTS `$settings` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `setting_key` VARCHAR(80) NOT NULL,
            `setting_value` TEXT NULL,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_gdc_setting` (`unit_id`,`setting_key`,`deleted`),
            KEY `idx_gdc_settings_unit` (`unit_id`,`deleted`)
        ) ENGINE=InnoDB");

        $db->query("CREATE TABLE IF NOT EXISTS `$subscriptions` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `customer_account_id` BIGINT UNSIGNED NOT NULL,
            `source_type` VARCHAR(30) NOT NULL,
            `source_id` BIGINT UNSIGNED NULL,
            `collection_method` VARCHAR(30) NOT NULL,
            `payment_method_id` BIGINT UNSIGNED NULL,
            `provider_code` VARCHAR(60) NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `charge_day` TINYINT UNSIGNED NULL,
            `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `retry_interval_days` TINYINT UNSIGNED NOT NULL DEFAULT 3,
            `last_charge_at` DATETIME NULL,
            `next_attempt_at` DATETIME NULL,
            `started_at` DATETIME NOT NULL,
            `ended_at` DATETIME NULL,
            `notes` TEXT NULL,
            `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `idx_gdc_subscriptions_source` (`unit_id`,`source_type`,`source_id`,`status`,`deleted`),
            KEY `idx_gdc_subscriptions_customer` (`unit_id`,`customer_account_id`,`status`,`deleted`),
            KEY `idx_gdc_subscriptions_due` (`unit_id`,`status`,`next_attempt_at`,`deleted`)
        ) ENGINE=InnoDB");

        $db->query("CREATE TABLE IF NOT EXISTS `$methods` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `customer_account_id` BIGINT UNSIGNED NOT NULL,
            `provider_code` VARCHAR(60) NOT NULL,
            `external_customer_id` VARCHAR(190) NULL,
            `provider_payment_method_ref` VARCHAR(255) NOT NULL,
            `method_type` VARCHAR(30) NOT NULL DEFAULT 'credit_card',
            `brand` VARCHAR(40) NULL,
            `last4` CHAR(4) NULL,
            `exp_month` TINYINT UNSIGNED NULL,
            `exp_year` SMALLINT UNSIGNED NULL,
            `holder_name_masked` VARCHAR(190) NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_gdc_provider_method` (`unit_id`,`provider_code`,`provider_payment_method_ref`),
            KEY `idx_gdc_methods_customer` (`unit_id`,`customer_account_id`,`status`,`deleted`)
        ) ENGINE=InnoDB");

        $db->query("CREATE TABLE IF NOT EXISTS `$charges` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `charge_uuid` CHAR(36) NOT NULL,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `receivable_id` BIGINT UNSIGNED NOT NULL,
            `customer_account_id` BIGINT UNSIGNED NOT NULL,
            `subscription_id` BIGINT UNSIGNED NULL,
            `payment_method_id` BIGINT UNSIGNED NULL,
            `provider_code` VARCHAR(60) NOT NULL,
            `collection_method` VARCHAR(30) NOT NULL,
            `idempotency_key` VARCHAR(190) NOT NULL,
            `external_charge_id` VARCHAR(190) NULL,
            `external_payment_id` VARCHAR(190) NULL,
            `pix_txid` VARCHAR(190) NULL,
            `pix_copy_paste` MEDIUMTEXT NULL,
            `pix_qr_code_url` VARCHAR(500) NULL,
            `amount` DECIMAL(15,2) NOT NULL,
            `paid_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `due_date` DATE NOT NULL,
            `expires_at` DATETIME NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'processing',
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 1,
            `next_retry_at` DATETIME NULL,
            `last_error_code` VARCHAR(100) NULL,
            `last_error_message` VARCHAR(500) NULL,
            `paid_at` DATETIME NULL,
            `gd_payment_id` BIGINT UNSIGNED NULL,
            `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NULL,
            `created_by` BIGINT UNSIGNED NULL,
            `updated_at` DATETIME NULL,
            `updated_by` BIGINT UNSIGNED NULL,
            `deleted` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_gdc_charge_uuid` (`charge_uuid`),
            UNIQUE KEY `uniq_gdc_idempotency` (`idempotency_key`),
            KEY `idx_gdc_charge_receivable` (`unit_id`,`receivable_id`,`status`,`deleted`),
            KEY `idx_gdc_charge_external` (`unit_id`,`provider_code`,`external_charge_id`),
            KEY `idx_gdc_charge_status` (`unit_id`,`status`,`due_date`,`deleted`)
        ) ENGINE=InnoDB");

        $db->query("CREATE TABLE IF NOT EXISTS `$events` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `unit_id` BIGINT UNSIGNED NOT NULL,
            `charge_id` BIGINT UNSIGNED NOT NULL,
            `provider_event_id` VARCHAR(190) NULL,
            `event_type` VARCHAR(80) NOT NULL,
            `status_before` VARCHAR(30) NULL,
            `status_after` VARCHAR(30) NULL,
            `payload_hash` CHAR(64) NULL,
            `message` VARCHAR(500) NULL,
            `occurred_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_gdc_provider_event` (`unit_id`,`provider_event_id`),
            KEY `idx_gdc_event_charge` (`unit_id`,`charge_id`,`id`)
        ) ENGINE=InnoDB");

        $db->table($schema)->ignore(true)->insert([
            'version' => Constants::SCHEMA_VERSION,
            'description' => 'Estrutura inicial do módulo de cobrança bancária.',
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

}
