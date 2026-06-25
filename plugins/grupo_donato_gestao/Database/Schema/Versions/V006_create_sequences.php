<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use grupo_donato_gestao\Database\Schema\SchemaVersion;
use CodeIgniter\Database\BaseConnection;

class V006_create_sequences extends SchemaVersion
{
    public function version(): string
    {
        return "006";
    }

    public function description(): string
    {
        return "Cria a tabela de sequências de numeração de documentos.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "gd_sequences";
        $this->ensureTable($db, $table, "
            CREATE TABLE IF NOT EXISTS `$table` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `unit_id` BIGINT UNSIGNED NOT NULL,
                `document_type` VARCHAR(40) NOT NULL,
                `prefix` VARCHAR(20) NULL,
                `current_value` BIGINT NOT NULL DEFAULT 0,
                `padding` TINYINT NOT NULL DEFAULT 0,
                `yearly_reset` TINYINT(1) NOT NULL DEFAULT 0,
                `last_reset_year` SMALLINT NULL,
                `deleted` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME NULL,
                `updated_at` DATETIME NULL,
                `created_by` BIGINT UNSIGNED NULL,
                `updated_by` BIGINT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_unit_doc` (`unit_id`, `document_type`),
                KEY `idx_deleted` (`deleted`)
            ) ENGINE=InnoDB
        ");
    }
}
