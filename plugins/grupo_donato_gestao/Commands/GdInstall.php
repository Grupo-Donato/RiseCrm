<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use grupo_donato_gestao\Database\Schema\SchemaRunner;
use grupo_donato_gestao\Database\Seeds\FoundationSeeder;

/**
 * Comando de instalação/atualização da fundação (espelha gd_install do index.php).
 * Útil para CI e para reaplicar schema/seeds com segurança.
 */
class GdInstall extends BaseCommand
{
    protected $group = "Grupo Donato";
    protected $name = "gd:install";
    protected $description = "Aplica o schema versionado e os seeds da fundação (idempotente).";

    public function run(array $params)
    {
        $runner = new SchemaRunner();
        $result = $runner->run();

        if ($result["skipped_lock"]) {
            CLI::error("Execução ignorada: lock do schema ocupado.");
            return;
        }

        if (count($result["ran"])) {
            CLI::write("Versões aplicadas: " . implode(", ", $result["ran"]), "green");
        } else {
            CLI::write("Nenhuma versão pendente.", "yellow");
        }

        if (!empty($result["failed"])) {
            CLI::error("Falha na versão: " . $result["failed"]);
            return;
        }

        (new FoundationSeeder(0))->run();
        CLI::write("Seeds aplicados.", "green");
        CLI::write("Instalação/atualização concluída.", "green");
    }
}
