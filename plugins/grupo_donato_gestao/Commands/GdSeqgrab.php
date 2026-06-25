<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use grupo_donato_gestao\Services\SequenceService;

/**
 * Obtém N números de sequência e os imprime (um por linha).
 * Usado no teste de CONCORRÊNCIA: dois processos rodando em paralelo não devem
 * produzir números repetidos (graças ao SELECT ... FOR UPDATE).
 *
 * Uso: php spark gd:seqgrab <quantidade> [document_type]
 */
class GdSeqgrab extends BaseCommand
{
    protected $group = "Grupo Donato";
    protected $name = "gd:seqgrab";
    protected $description = "Gera N números de sequência (teste de concorrência).";
    protected $usage = "gd:seqgrab [count] [document_type]";

    public function run(array $params)
    {
        $count = isset($params[0]) ? (int) $params[0] : 10;
        $type = $params[1] ?? "concurrency_test";

        $units = model("grupo_donato_gestao\\Models\\Gd_units_model");
        $default = $units->get_default();
        $unit_id = $default ? (int) $default->id : 1;

        $service = new SequenceService();
        $service->ensure($unit_id, $type, "T", 6, false);

        for ($i = 0; $i < $count; $i++) {
            $raw = $service->next_raw($unit_id, $type);
            CLI::write((string) $raw["current_value"]);
        }
    }
}
