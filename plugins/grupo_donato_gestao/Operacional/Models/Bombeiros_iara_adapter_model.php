<?php

namespace grupo_donato_gestao\Operacional\Models;

use App\Models\Crud_model;

class Bombeiros_iara_adapter_model extends Crud_model
{
    protected $table = null;
    private $iara_available = null;

    public function __construct()
    {
        $this->table = "grupo_donato_unidades";
        parent::__construct($this->table);
    }

    public function arquitetura_disponivel()
    {
        if ($this->iara_available !== null) {
            return $this->iara_available;
        }

        // O RISE usa MySQL neste ambiente. Esta verificação é propositalmente
        // defensiva: se as views dbo/iara_core não estiverem acessíveis, o plugin
        // segue usando as tabelas locais grupo_donato_* sem quebrar o painel.
        try {
            $this->db->query("SELECT 1 FROM dbo.unidades LIMIT 1")->getRow();
            $this->iara_available = true;
        } catch (\Throwable $e) {
            $this->iara_available = false;
        }

        return $this->iara_available;
    }

    public function set_current_unit($slug)
    {
        if (!$this->arquitetura_disponivel()) {
            return false;
        }

        try {
            $this->db->query("SELECT iara_core.set_current_unit(" . $this->db->escape($slug) . ")")->getRow();
            return true;
        } catch (\Throwable $e) {
            log_message("warning", "Grupo Donato: iara_core.set_current_unit indisponível, usando fallback local. " . $e->getMessage());
            $this->iara_available = false;
            return false;
        }
    }

    public function unidades()
    {
        if (!$this->arquitetura_disponivel()) {
            return [];
        }

        try {
            return $this->db->query("SELECT * FROM dbo.unidades ORDER BY unidade_nome ASC")->getResult();
        } catch (\Throwable $e) {
            $this->iara_available = false;
            return [];
        }
    }

    public function unidade_padrao()
    {
        if (!$this->arquitetura_disponivel()) {
            return null;
        }

        try {
            return $this->db->query("SELECT * FROM dbo.contexto_unidade_padrao LIMIT 1")->getRow();
        } catch (\Throwable $e) {
            $this->iara_available = false;
            return null;
        }
    }

    public function unidade_selecionada()
    {
        if (!$this->arquitetura_disponivel()) {
            return null;
        }

        try {
            return $this->db->query("SELECT * FROM dbo.contexto_unidade_selecionada LIMIT 1")->getRow();
        } catch (\Throwable $e) {
            $this->iara_available = false;
            return null;
        }
    }

    public function tabela_ou_view_existe($nome)
    {
        try {
            $this->db->query("SELECT 1 FROM " . $nome . " LIMIT 1")->getRow();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function listar_view($nome, $limit = 100)
    {
        if (!$this->tabela_ou_view_existe($nome)) {
            return [];
        }

        try {
            return $this->db->query("SELECT * FROM " . $nome . " LIMIT " . (int) $limit)->getResultArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function person_unit_access($user_id, $unit_id)
    {
        if (!$this->tabela_ou_view_existe("iara_core.person_unit_access")) {
            return [];
        }

        try {
            return $this->db->query("SELECT * FROM iara_core.person_unit_access WHERE user_id=" . (int) $user_id . " AND unit_id=" . (int) $unit_id . " LIMIT 10")->getResult();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
