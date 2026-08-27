<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Schema\Versions;

use CodeIgniter\Database\BaseConnection;
use grupo_donato_gestao\Database\Schema\SchemaVersion;

/** Adiciona a referência da foto ao cadastro de alunos efetivamente usado. */
class V050_add_operational_student_photo_path extends SchemaVersion
{
    public function version(): string
    {
        return "050";
    }

    public function description(): string
    {
        return "Adiciona caminho da foto ao aluno do módulo operacional.";
    }

    public function up(BaseConnection $db, string $prefix): void
    {
        $table = $prefix . "grupo_donato_alunos";

        // Em uma instalação nova o SchemaRunner roda antes do bootstrap do
        // módulo Operacional. Nesse caso, o bootstrap já cria a coluna.
        if (!$db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($db, $table, "photo_path", "VARCHAR(255) NULL AFTER `cpf_aluno`");
    }
}
