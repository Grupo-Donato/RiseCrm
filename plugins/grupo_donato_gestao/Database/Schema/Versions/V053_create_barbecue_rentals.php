<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Estrutura comercial independente para aluguel das churrasqueiras 1–6. */
class V053_create_barbecue_rentals extends SchemaVersion
{
    public function version(): string { return "053"; }
    public function description(): string { return "Cria o módulo comercial de aluguel de churrasqueiras."; }

    public function up(BaseConnection $db, string $prefix): void
    {
        $rentals = $prefix . "gd_barbecue_rentals";
        $this->ensureTable($db, $rentals, "
            CREATE TABLE IF NOT EXISTS `$rentals` (
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
                `extra_time_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
                `extra_time_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `extra_time_notes` TEXT NULL,
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
                UNIQUE KEY `uniq_unit_barbecue_rental_number` (`unit_id`,`rental_number`),
                KEY `idx_barbecue_rental_unit_status` (`unit_id`,`status`,`deleted`),
                KEY `idx_barbecue_rental_customer` (`unit_id`,`customer_account_id`,`deleted`),
                KEY `idx_barbecue_rental_type_status` (`unit_id`,`rental_type`,`status`),
                KEY `idx_barbecue_rental_updated` (`unit_id`,`updated_at`)
            ) ENGINE=InnoDB
        ");

        $links = $prefix . "gd_barbecue_rental_schedule_links";
        $this->ensureTable($db, $links, "
            CREATE TABLE IF NOT EXISTS `$links` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `booking_id` BIGINT UNSIGNED NULL,
                `booking_series_id` BIGINT UNSIGNED NULL,
                `link_kind` VARCHAR(20) NOT NULL DEFAULT 'primary',
                `active_booking_guard` BIGINT UNSIGNED NULL,
                `active_series_guard` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_barbecue_active_booking` (`unit_id`,`active_booking_guard`),
                UNIQUE KEY `uniq_barbecue_active_series` (`unit_id`,`active_series_guard`),
                KEY `idx_barbecue_link_rental` (`unit_id`,`rental_id`,`deleted`),
                KEY `idx_barbecue_link_booking` (`unit_id`,`booking_id`),
                KEY `idx_barbecue_link_series` (`unit_id`,`booking_series_id`)
            ) ENGINE=InnoDB
        ");

        $items = $prefix . "gd_barbecue_rental_price_items";
        $this->ensureTable($db, $items, "
            CREATE TABLE IF NOT EXISTS `$items` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `product_id` BIGINT UNSIGNED NULL,
                `variant_id` BIGINT UNSIGNED NULL,
                `resource_id` BIGINT UNSIGNED NULL,
                `price_id` BIGINT UNSIGNED NULL,
                `description` VARCHAR(255) NULL,
                `quantity` DECIMAL(15,3) NOT NULL DEFAULT 1.000,
                `unit_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                `currency` VARCHAR(3) NOT NULL,
                `snapshot` MEDIUMTEXT NULL,
                `created_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_at` DATETIME NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_barbecue_price_item_rental` (`unit_id`,`rental_id`,`deleted`)
            ) ENGINE=InnoDB
        ");

        $events = $prefix . "gd_barbecue_rental_events";
        $this->ensureTable($db, $events, "
            CREATE TABLE IF NOT EXISTS `$events` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `rental_id` BIGINT UNSIGNED NOT NULL,
                `event_type` VARCHAR(40) NOT NULL,
                `from_status` VARCHAR(20) NULL,
                `to_status` VARCHAR(20) NULL,
                `reason` VARCHAR(255) NULL,
                `payload` MEDIUMTEXT NULL,
                `actor_type` VARCHAR(20) NOT NULL DEFAULT 'system',
                `actor_id` BIGINT UNSIGNED NULL,
                `request_id` VARCHAR(64) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_barbecue_events_rental` (`unit_id`,`rental_id`,`id`),
                KEY `idx_barbecue_events_type` (`unit_id`,`event_type`,`created_at`)
            ) ENGINE=InnoDB
        ");
    }
}
