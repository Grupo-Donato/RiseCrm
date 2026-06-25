<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

final class EventService
{
    private $db;
    private int $unitId;

    public function __construct(int $unitId)
    {
        $this->db = db_connect();
        $this->unitId = $unitId;
    }

    public function add(int $chargeId, string $eventType, ?string $before, ?string $after, ?string $message = null, ?string $providerEventId = null, ?string $payloadHash = null, ?string $occurredAt = null): bool
    {
        $data = [
            'unit_id' => $this->unitId,
            'charge_id' => $chargeId,
            'provider_event_id' => $providerEventId ?: null,
            'event_type' => mb_substr($eventType, 0, 80),
            'status_before' => $before ?: null,
            'status_after' => $after ?: null,
            'payload_hash' => $payloadHash ?: null,
            'message' => $message ? mb_substr($message, 0, 500) : null,
            'occurred_at' => $occurredAt ?: null,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        try {
            $this->db->table($this->db->prefixTable('gdc_charge_events'))->insert($data);
            return true;
        } catch (\Throwable $e) {
            if ($providerEventId && str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return false;
            }
            throw $e;
        }
    }

    public function forCharge(int $chargeId): array
    {
        return $this->db->table($this->db->prefixTable('gdc_charge_events'))
            ->where('unit_id', $this->unitId)->where('charge_id', $chargeId)
            ->orderBy('id', 'DESC')->get()->getResult();
    }
}
