<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

class V030_create_court_rentals extends SchemaVersion
{
    public function version(): string { return "030"; }
    public function description(): string { return "Cria locações comerciais de quadras."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_court_rentals";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_number` VARCHAR(40) NOT NULL,
                `customer_account_id` BIGINT UNSIGNED NOT NULL,
                `contact_person_id` BIGINT UNSIGNED NULL,
                `rental_type` VARCHAR(20) NOT NULL,
                `title` VARCHAR(180) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
                `billing_cycle` VARCHAR(20) NOT NULL,
                `preferred_due_day` TINYINT UNSIGNED NULL,
                `effective_from` DATE NULL,
                `effective_until` DATE NULL,
                `currency` VARCHAR(3) NOT NULL,
                `list_amount` DECIMAL(15,2) NULL,
                `negotiated_amount` DECIMAL(15,2) NULL,
                `discount_amount` DECIMAL(15,2) NULL,
                `discount_reason` VARCHAR(255) NULL,
                `product_id` BIGINT UNSIGNED NULL,
                `price_list_id` BIGINT UNSIGNED NULL,
                `price_id` BIGINT UNSIGNED NULL,
                `commercial_notes` TEXT NULL,
                `metadata` MEDIUMTEXT NULL,
                `lock_version` INT UNSIGNED NOT NULL DEFAULT 1,
                `activated_at` DATETIME NULL,
                `activated_by` BIGINT UNSIGNED NULL,
                `suspended_at` DATETIME NULL,
                `suspended_by` BIGINT UNSIGNED NULL,
                `cancelled_at` DATETIME NULL,
                `cancelled_by` BIGINT UNSIGNED NULL,
                `completed_at` DATETIME NULL,
                `completed_by` BIGINT UNSIGNED NULL,
                `cancellation_reason` VARCHAR(255) NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_rental_number` (`unit_id`,`rental_number`),
                KEY `idx_rental_unit_status` (`unit_id`,`status`,`deleted`),
                KEY `idx_rental_customer` (`unit_id`,`customer_account_id`,`deleted`),
                KEY `idx_rental_type_status` (`unit_id`,`rental_type`,`status`),
                KEY `idx_rental_updated` (`unit_id`,`updated_at`)
            ) ENGINE=InnoDB
        ");
    }
}
