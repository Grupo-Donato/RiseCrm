<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Controllers;

use App\Controllers\BaseController;
use grupo_donato_cobranca\Services\ChargeService;
use grupo_donato_cobranca\Services\ConnectorRegistry;
use grupo_donato_cobranca\Services\PaymentMethodService;

final class Webhook extends BaseController
{
    public function receive($providerCode)
    {
        $providerCode = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $providerCode));
        $body = (string) $this->request->getBody();
        if ($providerCode === '' || strlen($body) > 1048576) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false]);
        }

        $headers = [];
        foreach ($this->request->headers() as $name => $header) {
            $headers[strtolower((string) $name)] = (string) $header->getValueLine();
        }

        try {
            // O conector deve validar assinatura/timestamp antes de retornar success=true.
            $event = ConnectorRegistry::get($providerCode)->parseWebhook([
                'provider_code' => $providerCode,
                'headers' => $headers,
                'body' => $body,
                'payload_hash' => hash('sha256', $body),
            ]);
            if (empty($event['success'])) {
                return $this->response->setStatusCode(401)->setJSON(['success' => false]);
            }
            $event['provider_code'] = $providerCode;
            $event['payload_hash'] = (string) ($event['payload_hash'] ?? hash('sha256', $body));
            $providerEventId = trim((string) ($event['provider_event_id'] ?? ''));
            if ($providerEventId === '') {
                throw new \DomainException('gdc_invalid_webhook_event');
            }

            if (($event['event_kind'] ?? 'charge') === 'payment_method') {
                $unitId = (int) ($event['unit_id'] ?? 0);
                if ($unitId <= 0) {
                    throw new \DomainException('gdc_invalid_payment_method_event');
                }
                (new PaymentMethodService($unitId))->storeFromWebhook($event);
                return $this->response->setJSON(['success' => true]);
            }

            $db = db_connect();
            $charges = $db->prefixTable('gdc_charges');
            $builder = $db->table($charges)->where('provider_code', $providerCode)->where('deleted', 0);
            $externalId = trim((string) ($event['external_charge_id'] ?? ''));
            $localUuid = trim((string) ($event['local_charge_uuid'] ?? ''));
            if ($externalId !== '') {
                $builder->where('external_charge_id', $externalId);
            } elseif ($localUuid !== '') {
                $builder->where('charge_uuid', $localUuid);
            } else {
                throw new \DomainException('gdc_webhook_charge_not_found');
            }
            $charge = $builder->get(1)->getRow();
            if (!$charge) {
                throw new \DomainException('gdc_webhook_charge_not_found');
            }

            $duplicate = $db->table($db->prefixTable('gdc_charge_events'))
                ->where('unit_id', (int) $charge->unit_id)->where('provider_event_id', $providerEventId)->countAllResults();
            $service = new ChargeService((int) $charge->unit_id);
            if ($duplicate > 0) {
                $service->reconcile((int) $charge->id, $event);
                return $this->response->setJSON(['success' => true, 'duplicate' => true]);
            }

            $service->applyWebhookResult((int) $charge->id, $event);
            return $this->response->setJSON(['success' => true]);
        } catch (\Throwable $e) {
            log_message('error', 'GDC webhook ' . $providerCode . ': ' . $e->getMessage());
            return $this->response->setStatusCode(422)->setJSON(['success' => false]);
        }
    }
}
