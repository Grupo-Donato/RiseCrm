<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services\Import;

use grupo_donato_gestao\Services\ImportFileService;

/**
 * Contrato base das estratégias de importação (Fase 6).
 *
 * Cada importador declara as colunas canônicas (com aliases para auto-mapeamento),
 * valida uma linha SEM escrever no domínio, e — apenas na confirmação — cria os
 * registros reutilizando os SERVICES de domínio existentes. A rastreabilidade e o
 * dedupe usam `gd_import_links` (chave lógica `source_key` + `target_type`).
 */
abstract class AbstractImporter
{
    protected int $unit_id;
    protected int $actor_id;
    protected ?object $login_user;
    protected $db;
    protected ImportFileService $files;
    protected $links;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        $this->unit_id = $unit_id;
        $this->actor_id = $actor_id;
        $this->login_user = $login_user;
        $this->db = db_connect();
        $this->files = new ImportFileService();
        $this->links = model("grupo_donato_gestao\\Models\\Gd_import_links_model");
    }

    abstract public function type(): string;

    /** @return array<array{field:string,label:string,aliases:array<string>,required:bool}> */
    abstract public function headerDefs(): array;

    /** @return array{status:string,normalized:array,issues:array,source_key:string} */
    abstract public function validateRow(array $row): array;

    /** @return array{links:array<array{source_key:string,target_type:string,target_id:int}>} */
    abstract public function importRow(array $normalized, string $source_key, int $row_number, int $row_id): array;

    /** Tipos de alvo que tornam uma linha "já importada" (idempotência no reprocesso). */
    abstract public function primaryTargetTypes(): array;

    /* ---------------- helpers ---------------- */

    protected function issue(string $type, string $severity, string $message, array $context = []): array
    {
        return ["issue_type" => $type, "severity" => $severity, "message" => $message, "context" => $context];
    }

    protected function find(string $source_key, string $target_type): ?int
    {
        $row = $this->links->target_for_source($this->unit_id, $source_key, $target_type);
        return $row ? (int) $row->target_id : null;
    }

    protected function link(string $source_key, string $target_type, int $target_id): array
    {
        return ["source_key" => $source_key, "target_type" => $target_type, "target_id" => $target_id];
    }

    /** Detecta mais de uma pessoa numa mesma célula (",", "/", "&", " e "). */
    protected function hasMultiplePeople(string $value): bool
    {
        return (bool) preg_match('#\s*(/|&|\+|\b e \b|,)\s*#iu', trim($value));
    }
}
