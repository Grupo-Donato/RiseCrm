<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

final class BookingSeriesEventService extends CustomerDataService
{
    private $events;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->events = model("grupo_donato_gestao\\Models\\Gd_booking_series_events_model");
    }

    public function append(int $series_id, string $event_type, ?string $from_status = null, ?string $to_status = null, ?string $reason = null, array $payload = []): int
    {
        if (!in_array($event_type, Constants::BOOKING_SERIES_EVENT_TYPES, true)) { throw new \DomainException("gd_invalid_booking_series_event"); }
        $safe = DataPrivacyService::forAudit($payload);
        return $this->events->add([
            "unit_id" => $this->unit_id,
            "series_id" => $series_id,
            "event_type" => $event_type,
            "from_status" => $from_status,
            "to_status" => $to_status,
            "reason" => $reason ? mb_substr(strip_tags($reason), 0, 255) : null,
            "payload" => $safe ? json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            "actor_type" => $this->actor_id ? "staff" : "system",
            "actor_id" => $this->actor_id ?: null,
            "request_id" => AuditService::request_id(),
            "created_at" => gmdate("Y-m-d H:i:s"),
        ]);
    }
}
