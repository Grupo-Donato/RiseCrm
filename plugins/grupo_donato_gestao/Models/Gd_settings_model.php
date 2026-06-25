<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Models;

class Gd_settings_model extends Gd_Model
{
    protected array $searchable_fields = ["key", "value_type"];

    public function __construct()
    {
        parent::__construct("gd_settings");
    }

    /**
     * Busca uma configuração por chave e escopo (unit_id NULL = global).
     */
    public function get_by_key(string $key, ?int $unit_id = null)
    {
        $builder = $this->db->table($this->table)
            ->where("deleted", 0)
            ->where("`key`", $key);
        if ($unit_id === null) {
            $builder->where("unit_id IS NULL", null, false);
        } else {
            $builder->where("unit_id", $unit_id);
        }
        return $builder->get()->getRow();
    }

    /**
     * Lista não-secretas de um escopo, para a tela de configurações gerais.
     *
     * @return \CodeIgniter\Database\ResultInterface
     */
    public function get_visible(array $options = [])
    {
        $builder = $this->db->table($this->table)->where("deleted", 0)->where("is_secret", 0);
        $unit_id = get_array_value($options, "unit_id");
        if ($unit_id !== null && $unit_id !== "") {
            $builder->where("unit_id", $unit_id);
        }
        $builder->orderBy("`key`", "ASC");
        return $builder->get();
    }
}
