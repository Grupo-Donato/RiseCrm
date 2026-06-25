<?php

echo "# Fase 6 — importação assistida\n";

$importTables = ["gd_import_batches", "gd_import_rows", "gd_import_issues", "gd_import_links"];
gd_assert("schema 046–049 aplicado", array_reduce($importTables, fn($ok, $t) => $ok && $db->tableExists($prefix . $t), true));
$linkIndexes = $db->query("SHOW INDEX FROM `{$prefix}gd_import_links`")->getResult();
gd_assert("unique protege vínculo lote/linha/alvo", (bool) array_filter($linkIndexes, static fn($i) => $i->Key_name === "uniq_batch_row_target" && (int) $i->Non_unique === 0));

$tk = substr(hash("sha256", uniqid("imp", true)), 0, 8);
$importTmp = [];
$writeCsv = function (array $rows) use (&$importTmp): string {
    $path = tempnam(sys_get_temp_dir(), "gdimp") . ".csv";
    $fh = fopen($path, "w");
    foreach ($rows as $r) { fputcsv($fh, $r); }
    fclose($fh);
    $importTmp[] = $path;
    return $path;
};
$writeXlsx = function (array $rows) use (&$importTmp): string {
    require_once rtrim(APPPATH, "/\\") . "/ThirdParty/PHPOffice-PhpSpreadsheet/vendor/autoload.php";
    $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $book->getActiveSheet();
    foreach ($rows as $i => $r) { foreach (array_values($r) as $j => $v) { $sheet->setCellValue([$j + 1, $i + 1], $v); } }
    $path = tempnam(sys_get_temp_dir(), "gdimp") . ".xlsx";
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
    $importTmp[] = $path;
    return $path;
};

$importSvc = new \grupo_donato_gestao\Services\ImportBatchService($unit_id);
$countOf = fn(string $t) => (int) $db->table($prefix . $t)->where("unit_id", $unit_id)->countAllResults();

// Turma e recurso dedicados (capacidade e disponibilidade garantidas).
$impClass = (new \grupo_donato_gestao\Services\SchoolClassService($unit_id))->save(["name" => "Turma Import " . $tk, "modality" => "Futebol", "class_type" => "group", "capacity" => 20, "status" => "active"]);
$impResource = (new \grupo_donato_gestao\Services\ResourceService($unit_id))->save(["code" => "IMPQ" . substr($tk, 0, 4), "name" => "Quadra import", "resource_type" => "court", "is_active" => 1, "is_bookable" => 1]);
$impRules = new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($unit_id);
for ($w = 0; $w <= 6; $w++) { $impRules->save(["resource_id" => (int) $impResource["id"], "weekday" => $w, "start_time" => "08:00", "end_time" => "22:00", "status" => "active"]); }

/* ===================== Alunos e pagamentos ===================== */
$schoolFile = $writeCsv([
    ["Aluno", "Responsavel", "Telefone", "Turma", "Mes", "Valor", "Vencimento", "Data Pagamento", "Forma"],
    ["Maria Import " . $tk, "Ana Resp " . $tk, "1199990000", "Turma Import " . $tk, "2025-03", "150,00", "2025-03-10", "2025-03-08", "Pix"],
    ["Joao Invalido " . $tk, "", "", "", "2025-03", "abc", "", "", ""],
    ["Pedro Revisao " . $tk, "", "", "", "2025-04", "100,00", "", "2025-04-05", "Bitcoin"],
]);
$recBefore = $countOf("gd_receivables"); $payBefore = $countOf("gd_payments");
$schoolBatch = $importSvc->createBatch(["import_type" => "school_payments", "file_path" => $schoolFile, "original_filename" => "pagamentos.csv"]);
$schoolId = (int) $schoolBatch["id"];
gd_assert("preview não persiste linhas nem domínio", $db->table($prefix . "gd_import_rows")->where("batch_id", $schoolId)->countAllResults() === 0 && $countOf("gd_receivables") === $recBefore && count($schoolBatch["sample"]) === 3);
$importSvc->validate($schoolId);
gd_assert("validação grava linhas e inconsistências sem escrever domínio", $db->table($prefix . "gd_import_rows")->where("batch_id", $schoolId)->where("deleted", 0)->countAllResults() === 3 && $db->table($prefix . "gd_import_issues")->where("batch_id", $schoolId)->where("deleted", 0)->countAllResults() > 0 && $countOf("gd_receivables") === $recBefore);
$schoolValidated = $importSvc->get($schoolId);
gd_assert("status de linha distingue válida/ inválida/ revisão", ($schoolValidated->row_status["valid"] ?? 0) === 1 && ($schoolValidated->row_status["invalid"] ?? 0) === 1 && ($schoolValidated->row_status["needs_review"] ?? 0) === 1);
$schoolConfirm = $importSvc->confirm($schoolId);
gd_assert("confirmação importa apenas a linha válida", $schoolConfirm["imported"] === 1 && $countOf("gd_receivables") === $recBefore + 1 && $countOf("gd_payments") === $payBefore + 1);
$schoolAfter = $importSvc->get($schoolId);
$linkTypes = array_column($schoolAfter->links, "target_type");
gd_assert("rastreabilidade liga lote→aluno, cobrança e pagamento", in_array("school_profile", $linkTypes, true) && in_array("receivable", $linkTypes, true) && in_array("payment", $linkTypes, true) && in_array("enrollment", $linkTypes, true));
gd_assert("dado em revisão não é importado automaticamente", $countOf("gd_receivables") === $recBefore + 1);
gd_assert("importação parcial reflete no lote", $schoolAfter->status === "partially_imported");

// Reprocesso só re-tenta falhas; não duplica o que já foi importado.
$recAfterFirst = $countOf("gd_receivables");
$importSvc->reprocess($schoolId);
gd_assert("reprocessamento não duplica registros já importados", $countOf("gd_receivables") === $recAfterFirst && count($db->table($prefix . "gd_import_links")->where("batch_id", $schoolId)->where("target_type", "receivable")->where("deleted", 0)->get()->getResult()) === 1);

// Mesmo arquivo não pode reimportar silenciosamente.
$schoolDup = $writeCsv([
    ["Aluno", "Responsavel", "Telefone", "Turma", "Mes", "Valor", "Vencimento", "Data Pagamento", "Forma"],
    ["Maria Import " . $tk, "Ana Resp " . $tk, "1199990000", "Turma Import " . $tk, "2025-03", "150,00", "2025-03-10", "2025-03-08", "Pix"],
    ["Joao Invalido " . $tk, "", "", "", "2025-03", "abc", "", "", ""],
    ["Pedro Revisao " . $tk, "", "", "", "2025-04", "100,00", "", "2025-04-05", "Bitcoin"],
]);
gd_assert("hash duplicado bloqueia reimport silencioso", gd_throws(fn() => $importSvc->createBatch(["import_type" => "school_payments", "file_path" => $schoolDup, "original_filename" => "pagamentos.csv"]), "gd_import_duplicate_file"));
$overrideBatch = $importSvc->createBatch(["import_type" => "school_payments", "file_path" => $schoolDup, "original_filename" => "pagamentos.csv", "override" => true]);
gd_assert("override explícito permite reimportar o mesmo arquivo", (int) $overrideBatch["id"] > 0);

/* ===================== Caixa (XLSX) ===================== */
$cashFile = $writeXlsx([
    ["Data", "Descricao", "Entrada", "Saida", "Forma"],
    ["2025-05-02", "Recebimento avulso " . $tk, "200,00", "", "Dinheiro"],
    ["2025-05-03", "Compra material " . $tk, "", "80,00", "Pix"],
    ["nodate", "Sem data " . $tk, "10,00", "", ""],
]);
$cashMovBefore = $countOf("gd_cash_movements"); $expBefore = $countOf("gd_expenses"); $payBeforeCash = $countOf("gd_payments");
$cashBatch = $importSvc->createBatch(["import_type" => "cash", "file_path" => $cashFile, "original_filename" => "caixa.xlsx"]);
$cashId = (int) $cashBatch["id"];
gd_assert("lê XLSX via PhpSpreadsheet embarcado", $cashBatch["id"] > 0 && count($cashBatch["sample"]) === 3);
$importSvc->validate($cashId);
$importSvc->confirm($cashId);
gd_assert("entrada vira movimento de caixa manual do lote", $countOf("gd_cash_movements") === $cashMovBefore + 1 && (int) $db->table($prefix . "gd_cash_movements")->where("unit_id", $unit_id)->where("source_type", "import")->where("movement_type", "in")->countAllResults() >= 1);
gd_assert("saída vira despesa paga", $countOf("gd_expenses") === $expBefore + 1 && (int) $db->table($prefix . "gd_expenses")->where("unit_id", $unit_id)->where("status", "paid")->like("description", "Compra material " . $tk)->countAllResults() === 1);
gd_assert("caixa não cria pagamento de cobrança automaticamente", $countOf("gd_payments") === $payBeforeCash);

/* ===================== Mensalistas de quadra ===================== */
$renterFile = $writeCsv([
    ["Cliente", "Telefone", "Quadra", "Dia", "Hora", "Vencimento", "Valor"],
    ["Carlos Mensalista " . $tk, "1198887777", "IMPQ" . substr($tk, 0, 4), "Segunda", "19:00", "10", "300,00"],
    ["Ana Incompleta " . $tk, "", "", "", "", "", "250,00"],
]);
$rentalBefore = $countOf("gd_court_rentals");
$renterBatch = $importSvc->createBatch(["import_type" => "court_renters", "file_path" => $renterFile, "original_filename" => "mensalistas.csv"]);
$renterId = (int) $renterBatch["id"];
$importSvc->validate($renterId);
$importSvc->confirm($renterId);
$renterAfter = $importSvc->get($renterId);
gd_assert("mensalista completo cria locação + série como rascunho", $countOf("gd_court_rentals") === $rentalBefore + 2 && in_array("booking_series", array_column($renterAfter->links, "target_type"), true));
$draftStatuses = array_column($db->table($prefix . "gd_court_rentals")->select("status")->whereIn("id", array_values(array_filter(array_map(static fn($l) => $l->target_type === "court_rental" ? (int) $l->target_id : 0, $renterAfter->links))))->get()->getResultArray(), "status");
gd_assert("locações importadas nascem como rascunho (sem ativação automática)", $draftStatuses && !array_filter($draftStatuses, static fn($s) => $s !== "draft"));

/* ===================== Segurança, permissões, idioma ===================== */
gd_assert("IDOR de lote entre unidades retorna null", (new \grupo_donato_gestao\Services\ImportBatchService($unit2_id))->get($schoolId) === null);
gd_assert("nenhuma exclusão física: linhas e issues usam soft delete", $db->fieldExists("deleted", $prefix . "gd_import_rows") && $db->fieldExists("deleted", $prefix . "gd_import_issues") && $db->table($prefix . "gd_import_issues")->where("batch_id", $schoolId)->countAllResults() > $db->table($prefix . "gd_import_issues")->where("batch_id", $schoolId)->where("deleted", 0)->countAllResults());
$impManage = new \grupo_donato_gestao\Services\AccessService($pm("gd_imports_manage"));
$impView = new \grupo_donato_gestao\Services\AccessService($pm("gd_imports_view"));
gd_assert("imports_manage implica imports_view", $impManage->can("gd_imports_view") && !$impView->can("gd_imports_manage"));
gd_assert("rotas de importação separam GET e POST", isset($get_routes["grupo_donato/imports"], $get_routes["grupo_donato/imports/new"]) && (bool) array_filter(array_keys($get_routes), static fn($r) => str_starts_with((string) $r, "grupo_donato/imports/view/")) && isset($post_routes["grupo_donato/imports/upload"], $post_routes["grupo_donato/imports/confirm"]) && !isset($get_routes["grupo_donato/imports/confirm"]));
gd_assert("CSRF protege escrita de importação", in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/imports/confirm", "POST"), "filter"), true));
gd_assert("idioma da Fase 6 resolve", app_lang("gd_menu_imports") === "Importações" && app_lang("gd_import_type_court_renters") !== "gd_import_type_court_renters" && app_lang("gd_import_status_partially_imported") !== "gd_import_status_partially_imported");
$importDynamic = [];
foreach (["gd_import_type_" => \grupo_donato_gestao\Config\Constants::IMPORT_TYPES, "gd_import_status_" => \grupo_donato_gestao\Config\Constants::IMPORT_BATCH_STATUSES, "gd_import_row_status_" => \grupo_donato_gestao\Config\Constants::IMPORT_ROW_STATUSES] as $pfx => $vals) {
    foreach ($vals as $v) { $importDynamic[] = $pfx . $v; }
}
$importMissing = array_values(array_filter($importDynamic, static fn($k) => app_lang($k) === $k));
gd_assert("chaves dinâmicas da Fase 6 resolvem", !$importMissing, implode(",", $importMissing));

// Limpeza de arquivos não transacionais.
foreach ([$schoolId, $cashId, $renterId, (int) $overrideBatch["id"]] as $bid) { $b = $importSvc->get($bid); if ($b && $b->stored_path && is_file($b->stored_path)) { @unlink($b->stored_path); } }
foreach ($importTmp as $f) { if (is_file($f)) { @unlink($f); } }
