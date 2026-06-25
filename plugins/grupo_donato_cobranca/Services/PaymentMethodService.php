<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class PaymentMethodService
{
    private $db;
    private int $unitId;
    private int $actorId;
    private ?object $user;

    public function __construct(int $unitId, int $actorId = 0, ?object $user = null)
    {
        $this->db = db_connect();
        $this->unitId = $unitId;
        $this->actorId = $actorId;
        $this->user = $user;
    }

    public function page(array $options): array
    {
        $m = $this->db->prefixTable('gdc_payment_methods');
        $a = $this->db->prefixTable('gd_customer_accounts');
        $base = function () use ($options, $m, $a) {
            $q = $this->db->table($m)->join($a, "$a.id=$m.customer_account_id AND $a.unit_id=$m.unit_id AND $a.deleted=0", 'inner', false)
                ->where("$m.unit_id", $this->unitId)->where("$m.deleted", 0);
            if (!empty($options['status'])) {
                $q->where("$m.status", (string) $options['status']);
            }
            $search = trim((string) ($options['search_by'] ?? ''));
            if ($search !== '') {
                $q->groupStart()->like("$a.display_name", $search)->orLike("$m.last4", $search)->orLike("$m.brand", $search)->groupEnd();
            }
            return $q;
        };
        $total = $this->db->table($m)->where('unit_id', $this->unitId)->where('deleted', 0)->countAllResults();
        $filtered = $base()->countAllResults(false);
        $rows = $base()->select("$m.*,$a.display_name customer_name", false)->orderBy("$m.id", 'DESC')
            ->limit(max(1, min(100, (int) ($options['limit'] ?? 25))), max(0, (int) ($options['skip'] ?? 0)))
            ->get()->getResult();
        return ['data' => $rows, 'recordsTotal' => $total, 'recordsFiltered' => $filtered];
    }

    public function createSession(int $customerId): array
    {
        $settings = new SettingsService($this->unitId);
        $provider = strtolower(trim($settings->get('provider_code')));
        if ($provider === '') {
            throw new \DomainException('gdc_connector_not_configured');
        }
        $connector = ConnectorRegistry::get($provider);
        if (empty($connector->capabilities()['hosted_tokenization'])) {
            throw new \DomainException('gdc_tokenization_not_supported');
        }
        $customer = (new CustomerPayloadService($this->unitId))->build($customerId);
        $external = $connector->upsertCustomer($customer);
        if (empty($external['success'])) {
            throw new \DomainException('gdc_connector_operation_failed');
        }
        $result = $connector->createPaymentMethodSession([
            'external_customer_id' => (string) ($external['external_customer_id'] ?? ''),
            'customer' => $customer,
            'success_url' => get_uri('cobranca/payment-methods'),
            'cancel_url' => get_uri('cobranca/payment-methods'),
            'webhook_url' => get_uri('cobranca/webhook/' . $provider),
            'metadata' => ['unit_id' => $this->unitId, 'customer_account_id' => $customerId],
        ]);
        if (empty($result['success']) || (empty($result['checkout_url']) && empty($result['client_token']))) {
            throw new \DomainException('gdc_connector_operation_failed');
        }
        return $result;
    }

    public function storeFromWebhook(array $event): int
    {
        $customerId = (int) ($event['customer_account_id'] ?? 0);
        $provider = strtolower(trim((string) ($event['provider_code'] ?? '')));
        $reference = trim((string) ($event['payment_method_ref'] ?? ''));
        if ($customerId <= 0 || $provider === '' || $reference === '') {
            throw new \DomainException('gdc_invalid_payment_method_event');
        }
        (new CustomerPayloadService($this->unitId))->build($customerId);
        $table = $this->db->prefixTable('gdc_payment_methods');
        $existing = $this->db->table($table)->where('unit_id', $this->unitId)->where('provider_code', $provider)->where('provider_payment_method_ref', $reference)->get(1)->getRow();
        $data = [
            'unit_id' => $this->unitId,
            'customer_account_id' => $customerId,
            'provider_code' => $provider,
            'external_customer_id' => trim((string) ($event['external_customer_id'] ?? '')) ?: null,
            'provider_payment_method_ref' => $reference,
            'method_type' => 'credit_card',
            'brand' => mb_substr(trim((string) ($event['brand'] ?? '')), 0, 40) ?: null,
            'last4' => preg_match('/^\d{4}$/', (string) ($event['last4'] ?? '')) ? (string) $event['last4'] : null,
            'exp_month' => max(1, min(12, (int) ($event['exp_month'] ?? 0))) ?: null,
            'exp_year' => (int) ($event['exp_year'] ?? 0) ?: null,
            'holder_name_masked' => mb_substr(trim((string) ($event['holder_name_masked'] ?? '')), 0, 190) ?: null,
            'is_default' => !empty($event['is_default']) ? 1 : 0,
            'status' => 'active',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'updated_by' => null,
            'deleted' => 0,
        ];
        if ($data['is_default']) {
            $this->db->table($table)->where('unit_id', $this->unitId)->where('customer_account_id', $customerId)->update(['is_default' => 0, 'updated_at' => gmdate('Y-m-d H:i:s')]);
        }
        if ($existing) {
            $this->db->table($table)->where('id', (int) $existing->id)->update($data);
            $id = (int) $existing->id;
        } else {
            $data += ['created_at' => gmdate('Y-m-d H:i:s'), 'created_by' => null];
            $this->db->table($table)->insert($data);
            $id = (int) $this->db->insertID();
        }
        AuditBridge::log(null, $this->unitId, 'attach', 'payment_method', $id, $existing ? (array) $existing : null, $data);
        return $id;
    }

    public function deactivate(int $id): void
    {
        $row = $this->db->table($this->db->prefixTable('gdc_payment_methods'))->where('id', $id)->where('unit_id', $this->unitId)->where('deleted', 0)->get(1)->getRow();
        if (!$row) {
            throw new \DomainException('gdc_payment_method_not_found');
        }
        $data = ['status' => 'inactive', 'is_default' => 0, 'updated_at' => gmdate('Y-m-d H:i:s'), 'updated_by' => $this->actorId ?: null];
        $this->db->table($this->db->prefixTable('gdc_payment_methods'))->where('id', $id)->update($data);
        AuditBridge::log($this->user, $this->unitId, 'deactivate', 'payment_method', $id, (array) $row, $data);
    }

    public function customers(): array
    {
        return (new SubscriptionService($this->unitId))->customers();
    }
}
