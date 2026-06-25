<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Resolve a unidade ativa de forma segura.
 *
 * Regras (corrigindo a falha de "unidade só em sessão" do sistema legado):
 *  - a unidade ativa fica em sessão, mas é SEMPRE revalidada no backend;
 *  - um unit_id arbitrário vindo do navegador é IGNORADO; trocar de unidade
 *    passa por set_active_unit(), que valida existência/ativação/acesso;
 *  - há suporte a unidade padrão.
 *
 * Multiunidade real (ACL por usuário×unidade) fica para fase futura; nesta fase
 * todo usuário com acesso ao plugin pode operar a unidade ativa.
 */
class UnitContextService
{
    private const SESSION_KEY = "gd_active_unit_id";

    private $units_model;
    private $session;
    private ?object $login_user;

    public function __construct(?object $login_user = null)
    {
        $this->units_model = model("grupo_donato_gestao\\Models\\Gd_units_model");
        $this->session = \Config\Services::session();
        $this->login_user = $login_user;
    }

    /**
     * Unidade ativa validada. Se a sessão apontar para uma unidade inexistente
     * ou inativa, volta para a unidade padrão.
     */
    public function get_active_unit(): ?object
    {
        $session_id = (int) $this->session->get(self::SESSION_KEY);

        if ($session_id) {
            $unit = $this->units_model->get_one($session_id);
            if ($unit && isset($unit->id) && $unit->id && $this->user_can_access_unit((int) $unit->id)) {
                return $unit;
            }
            // sessão inválida → limpar
            $this->session->remove(self::SESSION_KEY);
        }

        $default = $this->units_model->get_default();
        if ($default && $this->user_can_access_unit((int) $default->id)) {
            return $default;
        }

        // sem unidade padrão: primeira ativa
        $first = $this->units_model->get_details([
            "where" => ["status" => Constants::STATUS_ACTIVE],
            "order_by" => "id",
            "order_dir" => "ASC",
            "limit" => 1,
        ]);
        return $first ? $first->getRow() : null;
    }

    public function get_active_unit_id(): ?int
    {
        $unit = $this->get_active_unit();
        return $unit && isset($unit->id) && $unit->id ? (int) $unit->id : null;
    }

    /**
     * Troca a unidade ativa após validar. Nunca confia no valor sem checar.
     */
    public function set_active_unit(int $unit_id): bool
    {
        $unit = $this->units_model->get_one($unit_id);
        if (!$this->is_active_unit($unit)) {
            return false;
        }
        if (!$this->user_can_access_unit($unit_id)) {
            return false;
        }
        $this->session->set(self::SESSION_KEY, (int) $unit->id);
        return true;
    }

    /**
     * Acesso à unidade. Nesta fase: unidade existente e ativa é acessível por
     * qualquer usuário autorizado no plugin (ACL por unidade fica para depois).
     */
    public function user_can_access_unit(int $unit_id): bool
    {
        $unit = $this->units_model->get_one($unit_id);
        if (!$this->is_active_unit($unit)) {
            return false;
        }
        return true;
    }

    private function is_active_unit($unit): bool
    {
        return (bool) ($unit
            && isset($unit->id)
            && $unit->id
            && (int) $unit->deleted === 0
            && (string) $unit->status === Constants::STATUS_ACTIVE);
    }
}
