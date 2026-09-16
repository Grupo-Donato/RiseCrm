<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Vincula representações locais do mesmo aluno em unidades diferentes. */
final class V070_add_cross_unit_student_reference extends SchemaVersion
{
    public function version(): string
    {
        return "070";
    }

    public function description(): string
    {
        return "Adiciona vínculo de aluno compartilhado entre unidades.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "grupo_donato_alunos";

        // Em instalação nova o módulo Operacional cria esta tabela depois do
        // SchemaRunner. O bootstrap também aplica a mesma alteração de forma
        // idempotente quando a tabela surgir.
        if (!$db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($db, $table, "student_group_unit_id", "INT(11) NULL AFTER `id`");
        $this->ensureColumn($db, $table, "student_group_id", "INT(11) NULL AFTER `student_group_unit_id`");

        $db->query("UPDATE `{$table}`
            SET `student_group_unit_id` = COALESCE(`student_group_unit_id`, `unidade_id`),
                `student_group_id` = COALESCE(`student_group_id`, `id`)
            WHERE `student_group_unit_id` IS NULL OR `student_group_id` IS NULL");

        $this->ensureIndex($db, $table, "idx_student_group", "KEY `idx_student_group` (`student_group_unit_id`, `student_group_id`)");
        // Inclui deleted para permitir excluir uma representação local e
        // cadastrá-la novamente sem apagar o histórico anterior.
        $this->ensureIndex($db, $table, "uniq_student_group_unit", "UNIQUE KEY `uniq_student_group_unit` (`unidade_id`, `student_group_unit_id`, `student_group_id`, `deleted`)");
    }
}
