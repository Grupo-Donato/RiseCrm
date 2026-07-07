<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Permissions;

/**
 * Porta única de autorização do plugin.
 *
 * Integra-se às permissões nativas do Rise: admin tem acesso total; demais
 * usuários precisam da chave de permissão correspondente em
 * `login_user->permissions`. Centraliza a decisão para não espalhar checagens
 * inconsistentes pelos controllers (erro recorrente no sistema legado).
 */
class AccessService
{
    private object $login_user;

    public function __construct(object $login_user)
    {
        $this->login_user = $login_user;
    }

    public function is_admin(): bool
    {
        return !empty($this->login_user->is_admin);
    }

    public function can(string $permission_key): bool
    {
        if ($this->is_admin()) {
            return true;
        }
        if (($this->login_user->user_type ?? "") !== "staff") {
            return false;
        }

        if (RoleAccessService::is_full_access_role($this->login_user)) {
            return true;
        }

        if (RoleAccessService::is_professor($this->login_user)) {
            return false;
        }

        $permissions = $this->login_user->permissions ?? [];
        if ((bool) get_array_value($permissions, $permission_key)) {
            return true;
        }

        $implied_by = Permissions::impliedBy($permission_key);
        if ($implied_by && (bool) get_array_value($permissions, $implied_by)) {
            return true;
        }
        foreach (Permissions::additionallyImpliedBy($permission_key) as $additional) {
            if ((bool) get_array_value($permissions, $additional)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string> $keys
     */
    public function can_any(array $keys): bool
    {
        foreach ($keys as $key) {
            if ($this->can($key)) {
                return true;
            }
        }
        return false;
    }

    /** O usuário pode ver QUALQUER módulo do plugin? (para exibir o menu) */
    public function can_see_any_module(): bool
    {
        return $this->can_any(Permissions::KEYS);
    }

    /**
     * Exige a permissão; caso contrário responde 403 (JSON em AJAX, redirect
     * em navegação) e encerra a request.
     */
    public function require(string $permission_key): void
    {
        if ($this->can($permission_key)) {
            return;
        }

        $request = \Config\Services::request();
        if ($request->isAJAX() || strtolower((string) $request->getMethod()) === "post") {
            http_response_code(403);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "success" => false,
                "message" => app_lang("gd_access_denied"),
                "error_code" => "gd_access_denied",
            ]);
            exit();
        }

        app_redirect("forbidden");
    }

    /** @param array<string> $permission_keys */
    public function require_any(array $permission_keys): void
    {
        if ($this->can_any($permission_keys)) {
            return;
        }

        $request = \Config\Services::request();
        if ($request->isAJAX() || strtolower((string) $request->getMethod()) === "post") {
            http_response_code(403);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "success" => false,
                "message" => app_lang("gd_access_denied"),
                "error_code" => "gd_access_denied",
            ]);
            exit();
        }

        app_redirect("forbidden");
    }
}
