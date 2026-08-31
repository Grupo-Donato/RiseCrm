<?php

declare(strict_types=1);

/**
 * Verificação integrada do remanejamento pontual. Todos os dados ficam na
 * transação externa do harness e são descartados ao final.
 */
function gd_monthly_reschedule_selftest(): void
{
    $db = db_connect();
    $prefix = $db->getPrefix();
    $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    if (!$unit) { gd_assert("unidade disponível para remanejamento", false); return; }
    $unit_id = (int) $unit->id;
    $fields = $db->getFieldNames($prefix . "gd_booking_series_exceptions");
    gd_assert("schema de remanejamento possui snapshot e reversão", count(array_intersect(["revision", "status", "original_resource_id", "original_starts_at_utc", "original_ends_at_utc", "new_resource_id", "new_starts_at_utc", "new_ends_at_utc", "notes", "reverted_at", "reverted_by"], $fields)) === 11);

    $routes = (string) file_get_contents(__DIR__ . "/../Config/Routes.php");
    gd_assert("rota de opções de quadras da alteração pontual existe", str_contains($routes, 'court-rentals/reschedule-resource-options'));
    gd_assert("rotas de consulta, gravação e reversão existem", str_contains($routes, 'court-rentals/reschedule-availability') && str_contains($routes, 'court-rentals/reschedule/revert') && str_contains($routes, 'court-rentals/recurring-availability'));
    gd_assert("intervalos adjacentes não conflitam", !\grupo_donato_gestao\Services\TemporalService::overlaps("2099-12-01 10:00:00", "2099-12-01 11:00:00", "2099-12-01 11:00:00", "2099-12-01 12:00:00"));

    $courts = $db->table($prefix . "gd_resources")->select("id,code")->where("unit_id", $unit_id)->where("resource_type", "court")->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->orderBy("code")->limit(2)->get()->getResult();
    if (count($courts) < 2) { gd_assert("duas quadras disponíveis para o cenário", false); return; }
    $first_court = (int) $courts[0]->id;
    $second_court = (int) $courts[1]->id;
    $token = substr(hash("sha256", uniqid("gd-reschedule", true)), 0, 10);
    $db->transBegin();
    try {
        $account = (new \grupo_donato_gestao\Services\CustomerAccountService($unit_id))->save(["account_type" => "other", "display_name" => "Teste remanejamento " . $token, "document_type" => "none", "status" => "active"]);
        $rental_service = new \grupo_donato_gestao\Services\CourtRentalService($unit_id);
        $rental = $rental_service->createWithSeries([
            "rental_type" => "recurring", "title" => "Mensalista remanejamento " . $token, "customer_account_id" => $account["id"],
            "negotiated_amount" => "148.00", "preferred_due_day" => 5, "effective_from" => "2099-12-01", "has_vest" => 1, "has_ball" => 1, "vest_amount" => "10.00", "ball_amount" => "15.00",
            "frequency" => "weekly", "interval_value" => 1, "weekdays" => [1], "local_start_time" => "08:00", "local_end_time" => "09:00",
            "starts_on" => "2099-12-01", "ends_mode" => "count", "max_occurrences" => 2, "default_booking_status" => "pending_confirmation",
            "conflict_policy" => "reject_series", "generation_horizon_days" => 90,
            "resources" => [["resource_id" => $first_court, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
        ]);
        $series_before = (new \grupo_donato_gestao\Services\BookingSeriesService($unit_id))->get((int) $rental["series_id"]);
        $finance_table = $prefix . "gd_receivables";
        $finance_before = $db->table($finance_table)->select("id,original_amount,balance_amount,status")->where("unit_id", $unit_id)->where("source_type", "court_rental")->where("source_id", (int) $rental["id"])->where("deleted", 0)->orderBy("id")->get()->getResultArray();
        $booking = $db->table($prefix . "gd_bookings")->where("unit_id", $unit_id)->where("series_id", (int) $rental["series_id"])->where("deleted", 0)->orderBy("series_local_date")->get(1)->getRow();
        if (!$booking) { throw new \RuntimeException("test booking not generated"); }
        $reschedule_service = new \grupo_donato_gestao\Services\MonthlyRescheduleService($unit_id);
        $resource_options = $reschedule_service->availableResourceOptions((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "08:00"]);
        $resource_rows = [];
        foreach ($resource_options["resources"] as $resource_row) { $resource_rows[(int) $resource_row["id"]] = $resource_row; }
        gd_assert("disponibilidade exibe a quadra atual e a alternativa", !empty($resource_rows[$first_court]["is_current"]) && !empty($resource_rows[$second_court]["available"]));
        $court_only = $reschedule_service->reschedule((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "08:00", "new_resource_id" => $second_court, "reason" => "Teste somente quadra"]);
        $court_only_booking = $db->table($prefix . "gd_bookings")->where("id", (int) $booking->id)->get(1)->getRow();
        $court_only_resource = $db->table($prefix . "gd_booking_resources")->where("booking_id", (int) $booking->id)->where("deleted", 0)->get(1)->getRow();
        gd_assert("A: altera somente a quadra", $court_only["id"] > 0 && (int) $court_only_resource->resource_id === $second_court && (new \grupo_donato_gestao\Services\TemporalService($unit_id))->utcToLocal((string) $court_only_booking->starts_at_utc)->format("H:i") === "08:00");
        $reschedule_service->revert((int) $booking->id, ["reason" => "Restaurar teste de quadra"]);
        $time_only_options = $reschedule_service->availableResourceOptions((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "10:00"]);
        $time_only_rows = [];
        foreach ($time_only_options["resources"] as $resource_row) { $time_only_rows[(int) $resource_row["id"]] = $resource_row; }
        gd_assert("disponibilidade mantém a quadra atual quando o horário muda", !empty($time_only_rows[$first_court]["available"]) && empty($time_only_rows[$first_court]["is_current"]));
        $time_only = $reschedule_service->reschedule((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "10:00", "new_resource_id" => $first_court, "reason" => "Teste somente horário"]);
        $time_only_booking = $db->table($prefix . "gd_bookings")->where("id", (int) $booking->id)->get(1)->getRow();
        $time_only_resource = $db->table($prefix . "gd_booking_resources")->where("booking_id", (int) $booking->id)->where("deleted", 0)->get(1)->getRow();
        $time_only_time = new \grupo_donato_gestao\Services\TemporalService($unit_id);
        gd_assert("B: altera somente o horário", $time_only["id"] > 0 && (int) $time_only_resource->resource_id === $first_court && $time_only_time->utcToLocal((string) $time_only_booking->starts_at_utc)->format("H:i") === "10:00" && $time_only_time->utcToLocal((string) $time_only_booking->ends_at_utc)->format("H:i") === "11:00");
        $reschedule_service->revert((int) $booking->id, ["reason" => "Restaurar teste de horário"]);
        $slots = $reschedule_service->availableSlots((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "from_time" => "09:00", "until_time" => "12:00", "duration_minutes" => 60, "resource_id" => $second_court]);
        gd_assert("consulta retorna horários livres da quadra destino", !empty($slots["slots"]));
        $result = $reschedule_service->reschedule((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "10:00", "new_resource_id" => $second_court, "reason" => "Teste operacional", "notes" => "Troca pontual"]);
        $moved = $db->table($prefix . "gd_bookings")->where("id", (int) $booking->id)->get(1)->getRow();
        $moved_local = (new \grupo_donato_gestao\Services\TemporalService($unit_id))->utcToLocal((string) $moved->starts_at_utc);
        $moved_resource = $db->table($prefix . "gd_booking_resources")->where("booking_id", (int) $booking->id)->where("deleted", 0)->get(1)->getRow();
        $moved_time = new \grupo_donato_gestao\Services\TemporalService($unit_id);
        $original_start_utc = $moved_time->localToUtc((string) $booking->series_local_date, "08:00");
        $original_end_utc = $moved_time->localToUtc((string) $booking->series_local_date, "09:00");
        $availability_check = new \grupo_donato_gestao\Services\AvailabilityService($unit_id);
        $destination_check = $availability_check->check($second_court, (string) $moved->starts_at_utc, (string) $moved->ends_at_utc);
        $later_booking = $db->table($prefix . "gd_bookings")->where("unit_id", $unit_id)->where("series_id", (int) $rental["series_id"])->where("id !=", (int) $booking->id)->where("deleted", 0)->get(1)->getRow();
        $later_resource = $later_booking ? $db->table($prefix . "gd_booking_resources")->where("booking_id", (int) $later_booking->id)->where("deleted", 0)->get(1)->getRow() : null;
        gd_assert("horário original é liberado e destino fica ocupado", $availability_check->check($first_court, $original_start_utc, $original_end_utc)["available"] && !$destination_check["available"]);
        gd_assert("demais ocorrências permanecem inalteradas", $later_booking && $moved_time->utcToLocal((string) $later_booking->starts_at_utc)->format("H:i") === "08:00" && (int) ($later_resource->resource_id ?? 0) === $first_court);
        gd_assert("remanejamento altera somente a ocorrência", $result["id"] > 0 && $moved_local->format("H:i") === "10:00" && (int) $moved_resource->resource_id === $second_court && (int) $moved->detached_from_series === 1 && (int) $moved->is_series_exception === 1);
        $series_after = (new \grupo_donato_gestao\Services\BookingSeriesService($unit_id))->get((int) $rental["series_id"]);
        $rental_after_reschedule = $rental_service->get((int) $rental["id"]);
        gd_assert("remanejamento pontual preserva os adicionais do mensalista", (int) $rental_after_reschedule->has_vest === 1 && (int) $rental_after_reschedule->has_ball === 1 && $rental_after_reschedule->vest_amount === "10.00" && $rental_after_reschedule->ball_amount === "15.00");
        gd_assert("C: mantém duração ao combinar novo horário e quadra", $moved_local->format("H:i") === "10:00" && (new \grupo_donato_gestao\Services\TemporalService($unit_id))->utcToLocal((string) $moved->ends_at_utc)->format("H:i") === "11:00" && (int) $moved_resource->resource_id === $second_court);
        gd_assert("série permanece com o horário e a quadra originais", (string) $series_after->local_start_time === (string) $series_before->local_start_time && (int) $series_after->resources[0]->resource_id === $first_court);
        $finance_after = $db->table($finance_table)->select("id,original_amount,balance_amount,status")->where("unit_id", $unit_id)->where("source_type", "court_rental")->where("source_id", (int) $rental["id"])->where("deleted", 0)->orderBy("id")->get()->getResultArray();
        gd_assert("remanejamento não altera cobrança mensal", $finance_after === $finance_before);
        $again = $reschedule_service->reschedule((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "10:00", "new_resource_id" => $second_court, "reason" => "Teste operacional"]);
        gd_assert("requisição repetida é idempotente", !empty($again["idempotent"]));
        $history = $reschedule_service->historyForRental((int) $rental["id"]);
        $calendar_time = new \grupo_donato_gestao\Services\TemporalService($unit_id);
        $calendar = new \grupo_donato_gestao\Services\CalendarService($unit_id, true, "court");
        $calendar_events = $calendar->events($calendar_time->utcToIsoLocal((string) $moved->starts_at_utc), $calendar_time->utcToIsoLocal((string) $moved->ends_at_utc), [$second_court], ["booking"], ["pending_confirmation"]);
        $calendar_occurrence = array_values(array_filter($calendar_events, static fn (array $event): bool => (int) ($event["extendedProps"]["booking_id"] ?? 0) === (int) $booking->id));
        $revert_blocker = (new \grupo_donato_gestao\Services\BookingService($unit_id))->save(["booking_type" => "internal", "title" => "Bloqueador de reversão " . $token, "starts_at_local" => $booking->series_local_date . "T08:00", "ends_at_local" => $booking->series_local_date . "T09:00", "status" => "pending_confirmation", "resources" => [["resource_id" => $first_court, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]);
        $revert_blocked = false;
        try { $reschedule_service->revert((int) $booking->id, ["reason" => "Não deve reverter com conflito"]); }
        catch (\Throwable $e) { $revert_blocked = in_array($e->getMessage(), ["gd_booking_resource_unavailable", "gd_booking_conflict"], true); }
        gd_assert("reversão bloqueia quando o horário original foi ocupado", $revert_blocked);
        (new \grupo_donato_gestao\Services\BookingService($unit_id))->delete((int) $revert_blocker["id"]);
        gd_assert("calendário marca a ocorrência remanejada e recorrente", !empty($calendar_occurrence) && !empty($calendar_occurrence[0]["extendedProps"]["is_series"]) && !empty($calendar_occurrence[0]["extendedProps"]["is_rescheduled"]) && (int) ($calendar_occurrence[0]["extendedProps"]["court_rental_id"] ?? 0) === (int) $rental["id"]);
        gd_assert("histórico registra origem, destino, motivo e autor", !empty($history) && $history[0]["original_resource_id"] === $first_court && $history[0]["new_resource_id"] === $second_court && $history[0]["reason"] === "Teste operacional");
        $reverted = $reschedule_service->revert((int) $booking->id, ["reason" => "Teste de reversão"]);
        $restored = $db->table($prefix . "gd_bookings")->where("id", (int) $booking->id)->get(1)->getRow();
        $restored_resource = $db->table($prefix . "gd_booking_resources")->where("booking_id", (int) $booking->id)->where("deleted", 0)->get(1)->getRow();
        gd_assert("reversão restaura horário, quadra e vínculo da série", $reverted["reverted"] && (int) $restored->detached_from_series === 0 && (int) $restored_resource->resource_id === $first_court && (new \grupo_donato_gestao\Services\TemporalService($unit_id))->utcToLocal((string) $restored->starts_at_utc)->format("H:i") === "08:00");
        $history = $reschedule_service->historyForRental((int) $rental["id"]);
        gd_assert("reversão preserva o registro histórico", $history[0]["status"] === "reverted" && $history[0]["reverted_by"] === 0);
        $blocker = (new \grupo_donato_gestao\Services\BookingService($unit_id))->save(["booking_type" => "internal", "title" => "Bloqueador " . $token, "starts_at_local" => $booking->series_local_date . "T11:00", "ends_at_local" => $booking->series_local_date . "T12:00", "status" => "pending_confirmation", "resources" => [["resource_id" => $second_court, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]);
        $blocked = false;
        try { $reschedule_service->reschedule((int) $booking->id, ["occurrence_date" => $booking->series_local_date, "new_start_time" => "11:00", "new_resource_id" => $second_court, "reason" => "Teste de conflito"]); }
        catch (\Throwable $e) { $blocked = in_array($e->getMessage(), ["gd_booking_resource_unavailable", "gd_booking_conflict"], true); }
        gd_assert("conflito concorrente impede o remanejamento", $blocked);
        $db->transRollback();
    } catch (\Throwable $e) {
        $db->transRollback();
        gd_assert("cenário integrado de remanejamento conclui sem exceção", false, $e->getMessage());
    }
}
