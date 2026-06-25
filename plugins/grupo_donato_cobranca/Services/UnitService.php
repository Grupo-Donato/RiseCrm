<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class UnitService
{
    public static function activeUnitId(?object $user = null): int
    {
        if (class_exists('grupo_donato_gestao\\Services\\UnitContextService')) {
            $id = (new \grupo_donato_gestao\Services\UnitContextService($user))->get_active_unit_id();
            if ($id) {
                return (int) $id;
            }
        }
        throw new \DomainException('gdc_dependency_missing');
    }

    public static function dependencyReady(): bool
    {
        try {
            $db = db_connect();
            return class_exists('grupo_donato_gestao\\Services\\FinanceService')
                && $db->tableExists($db->prefixTable('gd_receivables'))
                && $db->tableExists($db->prefixTable('gd_customer_accounts'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function __construct() {}
}
