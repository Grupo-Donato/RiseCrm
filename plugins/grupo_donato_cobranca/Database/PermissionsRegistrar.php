<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Database;

use grupo_donato_cobranca\Config\Permissions;

final class PermissionsRegistrar
{
    public static function render(): void
    {
        $current = self::currentPermissions();
        echo '<li><span data-feather="credit-card" class="icon-14 ml-20"></span><h5>' . app_lang('gdc_app_title') . '</h5>';
        foreach ([
            Permissions::VIEW => 'gdc_permission_view',
            Permissions::MANAGE => 'gdc_permission_manage',
            Permissions::SETTINGS => 'gdc_permission_settings',
        ] as $key => $label) {
            echo '<div class="form-check">';
            echo form_checkbox($key, '1', !empty($current[$key]), "id='$key' class='form-check-input'");
            echo '<label for="' . $key . '" class="form-check-label">' . app_lang($label) . '</label></div>';
        }
        echo '</li>';
    }

    public static function save(array $permissions): array
    {
        $request = \Config\Services::request();
        foreach (Permissions::KEYS as $key) {
            $permissions[$key] = $request->getPost($key) ? '1' : '';
        }
        if (!empty($permissions[Permissions::MANAGE]) || !empty($permissions[Permissions::SETTINGS])) {
            $permissions[Permissions::VIEW] = '1';
        }
        return $permissions;
    }

    private static function currentPermissions(): array
    {
        try {
            $segments = \Config\Services::request()->getUri()->getSegments();
            $index = array_search('permissions', $segments, true);
            $roleId = ($index !== false && isset($segments[$index + 1]) && is_numeric($segments[$index + 1])) ? (int) $segments[$index + 1] : 0;
            if (!$roleId) {
                return [];
            }
            $role = model('App\\Models\\Roles_model')->get_one($roleId);
            if ($role && !empty($role->permissions)) {
                $data = unserialize($role->permissions, ['allowed_classes' => false]);
                return is_array($data) ? $data : [];
            }
        } catch (\Throwable $e) {
            log_message('error', 'GDC permissions: ' . $e->getMessage());
        }
        return [];
    }

    private function __construct() {}
}
