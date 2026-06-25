<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

use grupo_donato_cobranca\Config\Constants;
use grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface;

final class ChargeService
{
    private $db;
    private int $unitId;
    private int $actorId;
    private ?object $user;
    private SettingsService $settings;
    private EventService $events;

    public function __construct(int $unitId, int $actorId = 0, ?object $user = null)
    {
        $this->db = db_connect();
        $this->unitId = $unitId;
        $this->actorId = $actorId;
        $this->user = $user;
        $this->settings = new SettingsService($unitId);
        $this->events = new EventService($unitId);
    }

    public function dashboard(): array
    {
        $table = $this->db->prefixTable('gdc_charges');
        $base = $this->db->table($table)->where('unit_id', $this->unitId)->where('deleted', 0);
        $monthStart = gmdate('Y-m-01 00:00:00');
        return [
            'pending' => (clone $base)->whereIn('status', ['processing', 'pending', 'partially_paid'])->countAllResults(),
            'paid_month' => (clone $base)->where('status', 'paid')->where('paid_at >=', $monthStart)->selectSum('paid_amount')->get()->getRow()->paid_amount ?? '0.00',
            'failed' => (clone $base)->whereIn('status', ['failed', 'review'])->countAllResults(),
            'expired' => (clone $base)->where('status', 'expired')->countAllResults(),
            'subscriptions' => $this->db->table($this->db->prefixTable('gdc_subscriptions'))->where('unit_id', $this->unitId)->where('status', 'active')->where('deleted', 0)->countAllResults(),
            'connector_configured' => $this->settings->get('provider_code') !== '',
        ];
    }

    public function openReceivables(int $limit = 150): array
    {
        $r = $this->db->prefixTable('gd_receivables');
        $a = $this->db->prefixTable('gd_customer_accounts');
        return $this->db->table($r)
            ->select("$r.id,$r.receivable_number,$r.customer_account_id,$r.description,$r.due_date,$r.balance_amount,$a.display_name customer_name", false)
            ->join($a, "$a.id=$r.customer_account_id AND $a.unit_id=$r.unit_id AND $a.deleted=0", 'inner', false)
            ->where("$r.unit_id", $this->unitId)->where("$r.deleted", 0)
            ->whereIn("$r.status", ['open', 'partial', 'overdue'])
            ->where("$r.balance_amount >", 0)
            ->orderBy("$r.due_date", 'ASC')->limit(max(1, min(300, $limit)))->get()->getResultArray();
    }

    public function paymentMethodsForCustomer(int $customerAccountId): array
    {
        return $this->db->table($this->db->prefixTable('gdc_payment_methods'))
            ->select('id,brand,last4,exp_month,exp_year,is_default')
            ->where('unit_id', $this->unitId)->where('customer_account_id', $customerAccountId)
            ->where('status', 'active')->where('deleted', 0)
            ->orderBy('is_default', 'DESC')->orderBy('id', 'DESC')->get()->getResultArray();
    }

    public function create(array $input): array
    {
        $receivableId = (int) ($input['receivable_id'] ?? 0);
        $method = (string) ($input['collection_method'] ?? '');
        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        $subscriptionId = (int) ($input['subscription_id'] ?? 0);
        $attemptCount = max(1, (int) ($input['attempt_count'] ?? 1));
        if ($receivableId <= 0 || !in_array($method, Constants::COLLECTION_METHODS, true)) {
            throw new \DomainException('gdc_invalid_charge');
        }

        $providerCode = strtolower(trim($this->settings->get('provider_code')));
        if ($providerCode === '') {
            throw new \DomainException('gdc_connector_not_configured');
        }
        $connector = ConnectorRegistry::get($providerCode);
        $capabilities = $connector->capabilities();
        if (empty($capabilities[$method])) {
            throw new \DomainException('gdc_method_not_supported');
        }

        $lock = "gdc:create:{$this->unitId}:{$receivableId}";
        $this->acquire($lock);
        try {
            $receivable = $this->receivable($receivableId, true);
            if (!$receivable || in_array((string) $receivable->status, ['paid', 'cancelled'], true) || Money::compare((string) $receivable->balance_amount, '0.00') <= 0) {
                throw new \DomainException('gdc_receivable_unavailable');
            }
            $existing = $this->db->table($this->db->prefixTable('gdc_charges'))
                ->where('unit_id', $this->unitId)->where('receivable_id', $receivableId)
                ->whereIn('status', Constants::ACTIVE_CHARGE_STATUSES)->where('deleted', 0)->get(1)->getRow();
            if ($existing) {
                return ['created' => false, 'duplicate' => true, 'id' => (int) $existing->id];
            }

            $paymentMethod = null;
            if ($method === 'credit_card') {
                $paymentMethod = $this->paymentMethod($paymentMethodId, (int) $receivable->customer_account_id);
            }

            $uuid = $this->uuid();
            $idempotency = 'gdc:' . $this->unitId . ':' . $uuid;
            $now = gmdate('Y-m-d H:i:s');
            $data = [
                'charge_uuid' => $uuid,
                'unit_id' => $this->unitId,
                'receivable_id' => $receivableId,
                'customer_account_id' => (int) $receivable->customer_account_id,
                'subscription_id' => $subscriptionId ?: null,
                'payment_method_id' => $paymentMethodId ?: null,
                'provider_code' => $providerCode,
                'collection_method' => $method,
                'idempotency_key' => $idempotency,
                'amount' => Money::normalize((string) $receivable->balance_amount),
                'paid_amount' => '0.00',
                'due_date' => (string) $receivable->due_date,
                'status' => 'processing',
                'attempt_count' => $attemptCount,
                'lock_version' => 1,
                'created_at' => $now,
                'created_by' => $this->actorId ?: null,
                'updated_at' => $now,
                'updated_by' => $this->actorId ?: null,
                'deleted' => 0,
            ];
            $this->db->table($this->db->prefixTable('gdc_charges'))->insert($data);
            $chargeId = (int) $this->db->insertID();
            $this->events->add($chargeId, 'created', null, 'processing', 'Cobrança local criada.');
            AuditBridge::log($this->user, $this->unitId, 'create', 'charge', $chargeId, null, $data, ['receivable_id' => $receivableId]);
        } finally {
            $this->release($lock);
        }

        try {
            $customer = (new CustomerPayloadService($this->unitId))->build((int) $receivable->customer_account_id);
            $customerResult = $connector->upsertCustomer($customer);
            if (empty($customerResult['success'])) {
                throw new \RuntimeException((string) ($customerResult['message'] ?? 'customer_sync_failed'));
            }
            $request = [
                'idempotency_key' => $idempotency,
                'local_charge_id' => $chargeId,
                'local_charge_uuid' => $uuid,
                'external_customer_id' => (string) ($customerResult['external_customer_id'] ?? ''),
                'amount' => Money::normalize((string) $receivable->balance_amount),
                'currency' => 'BRL',
                'due_date' => (string) $receivable->due_date,
                'description' => (string) $receivable->description,
                'payer' => $customer,
                'metadata' => [
                    'unit_id' => $this->unitId,
                    'receivable_id' => $receivableId,
                    'receivable_number' => (string) $receivable->receivable_number,
                    'charge_uuid' => $uuid,
                ],
            ];
            if ($method === 'credit_card') {
                $request['payment_method_ref'] = (string) $paymentMethod->provider_payment_method_ref;
                $result = $connector->createCardCharge($request);
            } else {
                $result = $connector->createPixCharge($request);
            }
            if (empty($result['success'])) {
                throw new \RuntimeException((string) ($result['message'] ?? 'provider_create_failed'));
            }
            $this->applyProviderResult($chargeId, $result, 'provider_created');
        } catch (\Throwable $e) {
            $this->markFailed($chargeId, 'connector_error', $e->getMessage());
            throw new \DomainException('gdc_connector_operation_failed');
        }

        return ['created' => true, 'id' => $chargeId, 'status' => (string) ($result['status'] ?? 'pending')];
    }

    public function sync(int $chargeId): array
    {
        $charge = $this->charge($chargeId);
        if (!$charge || empty($charge->external_charge_id)) {
            throw new \DomainException('gdc_charge_not_syncable');
        }
        $connector = ConnectorRegistry::get((string) $charge->provider_code);
        $result = $connector->getCharge([
            'external_charge_id' => (string) $charge->external_charge_id,
            'local_charge_uuid' => (string) $charge->charge_uuid,
        ]);
        if (empty($result['success'])) {
            throw new \DomainException('gdc_connector_operation_failed');
        }
        $this->applyProviderResult($chargeId, $result, 'manual_sync');
        return ['id' => $chargeId];
    }

    public function cancel(int $chargeId): array
    {
        $charge = $this->charge($chargeId);
        if (!$charge || in_array((string) $charge->status, ['paid', 'cancelled', 'refunded'], true)) {
            throw new \DomainException('gdc_charge_not_cancellable');
        }
        $connector = ConnectorRegistry::get((string) $charge->provider_code);
        $result = $connector->cancelCharge([
            'external_charge_id' => (string) $charge->external_charge_id,
            'local_charge_uuid' => (string) $charge->charge_uuid,
        ]);
        if (empty($result['success'])) {
            throw new \DomainException('gdc_connector_operation_failed');
        }
        $result['status'] = $result['status'] ?? 'cancelled';
        $this->applyProviderResult($chargeId, $result, 'manual_cancel');
        return ['id' => $chargeId];
    }

    public function applyWebhookResult(int $chargeId, array $result): void
    {
        $this->applyProviderResult($chargeId, $result, 'webhook');
    }

    /**
     * Retoma uma baixa/estorno após webhook repetido ou falha entre sistemas.
     */
    public function reconcile(int $chargeId, array $result = []): void
    {
        $charge = $this->charge($chargeId, true);
        if (!$charge) {
            throw new \DomainException('gdc_charge_not_found');
        }
        if ((string) $charge->status === 'paid' && empty($charge->gd_payment_id)) {
            $this->settle($chargeId, $result);
        } elseif ((string) $charge->status === 'refunded' && !empty($charge->gd_payment_id)) {
            $this->handleRefund($chargeId, $result);
        }
    }

    public function findByExternal(string $providerCode, string $externalChargeId, string $localUuid = ''): ?object
    {
        $table = $this->db->prefixTable('gdc_charges');
        $builder = $this->db->table($table)->where('provider_code', $providerCode)->where('deleted', 0);
        if ($externalChargeId !== '') {
            $builder->where('external_charge_id', $externalChargeId);
        } elseif ($localUuid !== '') {
            $builder->where('charge_uuid', $localUuid);
        } else {
            return null;
        }
        return $builder->get(1)->getRow();
    }

    public function get(int $chargeId): ?object
    {
        $c = $this->charge($chargeId);
        if (!$c) {
            return null;
        }
        $c->events = $this->events->forCharge($chargeId);
        return $c;
    }

    public function page(array $options): array
    {
        $c = $this->db->prefixTable('gdc_charges');
        $r = $this->db->prefixTable('gd_receivables');
        $a = $this->db->prefixTable('gd_customer_accounts');
        $base = function () use ($options, $c, $r, $a) {
            $q = $this->db->table($c)
                ->join($r, "$r.id=$c.receivable_id AND $r.unit_id=$c.unit_id AND $r.deleted=0", 'inner', false)
                ->join($a, "$a.id=$c.customer_account_id AND $a.unit_id=$c.unit_id AND $a.deleted=0", 'inner', false)
                ->where("$c.unit_id", $this->unitId)->where("$c.deleted", 0);
            if (!empty($options['status'])) {
                $q->where("$c.status", (string) $options['status']);
            }
            if (!empty($options['collection_method'])) {
                $q->where("$c.collection_method", (string) $options['collection_method']);
            }
            $search = trim((string) ($options['search_by'] ?? ''));
            if ($search !== '') {
                $q->groupStart()->like("$r.receivable_number", $search)->orLike("$a.display_name", $search)->orLike("$c.external_charge_id", $search)->groupEnd();
            }
            return $q;
        };
        $total = $this->db->table($c)->where('unit_id', $this->unitId)->where('deleted', 0)->countAllResults();
        $filtered = $base()->countAllResults(false);
        $rows = $base()->select("$c.*,$r.receivable_number,$r.description,$a.display_name customer_name", false)
            ->orderBy("$c.id", 'DESC')
            ->limit(max(1, min(100, (int) ($options['limit'] ?? 25))), max(0, (int) ($options['skip'] ?? 0)))
            ->get()->getResult();
        return ['data' => $rows, 'recordsTotal' => $total, 'recordsFiltered' => $filtered];
    }

    private function applyProviderResult(int $chargeId, array $result, string $eventType): void
    {
        $lock = "gdc:charge:{$this->unitId}:{$chargeId}";
        $this->acquire($lock);
        try {
            $charge = $this->charge($chargeId, true);
            if (!$charge) {
                throw new \DomainException('gdc_charge_not_found');
            }
            $status = strtolower((string) ($result['status'] ?? $charge->status));
            if (!in_array($status, Constants::CHARGE_STATUSES, true)) {
                $status = 'review';
            }
            $before = (array) $charge;
            $paidAmount = isset($result['paid_amount']) ? Money::normalize($result['paid_amount']) : (string) $charge->paid_amount;
            $data = [
                'status' => $status,
                'external_charge_id' => $this->nullable($result['external_charge_id'] ?? $charge->external_charge_id),
                'external_payment_id' => $this->nullable($result['external_payment_id'] ?? $charge->external_payment_id),
                'pix_txid' => $this->nullable($result['pix_txid'] ?? $charge->pix_txid),
                'pix_copy_paste' => $this->nullable($result['pix_copy_paste'] ?? $charge->pix_copy_paste),
                'pix_qr_code_url' => $this->safeHttpsUrl($result['pix_qr_code_url'] ?? $charge->pix_qr_code_url),
                'paid_amount' => $paidAmount,
                'expires_at' => $this->nullable($result['expires_at'] ?? $charge->expires_at),
                'paid_at' => $this->nullable($result['paid_at'] ?? $charge->paid_at),
                'last_error_code' => $this->nullable($result['error_code'] ?? null),
                'last_error_message' => $this->nullable(isset($result['message']) ? mb_substr((string) $result['message'], 0, 500) : null),
                'lock_version' => (int) $charge->lock_version + 1,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actorId ?: null,
            ];
            $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $chargeId)->where('unit_id', $this->unitId)->update($data);
            $this->events->add(
                $chargeId,
                $eventType,
                (string) $charge->status,
                $status,
                isset($result['message']) ? (string) $result['message'] : null,
                isset($result['provider_event_id']) ? (string) $result['provider_event_id'] : null,
                isset($result['payload_hash']) ? (string) $result['payload_hash'] : null,
                isset($result['occurred_at']) ? (string) $result['occurred_at'] : null
            );
            AuditBridge::log($this->user, $this->unitId, 'provider_update', 'charge', $chargeId, $before, $data, ['event_type' => $eventType]);
        } finally {
            $this->release($lock);
        }

        if (($status ?? '') === 'paid') {
            $this->settle($chargeId, $result);
            $this->markSubscriptionProcessed($charge);
        } elseif (($status ?? '') === 'refunded') {
            $this->handleRefund($chargeId, $result);
        } elseif (in_array(($status ?? ''), ['failed', 'expired'], true)) {
            $this->scheduleSubscriptionRetry($charge);
        }
    }

    private function settle(int $chargeId, array $result): void
    {
        $lock = "gdc:settle:{$this->unitId}:{$chargeId}";
        $this->acquire($lock);
        try {
            $charge = $this->charge($chargeId, true);
            if (!$charge || !empty($charge->gd_payment_id)) {
                return;
            }
            if ($this->attachExistingFinancePayment($chargeId, $charge, $result)) {
                return;
            }
            $receivable = $this->receivable((int) $charge->receivable_id, true);
            if (!$receivable || in_array((string) $receivable->status, ['paid', 'cancelled'], true)) {
                $this->markReview($chargeId, 'Recebível indisponível no momento da baixa automática.');
                return;
            }
            $paidAmount = isset($result['paid_amount']) ? Money::normalize($result['paid_amount']) : Money::normalize((string) $charge->amount);
            if (Money::compare($paidAmount, '0.00') <= 0 || Money::compare($paidAmount, (string) $receivable->balance_amount) > 0) {
                $this->markReview($chargeId, 'Valor pago incompatível com o saldo atual da conta a receber.');
                return;
            }
            $financialAccountId = (int) $this->settings->get('financial_account_id');
            if ($financialAccountId <= 0 || !class_exists('grupo_donato_gestao\\Services\\FinanceService')) {
                $this->markReview($chargeId, 'Conta financeira ou dependência do financeiro não configurada.');
                return;
            }
            $paidAt = (string) ($result['paid_at'] ?? $charge->paid_at ?? gmdate('Y-m-d H:i:s'));
            $paymentDate = preg_match('/^\d{4}-\d{2}-\d{2}/', $paidAt) ? substr($paidAt, 0, 10) : gmdate('Y-m-d');
            $finance = new \grupo_donato_gestao\Services\FinanceService($this->unitId, $this->actorId, $this->user);
            $payment = $finance->registerPayment([
                'allocations' => [(int) $charge->receivable_id => $paidAmount],
                'amount' => $paidAmount,
                'payment_date' => $paymentDate,
                'payment_method' => (string) $charge->collection_method === 'pix' ? 'pix' : 'credit_card',
                'financial_account_id' => $financialAccountId,
                'external_reference' => (string) (($result['external_payment_id'] ?? '') ?: ($charge->external_charge_id ?? '')),
                'notes' => 'Baixa automática pelo módulo Cobrança. Cobrança local ' . $charge->charge_uuid,
            ]);
            $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $chargeId)->where('unit_id', $this->unitId)->update([
                'gd_payment_id' => (int) $payment['id'],
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
                'status' => 'paid',
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'updated_by' => $this->actorId ?: null,
            ]);
            $this->events->add($chargeId, 'finance_settled', 'paid', 'paid', 'Pagamento registrado no financeiro: ' . (string) ($payment['payment_number'] ?? ''));
        } finally {
            $this->release($lock);
        }
    }

    private function attachExistingFinancePayment(int $chargeId, object $charge, array $result): bool
    {
        $externalReference = trim((string) (($result['external_payment_id'] ?? '') ?: ($charge->external_payment_id ?? '') ?: ($charge->external_charge_id ?? '')));
        if ($externalReference === '') {
            return false;
        }
        $payments = $this->db->prefixTable('gd_payments');
        $allocations = $this->db->prefixTable('gd_payment_allocations');
        $existing = $this->db->table($payments)
            ->select("$payments.id,$payments.payment_number,$payments.amount,$payments.payment_date", false)
            ->join($allocations, "$allocations.payment_id=$payments.id AND $allocations.unit_id=$payments.unit_id AND $allocations.status='active'", 'inner', false)
            ->where("$payments.unit_id", $this->unitId)
            ->where("$payments.external_reference", $externalReference)
            ->where("$payments.status", 'confirmed')
            ->where("$payments.deleted", 0)
            ->where("$allocations.receivable_id", (int) $charge->receivable_id)
            ->orderBy("$payments.id", 'DESC')->get(1)->getRow();
        if (!$existing) {
            return false;
        }
        $paidAmount = isset($result['paid_amount'])
            ? Money::normalize($result['paid_amount'])
            : Money::normalize((string) $charge->paid_amount);
        if (Money::compare($paidAmount, '0.00') <= 0) {
            $paidAmount = Money::normalize((string) $existing->amount);
        }
        $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $chargeId)->where('unit_id', $this->unitId)->update([
            'gd_payment_id' => (int) $existing->id,
            'external_payment_id' => $this->nullable($result['external_payment_id'] ?? $charge->external_payment_id),
            'paid_amount' => $paidAmount,
            'paid_at' => $this->nullable($result['paid_at'] ?? $charge->paid_at) ?: ((string) $existing->payment_date . ' 00:00:00'),
            'status' => 'paid',
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'updated_by' => $this->actorId ?: null,
        ]);
        $this->events->add($chargeId, 'finance_recovered', 'paid', 'paid', 'Baixa financeira preexistente reconciliada: ' . (string) $existing->payment_number);
        return true;
    }

    private function handleRefund(int $chargeId, array $result): void
    {
        $lock = "gdc:refund:{$this->unitId}:{$chargeId}";
        $this->acquire($lock);
        try {
            $charge = $this->charge($chargeId, true);
            if (!$charge || empty($charge->gd_payment_id)) {
                return;
            }
            if (!class_exists('grupo_donato_gestao\Services\FinanceService')) {
                $this->markReview($chargeId, 'Dependência do financeiro indisponível para registrar o estorno.');
                return;
            }
            $refundType = strtolower(trim((string) ($result['refund_type'] ?? '')));
            $refundedAmount = isset($result['refunded_amount']) ? Money::normalize($result['refunded_amount']) : '0.00';
            $fullRefund = $refundType === 'full'
                || (Money::compare($refundedAmount, '0.00') > 0 && Money::compare($refundedAmount, (string) $charge->paid_amount) === 0);
            if (!$fullRefund) {
                $this->markReview($chargeId, 'Estorno parcial ou sem valor confirmado; conciliação manual necessária.');
                return;
            }

            $payment = $this->db->table($this->db->prefixTable('gd_payments'))
                ->select('id,status')->where('id', (int) $charge->gd_payment_id)
                ->where('unit_id', $this->unitId)->where('deleted', 0)->get(1)->getRow();
            if (!$payment || (string) $payment->status === 'reversed') {
                return;
            }
            if ((string) $payment->status !== 'confirmed') {
                $this->markReview($chargeId, 'Pagamento financeiro não está confirmado para estorno automático.');
                return;
            }

            $finance = new \grupo_donato_gestao\Services\FinanceService($this->unitId, $this->actorId, $this->user);
            $finance->reversePayment((int) $charge->gd_payment_id, 'Estorno confirmado pelo provedor da cobrança ' . $charge->charge_uuid);
            $this->events->add($chargeId, 'finance_reversed', 'refunded', 'refunded', 'Pagamento e movimento de caixa estornados no financeiro.');
        } finally {
            $this->release($lock);
        }
    }

    private function markSubscriptionProcessed(object $charge): void
    {
        if (empty($charge->subscription_id)) {
            return;
        }
        $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('id', (int) $charge->subscription_id)->where('unit_id', $this->unitId)->where('deleted', 0)
            ->update(['last_charge_at' => gmdate('Y-m-d H:i:s'), 'next_attempt_at' => null, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function scheduleSubscriptionRetry(object $charge): void
    {
        if (empty($charge->subscription_id)) {
            return;
        }
        $subscription = $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('id', (int) $charge->subscription_id)->where('unit_id', $this->unitId)
            ->where('status', 'active')->where('deleted', 0)->get(1)->getRow();
        if (!$subscription) {
            return;
        }
        $nextAttempt = (int) $charge->attempt_count < (int) $subscription->max_attempts
            ? gmdate('Y-m-d H:i:s', time() + ((int) $subscription->retry_interval_days * 86400))
            : null;
        $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('id', (int) $subscription->id)->where('unit_id', $this->unitId)
            ->update(['next_attempt_at' => $nextAttempt, 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    private function markFailed(int $chargeId, string $code, string $message): void
    {
        $charge = $this->charge($chargeId);
        if (!$charge) {
            return;
        }
        $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $chargeId)->where('unit_id', $this->unitId)->update([
            'status' => 'failed',
            'last_error_code' => mb_substr($code, 0, 100),
            'last_error_message' => mb_substr($message, 0, 500),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'updated_by' => $this->actorId ?: null,
        ]);
        $this->events->add($chargeId, 'failed', (string) $charge->status, 'failed', $message);
    }

    private function markReview(int $chargeId, string $message): void
    {
        $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $chargeId)->where('unit_id', $this->unitId)->update([
            'status' => 'review',
            'last_error_message' => mb_substr($message, 0, 500),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->events->add($chargeId, 'review_required', null, 'review', $message);
    }

    private function receivable(int $id, bool $forUpdate = false): ?object
    {
        $sql = "SELECT * FROM `{$this->db->prefixTable('gd_receivables')}` WHERE id=? AND unit_id=? AND deleted=0" . ($forUpdate && $this->db->transStatus() !== false ? '' : '');
        return $this->db->query($sql, [$id, $this->unitId])->getRow();
    }

    private function paymentMethod(int $id, int $customerAccountId): object
    {
        if ($id <= 0) {
            throw new \DomainException('gdc_payment_method_required');
        }
        $row = $this->db->table($this->db->prefixTable('gdc_payment_methods'))
            ->where('id', $id)->where('unit_id', $this->unitId)->where('customer_account_id', $customerAccountId)
            ->where('status', 'active')->where('deleted', 0)->get(1)->getRow();
        if (!$row) {
            throw new \DomainException('gdc_payment_method_required');
        }
        return $row;
    }

    private function charge(int $id, bool $includeDeleted = false): ?object
    {
        $q = $this->db->table($this->db->prefixTable('gdc_charges'))->where('id', $id)->where('unit_id', $this->unitId);
        if (!$includeDeleted) {
            $q->where('deleted', 0);
        }
        return $q->get(1)->getRow();
    }

    private function acquire(string $lock): void
    {
        $row = $this->db->query('SELECT GET_LOCK(?,10) acquired', [$lock])->getRow();
        if ((int) ($row->acquired ?? 0) !== 1) {
            throw new \RuntimeException('gdc_lock_timeout');
        }
    }

    private function release(string $lock): void
    {
        try {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$lock]);
        } catch (\Throwable $e) {
            log_message('error', 'GDC release lock: ' . $e->getMessage());
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function safeHttpsUrl($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (strlen($value) > 500 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return $scheme === 'https' ? $value : null;
    }

    private function nullable($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }
}
