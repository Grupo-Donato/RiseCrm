<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

use grupo_donato_cobranca\Config\Constants;

final class SubscriptionService
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
        $s = $this->db->prefixTable('gdc_subscriptions');
        $a = $this->db->prefixTable('gd_customer_accounts');
        $m = $this->db->prefixTable('gdc_payment_methods');
        $base = function () use ($options, $s, $a, $m) {
            $q = $this->db->table($s)
                ->join($a, "$a.id=$s.customer_account_id AND $a.unit_id=$s.unit_id AND $a.deleted=0", 'inner', false)
                ->join($m, "$m.id=$s.payment_method_id AND $m.unit_id=$s.unit_id AND $m.deleted=0", 'left', false)
                ->where("$s.unit_id", $this->unitId)->where("$s.deleted", 0);
            if (!empty($options['status'])) {
                $q->where("$s.status", (string) $options['status']);
            }
            $search = trim((string) ($options['search_by'] ?? ''));
            if ($search !== '') {
                $q->groupStart()->like("$a.display_name", $search)->orLike("$s.source_type", $search)->groupEnd();
            }
            return $q;
        };
        $total = $this->db->table($s)->where('unit_id', $this->unitId)->where('deleted', 0)->countAllResults();
        $filtered = $base()->countAllResults(false);
        $rows = $base()->select("$s.*,$a.display_name customer_name,$m.brand,$m.last4", false)
            ->orderBy("$s.id", 'DESC')
            ->limit(max(1, min(100, (int) ($options['limit'] ?? 25))), max(0, (int) ($options['skip'] ?? 0)))
            ->get()->getResult();
        return ['data' => $rows, 'recordsTotal' => $total, 'recordsFiltered' => $filtered];
    }

    public function get(int $id): ?object
    {
        return $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('id', $id)->where('unit_id', $this->unitId)->where('deleted', 0)->get(1)->getRow();
    }

    public function save(array $input, int $id = 0): array
    {
        $old = $id ? $this->get($id) : null;
        if ($id && !$old) {
            throw new \DomainException('gdc_subscription_not_found');
        }
        $customerId = (int) ($input['customer_account_id'] ?? 0);
        $sourceType = (string) ($input['source_type'] ?? '');
        $sourceId = (int) ($input['source_id'] ?? 0);
        $method = (string) ($input['collection_method'] ?? '');
        $paymentMethodId = (int) ($input['payment_method_id'] ?? 0);
        $chargeDay = (int) ($input['charge_day'] ?? 0);
        $maxAttempts = max(1, min(10, (int) ($input['max_attempts'] ?? 3)));
        $retryDays = max(1, min(30, (int) ($input['retry_interval_days'] ?? 3)));
        if ($customerId <= 0 || !in_array($sourceType, Constants::SOURCE_TYPES, true) || !in_array($method, Constants::COLLECTION_METHODS, true)) {
            throw new \DomainException('gdc_invalid_subscription');
        }
        if ($chargeDay < 1 || $chargeDay > 28) {
            throw new \DomainException('gdc_invalid_charge_day');
        }
        $this->assertCustomer($customerId);
        $this->assertSource($sourceType, $sourceId, $customerId);
        if ($method === 'credit_card') {
            $this->assertPaymentMethod($paymentMethodId, $customerId);
        } else {
            $paymentMethodId = 0;
        }
        $provider = strtolower(trim((new SettingsService($this->unitId))->get('provider_code')));
        if ($provider === '') {
            throw new \DomainException('gdc_connector_not_configured');
        }

        $duplicate = $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('unit_id', $this->unitId)->where('source_type', $sourceType)->where('source_id', $sourceId)
            ->whereIn('status', ['active', 'paused'])->where('deleted', 0);
        if ($id) {
            $duplicate->where('id !=', $id);
        }
        if ($sourceId > 0 && $duplicate->countAllResults() > 0) {
            throw new \DomainException('gdc_subscription_duplicate');
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = [
            'unit_id' => $this->unitId,
            'customer_account_id' => $customerId,
            'source_type' => $sourceType,
            'source_id' => $sourceId ?: null,
            'collection_method' => $method,
            'payment_method_id' => $paymentMethodId ?: null,
            'provider_code' => $provider,
            'status' => $old->status ?? 'active',
            'charge_day' => $chargeDay,
            'max_attempts' => $maxAttempts,
            'retry_interval_days' => $retryDays,
            'started_at' => $old->started_at ?? $now,
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'lock_version' => (int) ($old->lock_version ?? 0) + 1,
            'updated_at' => $now,
            'updated_by' => $this->actorId ?: null,
        ];
        if (!$old) {
            $data += ['created_at' => $now, 'created_by' => $this->actorId ?: null, 'deleted' => 0];
            $this->db->table($this->db->prefixTable('gdc_subscriptions'))->insert($data);
            $id = (int) $this->db->insertID();
        } else {
            $this->db->table($this->db->prefixTable('gdc_subscriptions'))->where('id', $id)->where('unit_id', $this->unitId)->update($data);
        }
        AuditBridge::log($this->user, $this->unitId, $old ? 'update' : 'create', 'subscription', $id, $old ? (array) $old : null, $data);
        return ['id' => $id];
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, Constants::SUBSCRIPTION_STATUSES, true)) {
            throw new \DomainException('gdc_invalid_subscription_status');
        }
        $old = $this->get($id);
        if (!$old) {
            throw new \DomainException('gdc_subscription_not_found');
        }
        $data = [
            'status' => $status,
            'ended_at' => $status === 'cancelled' ? gmdate('Y-m-d H:i:s') : null,
            'lock_version' => (int) $old->lock_version + 1,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'updated_by' => $this->actorId ?: null,
        ];
        $this->db->table($this->db->prefixTable('gdc_subscriptions'))->where('id', $id)->where('unit_id', $this->unitId)->update($data);
        AuditBridge::log($this->user, $this->unitId, $status, 'subscription', $id, (array) $old, $data);
    }

    public function customers(): array
    {
        return $this->db->table($this->db->prefixTable('gd_customer_accounts'))
            ->select('id,display_name')->where('unit_id', $this->unitId)->where('status', 'active')->where('deleted', 0)
            ->orderBy('display_name')->get()->getResultArray();
    }

    public function paymentMethods(): array
    {
        return $this->db->table($this->db->prefixTable('gdc_payment_methods'))
            ->select('id,customer_account_id,brand,last4,exp_month,exp_year')
            ->where('unit_id', $this->unitId)->where('status', 'active')->where('deleted', 0)
            ->orderBy('customer_account_id')->orderBy('is_default', 'DESC')->get()->getResultArray();
    }

    public function processAutomatic(int $limit = 50): array
    {
        $settings = new SettingsService($this->unitId);
        if ($settings->get('automatic_billing') !== '1') {
            return ['processed' => 0, 'created' => 0, 'failed' => 0];
        }

        $now = gmdate('Y-m-d H:i:s');
        $unit = $this->db->table($this->db->prefixTable('gd_units'))->select('timezone')
            ->where('id', $this->unitId)->where('deleted', 0)->get(1)->getRow();
        try {
            $timezone = new \DateTimeZone((string) ($unit->timezone ?? 'America/Sao_Paulo'));
        } catch (\Throwable $e) {
            $timezone = new \DateTimeZone('America/Sao_Paulo');
        }
        $localNow = new \DateTimeImmutable('now', $timezone);
        $today = $localNow->format('Y-m-d');
        $currentDay = (int) $localNow->format('j');
        $subscriptions = $this->db->table($this->db->prefixTable('gdc_subscriptions'))
            ->where('unit_id', $this->unitId)->where('status', 'active')->where('deleted', 0)
            ->groupStart()->where('next_attempt_at', null)->orWhere('next_attempt_at <=', $now)->groupEnd()
            ->orderBy('id')->limit(max(1, min(100, $limit)))->get()->getResult();

        $processed = $created = $failed = 0;
        foreach ($subscriptions as $subscription) {
            $processed++;
            $receivable = $this->db->table($this->db->prefixTable('gd_receivables'))
                ->where('unit_id', $this->unitId)->where('customer_account_id', (int) $subscription->customer_account_id)
                ->where('source_type', (string) $subscription->source_type)->where('source_id', (int) $subscription->source_id)
                ->whereIn('status', ['open', 'partial', 'overdue'])->where('deleted', 0)
                ->orderBy('due_date', 'ASC')->get(1)->getRow();
            if (!$receivable) {
                continue;
            }

            // A regra pode ser executada após o dia configurado; títulos vencidos têm prioridade imediata.
            if ($currentDay < (int) $subscription->charge_day && (string) $receivable->due_date >= $today) {
                continue;
            }

            $chargeTable = $this->db->prefixTable('gdc_charges');
            $activeOrPaid = $this->db->table($chargeTable)
                ->where('unit_id', $this->unitId)->where('receivable_id', (int) $receivable->id)
                ->whereIn('status', ['processing', 'pending', 'partially_paid', 'paid', 'refunded', 'review'])
                ->where('deleted', 0)->countAllResults();
            if ($activeOrPaid > 0) {
                continue;
            }

            $attempts = $this->db->table($chargeTable)
                ->where('unit_id', $this->unitId)->where('receivable_id', (int) $receivable->id)
                ->where('subscription_id', (int) $subscription->id)
                ->whereIn('status', ['failed', 'expired', 'cancelled'])
                ->where('deleted', 0)->countAllResults();
            if ($attempts >= (int) $subscription->max_attempts) {
                continue;
            }

            try {
                $result = (new ChargeService($this->unitId, 0, null))->create([
                    'receivable_id' => (int) $receivable->id,
                    'collection_method' => (string) $subscription->collection_method,
                    'payment_method_id' => (int) ($subscription->payment_method_id ?? 0),
                    'subscription_id' => (int) $subscription->id,
                    'attempt_count' => $attempts + 1,
                ]);
                if (!empty($result['created'])) {
                    $created++;
                    $update = ['last_charge_at' => $now, 'updated_at' => $now];
                    if (!in_array((string) ($result['status'] ?? 'pending'), ['failed', 'expired'], true)) {
                        $update['next_attempt_at'] = null;
                    }
                    $this->db->table($this->db->prefixTable('gdc_subscriptions'))->where('id', (int) $subscription->id)->update($update);
                }
            } catch (\Throwable $e) {
                $failed++;
                $nextAttempt = ($attempts + 1) < (int) $subscription->max_attempts
                    ? gmdate('Y-m-d H:i:s', time() + ((int) $subscription->retry_interval_days * 86400))
                    : null;
                $this->db->table($this->db->prefixTable('gdc_subscriptions'))->where('id', (int) $subscription->id)->update([
                    'next_attempt_at' => $nextAttempt,
                    'updated_at' => $now,
                ]);
                log_message('error', 'GDC automatic billing subscription ' . (int) $subscription->id . ': ' . $e->getMessage());
            }
        }
        return compact('processed', 'created', 'failed');
    }

    private function assertCustomer(int $id): void
    {
        $count = $this->db->table($this->db->prefixTable('gd_customer_accounts'))
            ->where('id', $id)->where('unit_id', $this->unitId)->where('deleted', 0)->countAllResults();
        if ($count !== 1) {
            throw new \DomainException('gdc_customer_not_found');
        }
    }

    private function assertSource(string $type, int $id, int $customerId): void
    {
        if ($id <= 0) {
            throw new \DomainException('gdc_source_required');
        }
        if (in_array($type, ['manual', 'other'], true)) {
            return;
        }
        if ($type === 'court_rental') {
            $count = $this->db->table($this->db->prefixTable('gd_court_rentals'))
                ->where('id', $id)->where('unit_id', $this->unitId)->where('customer_account_id', $customerId)->where('deleted', 0)->countAllResults();
        } else {
            $e = $this->db->prefixTable('gd_enrollments');
            $p = $this->db->prefixTable('gd_school_profiles');
            $count = $this->db->table($e)->join($p, "$p.id=$e.school_profile_id AND $p.unit_id=$e.unit_id AND $p.deleted=0", 'inner', false)
                ->where("$e.id", $id)->where("$e.unit_id", $this->unitId)->where("$p.family_account_id", $customerId)->where("$e.deleted", 0)->countAllResults();
        }
        if ($count !== 1) {
            throw new \DomainException('gdc_source_mismatch');
        }
    }

    private function assertPaymentMethod(int $id, int $customerId): void
    {
        $count = $this->db->table($this->db->prefixTable('gdc_payment_methods'))
            ->where('id', $id)->where('unit_id', $this->unitId)->where('customer_account_id', $customerId)
            ->where('status', 'active')->where('deleted', 0)->countAllResults();
        if ($count !== 1) {
            throw new \DomainException('gdc_payment_method_required');
        }
    }
}
