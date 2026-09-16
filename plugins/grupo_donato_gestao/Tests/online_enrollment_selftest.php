<?php

declare(strict_types=1);

use grupo_donato_gestao\Services\OnlineContractService;
use grupo_donato_gestao\Services\OnlineEnrollmentService;

/** Exercita o fluxo público contra o MySQL local, removendo a fixture ao fim. */
function gd_online_enrollment_selftest(): void
{
    $db = db_connect();
    $prefix = $db->getPrefix();
    $unit = $db->table($prefix . "grupo_donato_unidades")->where("deleted", 0)->where("status", "Ativo")->orderBy("is_default", "DESC")->orderBy("id", "ASC")->get(1)->getRowArray();
    gd_assert("unidade operacional disponível para matrícula online", (bool) $unit);
    if (!$unit) return;

    $suffix = substr(bin2hex(random_bytes(8)), 0, 12);
    $payload = [
        "idempotency_key" => "selftest-online-" . $suffix,
        "responsavel_nome" => "Responsável Online " . $suffix,
        "responsavel_whats" => "551199999" . substr($suffix, 0, 4),
        "responsavel_cpf" => "123.456.789-09",
        "responsavel_rg" => "RG-" . $suffix,
        "responsavel_email" => "online-" . $suffix . "@example.test",
        "responsavel_nascimento" => "1988-04-03",
        "nome_aluno" => "Aluno Online " . $suffix,
        "nascimento_aluno" => "2015-06-07",
        "cpf_aluno" => "987.654.321-00",
        "rg_aluno" => "ALUNO-" . $suffix,
        "horario" => "",
        "tamanho_camisa" => "M",
        "data_inicio" => date("Y-m-d"),
    ];
    $service = new OnlineEnrollmentService();
    $draft = null;
    $studentId = 0;
    $responsibleId = 0;
    $contractId = 0;
    $signature = null;
    try {
        $draft = $service->createDraft((int) $unit["id"], $payload);
        gd_assert("formulário cria rascunho sem criar aluno", $draft["status"] === "contract_pending" && empty($draft["student_id"]));
        $draftRetry = $service->createDraft((int) $unit["id"], $payload);
        gd_assert("repetição do formulário retorna o mesmo token", $draftRetry["token"] === $draft["token"]);
        gd_assert("contrato é gerado com os dados do formulário", str_contains((string) $draft["contract_html"], htmlspecialchars($payload["nome_aluno"], ENT_QUOTES, "UTF-8")) && str_contains((string) $draft["contract_html"], "3.1-"));
        gd_assert("assinatura sem aceite é bloqueada", gd_throws(fn() => $service->saveSignature($draft["token"], (int) $unit["id"], "data:image/png;base64,abc")));
        gd_assert("aceite desmarcado é bloqueado", gd_throws(fn() => $service->accept($draft["token"], (int) $unit["id"], false, "127.0.0.1", "selftest")));
        $accepted = $service->accept($draft["token"], (int) $unit["id"], true, "127.0.0.1", "selftest");
        gd_assert("aceite é registrado antes da assinatura", $accepted["accepted"] === true && $accepted["status"] === "contract_accepted");
        gd_assert("assinatura vazia é bloqueada", gd_throws(fn() => $service->saveSignature($draft["token"], (int) $unit["id"], "")));

        $image = imagecreatetruecolor(900, 410);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);
        $black = imagecolorallocatealpha($image, 25, 25, 25, 0);
        for ($x = 120; $x < 760; $x += 8) imageline($image, $x, 230 + (int) (sin($x / 35) * 45), $x + 8, 230 + (int) (sin(($x + 8) / 35) * 45), $black);
        ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
        $signed = $service->saveSignature($draft["token"], (int) $unit["id"], "data:image/png;base64," . base64_encode((string) $png));
        gd_assert("assinatura desenhada é salva como arquivo", $signed["signature_registered"] === true && is_file((new OnlineContractService())->absolutePath((string) $signed["token"] ? "gd_online_contracts/draft-" . (int) ($db->table($prefix . "gd_online_enrollment_drafts")->where("public_token_hash", hash("sha256", $draft["token"]))->get(1)->getRow()->id ?? 0) . "/signature.png" : "")));
        $final = $service->finalize($draft["token"], (int) $unit["id"]);
        $studentId = (int) ($final["student_id"] ?? 0);
        $contractId = (int) ($db->table($prefix . "gd_online_contracts")->where("draft_id", (int) ($db->table($prefix . "gd_online_enrollment_drafts")->where("public_token_hash", hash("sha256", $draft["token"]))->get(1)->getRow()->id ?? 0))->get(1)->getRow()->id ?? 0);
        $responsibleId = (int) ($db->table($prefix . "grupo_donato_alunos")->where("id", $studentId)->get(1)->getRow()->responsavel_id ?? 0);
        $contract = $db->table($prefix . "gd_online_contracts")->where("id", $contractId)->get(1)->getRowArray();
        gd_assert("finalização cria aluno e contrato", $final["status"] === "completed" && $studentId > 0 && $contractId > 0);
        gd_assert("PDF final existe, tem assinatura e hash", $contract && is_file((new OnlineContractService())->absolutePath((string) $contract["pdf_path"])) && strlen((string) $contract["pdf_sha256"]) === 64 && str_contains((string) $contract["content_html"], "ASSINATURA DO CONTRATANTE"));
        $studentCount = $db->table($prefix . "grupo_donato_alunos")->where("id", $studentId)->countAllResults();
        $chargeCount = $db->table($prefix . "grupo_donato_cobrancas")->where("aluno_id", $studentId)->countAllResults();
        $retryFinal = $service->finalize($draft["token"], (int) $unit["id"]);
        gd_assert("repetição da finalização não duplica aluno ou cobranças", (int) $retryFinal["student_id"] === $studentId && $db->table($prefix . "grupo_donato_alunos")->where("id", $studentId)->countAllResults() === $studentCount && $db->table($prefix . "grupo_donato_cobrancas")->where("aluno_id", $studentId)->countAllResults() === $chargeCount);
        gd_assert("IDOR de contrato é bloqueado pelo token e unidade", gd_throws(fn() => $service->state(str_repeat("a", 64), ((int) $unit["id"]) + 999999)));
        $delivery = $db->table($prefix . "gd_online_contract_deliveries")->where("contract_id", $contractId)->get(1)->getRowArray();
        gd_assert("falha de WhatsApp não desfaz aluno", $delivery && in_array($delivery["status"], ["failed", "pending", "sent"], true));
    } finally {
        $draftRow = $db->table($prefix . "gd_online_enrollment_drafts")->where("public_token_hash", hash("sha256", (string) ($draft["token"] ?? "")))->get(1)->getRowArray();
        $draftId = (int) ($draftRow["id"] ?? 0);
        if ($contractId) $db->table($prefix . "gd_online_contract_deliveries")->where("contract_id", $contractId)->delete();
        if ($contractId) $db->table($prefix . "gd_online_contracts")->where("id", $contractId)->delete();
        if ($studentId) { $db->table($prefix . "grupo_donato_cobrancas")->where("aluno_id", $studentId)->delete(); $db->table($prefix . "grupo_donato_alunos")->where("id", $studentId)->delete(); }
        if ($responsibleId) $db->table($prefix . "grupo_donato_responsaveis")->where("id", $responsibleId)->delete();
        if ($draftId) $db->table($prefix . "gd_online_enrollment_drafts")->where("id", $draftId)->delete();
        if ($draftId) foreach (["gd_online_contracts/draft-" . $draftId . "/signature.png", "gd_online_contracts/draft-" . $draftId . "/contrato.html", "gd_online_contracts/draft-" . $draftId . "/contrato.pdf"] as $file) @unlink((new OnlineContractService())->absolutePath($file));
    }
}
