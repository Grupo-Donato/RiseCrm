<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

use grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface;

final class ConnectorRegistry
{
    public static function get(string $providerCode): BillingConnectorInterface
    {
        $providerCode = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $providerCode));
        $connector = new NullBillingConnector($providerCode);
        if (function_exists('app_hooks')) {
            $resolved = app_hooks()->apply_filters('gdc_filter_billing_connector_' . $providerCode, $connector);
            if ($resolved instanceof BillingConnectorInterface) {
                return $resolved;
            }
        }
        return $connector;
    }

    private function __construct() {}
}
