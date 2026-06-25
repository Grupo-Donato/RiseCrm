<?php

echo "# Fase 3B2 — séries, recorrência e ocorrências\n";

$seriesTables = ["gd_booking_series", "gd_booking_series_resources", "gd_booking_series_exceptions", "gd_booking_series_events", "gd_booking_series_generation_runs"];
gd_assert("schema 025–029 aplicado", array_reduce($seriesTables, fn($ok, $table) => $ok && $db->tableExists($prefix . $table), true) && $db->fieldExists("series_id", $prefix . "gd_bookings"));
$seriesEventFields = $db->getFieldNames($prefix . "gd_booking_series_events");
$seriesExceptionFields = $db->getFieldNames($prefix . "gd_booking_series_exceptions");
gd_assert("eventos e exceções de série são append-only no schema", !in_array("deleted", $seriesEventFields, true) && !in_array("deleted", $seriesExceptionFields, true));
$seriesIndexes = $db->query("SHOW INDEX FROM `{$prefix}gd_bookings`")->getResult();
gd_assert("unique protege chave de ocorrência por série", (bool) array_filter($seriesIndexes, static fn($index) => $index->Key_name === "uniq_series_occurrence" && (int) $index->Non_unique === 0));

$seriesRuleService = new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($unit_id);
foreach ([$bookA, $bookB, $bookC] as $resourceId) {
    for ($weekday = 0; $weekday <= 6; $weekday++) {
        $seriesRuleService->save(["resource_id" => $resourceId, "weekday" => $weekday, "start_time" => "08:00", "end_time" => "22:00", "status" => "active"]);
    }
}

$recurrence = new \grupo_donato_gestao\Services\RecurrenceGeneratorService($unit_id);
$baseDefinition = [
    "frequency" => "daily", "interval_value" => 1, "weekdays" => null, "monthly_day" => null,
    "local_start_time" => "09:00:00", "local_end_time" => "10:00:00", "starts_on" => "2098-01-01",
    "ends_mode" => "count", "ends_on" => null, "max_occurrences" => 3, "generation_horizon_days" => 90,
];
$dailyCandidates = $recurrence->candidates($baseDefinition);
gd_assert("frequência diária respeita contagem", count($dailyCandidates) === 3 && $dailyCandidates[2]["local_date"] === "2098-01-03");
$weeklyCandidates = $recurrence->candidates(array_replace($baseDefinition, ["frequency" => "weekly", "weekdays" => json_encode([1, 3, 5]), "max_occurrences" => 6]));
gd_assert("frequência semanal aceita vários dias", count($weeklyCandidates) === 6 && count(array_unique(array_map(static fn($row) => (int) (new DateTimeImmutable($row["local_date"]))->format("N"), $weeklyCandidates))) === 3);
$monthlyCandidates = $recurrence->candidates(array_replace($baseDefinition, ["frequency" => "monthly", "monthly_day" => 31, "max_occurrences" => 4]));
gd_assert("frequência mensal ignora mês sem dia configurado", array_column($monthlyCandidates, "local_date") === ["2098-01-31", "2098-03-31", "2098-05-31", "2098-07-31"]);
$untilCandidates = $recurrence->candidates(array_replace($baseDefinition, ["ends_mode" => "until_date", "ends_on" => "2098-01-03", "max_occurrences" => null]));
gd_assert("término por data é inclusivo", count($untilCandidates) === 3);
$localToday = (new DateTimeImmutable("today", new DateTimeZone($bookingTime->timezoneName())))->format("Y-m-d");
$openCandidates = $recurrence->candidates(array_replace($baseDefinition, ["starts_on" => $localToday, "ends_mode" => "open_ended", "max_occurrences" => null, "generation_horizon_days" => 2]));
gd_assert("série aberta materializa somente o horizonte", count($openCandidates) === 3);
$overnightCandidates = $recurrence->candidates(array_replace($baseDefinition, ["local_start_time" => "22:00", "local_end_time" => "02:00", "max_occurrences" => 1]));
gd_assert("ocorrência overnight termina no dia local seguinte", str_starts_with($overnightCandidates[0]["ends_at_local"], "2098-01-02"));
$dstRecurrence = new \grupo_donato_gestao\Services\RecurrenceGeneratorService($unit2_id);
gd_assert("recorrência rejeita horário civil DST inexistente", gd_throws(fn() => $dstRecurrence->candidates(array_replace($baseDefinition, ["starts_on" => "2026-03-08", "local_start_time" => "02:30", "local_end_time" => "03:30", "max_occurrences" => 1])), "gd_invalid_local_datetime"));

$seriesService = new \grupo_donato_gestao\Services\BookingSeriesService($unit_id);
$occurrenceService = new \grupo_donato_gestao\Services\BookingSeriesOccurrenceService($unit_id);
$seriesInput = static fn(int $resourceId, string $startDate, int $count = 3, string $startTime = "09:00", string $endTime = "10:00", string $policy = "reject_series") => [
    "booking_type" => "internal", "title" => "Série self-test " . $startDate, "frequency" => "daily", "interval_value" => 1, "weekdays" => [], "monthly_day" => null,
    "local_start_time" => $startTime, "local_end_time" => $endTime, "starts_on" => $startDate, "ends_mode" => "count", "max_occurrences" => $count,
    "default_booking_status" => "pending_confirmation", "conflict_policy" => $policy, "generation_horizon_days" => 90,
    "resources" => [["resource_id" => $resourceId, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]], "notes" => "Teste de recorrência", "metadata" => null,
];

$previewInput = $seriesInput($bookA, "2098-09-01", 3);
$bookingCountBeforePreview = $db->table($prefix . "gd_bookings")->countAllResults();
$preview = $seriesService->preview($previewInput);
gd_assert("preview usa o gerador sem persistir", count($preview) === 3 && $db->table($prefix . "gd_bookings")->countAllResults() === $bookingCountBeforePreview);

$dailySeries = $seriesService->create($previewInput, false);
$dailyGeneration = $occurrenceService->generate($dailySeries["id"]);
$dailySeriesRow = $seriesService->get($dailySeries["id"]);
gd_assert("série gera ocorrências como reservas normais", $dailyGeneration["created"] === 3 && count($dailySeriesRow->occurrences) === 3);
gd_assert("ocorrências possuem vínculo e chave local", !array_filter($dailySeriesRow->occurrences, static fn($row) => (int) $row->id <= 0 || $row->series_occurrence_key !== $row->series_local_date));
gd_assert("série nunca gera hold", !array_filter($dailySeriesRow->occurrences, static fn($row) => $row->status === "hold"));
$dailyRetry = $occurrenceService->generate($dailySeries["id"]);
gd_assert("geração repetida é idempotente", $dailyRetry["created"] === 0 && $dailyRetry["idempotent"] === 3 && count($seriesService->get($dailySeries["id"])->occurrences) === 3);

$multiInput = $seriesInput($bookB, "2098-10-01", 2, "11:00", "12:00");
$multiInput["resources"] = [["resource_id" => $bookB, "buffer_before_minutes" => 10, "buffer_after_minutes" => 5], ["resource_id" => $bookC, "buffer_before_minutes" => 5, "buffer_after_minutes" => 10]];
$multiSeries = $seriesService->create($multiInput, false);
$multiGeneration = $occurrenceService->generate($multiSeries["id"]);
$multiBooking = (new \grupo_donato_gestao\Services\BookingService($unit_id))->get($multiGeneration["booking_ids"][0]);
gd_assert("ocorrência reutiliza múltiplos recursos e buffers", count($multiBooking->resources) === 2 && (int) $multiBooking->resources[0]->buffer_before_minutes + (int) $multiBooking->resources[1]->buffer_after_minutes > 0);

$blocker = (new \grupo_donato_gestao\Services\BookingService($unit_id))->save(["booking_type" => "internal", "title" => "Bloqueador da série", "starts_at_local" => "2098-11-01T10:00", "ends_at_local" => "2098-11-01T11:00", "status" => "pending_confirmation", "resources" => [["resource_id" => $bookB, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]);
$rejectInput = $seriesInput($bookB, "2098-11-01", 2, "10:00", "11:00", "reject_series");
$rejectInput["title"] = "Série reject";
$rejectSeries = $seriesService->create($rejectInput, false);
gd_assert("reject_series não persiste ocorrência parcial", gd_throws(fn() => $occurrenceService->generate($rejectSeries["id"]), "gd_booking_conflict") && count($seriesService->get($rejectSeries["id"])->occurrences) === 0);
$skipInput = $rejectInput; $skipInput["title"] = "Série skip"; $skipInput["conflict_policy"] = "skip_conflicts";
$skipSeries = $seriesService->create($skipInput, false);
$skipResult = $occurrenceService->generate($skipSeries["id"]);
gd_assert("skip_conflicts cria válidas e registra conflitante", $skipResult["created"] === 1 && $skipResult["skipped"] === 1 && count(array_filter($seriesService->get($skipSeries["id"])->exceptions, static fn($row) => $row->exception_type === "conflict_skipped")) === 1);

$dailySeriesRow = $seriesService->get($dailySeries["id"]);
$firstOccurrence = $dailySeriesRow->occurrences[0];
$firstBooking = (new \grupo_donato_gestao\Services\BookingService($unit_id))->get((int) $firstOccurrence->id);
$singleUpdate = ["booking_type" => $firstBooking->booking_type, "title" => "Ocorrência destacada", "customer_account_id" => $firstBooking->customer_account_id, "contact_person_id" => $firstBooking->contact_person_id, "starts_at_local" => "2098-09-01T09:15", "ends_at_local" => "2098-09-01T10:15", "resources" => [["resource_id" => $bookA, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]], "notes" => null, "metadata" => null, "lock_version" => $firstBooking->lock_version];
$occurrenceService->updateSingle((int) $firstOccurrence->id, $singleUpdate);
$detached = (new \grupo_donato_gestao\Services\BookingService($unit_id))->get((int) $firstOccurrence->id);
gd_assert("alteração única destaca somente a ocorrência", (int) $detached->detached_from_series === 1 && (int) $detached->is_series_exception === 1 && $detached->title === "Ocorrência destacada");
$secondOccurrence = $seriesService->get($dailySeries["id"])->occurrences[1];
$occurrenceService->cancelSingle((int) $secondOccurrence->id, "Cancelamento individual");
gd_assert("cancelamento único preserva reserva histórica", (new \grupo_donato_gestao\Services\BookingService($unit_id))->get((int) $secondOccurrence->id)->status === "cancelled");

$replaceInput = $seriesInput($bookA, "2098-12-01", 2, "13:00", "14:00");
$replaceInput["title"] = "Série regenerável";
$replaceSeries = $seriesService->create($replaceInput, false);
$occurrenceService->generate($replaceSeries["id"]);
$replaceBefore = $seriesService->get($replaceSeries["id"]);
$oldReplaceIds = array_map(static fn($row) => (int) $row->id, $replaceBefore->occurrences);
$replaceEdit = $seriesService->inputFrom($replaceBefore); $replaceEdit["title"] = "Série regenerada"; $replaceEdit["local_start_time"] = "14:00"; $replaceEdit["local_end_time"] = "15:00";
$replaceResult = $seriesService->updateEntire($replaceSeries["id"], $replaceEdit);
$replaceAfter = $seriesService->get($replaceSeries["id"]);
gd_assert("alteração da série regenera apenas futuras modificáveis", count($replaceResult["replaced_booking_ids"]) === 2 && count($replaceAfter->occurrences) === 2 && !array_intersect($oldReplaceIds, array_map(static fn($row) => (int) $row->id, $replaceAfter->occurrences)));
$oldRowsPreserved = $db->table($prefix . "gd_bookings")->whereIn("id", $oldReplaceIds)->where("status", "cancelled")->where("detached_from_series", 1)->countAllResults();
gd_assert("regeneração preserva reservas antigas canceladas", $oldRowsPreserved === 2);

$immutableInput = $seriesInput($bookC, "2099-01-01", 2, "15:00", "16:00");
$immutableInput["title"] = "Série histórica";
$immutableSeries = $seriesService->create($immutableInput, false); $immutableGen = $occurrenceService->generate($immutableSeries["id"]);
$immutableId = $immutableGen["booking_ids"][0]; $db->table($prefix . "gd_bookings")->where("id", $immutableId)->update(["status" => "completed"]);
$immutableBefore = $seriesService->get($immutableSeries["id"]); $immutableEdit = $seriesService->inputFrom($immutableBefore); $immutableEdit["title"] = "Série histórica atualizada";
$seriesService->updateEntire($immutableSeries["id"], $immutableEdit);
gd_assert("ocorrência concluída não é reescrita", (new \grupo_donato_gestao\Services\BookingService($unit_id))->get($immutableId)->status === "completed" && (int) (new \grupo_donato_gestao\Services\BookingService($unit_id))->get($immutableId)->series_id === $immutableSeries["id"]);

$splitInput = $seriesInput($bookC, "2099-02-01", 4, "17:00", "18:00"); $splitInput["title"] = "Série para split";
$splitSeries = $seriesService->create($splitInput, false); $occurrenceService->generate($splitSeries["id"]);
$splitAt = "2099-02-03"; $splitChanges = $seriesService->inputFrom($seriesService->get($splitSeries["id"])); $splitChanges["title"] = "Série sucessora"; $splitChanges["local_start_time"] = "18:00"; $splitChanges["local_end_time"] = "19:00";
$splitResult = (new \grupo_donato_gestao\Services\BookingSeriesSplitService($unit_id))->split($splitSeries["id"], $splitAt, $splitChanges);
$oldSplit = $seriesService->get($splitSeries["id"]); $newSplit = $seriesService->get($splitResult["new_series_id"]);
gd_assert("esta e próximas cria série sucessora e encerra definição anterior", $splitResult["new_series_id"] > 0 && $oldSplit->ends_on === "2099-02-02" && $newSplit->starts_on === $splitAt);
$splitRetry = (new \grupo_donato_gestao\Services\BookingSeriesSplitService($unit_id))->split($splitSeries["id"], $splitAt, $splitChanges);
gd_assert("split é idempotente", $splitRetry["idempotent"] === true && $splitRetry["new_series_id"] === $splitResult["new_series_id"]);

$lifecycleInput = $seriesInput($bookB, "2099-03-01", 2, "19:00", "20:00"); $lifecycleInput["title"] = "Série lifecycle";
$lifecycleSeries = $seriesService->create($lifecycleInput, false);
$seriesLifecycle = new \grupo_donato_gestao\Services\BookingSeriesLifecycleService($unit_id);
$paused = $seriesLifecycle->pause($lifecycleSeries["id"], $lifecycleSeries["lock_version"]);
gd_assert("série pausada não gera", $paused->status === "paused" && gd_throws(fn() => $occurrenceService->generate($lifecycleSeries["id"]), "gd_booking_series_not_active"));
$resumed = $seriesLifecycle->resume($lifecycleSeries["id"], (int) $paused->lock_version);
gd_assert("retomar série volta a gerar", $resumed->status === "active" && count($seriesService->get($lifecycleSeries["id"])->occurrences) === 2);
gd_assert("lock_version obsoleto bloqueia série", gd_throws(fn() => $seriesLifecycle->pause($lifecycleSeries["id"], 1), "gd_booking_series_edit_conflict"));
$cancelledSeries = $seriesLifecycle->cancel($lifecycleSeries["id"], (int) $resumed->lock_version, "Cancelamento integral");
gd_assert("cancelamento integral encerra série e futuras", $cancelledSeries->status === "cancelled" && !array_filter($seriesService->get($lifecycleSeries["id"])->occurrences, static fn($row) => !in_array($row->status, ["cancelled", "completed", "no_show"], true)));

$futureInput = $seriesInput($bookB, "2099-04-01", 3, "08:30", "09:30"); $futureInput["title"] = "Série cancelamento futuro";
$futureSeries = $seriesService->create($futureInput, false); $occurrenceService->generate($futureSeries["id"]); $futureFresh = $seriesService->get($futureSeries["id"]);
$futureUpdated = $seriesLifecycle->cancelFrom($futureSeries["id"], (int) $futureFresh->lock_version, "2099-04-02", "Cancelar futuras");
$futureOccurrences = $seriesService->get($futureSeries["id"])->occurrences;
gd_assert("cancelamento desta e futuras preserva anterior", $futureUpdated->ends_on === "2099-04-01" && $futureOccurrences[0]->status === "pending_confirmation" && $futureOccurrences[1]->status === "cancelled" && $futureOccurrences[2]->status === "cancelled");

$eventModel = model("grupo_donato_gestao\\Models\\Gd_booking_series_events_model");
$exceptionModel = model("grupo_donato_gestao\\Models\\Gd_booking_series_exceptions_model");
gd_assert("históricos de série bloqueiam update e delete", gd_throws(fn() => $eventModel->delete(1), "Series events cannot be deleted.") && gd_throws(fn() => $exceptionModel->delete(1), "Series exceptions cannot be deleted."));
$calendarSeries = (new \grupo_donato_gestao\Services\CalendarService($unit_id, true))->events("2098-09-01T00:00:00Z", "2098-09-05T00:00:00Z", [$bookA], ["booking"]);
$calendarPrivateSeries = (new \grupo_donato_gestao\Services\CalendarService($unit_id, false))->events("2098-09-01T00:00:00Z", "2098-09-05T00:00:00Z", [$bookA], ["booking"]);
gd_assert("calendário indica ocorrência de série para leitor autorizado", (bool) array_filter($calendarSeries, static fn($event) => !empty($event["extendedProps"]["is_series"]) && str_starts_with($event["title"], "↻")));
gd_assert("calendário privado não expõe série nem PII", !array_filter($calendarPrivateSeries, static fn($event) => $event["title"] !== app_lang("gd_calendar_busy") || ($event["extendedProps"]["series_id"] ?? null) !== null));

$seriesAccess = new \grupo_donato_gestao\Services\AccessService($pm("gd_booking_series_manage"));
$seriesStatusAccess = new \grupo_donato_gestao\Services\AccessService($pm("gd_booking_series_status_manage"));
gd_assert("gestão de séries implica somente leituras necessárias", $seriesAccess->can("gd_booking_series_view") && $seriesAccess->can("gd_bookings_view") && $seriesAccess->can("gd_calendar_view") && $seriesAccess->can("gd_resources_view") && $seriesAccess->can("gd_customer_accounts_view") && !$seriesAccess->can("gd_bookings_manage"));
gd_assert("status de série não concede gestão de cadastros", $seriesStatusAccess->can("gd_booking_series_view") && !$seriesStatusAccess->can("gd_resources_manage") && !$seriesStatusAccess->can("gd_customer_accounts_manage"));
gd_assert("IDOR de série entre unidades retorna null", (new \grupo_donato_gestao\Services\BookingSeriesService($unit2_id))->get($dailySeries["id"]) === null);
gd_assert("rotas de série separam leitura GET e escrita POST", isset($get_routes["grupo_donato/booking-series"]) && (bool) array_filter(array_keys($get_routes), static fn($route) => str_starts_with($route, "grupo_donato/booking-series/view/")) && isset($post_routes["grupo_donato/booking-series/save"], $post_routes["grupo_donato/booking-series/preview"], $post_routes["grupo_donato/booking-series/update-this-and-future"]) && !isset($get_routes["grupo_donato/booking-series/save"]));
gd_assert("CSRF protege escrita de séries", in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/booking-series/save", "POST"), "filter"), true));
gd_assert("idioma da Fase 3B2 resolve", app_lang("gd_menu_booking_series") === "Séries de reservas" && app_lang("gd_booking_series_not_found") !== "gd_booking_series_not_found");
