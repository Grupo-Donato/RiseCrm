<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use grupo_donato_cobranca\Config\Permissions;
use grupo_donato_cobranca\Services\ConnectorRegistry;
use grupo_donato_cobranca\Services\SettingsService;

final class Settings extends Billing_Controller
{
    private SettingsService $service;

    public function __construct()
    {
        parent::__construct();
        $this->access->require(Permissions::SETTINGS);
        $this->service = new SettingsService($this->unitId);
    }

    public function index()
    {
        return $this->render('settings/index', [
            'settings' => $this->service->all(),
            'accounts' => $this->service->financialAccounts(),
        ]);
    }

    public function save()
    {
        try {
            $this->access->require(Permissions::SETTINGS);
            $this->service->save([
                'provider_code' => $this->request->getPost('provider_code'),
                'environment' => $this->request->getPost('environment'),
                'financial_account_id' => $this->request->getPost('financial_account_id'),
                'automatic_billing' => $this->request->getPost('automatic_billing'),
                'connector_label' => $this->request->getPost('connector_label'),
            ], $this->actorId());
            $this->success(app_lang('record_saved'));
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }

    public function health()
    {
        try {
            $this->access->require(Permissions::SETTINGS);
            $settings = $this->service->all();
            $provider = strtolower(trim((string) $settings['provider_code']));
            if ($provider === '') {
                throw new \DomainException('gdc_connector_not_configured');
            }
            $result = ConnectorRegistry::get($provider)->healthCheck([
                'unit_id' => $this->unitId,
                'environment' => (string) $settings['environment'],
                'financial_account_id' => (int) $settings['financial_account_id'],
            ]);
            if (empty($result['success'])) {
                throw new \DomainException('gdc_connector_health_failed');
            }
            $this->success((string) ($result['message'] ?? app_lang('gdc_connector_health_ok')), ['details' => $result['details'] ?? []]);
        } catch (\Throwable $e) {
            $this->fail($e);
        }
    }
}
