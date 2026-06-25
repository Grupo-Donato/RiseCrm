<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use App\Controllers\Security_Controller;
use grupo_donato_gestao\Services\AccessService;
use grupo_donato_gestao\Services\UnitContextService;
use grupo_donato_gestao\Services\AuditService;

/**
 * Controller base do plugin (fino).
 *
 * Estende o Security_Controller do Rise (login obrigatório) e disponibiliza:
 *  - serviços de acesso, contexto de unidade e auditoria;
 *  - render de views do plugin (página completa e fragmento/modal);
 *  - respostas JSON padronizadas {success, message, data};
 *  - request_id propagável.
 *
 * NÃO contém regra de domínio (essa fica nos Services).
 */
abstract class Gd_Controller extends Security_Controller
{
    protected AccessService $access;
    protected UnitContextService $unit_context;
    protected AuditService $audit;
    protected string $request_id;

    public function __construct()
    {
        parent::__construct();

        // somente equipe interna acessa a área administrativa do plugin
        $this->access_only_team_members();

        $this->access = new AccessService($this->login_user);
        $this->unit_context = new UnitContextService($this->login_user);
        $this->audit = new AuditService($this->login_user);
        $this->request_id = AuditService::request_id();
    }

    /** Carrega um model do plugin pelo nome curto. */
    protected function gd_model(string $short_name)
    {
        return model("grupo_donato_gestao\\Models\\" . $short_name);
    }

    /** Converte "dashboard/index" em "grupo_donato_gestao\Views\dashboard\index". */
    private function view_path(string $view): string
    {
        return "grupo_donato_gestao\\Views\\" . str_replace("/", "\\", $view);
    }

    /** Render de página completa (com layout do Rise). */
    protected function gd_render(string $view, array $data = [])
    {
        return $this->template->rander($this->view_path($view), $data);
    }

    /** Render de fragmento/modal (sem layout). */
    protected function gd_view(string $view, array $data = [])
    {
        return $this->template->view($this->view_path($view), $data);
    }

    protected function json_success(string $message = "", array $extra = []): void
    {
        echo json_encode(array_merge(["success" => true, "message" => $message], $extra));
    }

    protected function json_error(string $message = "", array $extra = []): void
    {
        echo json_encode(array_merge(["success" => false, "message" => $message], $extra));
    }

    protected function active_unit_id(): ?int
    {
        return $this->unit_context->get_active_unit_id();
    }

    /** ID do usuário logado (para created_by/updated_by). */
    protected function user_id(): int
    {
        return isset($this->login_user->id) ? (int) $this->login_user->id : 0;
    }

    protected function record_exists($record): bool
    {
        return (bool) ($record && isset($record->id) && (int) $record->id > 0 && (!isset($record->deleted) || (int) $record->deleted === 0));
    }

    protected function escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
    }

    protected function gd_fail(\Throwable $e): void
    {
        $key = $e->getMessage();
        $this->json_error(str_starts_with($key, "gd_") ? app_lang($key) : app_lang("error_occurred"));
    }
}
