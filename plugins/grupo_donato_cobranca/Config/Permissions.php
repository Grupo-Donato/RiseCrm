<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Config;

final class Permissions
{
    public const VIEW = 'gdc_billing_view';
    public const MANAGE = 'gdc_billing_manage';
    public const SETTINGS = 'gdc_billing_settings';

    public const KEYS = [self::VIEW, self::MANAGE, self::SETTINGS];

    public static function can(object $user, string $key): bool
    {
        if (!empty($user->is_admin)) {
            return true;
        }
        if (($user->user_type ?? '') !== 'staff') {
            return false;
        }
        $permissions = is_array($user->permissions ?? null) ? $user->permissions : [];
        if (!empty($permissions[$key])) {
            return true;
        }
        return $key === self::VIEW && (!empty($permissions[self::MANAGE]) || !empty($permissions[self::SETTINGS]));
    }

    public static function require(object $user, string $key): void
    {
        if (self::can($user, $key)) {
            return;
        }
        $request = \Config\Services::request();
        if ($request->isAJAX() || strtolower((string) $request->getMethod()) === 'post') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => app_lang('gdc_access_denied')]);
            exit;
        }
        app_redirect('forbidden');
    }

    private function __construct() {}
}
