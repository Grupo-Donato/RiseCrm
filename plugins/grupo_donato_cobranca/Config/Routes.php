<?php

if (defined('GRUPO_DONATO_COBRANCA_ROUTES_LOADED')) {
    return;
}
define('GRUPO_DONATO_COBRANCA_ROUTES_LOADED', true);

if (!isset($routes)) {
    $routes = \Config\Services::routes(true);
}

$routes->group('cobranca', [
    'namespace' => 'grupo_donato_cobranca\\Controllers',
    'filter' => 'csrf',
], static function ($routes) {
    $routes->get('/', 'Billing::index');

    $routes->get('charges', 'Billing::charges');
    $routes->post('charges/data', 'Billing::charges_data');
    $routes->post('charges/modal', 'Billing::charge_modal');
    $routes->post('charges/create', 'Billing::create_charge');
    $routes->get('charges/view/(:num)', 'Billing::view_charge/$1');
    $routes->post('charges/sync', 'Billing::sync_charge');
    $routes->post('charges/cancel', 'Billing::cancel_charge');

    $routes->get('subscriptions', 'Subscriptions::index');
    $routes->post('subscriptions/data', 'Subscriptions::data');
    $routes->post('subscriptions/modal', 'Subscriptions::modal');
    $routes->post('subscriptions/save', 'Subscriptions::save');
    $routes->post('subscriptions/status', 'Subscriptions::status');

    $routes->get('payment-methods', 'Payment_methods::index');
    $routes->post('payment-methods/data', 'Payment_methods::data');
    $routes->post('payment-methods/session', 'Payment_methods::session');
    $routes->post('payment-methods/deactivate', 'Payment_methods::deactivate');

    $routes->get('settings', 'Settings::index');
    $routes->post('settings/save', 'Settings::save');
    $routes->post('settings/health', 'Settings::health');
});

// Endpoint público: a assinatura e a autenticidade são obrigatoriamente validadas pelo conector.
$routes->post('cobranca/webhook/(:segment)', 'Webhook::receive/$1', [
    'namespace' => 'grupo_donato_cobranca\\Controllers',
]);
