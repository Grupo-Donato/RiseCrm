<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V002_create_units extends SchemaVersion
{
    public function version(): string
    {
        return "002";
    }

    public function description(): string
    {
        return "Cria a tabela de unidades operacionais.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_units";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) NOT NULL,
                `legal_name` VARCHAR(190) NULL,
                `document` VARCHAR(20) NULL,
                `timezone` VARCHAR(64) NULL,
                `status` VARCHAR(30) NOT NULL DEFAULT 'active',
                `is_default` TINYINT(1) NOT NULL DEFAULT 0,
                `rise_client_id` BIGINT UNSIGNED NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                KEY `idx_status` (`status`),
                KEY `idx_default` (`is_default`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
