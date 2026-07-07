<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Plugin Name: Grupo Donato — Gestão
Plugin URL: https://grupodonato.local
Description: Gestão integrada de cadastro, agenda, locações, escola, personal e financeiro básico (até a Fase 5).
Version: 0.9.6
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
use grupo_donato_gestao\Services\RoleAccessService;

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

    /** Checa acesso ao plugin de cobrança sem criar dependência dura entre plugins. */
    function gd_cobranca_can_view($user): bool
    {
        if (!$user) {
            return false;
        }

        if (RoleAccessService::has_full_plugin_access($user)) {
            return true;
        }

        if (RoleAccessService::is_professor($user)) {
            return false;
        }

        if (class_exists('grupo_donato_cobranca\\Config\\Permissions')) {
            try {
                return \grupo_donato_cobranca\Config\Permissions::can($user, \grupo_donato_cobranca\Config\Permissions::VIEW);
            } catch (\Throwable $e) {
                return false;
            }
        }

        $permissions = $user->permissions ?? [];
        if (is_string($permissions)) {
            $unserialized = @unserialize($permissions, ['allowed_classes' => false]);
            $permissions = is_array($unserialized) ? $unserialized : [];
        }
        if (!is_array($permissions)) {
            return false;
        }

        return !empty($permissions['gdc_billing_view'])
            || !empty($permissions['gdc_billing_manage'])
            || !empty($permissions['gdc_billing_settings']);
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

        $can_calendar = $can('gd_calendar_view');
        $can_bookings = $can('gd_bookings_view');
        $can_booking_series = $can('gd_booking_series_view');
        $can_court_rentals = $can('gd_court_rentals_view');
        $can_school = $can('gd_school_view');
        $can_finance = $can('gd_finance_view');
        $can_billing = gd_cobranca_can_view($user);

        if (!$can_calendar && !$can_bookings && !$can_booking_series && !$can_court_rentals && !$can_school && !$can_finance && !$can_billing) {
            return $sidebar_menu; // sem permissão → sem menu
        }

        // Protótipo: menu principal enxuto, preservando escola/caixa fora das locações.
        $submenu = [];
        if ($can_school) {
            $submenu[] = ["name" => "customers_students", "language_key" => "gd_menu_customers_students", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/school/students"), "class" => "users"];
            $submenu[] = ["name" => "classes_personal", "language_key" => "gd_menu_classes_personal", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/school/classes"), "class" => "layers"];
        }
        if ($can_finance) {
            $submenu[] = ["name" => "cash_expenses", "language_key" => "gd_menu_cash_expenses", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/finance/cash"), "class" => "book"];
        }

        if ($submenu) {
            $landing_uri = $can_school ? "grupo_donato/school/students" : "grupo_donato/finance/cash";
            $sidebar_menu['grupo_donato'] = [
                "name" => "Grupo Donato",
                "language_key" => "gd_app_title",
                "class" => "activity",
                "is_custom_menu_item" => true,
                "url" => get_uri($landing_uri),
                "position" => 12,
                "submenu" => $submenu,
            ];
        }

        // A navegação de locações foi consolidada em três pontos de trabalho.
        // Reservas, ocupações e séries continuam existindo tecnicamente, mas
        // aparecem como abas da mesma tela para evitar itens duplicados.
        $rental_submenu = [];
        if ($can_calendar) {
            $rental_submenu[] = ["name" => "rental_agenda", "language_key" => "gd_menu_rental_agenda", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/calendar"), "class" => "calendar"];
        }
        if ($can_court_rentals || $can_bookings || $can_booking_series) {
            $reservations_uri = $can_court_rentals
                ? "grupo_donato/court-rentals"
                : ($can_bookings ? "grupo_donato/bookings" : "grupo_donato/booking-series");
            $rental_submenu[] = ["name" => "rental_bookings", "language_key" => "gd_menu_rental_bookings", "is_custom_menu_item" => true, "url" => get_uri($reservations_uri), "class" => "clipboard"];
        }
        if ($can_court_rentals) {
            $rental_submenu[] = ["name" => "rental_monthly", "language_key" => "gd_menu_rental_monthly", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/court-rentals/monthly"), "class" => "repeat"];
        }
        if ($can_finance) {
            $rental_submenu[] = ["name" => "rental_finance", "language_key" => "gd_menu_rental_finance", "is_custom_menu_item" => true, "url" => get_uri("grupo_donato/finance/receivables?source_type=court_rental"), "class" => "file-text"];
        }
        if ($can_billing) {
            $rental_submenu[] = ["name" => "rental_charges", "language_key" => "gd_menu_rental_charges", "is_custom_menu_item" => true, "url" => get_uri("cobranca/charges"), "class" => "credit-card"];
        }

        if ($rental_submenu) {
            $sidebar_menu['locacoes'] = [
                "name" => "Locações",
                "language_key" => "gd_menu_rentals",
                "class" => "grid",
                "is_custom_menu_item" => true,
                "url" => $rental_submenu[0]["url"],
                "position" => 13,
                "submenu" => $rental_submenu,
            ];
            unset($sidebar_menu['cobranca']);
        }

        return $sidebar_menu;
    }

    /** Aba de configurações no painel de Settings do Rise. */
    function gd_settings_menu($settings_menu)
    {
        $user = gd_current_login_user();
        if (!$user) {
            return $settings_menu;
        }
        $access = new AccessService($user);
        if (!$access->can('gd_settings_view') && !$access->can('gd_settings_manage')) {
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
