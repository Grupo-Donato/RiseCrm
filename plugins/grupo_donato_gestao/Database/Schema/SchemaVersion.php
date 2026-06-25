<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema;

use CodeIgniter\Database\BaseConnection;

/**
 * Contrato de uma versão de schema. Cada versão tem responsabilidade pequena
 * (tipicamente UMA tabela) e DEVE ser idempotente.
 */
abstract class SchemaVersion
{
    /** Identificador ordenável e estável (ex.: "001"). */
    abstract public function version(): string;

    abstract public function description(): string;

    /** Aplica a alteração de schema. Deve ser seguro reexecutar. */
    abstract public function up(BaseConnection $db, string $prefix): void;

    // -------- Helpers idempotentes --------

    protected function ensureTable(BaseConnection $db, string $table, string $createSql): void
    {
        if (!$db->tableExists($table)) {
            $db->query($createSql);
        }
    }

    protected function ensureColumn(BaseConnection $db, string $table, string $column, string $definition): void
    {
        $exists = $db->query("SHOW COLUMNS FROM `" . $table . "` LIKE " . $db->escape($column))->getRow();
        if (!$exists) {
            $db->query("ALTER TABLE `" . $table . "` ADD `" . $column . "` " . $definition);
        }
    }

    protected function ensureIndex(BaseConnection $db, string $table, string $indexName, string $definition): void
    {
        $exists = $db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name=" . $db->escape($indexName))->getRow();
        if (!$exists) {
            $db->query("ALTER TABLE `" . $table . "` ADD " . $definition);
        }
    }
}
