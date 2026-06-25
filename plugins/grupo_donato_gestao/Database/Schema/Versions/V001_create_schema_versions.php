<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V001_create_schema_versions extends SchemaVersion
{
    public function version(): string
    {
        return "001";
    }

    public function description(): string
    {
        return "Cria a tabela de controle de versões de schema.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_schema_versions";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version` VARCHAR(20) NOT NULL,
                `description` VARCHAR(190) NULL,
                `checksum` VARCHAR(64) NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'running',
                `started_at` DATETIME NULL,
                `finished_at` DATETIME NULL,
                `error_message` TEXT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_version` (`version`)
            ) ENGINE=InnoDB
        ");
    }
}
