<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Grupo Donato — Gestão
Plugin URL: https://grupodonato.local
Description: Gestão integrada de cadastro, agenda, locações, escola, personal e financeiro básico (até a Fase 5).
Version: 0.9.1
Requires at least: 3.9.6
Author: Grupo Donato
*/

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Config\Permissions;
use grupo_donato_gestao\Database\Schema\SchemaRunner;
use grupo_donato_gestao\Database\Seeds\FoundationSeeder;
use grupo_donato_gestao\Database\Seeds\PermissionsRegistrar;
use grupo_donato_gestao\Services\AccessService;
use grupo_donato_gestao\Services\AuditService;

// evita execução dupla no mesmo request
if (defined('GD_PLUGIN_LOADED')) {
    return;
}
define('GD_PLUGIN_LOADED', true);

// garante o autoload do namespace do plugin mesmo durante a instalação,
// quando a pasta ainda não consta em activated_plugins.json (Autoload do Rise).
if (function_exists('service')) {
    try {
        \Config\Services::autoloader()->addNamespace('grupo_donato_gestao', __DIR__);
    } catch (\Throwable $e) {
        log_message('error', 'GD: falha ao registrar namespace: ' . $e->getMessage());
    }
}

if (!function_exists('gd_current_login_user')) {

    /** Usuário logado (com permissions já desserializadas) ou null. */
    function gd_current_login_user()
    {
        static $cached = null;
        static $done = false;
        if ($done) {
            return $cached;
        }
        $done = true;

        try {
            $users = model('App\\Models\\Users_model');
            $uid = $users->login_user_id();
            if (!$uid) {
                return $cached = null;
            }
            $user = $users->get_access_info($uid);
            if ($user) {
                if (!empty($user->permissions) && is_string($user->permissions)) {
                    $perms = @unserialize($user->permissions, ["allowed_classes" => false]);
                    $user->permissions = is_array($perms) ? $perms : [];
                } else if (empty($user->permissions)) {
                    $user->permissions = [];
                }
            }
            return $cached = $user;
        } catch (\Throwable $e) {
            return $cached = null;
        }
    }

    /** Constrói o item de menu do plugin filtrado por permissão. */
    function gd_left_menu($sidebar_menu)
    {
        $user = gd_current_login_user();
        if (!$user || ($user->user_type ?? '') !== 'staff') {
            return $sidebar_menu;
        }

        $access = new AccessService($user);
        $can = static fn($key) => $access->can($key);

        // Protótipo: submenu enxuto operacional. A pedido, foram removidos do menu:
        // Visão geral, Financeiro, Presença e Configurações — todos continuam acessíveis
        // por URL/abas internas (e Configurações também pelo painel de Settings do Rise).
        $can_calendar = $can('gd_calendar_view');
        $can_court_rentals = $can('gd_court_rentals_view');
        $can_school = $can('gd_school_view');
        $can_finance = $can('gd_finance_view');

        if (!$can_calendar && !$can_court_rentals && !$can_school && !$can_finance) {
            return $sidebar_menu; // sem permissão → sem menu
        }

        $submenu = [];
        if ($can_school) {
            $submenu[] = ["name" => "customers_students", "language_key" => "gd_menu_customers_students", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/school/students"), "class" => "users"];
            $submenu[] = ["name" => "classes_personal", "language_key" => "gd_menu_classes_personal", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/school/classes"), "class" => "layers"];
        }
        if ($can_calendar) {
            $submenu[] = ["name" => "agenda", "language_key" => "gd_menu_agenda", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/calendar"), "class" => "calendar"];
        }
        if ($can_court_rentals) {
            $submenu[] = ["name" => "court_monthly", "language_key" => "gd_menu_court_monthly", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/court-rentals/monthly"), "class" => "dollar-sign"];
        }
        if ($can_finance) {
            $submenu[] = ["name" => "cash_expenses", "language_key" => "gd_menu_cash_expenses", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/finance/cash"), "class" => "book"];
        }

        $landing_uri = $can_school ? "grupo_donato/school/students" : ($can_calendar ? "grupo_donato/calendar" : ($can_court_rentals ? "grupo_donato/court-rentals/monthly" : ($can_finance ? "grupo_donato/finance/cash" : "grupo_donato/school/students")));
        $sidebar_menu['grupo_donato'] = [
            "name" => "Grupo Donato",
            "language_key" => "gd_app_title",
            "class" => "activity",
            "is_custom_menu_item" => true,
            "url" => get_uri($landing_uri),
            "position" => 12,
            "submenu" => $submenu,
        ];

        return $sidebar_menu;
    }

    /** Aba de configurações no painel de Settings do Rise. */
    function gd_settings_menu($settings_menu)
    {
        $user = gd_current_login_user();
        if (!$user) {
            return $settings_menu;
        }
        $is_admin = !empty($user->is_admin);
        $perms = is_array($user->permissions ?? null) ? $user->permissions : [];
        if (!($is_admin || get_array_value($perms, 'gd_settings_view') || get_array_value($perms, 'gd_settings_manage'))) {
            return $settings_menu;
        }

        $settings_menu['gd_app_title'] = [
            ["name" => "gd_settings_general", "url" => "grupo_donato/settings/general"],
        ];
        return $settings_menu;
    }

    /** Instala/atualiza: aplica schema, seeds e registra auditoria. */
    function gd_install($purchase_code = null)
    {
        if (!function_exists('db_connect')) {
            return;
        }

        try {
            if (version_compare(PHP_VERSION, '8.1', '<')) {
                log_message('error', 'GD install: requer PHP 8.1+ (atual ' . PHP_VERSION . ').');
                return;
            }

            $runner = new SchemaRunner();
            $result = $runner->run();

            if (!empty($result['skipped_lock'])) {
                throw new \RuntimeException('Schema runner indisponível: outra instalação está em andamento.');
            }
            if (!empty($result['failed'])) {
                throw new \RuntimeException('Falha ao aplicar a versão de schema ' . $result['failed'] . '.');
            }

            $actor = 0;
            try {
                $actor = (int) model('App\\Models\\Users_model')->login_user_id();
            } catch (\Throwable $e) {
                $actor = 0;
            }

            (new FoundationSeeder($actor))->run();
            (new \grupo_donato_gestao\Database\Seeds\CatalogSeeder($actor))->run();
            (new \grupo_donato_gestao\Database\Seeds\FinanceSeeder($actor))->run();

            // Módulo operacional embutido: cria/atualiza as tabelas do módulo Bombeiros (idempotente).
            if (function_exists('bombeiros_install_or_update')) {
                try { bombeiros_install_or_update(); } catch (\Throwable $e) { log_message('error', 'GD/Operacional install: ' . $e->getMessage()); }
            }

            try {
                (new AuditService(gd_current_login_user()))->log('install', 'plugin', null, null, [
                    "plugin_version" => Constants::PLUGIN_VERSION,
                    "schema_applied" => $result['ran'],
                ]);
            } catch (\Throwable $e) {
                // auditoria é best-effort durante a instalação
            }

            log_message('notice', 'GD install/update concluído. Versões aplicadas: ' . implode(',', $result['ran']));
        } catch (\Throwable $e) {
            log_message('error', 'GD install: erro inesperado: ' . $e->getMessage());
            throw new \RuntimeException('Não foi possível instalar/atualizar o Grupo Donato. Consulte os logs administrativos.', 0, $e);
        }
    }

    /** Desinstalação NÃO-destrutiva: preserva tabelas, dados e auditoria. */
    function gd_uninstall()
    {
        try {
            (new AuditService(gd_current_login_user()))->log('uninstall', 'plugin', null, null, [
                "note" => "Dados preservados; nenhuma tabela removida.",
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }
        log_message('notice', 'GD uninstall: hooks/menu desativados; dados e auditoria preservados (sem DROP TABLE).');
    }

    /** Expiração leve e limitada de holds após o cron nativo do Rise. */
    function gd_expire_booking_holds()
    {
        try {
            $db = db_connect();
            $units = $db->table($db->prefixTable('gd_units'))->select('id')->where('deleted', 0)->where('status', 'active')->get()->getResult();
            foreach ($units as $unit) {
                (new \grupo_donato_gestao\Services\BookingHoldService((int) $unit->id))->expireBatch(100);
            }
        } catch (\Throwable $e) {
            log_message('error', 'GD hold expiration: ' . $e->getMessage());
        }
    }

    /** Verificação barata por request: roda o schema apenas se houver pendência. */
    function gd_maybe_run_schema()
    {
        try {
            $marker = SchemaRunner::marker_path();
            $applied = is_file($marker) ? trim((string) @file_get_contents($marker)) : '';
            if ($applied === Constants::SCHEMA_TARGET) {
                return; // já atualizado → sem consulta ao banco
            }
            if (!function_exists('db_connect')) {
                return;
            }
            (new SchemaRunner())->run();
        } catch (\Throwable $e) {
            log_message('error', 'GD schema check: ' . $e->getMessage());
        }
    }
}

// rotas
if (function_exists('service')) {
    require __DIR__ . '/Config/Routes.php';
}

// Módulo operacional embutido (Bombeiros): registra menu próprio, exclusões CSRF, rotas e a
// função de instalação das tabelas. Integrado sob o sub-namespace
// grupo_donato_gestao\Operacional (painel operacional do Grupo Donato).
require __DIR__ . '/Operacional/bootstrap.php';

// menus
app_hooks()->add_filter('app_filter_staff_left_menu', 'gd_left_menu');
app_hooks()->add_filter('app_filter_admin_settings_menu', 'gd_settings_menu');
app_hooks()->add_action('app_hook_after_cron_run', 'gd_expire_booking_holds');

// permissões nativas (render + save)
app_hooks()->add_action('app_hook_role_permissions_extension', function () {
    PermissionsRegistrar::render_extension();
});
app_hooks()->add_filter('app_filter_role_permissions_save_data', function ($permissions) {
    return PermissionsRegistrar::apply_to_save_data(is_array($permissions) ? $permissions : []);
});

// ciclo de vida (o nome registrado = nome da pasta ativada em activated_plugins.json)
register_installation_hook(Constants::PLUGIN_FOLDER, 'gd_install');
register_activation_hook(Constants::PLUGIN_FOLDER, 'gd_install');
register_update_hook(Constants::PLUGIN_FOLDER, 'gd_install');
register_uninstallation_hook(Constants::PLUGIN_FOLDER, 'gd_uninstall');

// rede de segurança: aplica versões pendentes de schema (checagem barata por arquivo)
gd_maybe_run_schema();
