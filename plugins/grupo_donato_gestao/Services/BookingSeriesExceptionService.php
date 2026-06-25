<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

final class BookingSeriesExceptionService extends CustomerDataService
{
    private $exceptions;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->exceptions = model("grupo_donato_gestao\\Models\\Gd_booking_series_exceptions_model");
    }

    public function append(int $series_id, string $occurrence_key, string $local_date, string $type, ?int $booking_id = null, ?string $reason = null, array $payload = [], ?int $replacement_series_id = null): int
    {
        if (!in_array($type, Constants::BOOKING_SERIES_EXCEPTION_TYPES, true)) { throw new \DomainException("gd_invalid_booking_series_exception"); }
        $table = $this->db->prefixTable("gd_booking_series_exceptions");
        $existing = $this->db->table($table)->select("id")->where("series_id", $series_id)->where("occurrence_key", $occurrence_key)->where("exception_type", $type)->get(1)->getRow();
        if ($existing) { return (int) $existing->id; }
        $safe = DataPrivacyService::forAudit($payload);
        return $this->exceptions->add([
            "unit_id" => $this->unit_id,
            "series_id" => $series_id,
            "booking_id" => $booking_id ?: null,
            "replacement_series_id" => $replacement_series_id ?: null,
            "occurrence_key" => mb_substr($occurrence_key, 0, 40),
            "local_date" => $local_date,
            "exception_type" => $type,
            "reason" => $reason ? mb_substr(strip_tags($reason), 0, 255) : null,
            "payload" => $safe ? json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) : null,
            "created_at" => gmdate("Y-m-d H:i:s"),
            "created_by" => $this->actor_id ?: null,
        ]);
    }
}
