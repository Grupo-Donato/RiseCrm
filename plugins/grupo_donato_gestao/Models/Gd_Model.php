<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

use App\Models\Crud_model;

/**
 * Model base do plugin Grupo Donato.
 *
 * Estende o Crud_model do Rise (mantendo soft-delete `deleted`, hooks
 * `app_hook_data_*` e o padrão de prefixo do framework) e adiciona o que o
 * Crud_model NÃO faz:
 *  - timestamps automáticos em UTC (`created_at`/`updated_at`) — verificado:
 *    o Crud_model não seta timestamps (ver docs/rise-integration.md);
 *  - um `get_details()` genérico que suporta tanto listagem client-side
 *    (retorna ResultInterface) quanto o contrato server-side do Rise
 *    (retorna array com data/recordsTotal/recordsFiltered).
 *
 * created_by/updated_by NÃO são setados aqui: são responsabilidade explícita
 * dos Services (que conhecem o usuário logado), evitando acoplar models à sessão.
 */
abstract class Gd_Model extends Crud_model
{
    /** Campos pesquisáveis no get_details() genérico (search_by). */
    protected array $searchable_fields = [];

    /** Cache dos nomes de coluna da tabela. */
    private ?array $field_names_cache = null;

    public function __construct(string $table)
    {
        parent::__construct($table);
    }

    protected function table_field_names(): array
    {
        if ($this->field_names_cache === null) {
            $this->field_names_cache = $this->db->getFieldNames($this->table);
        }
        return $this->field_names_cache;
    }

    private function now_utc(): string
    {
        return function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s");
    }

    /**
     * Seta timestamps em UTC antes de delegar ao Crud_model.
     * Só preenche colunas que existem na tabela.
     *
     * @param array $data
     * @param int|string $id
     */
    public function ci_save(&$data = [], $id = 0)
    {
        $fields = $this->table_field_names();
        $now = $this->now_utc();

        if (!$id && in_array("created_at", $fields, true) && !isset($data["created_at"])) {
            $data["created_at"] = $now;
        }

        if (in_array("updated_at", $fields, true)) {
            $data["updated_at"] = $now;
        }

        return parent::ci_save($data, $id);
    }

    /**
     * Listagem genérica. Em modo server-side retorna array compatível com o
     * appTable do Rise; caso contrário retorna o objeto de resultado.
     *
     * Opções suportadas: id, where (assoc), search_by, order_by, order_dir,
     * limit, skip, server_side, include_deleted.
     *
     * @return \CodeIgniter\Database\ResultInterface|array
     */
    public function get_details(array $options = [])
    {
        $table = $this->table;
        $builder = $this->db->table($table);

        if (!get_array_value($options, "include_deleted")) {
            $builder->where("$table.deleted", 0);
        }

        $id = get_array_value($options, "id");
        if ($id) {
            $builder->where("$table.id", $id);
        }

        $where = get_array_value($options, "where");
        if (is_array($where)) {
            foreach ($where as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $builder->where($key, $value);
            }
        }

        $this->apply_extra_conditions($builder, $options);

        $search_by = get_array_value($options, "search_by");
        if ($search_by && $this->searchable_fields) {
            $builder->groupStart();
            foreach (array_values($this->searchable_fields) as $i => $field) {
                if ($i === 0) {
                    $builder->like($field, $search_by);
                } else {
                    $builder->orLike($field, $search_by);
                }
            }
            $builder->groupEnd();
        }

        if (get_array_value($options, "server_side")) {
            $records_total = $this->db->table($table)->where("$table.deleted", 0)->countAllResults();
            $records_filtered = $builder->countAllResults(false);

            $this->apply_order($builder, $options);

            $limit = (int) get_array_value($options, "limit");
            $skip = (int) get_array_value($options, "skip");
            if ($limit > 0) {
                $builder->limit($limit, $skip);
            }

            return [
                "data" => $builder->get()->getResult(),
                "recordsTotal" => $records_total,
                "recordsFiltered" => $records_filtered,
            ];
        }

        $this->apply_order($builder, $options);

        $limit = (int) get_array_value($options, "limit");
        if ($limit > 0) {
            $builder->limit($limit, (int) get_array_value($options, "skip"));
        }

        return $builder->get();
    }

    /**
     * Ponto de extensão para joins/condições específicas de cada model.
     */
    protected function apply_extra_conditions($builder, array $options): void
    {
        // sobrescrever quando necessário
    }

    protected function apply_order($builder, array $options): void
    {
        $order_by = get_array_value($options, "order_by");
        if ($order_by) {
            $dir = get_array_value($options, "order_dir") === "DESC" ? "DESC" : "ASC";
            $builder->orderBy($order_by, $dir);
        } else {
            $builder->orderBy($this->table . ".id", "DESC");
        }
    }
}
