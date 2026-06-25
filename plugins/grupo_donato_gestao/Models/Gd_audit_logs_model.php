<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

/**
 * Trilha de auditoria append-only do plugin.
 *
 * Sem coluna `deleted` e sem fluxo de update/delete (imutável). A escrita ocorre
 * via AuditService (que mascara dados sensíveis). Aqui ficam apenas leitura
 * (listagem server-side) e o insert append-only.
 */
class Gd_audit_logs_model extends Gd_Model
{
    public function __construct()
    {
        parent::__construct("gd_audit_logs");
    }

    /**
     * Listagem com suporte ao contrato server-side do Rise + joins de exibição.
     *
     * @return \CodeIgniter\Database\ResultInterface|array
     */
    public function get_details(array $options = [])
    {
        $logs = $this->table;
        $users = $this->db->prefixTable("users");

        $base = function () use ($logs, $users, $options) {
            $builder = $this->db->table($logs);
            $builder->select("$logs.*, $users.first_name, $users.last_name");
            $builder->join($users, "$users.id = $logs.actor_id", "left");

            $id = get_array_value($options, "id");
            if ($id) {
                $builder->where("$logs.id", $id);
            }
            $entity_type = get_array_value($options, "entity_type");
            if ($entity_type) {
                $builder->where("$logs.entity_type", $entity_type);
            }
            $entity_id = get_array_value($options, "entity_id");
            if ($entity_id) {
                $builder->where("$logs.entity_id", (int) $entity_id);
            }
            $action = get_array_value($options, "action");
            if ($action) {
                $builder->where("$logs.action", $action);
            }
            $unit_id = get_array_value($options, "unit_id");
            if ($unit_id) {
                $builder->where("$logs.unit_id", $unit_id);
            }

            $search_by = get_array_value($options, "search_by");
            if ($search_by) {
                $builder->groupStart()
                    ->like("$logs.entity_type", $search_by)
                    ->orLike("$logs.action", $search_by)
                    ->orLike("$logs.request_id", $search_by)
                    ->orLike("$users.first_name", $search_by)
                    ->orLike("$users.last_name", $search_by)
                    ->groupEnd();
            }
            return $builder;
        };

        if (get_array_value($options, "server_side")) {
            $total_builder = $this->db->table($logs);
            $unit_id = get_array_value($options, "unit_id");
            if ($unit_id) {
                $total_builder->where("unit_id", (int) $unit_id);
            }
            $records_total = $total_builder->countAllResults();
            $records_filtered = $base()->countAllResults(false);

            $builder = $base();
            $order_map = [
                "id" => "$logs.id",
                "created_at" => "$logs.created_at",
                "action" => "$logs.action",
                "entity_type" => "$logs.entity_type",
            ];
            $order_by = $order_map[(string) get_array_value($options, "order_by")] ?? "$logs.id";
            $order_dir = get_array_value($options, "order_dir") === "ASC" ? "ASC" : "DESC";
            $builder->orderBy($order_by, $order_dir);

            $limit = (int) get_array_value($options, "limit");
            if ($limit > 0) {
                $builder->limit($limit, (int) get_array_value($options, "skip"));
            }

            return [
                "data" => $builder->get()->getResult(),
                "recordsTotal" => $records_total,
                "recordsFiltered" => $records_filtered,
            ];
        }

        $builder = $base();
        $builder->orderBy("$logs.id", "DESC");
        $limit = (int) get_array_value($options, "limit");
        if ($limit > 0) {
            $builder->limit($limit);
        }
        return $builder->get();
    }

    /**
     * Insert append-only direto (já mascarado pelo AuditService).
     */
    public function add(array $data): int
    {
        $this->db->table($this->table)->insert($data);
        return (int) $this->db->insertID();
    }

    public function ci_save(&$data = [], $id = 0)
    {
        throw new \LogicException("Audit records are append-only; use add().");
    }

    public function update_where($data = [], $where = [])
    {
        throw new \LogicException("Audit records cannot be updated.");
    }

    public function delete($id = 0, $undo = false)
    {
        throw new \LogicException("Audit records cannot be deleted.");
    }

    public function delete_permanently($id = 0)
    {
        throw new \LogicException("Audit records cannot be deleted.");
    }
}
