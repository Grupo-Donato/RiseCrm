<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/** Mantém materializadas as ocorrências futuras de séries abertas. */
final class BookingSeriesGenerationJobService extends CustomerDataService
{
    private TemporalService $time;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->time = new TemporalService($unit_id);
    }

    /**
     * Gera somente séries que já ficaram aquém do próprio horizonte.
     * Cada série mantém seu lock e sua política de conflito no serviço existente.
     */
    public function run(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $result = [
            "checked" => 0,
            "due" => 0,
            "processed" => 0,
            "created" => 0,
            "idempotent" => 0,
            "skipped" => 0,
            "failed" => 0,
            "errors" => [],
        ];

        $table = $this->db->prefixTable("gd_booking_series");
        if (!$this->db->tableExists($table)) {
            return $result;
        }

        $series_rows = $this->db->table($table)
            ->select("id,starts_on,frequency,interval_value,generation_horizon_days,last_generated_until")
            ->where("unit_id", $this->unit_id)
            ->where("status", "active")
            ->where("ends_mode", "open_ended")
            ->where("deleted", 0)
            ->orderBy("last_generated_until", "ASC")
            ->orderBy("id", "ASC")
            ->get()
            ->getResult();

        $today = new \DateTimeImmutable("today", new \DateTimeZone($this->time->timezoneName()));
        $occurrences = new BookingSeriesOccurrenceService($this->unit_id, $this->actor_id);

        foreach ($series_rows as $series) {
            $result["checked"]++;
            if (!$this->needsGeneration($series, $today)) {
                continue;
            }
            if ($result["processed"] >= $limit) {
                break;
            }

            $result["due"]++;
            $result["processed"]++;
            try {
                $generated = $occurrences->generate((int) $series->id);
                $result["created"] += (int) ($generated["created"] ?? 0);
                $result["idempotent"] += (int) ($generated["idempotent"] ?? 0);
                $result["skipped"] += (int) ($generated["skipped"] ?? 0);
            } catch (\Throwable $e) {
                $result["failed"]++;
                if (count($result["errors"]) < 20) {
                    $result["errors"][] = [
                        "series_id" => (int) $series->id,
                        "error" => $e->getMessage(),
                    ];
                }
                log_message("error", "GD recurring series generation ({series_id}): {error}", [
                    "series_id" => (int) $series->id,
                    "error" => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function needsGeneration(object $series, \DateTimeImmutable $today): bool
    {
        $horizon = max(
            1,
            min(
                Constants::BOOKING_SERIES_MAX_HORIZON_DAYS,
                (int) ($series->generation_horizon_days ?? Constants::BOOKING_SERIES_DEFAULT_HORIZON_DAYS)
            )
        );
        $through_date = $today->modify("+{$horizon} days");
        $through = $through_date->format("Y-m-d");

        // Uma série que ainda não entrou no horizonte não precisa criar um lote
        // vazio; ela será considerada quando o horizonte deslizante a alcançar.
        if ((string) $series->starts_on > $through) {
            return false;
        }

        $last = trim((string) ($series->last_generated_until ?? ""));
        if ($last === "") {
            return true;
        }

        // last_generated_until stores the last occurrence date, not the date
        // through which the calendar was inspected. Leave a recurrence-sized
        // buffer so weekly/monthly series are not retried on every cron tick.
        $interval = max(1, (int) ($series->interval_value ?? 1));
        $buffer_days = match ((string) ($series->frequency ?? "weekly")) {
            "daily" => $interval,
            "weekly" => 7 * $interval,
            "monthly" => 31 * $interval,
            default => 7,
        };
        $refresh_at = $through_date->modify("-{$buffer_days} days")->format("Y-m-d");

        return $last < $refresh_at;
    }
}
