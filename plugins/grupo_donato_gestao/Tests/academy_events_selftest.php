<?php

declare(strict_types=1);

/** Focused integration test for the Academy event workspace. */
function gd_academy_events_selftest(): void
{
    $db = db_connect();
    $prefix = $db->getPrefix();
    $tables = [
        "gd_academy_events", "gd_academy_event_categories", "gd_academy_event_matches",
        "gd_academy_external_athletes", "gd_academy_event_participants", "gd_academy_event_confirmations",
        "gd_academy_event_checklist", "gd_academy_evaluation_criteria", "gd_academy_athlete_evaluations",
        "gd_academy_evaluation_scores", "gd_academy_match_player_stats",
    ];
    gd_assert("schema de eventos aplicado", array_reduce($tables, static fn(bool $ok, string $table): bool => $ok && $db->tableExists($prefix . $table), true));

    $professor = (object) ["user_type" => "staff", "job_title" => "Professor", "permissions" => ["gd_academy_events_view" => "1"]];
    $professorAccess = new \grupo_donato_gestao\Services\AccessService($professor);
    gd_assert("professor recebe somente permissao Academy explicitamente atribuida", $professorAccess->can("gd_academy_events_view") && !$professorAccess->can("gd_finance_view"));
    $professorWithoutEvents = (object) ["user_type" => "staff", "job_title" => "Professor", "permissions" => []];
    gd_assert("professor sem permissao nao recebe a aba de eventos", !(new \grupo_donato_gestao\Services\AccessService($professorWithoutEvents))->can("gd_academy_events_view") && !in_array("eventos", \grupo_donato_gestao\Services\RoleAccessService::allowed_operational_sections($professorWithoutEvents), true));

    $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    if (!$unit) {
        gd_assert("unidade ativa disponível para o self-test", false);
        return;
    }
    $unitId = (int) $unit->id;
    $legacyUnitId = (int) ($db->table($prefix . "grupo_donato_unidades")->where("status", "Ativo")->where("deleted", 0)->orderBy("id", "ASC")->get(1)->getRow()->id ?? 0);
    $student = $db->table($prefix . "grupo_donato_alunos")->where("unidade_id", $legacyUnitId)->where("status", "Ativo")->where("deleted", 0)->orderBy("id", "ASC")->get(1)->getRow();
    gd_assert("cadastro legado fornece atleta interno", $student !== null);
    if (!$student) return;

    $service = new \grupo_donato_gestao\Services\AcademyEventService($unitId, 0, null, $legacyUnitId);
    $positionOptions = $service::positionOptions();
    gd_assert("funções esportivas usam opções fechadas", isset($positionOptions["Goleiro"], $positionOptions["Atacante"]));
    $goalkeeperCriteria = array_filter($service->criteria("Goleiro"), static fn($criterion): bool => (string) ($criterion->scope ?? "") === "position" && (string) ($criterion->position_key ?? "") === "goalkeeper");
    $attackerCriteria = array_filter($service->criteria("Atacante"), static fn($criterion): bool => (string) ($criterion->scope ?? "") === "position");
    gd_assert("critérios de goleiro são filtrados pela posição", count($goalkeeperCriteria) === 4 && count($attackerCriteria) === 0);
    $token = substr(hash("sha256", uniqid("academy-events-", true)), 0, 12);
    $eventId = $categoryId = $matchId = $participantId = $externalParticipantId = $externalAthleteId = $receivableId = $accountId = $staffId = $confirmationId = $evaluationId = $statsId = 0;
    $accountWasExisting = $db->table($prefix . "gd_customer_accounts")->where("legacy_responsible_id", (int) $student->responsavel_id)->where("unit_id", $unitId)->where("deleted", 0)->countAllResults() > 0;
    try {
        $event = $service->saveEvent(["name" => "Self-test Academy " . $token, "event_type" => "championship", "starts_on" => "2099-02-10", "ends_on" => "2099-02-11", "status" => "confirmed", "default_participation_amount" => "75.00"]);
        $eventId = (int) $event["id"];
        $category = $service->saveCategory($eventId, ["name" => "Sub-test " . $token, "min_age" => 99, "max_age" => 100, "participation_amount" => "75.00"]);
        $categoryId = (int) $category["id"];
        $match = $service->saveMatch($categoryId, ["name" => "Jogo teste", "opponent" => "Adversário teste", "match_date" => "2099-02-10"]);
        $matchId = (int) $match["id"];
        $participant = $service->addParticipant($categoryId, ["athlete_type" => "internal", "student_id" => (int) $student->id]);
        $participantId = (int) $participant["id"];
        $external = $service->addParticipant($categoryId, ["athlete_type" => "external", "external_name" => "Convidado " . $token, "birth_date" => "2015-05-10", "responsible_id" => (int) $student->responsavel_id, "origin_club" => "Clube parceiro"]);
        $externalParticipantId = (int) $external["id"];
        $externalRow = $db->table($prefix . "gd_academy_event_participants")->where("id", $externalParticipantId)->get(1)->getRow();
        $externalAthleteId = (int) ($externalRow->external_athlete_id ?? 0);
        $service->updateParticipant($externalParticipantId, ["lineup_status" => "cut", "confirmation_status" => "confirmed"]);
        $courtesy = $service->setParticipantFinancialStatus($externalParticipantId, "courtesy", "Parceria de teste");
        gd_assert("cria evento, categoria e convocação fora da faixa como exceção autorizada", $eventId > 0 && $categoryId > 0 && $matchId > 0 && $participantId > 0 && $externalParticipantId > 0 && $externalAthleteId > 0 && ($participant["age_compatible"] ?? true) === false && !empty($courtesy["saved"]));
        gd_assert("finalização bloqueia pendências sem justificativa", gd_throws(fn() => $service->finalizeEvent($eventId), "gd_event_pending"));

        $confirmation = $service->saveConfirmation($participantId, ["status" => "confirmed", "origin" => "selftest"]);
        $confirmationId = (int) ($confirmation["id"] ?? 0);
        gd_assert("confirmação fica registrada", !empty($confirmation["saved"]));
        $score = $service->criteria()[0] ?? null;
        $evaluation = $service->saveEvaluation($participantId, ["match_id" => $matchId, "scores" => $score ? [(int) $score->id => "4.5"] : [], "performance_classification" => "good", "strengths" => "Teste", "internal_note" => "Não compartilhar"]);
        $evaluationId = (int) ($evaluation["id"] ?? 0);
        gd_assert("avaliação relacional aceita nota de 1 a 5", !empty($evaluation["saved"]));
        $stats = $service->saveStats($participantId, ["match_id" => $matchId, "goals" => 1, "assists" => 2, "minutes_played" => 30]);
        $statsId = (int) ($stats["id"] ?? 0);
        gd_assert("estatística individual fica vinculada à partida", !empty($stats["saved"]));
        $result = $service->saveMatchScore($matchId, ["gd_score" => 2, "opponent_score" => 1, "status" => "completed"]);
        gd_assert("placar tem endpoint próprio e auditável", !empty($result["saved"]));
        $user = $db->table($prefix . "users")->where("deleted", 0)->orderBy("id", "ASC")->get(1)->getRow();
        if ($user) {
            $staff = $service->saveStaff($eventId, ["user_id" => (int) $user->id, "role" => "coach", "notes" => "Self-test"]);
            $staffId = (int) ($staff["id"] ?? 0);
        }
        gd_assert("equipe do evento aceita função técnica", !$user || $staffId > 0);

        $charged = $service->chargeParticipant($participantId, ["charge_strategy" => "open", "due_date" => "2099-02-05"]);
        $receivableId = (int) ($charged["id"] ?? 0);
        $accountId = (int) ($db->table($prefix . "gd_customer_accounts")->where("legacy_responsible_id", (int) $student->responsavel_id)->where("unit_id", $unitId)->where("deleted", 0)->get(1)->getRow()->id ?? 0);
        $chargedAgain = $service->chargeParticipant($participantId, ["charge_strategy" => "open", "due_date" => "2099-02-05"]);
        gd_assert("cobrança é idempotente por evento e participante", $receivableId > 0 && empty($chargedAgain["created"]) && !empty($chargedAgain["duplicate"]));

        $workspace = $service->getEvent($eventId);
        $evaluatedParticipant = $workspace ? (array_values(array_filter($workspace["participants"], static fn($row): bool => !empty($row->evaluation)))[0] ?? null) : null;
        gd_assert("workspace retorna participantes, nota, estatística e histórico", $workspace && count($workspace["participants"]) === 2 && $evaluatedParticipant && count($evaluatedParticipant->scores) === 1 && !empty($evaluatedParticipant->match_stats[$matchId]) && count($workspace["history"]) > 0 && (int) $workspace["metrics"]["categories"] === 1);
        $overview = $service->eventOverview($eventId);
        $categoryView = $service->categoryOverview($eventId, $categoryId);
        $categoryParticipants = $service->categoryParticipants($eventId, $categoryId);
        $externalCategoryRow = array_values(array_filter($categoryParticipants, static fn($row): bool => (int) $row->id === $externalParticipantId))[0] ?? null;
        $categoryMatches = $service->categoryMatches($eventId, $categoryId);
        $categoryEvaluations = $service->categoryEvaluations($eventId, $categoryId);
        $categoryStats = $service->categoryStats($eventId, $categoryId);
        $matchView = $service->matchOverview($eventId, $categoryId, $matchId);
        $matchParticipants = $service->matchParticipants($eventId, $categoryId, $matchId);
        $evaluationView = $service->evaluationDetail($eventId, $categoryId, $participantId);
        $financeView = $service->eventFinance($eventId);
        $financeOpenPage = $service->eventFinancePage($eventId, ["status_pagamento" => "open"]);
        $financeGuestPage = $service->eventFinancePage($eventId, ["search_by" => "Convidado"]);
        $checklistView = $service->eventChecklist($eventId);
        gd_assert("leitura de resumo do evento", !empty($overview["event"]));
        gd_assert("leitura de categorias do evento", count($service->eventCategories($eventId)) === 1);
        gd_assert("leitura de resumo da categoria", count($categoryView["metrics"]) > 0);
        gd_assert("leitura de convocação da categoria", count($categoryParticipants) === 2);
        gd_assert("leitura de atleta convidado preserva clube de origem", $externalCategoryRow && (string) ($externalCategoryRow->origin_club ?? "") === "Clube parceiro");
        gd_assert("leitura de partidas da categoria", count($categoryMatches) === 1);
        gd_assert("leitura de avaliações da categoria", count($categoryEvaluations) === 1);
        gd_assert("leitura de estatísticas da categoria", count($categoryStats) === 1);
        gd_assert("leitura de resumo da partida", (int) $matchView["match"]->id === $matchId);
        gd_assert("leitura de escalação da partida", count($matchParticipants) === 1);
        gd_assert("leitura de avaliação individual", (int) $evaluationView["participant"]->id === $participantId);
        gd_assert("leitura de financeiro do evento", count($financeView["participants"]) === 2);
        gd_assert("filtros da aba de pagamentos do evento", count($financeOpenPage["data"]) === 1 && count($financeGuestPage["data"]) === 1);
        gd_assert("leitura de checklist do evento", isset($checklistView["checklist"]));
        $studentHistory = $service->studentHistory((int) $student->id);
        gd_assert("histórico do aluno usa o mesmo cadastro legado", count($studentHistory["events"]) >= 1 && count(array_filter($studentHistory["events"], static fn($row): bool => (int) $row->event_id === $eventId)) === 1);
        gd_assert("relatório trimestral calcula médias do aluno", ($service->studentProgressReport((int) $student->id, "2099-01-01", "2099-12-31")["event_count"] ?? 0) === 1);
        gd_assert("filtro de categoria resolve categorias do evento", count(array_filter($service->categoryOptions(), static fn($option): bool => (int) $option->id === $categoryId)) === 1);
        gd_assert("dashboard aceita filtro por categoria", count($service->dashboard(["category_id" => $categoryId])["events"] ?? []) === 1);
        gd_assert("conta familiar reúne recebível moderno", count($service->familyAccount((int) $student->responsavel_id)["receivables"]) >= 1);
        $finalized = $service->finalizeEvent($eventId);
        gd_assert("finaliza sem pendências quando checklist, avaliação, confirmação e placar estão completos", !empty($finalized["saved"]) && empty($finalized["pending"]));
    } finally {
        $audit = $prefix . "gd_audit_logs";
        if ($receivableId > 0) {
            $db->table($prefix . "gd_receivable_items")->where("receivable_id", $receivableId)->delete();
            $db->table($prefix . "gd_receivables")->where("id", $receivableId)->where("unit_id", $unitId)->delete();
        }
        if ($participantId > 0) {
            $evaluationIds = array_column($db->table($prefix . "gd_academy_athlete_evaluations")->select("id")->where("participant_id", $participantId)->get()->getResultArray(), "id");
            if ($evaluationIds) $db->table($prefix . "gd_academy_evaluation_scores")->whereIn("evaluation_id", array_map("intval", $evaluationIds))->delete();
            $db->table($prefix . "gd_academy_athlete_evaluations")->where("participant_id", $participantId)->delete();
            $db->table($prefix . "gd_academy_match_player_stats")->where("participant_id", $participantId)->delete();
            $db->table($prefix . "gd_academy_event_confirmations")->where("participant_id", $participantId)->delete();
            $db->table($prefix . "gd_academy_event_participants")->where("id", $participantId)->delete();
        }
        if ($externalParticipantId > 0) $db->table($prefix . "gd_academy_event_participants")->where("id", $externalParticipantId)->delete();
        if ($externalAthleteId > 0) $db->table($prefix . "gd_academy_external_athletes")->where("id", $externalAthleteId)->where("unit_id", $unitId)->delete();
        if ($matchId > 0) $db->table($prefix . "gd_academy_event_matches")->where("id", $matchId)->delete();
        if ($categoryId > 0) $db->table($prefix . "gd_academy_event_categories")->where("id", $categoryId)->delete();
        if ($eventId > 0) { $db->table($prefix . "gd_academy_event_staff")->where("event_id", $eventId)->delete(); $db->table($prefix . "gd_academy_event_checklist")->where("event_id", $eventId)->delete(); $db->table($prefix . "gd_academy_events")->where("id", $eventId)->delete(); }
        if ($accountId > 0 && !$accountWasExisting) $db->table($prefix . "gd_customer_accounts")->where("id", $accountId)->where("legacy_responsible_id", (int) $student->responsavel_id)->delete();
        foreach ([["academy_event", $eventId], ["academy_event_category", $categoryId], ["academy_event_match", $matchId], ["academy_event_participant", $participantId], ["academy_event_participant", $externalParticipantId], ["academy_event_staff", $staffId], ["academy_athlete_evaluation", $evaluationId], ["academy_match_player_stats", $statsId]] as [$entity, $id]) {
            if ($id > 0) $db->table($audit)->where("entity_type", $entity)->where("entity_id", $id)->delete();
        }
    }
}
