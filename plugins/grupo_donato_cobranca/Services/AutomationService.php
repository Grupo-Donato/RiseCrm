<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class AutomationService
{
    public static function run(): void
    {
        if (!UnitService::dependencyReady()) {
            return;
        }
        try {
            $db = db_connect();
            $units = $db->table($db->prefixTable('gd_units'))->select('id')->where('status', 'active')->where('deleted', 0)->get()->getResult();
            foreach ($units as $unit) {
                (new SubscriptionService((int) $unit->id))->processAutomatic(50);
            }
        } catch (\Throwable $e) {
            log_message('error', 'GDC automation: ' . $e->getMessage());
        }
    }

    private function __construct() {}
}
