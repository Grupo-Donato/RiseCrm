<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Grupo Donato — Cobrança
Plugin URL: https://grupodonato.local
Description: Orquestra cobranças PIX rastreáveis e cartão recorrente sobre o financeiro do Grupo Donato.
Version: 0.1.0
Requires at least: 3.9.6
Author: Grupo Donato
*/

use grupo_donato_cobranca\Config\Constants;
use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Database\Installer;
use grupo_donato_cobranca\Database\PermissionsRegistrar;
use grupo_donato_cobranca\Services\AutomationService;

if (defined('GDC_PLUGIN_LOADED')) {
    return;
}
define('GDC_PLUGIN_LOADED', true);

if (function_exists('service')) {
    try {
        \Config\Services::autoloader()->addNamespace('grupo_donato_cobranca', __DIR__);
    } catch (\Throwable $e) {
        log_message('error', 'GDC autoload: ' . $e->getMessage());
    }
}

if (!function_exists('gdc_current_login_user')) {
    function gdc_current_login_user()
    {
        static $user = null;
        static $loaded = false;
        if ($loaded) {
            return $user;
        }
        $loaded = true;
        try {
            $users = model('App\\Models\\Users_model');
            $id = $users->login_user_id();
            if (!$id) {
                return null;
            }
            $user = $users->get_access_info($id);
            if ($user && is_string($user->permissions ?? null)) {
                $permissions = @unserialize($user->permissions, ['allowed_classes' => false]);
                $user->permissions = is_array($permissions) ? $permissions : [];
            } elseif ($user && !is_array($user->permissions ?? null)) {
                $user->permissions = [];
            }
            return $user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    function gdc_user_can(string $permission): bool
    {
        $user = gdc_current_login_user();
        return $user ? Permissions::can($user, $permission) : false;
    }

    function gdc_left_menu($menu)
    {
        $user = gdc_current_login_user();
        if (!$user || !Permissions::can($user, Permissions::VIEW)) {
            return $menu;
        }
        if (isset($menu['locacoes'])) {
            return $menu;
        }
        $menu['cobranca'] = [
            'name' => 'Cobrança',
            'language_key' => 'gdc_app_title',
            'url' => get_uri('cobranca'),
            'class' => 'credit-card',
            'is_custom_menu_item' => true,
            'position' => 13,
        ];
        return $menu;
    }

    function gdc_install($purchaseCode = null): void
    {
        try {
            (new Installer())->install();
            log_message('notice', 'GDC install/update concluído.');
        } catch (\Throwable $e) {
            log_message('error', 'GDC install: ' . $e->getMessage());
            throw new \RuntimeException('Não foi possível instalar o módulo Cobrança.', 0, $e);
        }
    }

    function gdc_uninstall(): void
    {
        log_message('notice', 'GDC uninstall: tabelas e dados preservados.');
    }
}

if (function_exists('service')) {
    require __DIR__ . '/Config/Routes.php';
}

app_hooks()->add_filter('app_filter_staff_left_menu', 'gdc_left_menu');
app_hooks()->add_filter('app_filter_app_csrf_exclude_uris', static function ($uris) {
    $uris[] = 'cobranca/webhook.*+';
    return $uris;
});
app_hooks()->add_action('app_hook_role_permissions_extension', static function () {
    PermissionsRegistrar::render();
});
app_hooks()->add_filter('app_filter_role_permissions_save_data', static function ($permissions) {
    return PermissionsRegistrar::save(is_array($permissions) ? $permissions : []);
});
app_hooks()->add_action('app_hook_after_cron_run', static function () {
    AutomationService::run();
});

register_installation_hook(Constants::PLUGIN_FOLDER, 'gdc_install');
register_activation_hook(Constants::PLUGIN_FOLDER, 'gdc_install');
register_update_hook(Constants::PLUGIN_FOLDER, 'gdc_install');
register_uninstallation_hook(Constants::PLUGIN_FOLDER, 'gdc_uninstall');
