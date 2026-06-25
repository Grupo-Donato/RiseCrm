<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Config;

final class Constants
{
    public const PLUGIN_FOLDER = 'grupo_donato_cobranca';
    public const ROUTE_PREFIX = 'cobranca';
    public const PLUGIN_VERSION = '0.1.0';
    public const SCHEMA_VERSION = '001';

    public const COLLECTION_METHODS = ['pix', 'credit_card'];
    public const CHARGE_STATUSES = [
        'processing', 'pending', 'paid', 'partially_paid', 'failed',
        'expired', 'cancelled', 'refunded', 'review',
    ];
    public const ACTIVE_CHARGE_STATUSES = ['processing', 'pending', 'partially_paid'];
    public const SUBSCRIPTION_STATUSES = ['active', 'paused', 'cancelled'];
    public const PAYMENT_METHOD_STATUSES = ['active', 'inactive', 'expired'];
    public const SOURCE_TYPES = ['enrollment', 'court_rental', 'manual', 'other'];

    private function __construct() {}
}
