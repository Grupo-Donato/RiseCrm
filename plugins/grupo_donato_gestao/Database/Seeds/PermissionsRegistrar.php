<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Seeds;

use grupo_donato_gestao\Config\Permissions;

/**
 * Integração das permissões do plugin com o mecanismo NATIVO de papéis do Rise.
 *
 * As permissões não têm tabela própria: são chaves no array serializado de
 * `roles.permissions`. Esta classe é o ponto único que:
 *  - renderiza os checkboxes na tela de papéis (hook app_hook_role_permissions_extension);
 *  - persiste os valores postados (filtro app_filter_role_permissions_save_data).
 */
class PermissionsRegistrar
{
    /** Renderiza a seção de permissões do plugin na tela de papéis. */
    public static function render_extension(): void
    {
        $current = self::current_role_permissions();
        $groups = Permissions::groups();

        echo "<li>";
        echo "<span data-feather='shield' class='icon-14 ml-20'></span>";
        echo "<h5>" . app_lang("gd_app_title") . "</h5>";

        foreach ($groups as $group_key => $items) {
            echo "<div class='mb10 mt10'><strong>" . app_lang($group_key) . "</strong></div>";
            foreach ($items as $item) {
                $key = $item["key"];
                $checked = (bool) get_array_value($current, $key);
                echo "<div class='form-check'>";
                echo form_checkbox($key, "1", $checked, "id='$key' class='form-check-input'");
                echo "<label for='$key' class='form-check-label'>" . app_lang($item["label_key"]) . "</label>";
                echo "</div>";
            }
        }

        echo "</li>";
    }

    /**
     * Mescla os valores postados das permissões do plugin no array de permissões.
     *
     * @param array $permissions
     * @return array
     */
    public static function apply_to_save_data(array $permissions): array
    {
        $request = \Config\Services::request();
        foreach (Permissions::KEYS as $key) {
            $permissions[$key] = $request->getPost($key) ? "1" : "";
        }
        foreach (Permissions::MANAGE_IMPLIES_VIEW as $view => $manage) {
            if (!empty($permissions[$manage])) {
                $permissions[$view] = "1";
            }
        }
        foreach (Permissions::ADDITIONAL_VIEW_IMPLICATIONS as $view => $manage_keys) {
            foreach ($manage_keys as $manage) {
                if (!empty($permissions[$manage])) {
                    $permissions[$view] = "1";
                    break;
                }
            }
        }
        return $permissions;
    }

    /** Permissões já salvas do papel em edição (para pré-marcar os checkboxes). */
    private static function current_role_permissions(): array
    {
        $role_id = self::current_role_id();
        if (!$role_id) {
            return [];
        }
        try {
            $roles_model = model("App\\Models\\Roles_model");
            $role = $roles_model->get_one($role_id);
            if ($role && !empty($role->permissions)) {
                $permissions = unserialize($role->permissions, ["allowed_classes" => false]);
                return is_array($permissions) ? $permissions : [];
            }
        } catch (\Throwable $e) {
            log_message("error", "GD PermissionsRegistrar: " . $e->getMessage());
        }
        return [];
    }

    private static function current_role_id(): int
    {
        try {
            $segments = \Config\Services::request()->getUri()->getSegments();
            $idx = array_search("permissions", $segments, true);
            if ($idx !== false && isset($segments[$idx + 1]) && is_numeric($segments[$idx + 1])) {
                return (int) $segments[$idx + 1];
            }
        } catch (\Throwable $e) {
            // ignora — renderiza desmarcado
        }
        return 0;
    }
}
