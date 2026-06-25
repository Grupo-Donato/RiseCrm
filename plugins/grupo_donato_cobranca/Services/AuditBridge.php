<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class AuditBridge
{
    public static function log(?object $user, int $unitId, string $action, string $entity, int $entityId, ?array $before, ?array $after, ?array $metadata = null): void
    {
        if (!class_exists('grupo_donato_gestao\\Services\\AuditService')) {
            return;
        }
        try {
            (new \grupo_donato_gestao\Services\AuditService($user))->log(
                $action,
                'billing_' . $entity,
                $entityId,
                self::sanitize($before),
                self::sanitize($after),
                self::sanitize($metadata),
                $unitId
            );
        } catch (\Throwable $e) {
            log_message('error', 'GDC audit: ' . $e->getMessage());
        }
    }

    private static function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        foreach ($data as $key => $value) {
            $lower = strtolower((string) $key);
            if (str_contains($lower, 'token') || str_contains($lower, 'secret') || str_contains($lower, 'payment_method_ref') || str_contains($lower, 'pix_copy')) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }
        return $data;
    }

    private function __construct() {}
}
