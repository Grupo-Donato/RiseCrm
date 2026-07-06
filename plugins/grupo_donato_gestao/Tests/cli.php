<?php

/**
 * Harness de verificação executável da Fase 1 (este Rise não possui o `spark`).
 *
 * Bootstrapa o framework em modo console e exercita schema/seeds/serviços.
 * Uso:
 *   php plugins/grupo_donato_gestao/Tests/cli.php install
 *   php plugins/grupo_donato_gestao/Tests/cli.php selftest
 *   php plugins/grupo_donato_gestao/Tests/cli.php seqgrab 25 [tipo]
 */

declare(strict_types=1);

// Rise's Config\App::set_base_url() lê HTTP_HOST; em CLI precisamos prover.
$_SERVER["HTTP_HOST"] = $_SERVER["HTTP_HOST"] ?? "localhost";
if (PHP_SAPI === "cli") {
    $_SERVER["SCRIPT_NAME"] = "/index.php";
    $_SERVER["REQUEST_URI"] = "/";
}
$_SERVER["REMOTE_ADDR"] = $_SERVER["REMOTE_ADDR"] ?? "127.0.0.1";

$root = realpath(__DIR__ . "/../../../");
chdir($root);

if (!defined("FCPATH")) {
    define("FCPATH", $root . DIRECTORY_SEPARATOR);
}

if (!defined("ENVIRONMENT")) {
    define("ENVIRONMENT", $_SERVER["CI_ENVIRONMENT"] ?? "development");
}
if (!defined("APP_NAMESPACE")) {
    define("APP_NAMESPACE", "App");
}

require $root . "/app/Config/Paths.php";
$paths = new Config\Paths();
require $paths->systemDirectory . "/Boot.php";
CodeIgniter\Boot::bootConsole($paths);

// dispara o pre_system (igual ao fluxo web): inicializa $hooks, carrega o
// helper de plugins e os index.php dos plugins ativos.
\CodeIgniter\Events\Events::trigger("pre_system");

helper(["general", "date_time", "app_files", "form", "language", "url", "plugin"]);

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Database\Schema\SchemaRunner;
use grupo_donato_gestao\Database\Seeds\FoundationSeeder;
use grupo_donato_gestao\Services\SequenceService;
use grupo_donato_gestao\Services\AuditService;
use grupo_donato_gestao\Services\UnitContextService;
use grupo_donato_gestao\Services\AccessService;
use grupo_donato_gestao\Services\SettingsService;
use grupo_donato_gestao\Services\CustomerAccountService;
use grupo_donato_gestao\Services\PersonService;
use grupo_donato_gestao\Services\AccountPersonService;
use grupo_donato_gestao\Services\ContactMethodService;
use grupo_donato_gestao\Services\AddressService;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\DataPrivacyService;

$task = $argv[1] ?? "install";

$GLOBALS["gd_pass"] = 0;
$GLOBALS["gd_fail"] = 0;
function gd_assert(string $label, bool $cond, string $detail = ""): void
{
    if ($cond) {
        $GLOBALS["gd_pass"]++;
        echo "  [PASS] $label\n";
    } else {
        $GLOBALS["gd_fail"]++;
        echo "  [FAIL] $label" . ($detail ? " — $detail" : "") . "\n";
    }
}

function gd_throws(callable $callback, string $message = ""): bool
{
    try {
        $callback();
    } catch (\Throwable $e) {
        return $message === "" || $e->getMessage() === $message;
    }
    return false;
}

function gd_like_literal_prefix(string $prefix): string
{
    return strtr($prefix, ["!" => "!!", "%" => "!%", "_" => "!_"]) . "%";
}

if ($task === "install") {
    $r = (new SchemaRunner())->run();
    echo "schema ran: " . implode(",", $r["ran"]) . ($r["failed"] ? " FAILED:" . $r["failed"] : "") . "\n";
    (new FoundationSeeder(0))->run();
    (new \grupo_donato_gestao\Database\Seeds\CatalogSeeder(0))->run();
    (new \grupo_donato_gestao\Database\Seeds\FinanceSeeder(0))->run();
    echo "seeds done\n";
    exit($r["failed"] ? 1 : 0);
}

if ($task === "operacional-install") {
    // Cria/atualiza as tabelas do módulo Operacional (Bombeiros) embutido.
    if (!function_exists("bombeiros_install_or_update")) {
        fwrite(STDERR, "bombeiros_install_or_update indisponível (bootstrap não carregado).\n");
        exit(1);
    }
    bombeiros_install_or_update();
    $db = db_connect();
    $tables = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ESCAPE '!' ORDER BY TABLE_NAME", [gd_like_literal_prefix($db->getPrefix() . "grupo_donato_")])->getResultArray();
    echo "Operacional tabelas grupo_donato_*: " . count($tables) . "\n";
    foreach ($tables as $t) { echo "  - " . reset($t) . "\n"; }
    exit(0);
}

if ($task === "operacional-check") {
    $ok = true;
    $cls = "grupo_donato_gestao\\Operacional\\Controllers\\Bombeiros";
    $clsOk = class_exists($cls); $ok = $ok && $clsOk;
    echo ($clsOk ? "[PASS]" : "[FAIL]") . " controller autoload: $cls\n";
    foreach (["Bombeiros_alunos_model", "Bombeiros_cobrancas_model", "Bombeiros_unidades_model"] as $m) {
        $mc = "grupo_donato_gestao\\Operacional\\Models\\$m"; $mok = class_exists($mc); $ok = $ok && $mok;
        echo ($mok ? "[PASS]" : "[FAIL]") . " model autoload: $m\n";
    }
    // Rotas só são registradas no contexto web (não no CLI); validamos o arquivo de rotas estaticamente.
    $routesSrc = (string) @file_get_contents(__DIR__ . "/../Operacional/Config/Routes.php");
    $hasRoute = strpos($routesSrc, 'group("grupo_donato/operacional"') !== false && strpos($routesSrc, 'grupo_donato_gestao\\Operacional\\Controllers') !== false;
    $ok = $ok && $hasRoute; echo ($hasRoute ? "[PASS]" : "[FAIL]") . " rotas operacional (arquivo + namespace)\n";
    $views = ["index", "lista_pagamentos", "modal_aluno", "financeiro_resumo", "public_matricula"];
    foreach ($views as $v) { $p = __DIR__ . "/../Operacional/Views/$v.php"; $vok = is_file($p); $ok = $ok && $vok; echo ($vok ? "[PASS]" : "[FAIL]") . " view: $v\n"; }
    $db = db_connect();
    $tcount = count($db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ESCAPE '!'", [gd_like_literal_prefix($db->getPrefix() . "grupo_donato_")])->getResultArray());
    $tok = $tcount === 9; $ok = $ok && $tok; echo ($tok ? "[PASS]" : "[FAIL]") . " 9 tabelas grupo_donato_* ($tcount)\n";
    echo $ok ? "OPERACIONAL-CHECK: PASS\n" : "OPERACIONAL-CHECK: FAIL\n";
    exit($ok ? 0 : 1);
}

if ($task === "seqgrab") {
    $count = isset($argv[2]) ? (int) $argv[2] : 10;
    $type = $argv[3] ?? "concurrency_test";
    $units = model("grupo_donato_gestao\\Models\\Gd_units_model");
    $def = $units->get_default();
    $unit_id = $def ? (int) $def->id : 1;
    $seq = new SequenceService();
    $seq->ensure($unit_id, $type, "T", 6, false);
    for ($i = 0; $i < $count; $i++) {
        echo $seq->next_raw($unit_id, $type)["current_value"] . "\n";
    }
    exit(0);
}

if ($task === "seqcleanup") {
    $type = $argv[2] ?? "";
    if ($type === "" || !preg_match('/^[a-zA-Z0-9_.-]{1,40}$/', $type)) {
        fwrite(STDERR, "Invalid sequence type.\n");
        exit(2);
    }
    $db = db_connect();
    $db->table($db->prefixTable("gd_sequences"))->where("document_type", $type)->update(["deleted" => 1]);
    exit(0);
}

if ($task === "temporalsetup") {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($argv[2] ?? ""));
    if ($token === "") { exit(2); }
    $db = db_connect(); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    $service = new \grupo_donato_gestao\Services\ResourceService((int) $unit->id, 0, null);
    $saved = $service->save(["code" => "GDRACE" . substr($token, -20), "name" => "GD temporal race", "resource_type" => "room", "is_active" => 1, "is_bookable" => 1]);
    echo (int) $saved["id"] . "\n"; exit(0);
}

if ($task === "temporalwrite") {
    $resource_id = (int) ($argv[2] ?? 0); if ($resource_id <= 0) { exit(2); }
    $db = db_connect(); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try {
        (new \grupo_donato_gestao\Services\ResourceBlockService((int) $unit->id, 0, null))->save(["resource_id"=>$resource_id,"block_type"=>"maintenance","starts_at_utc"=>"2099-01-01 12:00:00","ends_at_utc"=>"2099-01-01 13:00:00","title"=>"Race","reason"=>"Concurrency test"]);
        echo "saved\n"; exit(0);
    } catch (\Throwable $e) {
        if ($e->getMessage() === "gd_duplicate_exact_interval") { echo "duplicate\n"; exit(0); }
        fwrite(STDERR, $e->getMessage() . "\n"); exit(1);
    }
}

if ($task === "temporalcleanup") {
    $resource_id = (int) ($argv[2] ?? 0); $db = db_connect(); $prefix = $db->getPrefix();
    if ($resource_id > 0) {
        foreach (["gd_resource_blocks","gd_resource_availability_exceptions","gd_resource_availability_rules"] as $table) { $db->table($prefix.$table)->where("resource_id",$resource_id)->delete(); }
        $db->table($prefix."gd_resources")->where("id",$resource_id)->like("code","GDRACE","after")->delete();
    }
    exit(0);
}

if ($task === "expire-holds") {
    $db=db_connect();$units=$db->table($db->prefixTable("gd_units"))->select("id")->where("deleted",0)->where("status","active")->get()->getResult();$total=0;
    foreach($units as $unit){$result=(new \grupo_donato_gestao\Services\BookingHoldService((int)$unit->id))->expireBatch((int)($argv[2]??100));$total+=$result["expired"];}
    echo "expired=$total\n";exit(0);
}

if ($task === "bookingsetup") {
    $token=preg_replace('/[^a-zA-Z0-9]/','',(string)($argv[2]??''));$count=max(1,min(2,(int)($argv[3]??1)));if($token===''){exit(2);}$db=db_connect();$unit=model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();$unit_id=(int)$unit->id;$resource_service=new \grupo_donato_gestao\Services\ResourceService($unit_id);$exception_service=new \grupo_donato_gestao\Services\ResourceAvailabilityExceptionService($unit_id);$time=new \grupo_donato_gestao\Services\TemporalService($unit_id);$ids=[];
    for($i=1;$i<=$count;$i++){$saved=$resource_service->save(["code"=>"GDBR".substr($token,-20).$i,"name"=>"GD booking race $i","resource_type"=>"room","is_active"=>1,"is_bookable"=>1]);$rid=(int)$saved["id"];$ids[]=$rid;$exception_service->save(["resource_id"=>$rid,"exception_type"=>"open","starts_at_utc"=>$time->localToUtc("2099-07-20","08:00"),"ends_at_utc"=>$time->localToUtc("2099-07-20","18:00"),"title"=>"Booking race open"]);}
    echo implode(",",$ids)."\n";exit(0);
}

if ($task === "bookingwrite") {
    $ids=array_values(array_filter(array_map("intval",explode(",",(string)($argv[2]??"")))));$reverse=(bool)($argv[3]??false);if(!$ids){exit(2);}if($reverse){$ids=array_reverse($ids);}$unit=model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();$resources=array_map(static fn($id)=>["resource_id"=>$id,"buffer_before_minutes"=>10,"buffer_after_minutes"=>10],$ids);
    try{(new \grupo_donato_gestao\Services\BookingService((int)$unit->id))->save(["booking_type"=>"internal","title"=>"Concurrency booking","starts_at_local"=>"2099-07-20T12:00","ends_at_local"=>"2099-07-20T13:00","status"=>"pending_confirmation","resources"=>$resources]);echo "saved\n";exit(0);}catch(\Throwable $e){if(in_array($e->getMessage(),["gd_booking_conflict","gd_booking_duplicate"],true)){echo "conflict\n";exit(0);}fwrite(STDERR,$e->getMessage()."\n");exit(1);}
}

if ($task === "bookingcleanup") {
    $ids=array_values(array_filter(array_map("intval",explode(",",(string)($argv[2]??"")))));$db=db_connect();$p=$db->getPrefix();if($ids){$booking_ids=array_column($db->table($p."gd_booking_resources")->select("booking_id")->whereIn("resource_id",$ids)->get()->getResultArray(),"booking_id");if($booking_ids){$db->table($p."gd_booking_events")->whereIn("booking_id",$booking_ids)->delete();$db->table($p."gd_booking_resources")->whereIn("booking_id",$booking_ids)->delete();$db->table($p."gd_bookings")->whereIn("id",$booking_ids)->delete();}$db->table($p."gd_resource_availability_exceptions")->whereIn("resource_id",$ids)->delete();$db->table($p."gd_resources")->whereIn("id",$ids)->like("code","GDBR","after")->delete();}exit(0);
}

if ($task === "seriessetup") {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($argv[2] ?? ''));
    if ($token === '') { exit(2); }
    $db = db_connect(); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default(); $unit_id = (int) $unit->id;
    $resource = (new \grupo_donato_gestao\Services\ResourceService($unit_id))->save(["code" => "GDSR" . substr($token, -20), "name" => "GD series race", "resource_type" => "room", "is_active" => 1, "is_bookable" => 1]);
    $resource_id = (int) $resource["id"]; $rules = new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($unit_id);
    for ($weekday = 0; $weekday <= 6; $weekday++) { $rules->save(["resource_id" => $resource_id, "weekday" => $weekday, "start_time" => "08:00", "end_time" => "18:00", "status" => "active"]); }
    $series = (new \grupo_donato_gestao\Services\BookingSeriesService($unit_id))->create(["booking_type" => "internal", "title" => "Concurrency series", "frequency" => "daily", "interval_value" => 1, "weekdays" => [], "local_start_time" => "12:00", "local_end_time" => "13:00", "starts_on" => "2099-06-10", "ends_mode" => "count", "max_occurrences" => 3, "default_booking_status" => "pending_confirmation", "conflict_policy" => "reject_series", "generation_horizon_days" => 90, "resources" => [["resource_id" => $resource_id, "buffer_before_minutes" => 5, "buffer_after_minutes" => 5]]], false);
    echo (int) $series["id"] . "," . $resource_id . "\n"; exit(0);
}

if ($task === "seriesgenerate") {
    $series_id = (int) ($argv[2] ?? 0); if ($series_id <= 0) { exit(2); }
    $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try { $result = (new \grupo_donato_gestao\Services\BookingSeriesOccurrenceService((int) $unit->id))->generate($series_id); echo "created={$result['created']} idempotent={$result['idempotent']}\n"; exit(0); }
    catch (\Throwable $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}

if ($task === "seriesinspect") {
    $series_id = (int) ($argv[2] ?? 0); if ($series_id <= 0) { exit(2); }
    $db = db_connect(); $p = $db->getPrefix();
    $count = $db->table($p . "gd_bookings")->where("series_id", $series_id)->where("deleted", 0)->countAllResults();
    $duplicate = $db->query("SELECT COUNT(*) AS c FROM (SELECT series_occurrence_key FROM `{$p}gd_bookings` WHERE series_id=? AND deleted=0 GROUP BY series_occurrence_key HAVING COUNT(*)>1) d", [$series_id])->getRow();
    $runs = $db->table($p . "gd_booking_series_generation_runs")->where("series_id", $series_id)->where("status", "completed")->countAllResults();
    $effective = $db->table($p . "gd_booking_series_generation_runs")->where("series_id", $series_id)->where("status", "completed")->where("created_count", 3)->countAllResults();
    $idempotent = $db->table($p . "gd_booking_series_generation_runs")->where("series_id", $series_id)->where("status", "completed")->where("idempotent_count", 3)->countAllResults();
    echo "bookings=$count duplicates=" . (int) ($duplicate->c ?? 0) . " runs=$runs effective=$effective idempotent=$idempotent\n"; exit($count === 3 && (int) ($duplicate->c ?? 0) === 0 && $runs === 2 && $effective === 1 && $idempotent === 1 ? 0 : 1);
}

if ($task === "seriescleanup") {
    $parts = array_map("intval", explode(",", (string) ($argv[2] ?? ""))); $series_id = $parts[0] ?? 0; $resource_id = $parts[1] ?? 0;
    $db = db_connect(); $p = $db->getPrefix();
    if ($series_id > 0) {
        $booking_ids = array_column($db->table($p . "gd_bookings")->select("id")->where("series_id", $series_id)->get()->getResultArray(), "id");
        if ($booking_ids) { $db->table($p . "gd_booking_events")->whereIn("booking_id", $booking_ids)->delete(); $db->table($p . "gd_booking_resources")->whereIn("booking_id", $booking_ids)->delete(); $db->table($p . "gd_audit_logs")->where("entity_type", "booking")->whereIn("entity_id", $booking_ids)->delete(); $db->table($p . "gd_bookings")->whereIn("id", $booking_ids)->delete(); }
        $db->table($p . "gd_booking_series_generation_runs")->where("series_id", $series_id)->delete(); $db->table($p . "gd_booking_series_events")->where("series_id", $series_id)->delete(); $db->table($p . "gd_booking_series_exceptions")->where("series_id", $series_id)->delete(); $db->table($p . "gd_booking_series_resources")->where("series_id", $series_id)->delete(); $db->table($p . "gd_audit_logs")->where("entity_type", "booking_series")->where("entity_id", $series_id)->delete(); $db->table($p . "gd_booking_series")->where("id", $series_id)->delete();
    }
    if ($resource_id > 0) { $db->table($p . "gd_resource_availability_rules")->where("resource_id", $resource_id)->delete(); $db->table($p . "gd_resources")->where("id", $resource_id)->like("code", "GDSR", "after")->delete(); }
    exit(0);
}

if ($task === "rentalracesetup") {
    $token = preg_replace('/[^a-zA-Z0-9]/', '', (string) ($argv[2] ?? '')); if ($token === '') { exit(2); }
    $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default(); $unit_id = (int) $unit->id;
    $resource = (new \grupo_donato_gestao\Services\ResourceService($unit_id))->save(["code" => "GDCR" . substr($token, -18), "name" => "GD court rental race", "resource_type" => "court", "is_active" => 1, "is_bookable" => 1]);
    $rid = (int) $resource["id"]; $rules = new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($unit_id);
    for ($w = 0; $w <= 6; $w++) { $rules->save(["resource_id" => $rid, "weekday" => $w, "start_time" => "08:00", "end_time" => "22:00", "status" => "active"]); }
    $aid = (int) (new \grupo_donato_gestao\Services\CustomerAccountService($unit_id))->save(["account_type" => "other", "display_name" => "GD CR Race " . $token, "document_type" => "none", "status" => "active"])["id"];
    $rentalService = new \grupo_donato_gestao\Services\CourtRentalService($unit_id);
    $activate = $rentalService->createWithBooking(["rental_type" => "single", "title" => "Race activate", "customer_account_id" => $aid, "negotiated_amount" => "100.00", "starts_at_local" => "2099-06-10T09:00", "ends_at_local" => "2099-06-10T10:00", "resources" => [["resource_id" => $rid, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]);
    $override = $rentalService->createDraft(["rental_type" => "single", "title" => "Race override", "customer_account_id" => $aid, "negotiated_amount" => "100.00"], "single");
    $series = (new \grupo_donato_gestao\Services\BookingSeriesService($unit_id))->create(["booking_type" => "customer_rental", "title" => "Race series", "customer_account_id" => $aid, "frequency" => "daily", "interval_value" => 1, "weekdays" => [], "local_start_time" => "11:00", "local_end_time" => "12:00", "starts_on" => "2099-06-11", "ends_mode" => "count", "max_occurrences" => 2, "default_booking_status" => "pending_confirmation", "conflict_policy" => "reject_series", "generation_horizon_days" => 90, "resources" => [["resource_id" => $rid, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]], false);
    $draftA = $rentalService->createDraft(["rental_type" => "recurring", "title" => "Race link A", "customer_account_id" => $aid], "recurring");
    $draftB = $rentalService->createDraft(["rental_type" => "recurring", "title" => "Race link B", "customer_account_id" => $aid], "recurring");
    echo $activate["id"] . "," . $series["id"] . "," . $draftA["id"] . "," . $draftB["id"] . "," . $override["id"] . "," . $rid . "," . $aid . "\n"; exit(0);
}

if ($task === "rentalactivate") {
    $id = (int) ($argv[2] ?? 0); $lv = (int) ($argv[3] ?? 0); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try { (new \grupo_donato_gestao\Services\CourtRentalLifecycleService((int) $unit->id))->activate($id, $lv, true, "race"); echo "activated\n"; exit(0); }
    catch (\Throwable $e) { if (in_array($e->getMessage(), ["gd_court_rental_edit_conflict", "gd_invalid_court_rental_transition"], true)) { echo "conflict\n"; exit(0); } fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}

if ($task === "rentallink") {
    $draft = (int) ($argv[2] ?? 0); $series = (int) ($argv[3] ?? 0); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try { (new \grupo_donato_gestao\Services\CourtRentalService((int) $unit->id))->linkExisting($draft, ["booking_series_id" => $series, "link_kind" => "primary"]); echo "linked\n"; exit(0); }
    catch (\Throwable $e) { if ($e->getMessage() === "gd_court_rental_already_linked") { echo "conflict\n"; exit(0); } fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}

if ($task === "rentaloverride") {
    $id = (int) ($argv[2] ?? 0); $lv = (int) ($argv[3] ?? 0); $val = (string) ($argv[4] ?? "90.00"); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try { (new \grupo_donato_gestao\Services\CourtRentalService((int) $unit->id))->reprice($id, ["negotiated_amount" => $val, "discount_reason" => "race", "lock_version" => $lv], true); echo "repriced\n"; exit(0); }
    catch (\Throwable $e) { if ($e->getMessage() === "gd_court_rental_edit_conflict") { echo "conflict\n"; exit(0); } fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}

if ($task === "rentalcreate") {
    $rid = (int) ($argv[2] ?? 0); $aid = (int) ($argv[3] ?? 0); $start = (string) ($argv[4] ?? ""); $end = (string) ($argv[5] ?? ""); $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
    try { (new \grupo_donato_gestao\Services\CourtRentalService((int) $unit->id))->createWithBooking(["rental_type" => "single", "title" => "Race create", "customer_account_id" => $aid, "negotiated_amount" => "100.00", "starts_at_local" => $start, "ends_at_local" => $end, "resources" => [["resource_id" => $rid, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]); echo "created\n"; exit(0); }
    catch (\Throwable $e) { if (in_array($e->getMessage(), ["gd_booking_conflict", "gd_booking_duplicate"], true)) { echo "conflict\n"; exit(0); } fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
}

if ($task === "rentalraceinspect") {
    $parts = array_map("intval", explode(",", (string) ($argv[2] ?? ""))); [$activateId, $seriesId, $overrideId, $rid] = array_pad($parts, 4, 0);
    $db = db_connect(); $p = $db->getPrefix();
    $active = $db->table($p . "gd_court_rentals")->where("id", $activateId)->where("status", "active")->countAllResults();
    $activatedEvents = $db->table($p . "gd_court_rental_events")->where("rental_id", $activateId)->where("event_type", "activated")->countAllResults();
    $seriesLinks = $db->table($p . "gd_court_rental_schedule_links")->where("active_series_guard", $seriesId)->where("deleted", 0)->countAllResults();
    $overrideRow = $db->table($p . "gd_court_rentals")->where("id", $overrideId)->get(1)->getRow();
    $overrideItems = $db->table($p . "gd_court_rental_price_items")->where("rental_id", $overrideId)->where("deleted", 0)->countAllResults();
    $createBookingIds = array_column($db->table($p . "gd_booking_resources")->select("booking_id")->where("resource_id", $rid)->where("deleted", 0)->get()->getResultArray(), "booking_id");
    $occupied = $createBookingIds ? $db->table($p . "gd_bookings")->whereIn("id", $createBookingIds)->where("title", "Race create")->whereNotIn("status", ["cancelled", "expired"])->countAllResults() : 0;
    $overrideLock = (int) ($overrideRow->lock_version ?? 0);
    echo "active=$active activated_events=$activatedEvents series_links=$seriesLinks override_lock=$overrideLock override_items=$overrideItems create_occupancy=$occupied\n";
    exit(($active === 1 && $activatedEvents === 1 && $seriesLinks === 1 && $overrideLock === 2 && $overrideItems === 1 && $occupied === 1) ? 0 : 1);
}

if ($task === "rentalracecleanup") {
    $parts = explode(",", (string) ($argv[2] ?? "")); $db = db_connect(); $p = $db->getPrefix();
    $seriesId = (int) ($parts[1] ?? 0); $rid = (int) ($parts[5] ?? 0); $aid = (int) ($parts[6] ?? 0);
    $rentalIds = $aid > 0 ? array_map("intval", array_column($db->table($p . "gd_court_rentals")->select("id")->where("customer_account_id", $aid)->get()->getResultArray(), "id")) : [];
    foreach ($rentalIds as $rentalId) { if ($rentalId <= 0) { continue; } $db->table($p . "gd_court_rental_events")->where("rental_id", $rentalId)->delete(); $db->table($p . "gd_court_rental_price_items")->where("rental_id", $rentalId)->delete(); $db->table($p . "gd_court_rental_schedule_links")->where("rental_id", $rentalId)->delete(); $db->table($p . "gd_audit_logs")->where("entity_type", "court_rental")->where("entity_id", $rentalId)->delete(); $db->table($p . "gd_court_rentals")->where("id", $rentalId)->delete(); }
    if ($seriesId > 0) {
        $seriesBookings = array_column($db->table($p . "gd_bookings")->select("id")->where("series_id", $seriesId)->get()->getResultArray(), "id");
        if ($seriesBookings) { $db->table($p . "gd_booking_events")->whereIn("booking_id", $seriesBookings)->delete(); $db->table($p . "gd_booking_resources")->whereIn("booking_id", $seriesBookings)->delete(); $db->table($p . "gd_bookings")->whereIn("id", $seriesBookings)->delete(); }
        $db->table($p . "gd_booking_series_generation_runs")->where("series_id", $seriesId)->delete(); $db->table($p . "gd_booking_series_events")->where("series_id", $seriesId)->delete(); $db->table($p . "gd_booking_series_exceptions")->where("series_id", $seriesId)->delete(); $db->table($p . "gd_booking_series_resources")->where("series_id", $seriesId)->delete(); $db->table($p . "gd_audit_logs")->where("entity_type", "booking_series")->where("entity_id", $seriesId)->delete(); $db->table($p . "gd_booking_series")->where("id", $seriesId)->delete();
    }
    if ($rid > 0) {
        $resourceBookings = array_column($db->table($p . "gd_booking_resources")->select("booking_id")->where("resource_id", $rid)->get()->getResultArray(), "booking_id");
        if ($resourceBookings) { $db->table($p . "gd_booking_events")->whereIn("booking_id", $resourceBookings)->delete(); $db->table($p . "gd_booking_resources")->whereIn("booking_id", $resourceBookings)->delete(); $db->table($p . "gd_bookings")->whereIn("id", $resourceBookings)->delete(); }
        $db->table($p . "gd_resource_availability_rules")->where("resource_id", $rid)->delete(); $db->table($p . "gd_resources")->where("id", $rid)->like("code", "GDCR", "after")->delete();
    }
    if ($aid > 0) { $db->table($p . "gd_audit_logs")->where("entity_type", "customer_account")->where("entity_id", $aid)->delete(); $db->table($p . "gd_customer_accounts")->where("id", $aid)->delete(); }
    exit(0);
}

if ($task === "uninstallcheck") {
    $db = db_connect();
    $prefix = $db->getPrefix();
    $gd_table_pattern = gd_like_literal_prefix($prefix . "gd_");
    $before = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ESCAPE '!' ORDER BY TABLE_NAME", [$gd_table_pattern])->getResultArray();
    gd_uninstall();
    $after = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ESCAPE '!' ORDER BY TABLE_NAME", [$gd_table_pattern])->getResultArray();
    $ok = $before === $after && count($after) === 49;
    echo "before=" . count($before) . " after=" . count($after) . " preserved=" . ($ok ? "yes" : "no") . "\n";
    exit($ok ? 0 : 1);
}

if ($task === "selftest") {
    $db = db_connect();
    $prefix = $db->getPrefix();
    $gd_table_pattern = gd_like_literal_prefix($prefix . "gd_");

    echo "# Schema & tabelas\n";
    foreach (["gd_schema_versions", "gd_units", "gd_business_areas", "gd_cost_centers", "gd_settings", "gd_sequences", "gd_audit_logs", "gd_customer_accounts", "gd_people", "gd_account_people", "gd_contact_methods", "gd_addresses", "gd_product_categories", "gd_resources", "gd_products", "gd_product_variants", "gd_price_lists", "gd_prices", "gd_resource_availability_rules", "gd_resource_availability_exceptions", "gd_resource_blocks", "gd_bookings", "gd_booking_resources", "gd_booking_events", "gd_court_rentals", "gd_court_rental_schedule_links", "gd_court_rental_price_items", "gd_court_rental_events", "gd_school_profiles", "gd_classes", "gd_enrollments", "gd_attendance_sessions", "gd_attendance_records", "gd_financial_accounts", "gd_receivables", "gd_receivable_items", "gd_payments", "gd_payment_allocations", "gd_expenses", "gd_cash_movements", "gd_import_batches", "gd_import_rows", "gd_import_issues", "gd_import_links"] as $t) {
        gd_assert("tabela {$prefix}{$t} existe", $db->tableExists($prefix . $t));
    }
    $sv = model("grupo_donato_gestao\\Models\\Gd_schema_versions_model");
    gd_assert("49 versões completed", $sv->count_by_status("completed") === 49, $sv->count_by_status("completed") . " completed");
    gd_assert("nenhuma falha de schema", !$sv->has_failed());
    gd_assert("versão aplicada == alvo " . Constants::SCHEMA_TARGET, $sv->get_applied_version() === Constants::SCHEMA_TARGET, "aplicada=" . $sv->get_applied_version());
    $physical = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ? ESCAPE '!'", [$gd_table_pattern])->getResult();
    gd_assert("49 tabelas do plugin", count($physical) === 49, count($physical) . " tabelas gd_*");
    gd_assert("marker em disco atualizado para " . Constants::SCHEMA_TARGET, trim((string) @file_get_contents(SchemaRunner::marker_path())) === Constants::SCHEMA_TARGET);

    echo "# Seeds\n";
    $units = model("grupo_donato_gestao\\Models\\Gd_units_model");
    $areas = model("grupo_donato_gestao\\Models\\Gd_business_areas_model");
    gd_assert("1 unidade padrão", (int) $units->count_active() === 1, $units->count_active() . " unidades");
    gd_assert("7 áreas de negócio", (int) $areas->count_active() === 7, $areas->count_active() . " áreas");

    echo "# Idempotência\n";
    $before = (int) $areas->count_active();
    (new FoundationSeeder(0))->run();
    gd_assert("seeds re-rodados sem duplicar", (int) $areas->count_active() === $before, $areas->count_active() . " áreas");
    $rerun = (new SchemaRunner())->run();
    gd_assert("schema re-rodado sem novas versões", count($rerun["ran"]) === 0, "ran=" . implode(",", $rerun["ran"]));
    $dups = $db->query("SELECT version, COUNT(*) c FROM `{$prefix}gd_schema_versions` GROUP BY version HAVING c>1")->getResult();
    gd_assert("sem versões duplicadas", count($dups) === 0);

    echo "# Seeds do catálogo (Fase 2B)\n";
    $def_unit = $units->get_default();
    $def_unit_id = (int) $def_unit->id;
    $court_count = static fn() => $db->table($prefix . "gd_resources")->where("unit_id", $def_unit_id)->where("resource_type", "court")->where("deleted", 0)->countAllResults();
    $default_list_count = static fn() => $db->table($prefix . "gd_price_lists")->where("unit_id", $def_unit_id)->where("code", "DEFAULT")->where("deleted", 0)->countAllResults();
    gd_assert("Q2–Q6 cadastradas (5 quadras)", $court_count() === 5, $court_count() . " quadras");
    $q_codes = array_column($db->table($prefix . "gd_resources")->select("code")->where("unit_id", $def_unit_id)->where("resource_type", "court")->where("deleted", 0)->orderBy("code")->get()->getResultArray(), "code");
    gd_assert("códigos Q2–Q6 corretos", $q_codes === ["Q2", "Q3", "Q4", "Q5", "Q6"], implode(",", $q_codes));
    gd_assert("nenhuma quadra com preço/capacidade inventados", $db->table($prefix . "gd_resources")->where("unit_id", $def_unit_id)->where("resource_type", "court")->where("capacity IS NOT NULL", null, false)->countAllResults() === 0);
    gd_assert("1 tabela de preço padrão", $default_list_count() === 1, $default_list_count() . " tabelas DEFAULT");
    (new \grupo_donato_gestao\Database\Seeds\CatalogSeeder(0))->run();
    gd_assert("seed do catálogo idempotente (quadras)", $court_count() === 5, $court_count() . " quadras após re-seed");
    gd_assert("seed do catálogo idempotente (tabela padrão)", $default_list_count() === 1, $default_list_count() . " tabelas DEFAULT após re-seed");

    // Todos os dados de teste abaixo são revertidos ao final.
    $db->transBegin();

    $settings = new SettingsService();
    $settings->set("selftest_setting", "a");
    $settings->set("selftest_setting", "b");
    $setting_count = $db->table($prefix . "gd_settings")->where("unit_id IS NULL", null, false)->where("key", "selftest_setting")->where("deleted", 0)->countAllResults();
    gd_assert("configuração global não duplica por escopo", $setting_count === 1, $setting_count . " registros");
    $db->query(
        "INSERT IGNORE INTO `{$prefix}gd_settings` (unit_id, `key`, value, value_type, is_secret, deleted) VALUES (NULL, 'selftest_setting', 'duplicate', 'string', 0, 0)"
    );
    $setting_count_after_db_attempt = $db->table($prefix . "gd_settings")->where("unit_id IS NULL", null, false)->where("key", "selftest_setting")->where("deleted", 0)->countAllResults();
    gd_assert("índice normalizado bloqueia duplicata global", $setting_count_after_db_attempt === 1, $setting_count_after_db_attempt . " registros");
    gd_assert("segredo é recusado", $settings->set("selftest_secret", "never-store", null, "string", true) === false);
    gd_assert("segredo recusado não foi persistido", $db->table($prefix . "gd_settings")->where("key", "selftest_secret")->countAllResults() === 0);

    echo "# Soft delete\n";
    $tmp = ["name" => "__selftest_unit__", "status" => "active", "is_default" => 0, "deleted" => 0];
    $tmp_id = (int) $units->ci_save($tmp);
    gd_assert("unidade temporária criada", $tmp_id > 0);
    $units->delete($tmp_id);
    $row = $db->table($prefix . "gd_units")->where("id", $tmp_id)->get()->getRow();
    gd_assert("soft delete marca deleted=1", $row && (int) $row->deleted === 1);
    gd_assert("deletado não aparece na listagem", $units->get_details(["id" => $tmp_id])->getRow() === null);

    echo "# Auditoria + mascaramento\n";
    (new AuditService(null))->log("selftest", "selftest", 1, ["password" => "supersecret", "api_key" => "abc123", "name" => "ok"], ["token" => "zzz", "headers" => ["Authorization" => "Bearer secret", "Cookie" => "sid=secret"], "value" => 42]);
    $last = $db->table($prefix . "gd_audit_logs")->orderBy("id", "DESC")->get(1)->getRow();
    gd_assert("evento gravado", $last && $last->action === "selftest");
    $bj = $last ? (string) $last->before_data : "";
    gd_assert("segredo não aparece em claro", strpos($bj, "supersecret") === false && strpos($bj, "abc123") === false, $bj);
    gd_assert("chave sensível mascarada (***)", strpos($bj, "***") !== false);
    gd_assert("campo não sensível preservado", strpos($bj, "ok") !== false);
    $aj = $last ? (string) $last->after_data : "";
    gd_assert("authorization/cookie não aparecem em claro", strpos($aj, "Bearer secret") === false && strpos($aj, "sid=secret") === false, $aj);
    $append_only = false;
    try {
        $audit_model = model("grupo_donato_gestao\\Models\\Gd_audit_logs_model");
        $audit_model->delete((int) $last->id);
    } catch (\LogicException $e) {
        $append_only = true;
    }
    gd_assert("model de auditoria bloqueia delete", $append_only);

    echo "# Sequências (unicidade)\n";
    $seq = new SequenceService();
    $def = $units->get_default();
    $unit_id = $def ? (int) $def->id : 1;
    $seq->ensure($unit_id, "selftest_seq", "S", 0, false);
    $seen = [];
    $ok = true;
    $prev = 0;
    for ($i = 0; $i < 30; $i++) {
        $v = (int) $seq->next_raw($unit_id, "selftest_seq")["current_value"];
        if (isset($seen[$v]) || $v <= $prev) {
            $ok = false;
            break;
        }
        $seen[$v] = true;
        $prev = $v;
    }
    gd_assert("30 números únicos e crescentes", $ok);
    $seq->ensure($unit_id, "selftest_yearly", "Y", 4, true);
    $db->table($prefix . "gd_sequences")->where("unit_id", $unit_id)->where("document_type", "selftest_yearly")
        ->update(["current_value" => 41, "last_reset_year" => (int) gmdate("Y") - 1]);
    $yearly = $seq->next_raw($unit_id, "selftest_yearly");
    gd_assert("reset anual reinicia em 1", (int) $yearly["current_value"] === 1, (string) $yearly["current_value"]);
    gd_assert("prefixo e padding são aplicados", $seq->next($unit_id, "selftest_yearly") === "Y0002");
    $invalid_sequence_unit = false;
    try {
        $seq->ensure(999999, "selftest_invalid");
    } catch (\InvalidArgumentException $e) {
        $invalid_sequence_unit = true;
    }
    gd_assert("sequência rejeita unidade inexistente", $invalid_sequence_unit);

    echo "# Contexto de unidade\n";
    $uc = new UnitContextService(null);
    gd_assert("acesso à unidade padrão", $uc->user_can_access_unit($unit_id));
    gd_assert("rejeita unidade inexistente", !$uc->user_can_access_unit(999999));
    $inactive = ["name" => "__selftest_inactive__", "status" => "inactive", "is_default" => 0, "deleted" => 0];
    $inactive_id = (int) $units->ci_save($inactive);
    gd_assert("rejeita unidade inativa", !$uc->user_can_access_unit($inactive_id));
    gd_assert("não define unidade inativa na sessão", !$uc->set_active_unit($inactive_id));

    echo "# Fase 2A — contas e pessoas\n";
    $account_service = new CustomerAccountService($unit_id);
    $person_service = new PersonService($unit_id);
    $relation_service = new AccountPersonService($unit_id);
    $contact_service = new ContactMethodService($unit_id);
    $address_service = new AddressService($unit_id);

    $individual_data = [
        "account_type" => "individual", "display_name" => "  Conta   Individual  Teste ",
        "document_type" => "cpf", "document_number" => "123.456.789-01", "status" => "active",
        "email" => "CONTA.TESTE@EXAMPLE.COM", "phone" => "(11) 99999-1111",
    ];
    $individual = $account_service->save($individual_data);
    gd_assert("cria conta individual", $individual["saved"] && $individual["id"] > 0);
    $individual_row = $account_service->get($individual["id"]);
    gd_assert("normaliza nome sem destruir exibição", $individual_row->display_name === "Conta Individual Teste" && $individual_row->normalized_name === "conta individual teste");
    gd_assert("normaliza documento", $individual_row->document_number_normalized === "12345678901");
    gd_assert("normaliza e-mail e telefone", $individual_row->email_normalized === "conta.teste@example.com" && $individual_row->phone_normalized === "11999991111");
    $individual_data["status"] = "blocked";
    $updated_individual = $account_service->save($individual_data, $individual["id"]);
    gd_assert("atualiza conta e altera status", $updated_individual["saved"] && $account_service->get($individual["id"])->status === "blocked");

    $family = $account_service->save(["account_type" => "family", "display_name" => "Família Teste Transacional", "document_type" => "none", "status" => "active"]);
    $company = $account_service->save(["account_type" => "company", "display_name" => "Empresa Teste Transacional", "legal_name" => "Empresa Teste Ltda", "document_type" => "cnpj", "document_number" => "12.345.678/0001-99", "status" => "active"]);
    gd_assert("cria conta familiar", $family["saved"] && $account_service->get($family["id"])->account_type === "family");
    gd_assert("cria empresa", $company["saved"] && $account_service->get($company["id"])->account_type === "company");
    $duplicate_account = $account_service->save($individual_data);
    gd_assert("documento exato exige confirmação", !$duplicate_account["saved"] && $duplicate_account["duplicate_confirmation_required"] && $duplicate_account["duplicates"][0]["confidence"] === "exact");
    $override_account = $account_service->save($individual_data, 0, true);
    gd_assert("permite duplicidade confirmada", $override_account["saved"] && $override_account["id"] > 0);
    gd_assert("rejeita tipo de conta inválido", gd_throws(fn() => $account_service->save(["account_type" => "invalid", "display_name" => "Inválida", "document_type" => "none"]), "gd_invalid_value"));
    gd_assert("rejeita unidade inválida", gd_throws(fn() => new CustomerAccountService(999999), "gd_invalid_unit"));

    $deletable = $account_service->save(["account_type" => "other", "display_name" => "Conta para exclusão lógica", "document_type" => "none", "status" => "active"]);
    gd_assert("exclusão de conta exige motivo", gd_throws(fn() => $account_service->delete($deletable["id"], ""), "gd_delete_reason_required"));
    $account_service->delete($deletable["id"], "self-test transacional");
    $deleted_account = $db->table($prefix . "gd_customer_accounts")->where("id", $deletable["id"])->get(1)->getRow();
    gd_assert("soft delete de conta preserva linha", $deleted_account && (int) $deleted_account->deleted === 1 && $account_service->get($deletable["id"]) === null);

    $person_one_data = ["full_name" => "  Pessoa   Árvore  Teste ", "preferred_name" => "Árvore", "birth_date" => "1990-05-20", "status" => "active"];
    $person_one = $person_service->save($person_one_data);
    $person_two = $person_service->save(["full_name" => "Pessoa Beta Teste", "birth_date" => "1992-06-21", "status" => "active"]);
    gd_assert("cria pessoa", $person_one["saved"] && $person_service->get($person_one["id"])->normalized_name === "pessoa arvore teste");
    $person_update = $person_service->save(["full_name" => "Pessoa Árvore Atualizada", "preferred_name" => "Árvore", "birth_date" => "1990-05-20", "status" => "inactive"], $person_one["id"]);
    gd_assert("atualiza pessoa e status", $person_update["saved"] && $person_service->get($person_one["id"])->status === "inactive");
    $person_one_data["status"] = "active";
    $person_service->save($person_one_data, $person_one["id"]);
    gd_assert("nome da pessoa é obrigatório", gd_throws(fn() => $person_service->save(["full_name" => "", "status" => "active"]), "gd_person_name_required"));
    gd_assert("data inválida é rejeitada", gd_throws(fn() => $person_service->save(["full_name" => "Data Inválida", "birth_date" => "2025-02-30", "status" => "active"]), "gd_invalid_date"));
    $duplicate_person = $person_service->save($person_one_data);
    gd_assert("nome e nascimento detectam pessoa provável", !$duplicate_person["saved"] && $duplicate_person["duplicates"][0]["confidence"] === "high");
    $override_person = $person_service->save($person_one_data, 0, true);
    gd_assert("confirmação de pessoa duplicada é aceita", $override_person["saved"]);

    echo "# Fase 2A — relações\n";
    $relation_one = $relation_service->save(["account_id" => $family["id"], "person_id" => $person_one["id"], "role" => "mother", "is_primary" => 1, "status" => "active", "start_date" => "2024-01-01"]);
    $relation_two = $relation_service->save(["account_id" => $family["id"], "person_id" => $person_two["id"], "role" => "participant", "status" => "active"]);
    gd_assert("vincula duas pessoas à família", $relation_one > 0 && $relation_two > 0);
    $second_role = $relation_service->save(["account_id" => $family["id"], "person_id" => $person_one["id"], "role" => "financial_responsible", "status" => "active"]);
    $second_role_row = model("grupo_donato_gestao\\Models\\Gd_account_people_model")->get_scoped($second_role, $unit_id);
    gd_assert("pessoa possui múltiplos papéis", $second_role > 0 && (int) $second_role_row->is_financial_responsible === 1);
    gd_assert("rejeita relação idêntica ativa", gd_throws(fn() => $relation_service->save(["account_id" => $family["id"], "person_id" => $person_one["id"], "role" => "mother", "status" => "active"]), "gd_duplicate_relation"));
    gd_assert("rejeita conta inexistente", gd_throws(fn() => $relation_service->save(["account_id" => 999999, "person_id" => $person_one["id"], "role" => "member", "status" => "active"]), "gd_invalid_relation"));
    gd_assert("rejeita intervalo de datas inválido", gd_throws(fn() => $relation_service->save(["account_id" => $company["id"], "person_id" => $person_two["id"], "role" => "representative", "status" => "active", "start_date" => "2025-02-01", "end_date" => "2025-01-01"]), "gd_invalid_date_range"));
    $relation_service->save(["account_id" => $family["id"], "person_id" => $person_two["id"], "role" => "participant", "is_primary" => 1, "status" => "active"], $relation_two);
    $first_primary = model("grupo_donato_gestao\\Models\\Gd_account_people_model")->get_scoped($relation_one, $unit_id);
    gd_assert("troca relação principal transacionalmente", (int) $first_primary->is_primary === 0);
    $relation_service->end($second_role, "self-test");
    $ended_relation = model("grupo_donato_gestao\\Models\\Gd_account_people_model")->get_scoped($second_role, $unit_id);
    gd_assert("encerra relação preservando histórico", $ended_relation->status === "ended" && $ended_relation->end_date !== null && (int) $ended_relation->deleted === 0);
    gd_assert("conta com relações ativas não é excluída", gd_throws(fn() => $account_service->delete($family["id"], "não deve excluir"), "gd_account_has_relations"));

    $other_unit_data = ["name" => "__phase2a_other_unit__", "status" => "active", "is_default" => 0, "deleted" => 0];
    $other_unit_id = (int) $units->ci_save($other_unit_data);
    $other_person_service = new PersonService($other_unit_id);
    $other_account_service = new CustomerAccountService($other_unit_id);
    $other_person = $other_person_service->save(["full_name" => "Pessoa Outra Unidade", "status" => "active"]);
    $other_account = $other_account_service->save(["account_type" => "other", "display_name" => "Conta Outra Unidade", "document_type" => "none", "status" => "active"]);
    gd_assert("rejeita pessoa de outra unidade", gd_throws(fn() => $relation_service->save(["account_id" => $family["id"], "person_id" => $other_person["id"], "role" => "member", "status" => "active"]), "gd_invalid_relation"));
    gd_assert("IDOR entre unidades não retorna conta", $account_service->get($other_account["id"]) === null);

    echo "# Fase 2A — contatos\n";
    $phone_one = $contact_service->save(["person_id" => $person_one["id"], "contact_type" => "phone", "label" => "Principal", "value" => "(11) 98888-7777", "is_primary" => 1, "status" => "active"]);
    $whatsapp = $contact_service->save(["person_id" => $person_one["id"], "contact_type" => "whatsapp", "value" => "+55 11 97777-6666", "is_primary" => 1, "status" => "active"]);
    $email = $contact_service->save(["person_id" => $person_one["id"], "contact_type" => "email", "value" => "PESSOA.TESTE@EXAMPLE.COM", "is_primary" => 1, "status" => "active"]);
    $phone_two = $contact_service->save(["person_id" => $person_one["id"], "contact_type" => "phone", "value" => "(11) 96666-5555", "is_primary" => 1, "status" => "active"]);
    $contact_model = model("grupo_donato_gestao\\Models\\Gd_contact_methods_model");
    gd_assert("cria telefone, WhatsApp e e-mail", $phone_one > 0 && $whatsapp > 0 && $email > 0);
    gd_assert("normaliza contatos por tipo", $contact_model->get_scoped($phone_one, $unit_id)->normalized_value === "11988887777" && $contact_model->get_scoped($email, $unit_id)->normalized_value === "pessoa.teste@example.com");
    gd_assert("troca contato principal por tipo", (int) $contact_model->get_scoped($phone_one, $unit_id)->is_primary === 0 && (int) $contact_model->get_scoped($phone_two, $unit_id)->is_primary === 1);
    $shared_phone = $contact_service->save(["person_id" => $person_two["id"], "contact_type" => "phone", "value" => "(11) 96666-5555", "is_primary" => 1, "status" => "active"]);
    gd_assert("permite telefone compartilhado", $shared_phone > 0);
    gd_assert("rejeita tipo de contato inválido", gd_throws(fn() => $contact_service->save(["person_id" => $person_one["id"], "contact_type" => "fax", "value" => "123", "status" => "active"]), "gd_invalid_value"));

    $person_to_delete = $person_service->save(["full_name" => "Pessoa para exclusão lógica", "status" => "active"]);
    $delete_relation = $relation_service->save(["account_id" => $company["id"], "person_id" => $person_to_delete["id"], "role" => "representative", "status" => "active"]);
    $delete_contact = $contact_service->save(["person_id" => $person_to_delete["id"], "contact_type" => "phone", "value" => "11900000000", "status" => "active"]);
    $person_service->delete($person_to_delete["id"], "self-test transacional");
    $deleted_person = $db->table($prefix . "gd_people")->where("id", $person_to_delete["id"])->get(1)->getRow();
    $ended_on_delete = $db->table($prefix . "gd_account_people")->where("id", $delete_relation)->get(1)->getRow();
    $contact_on_delete = $db->table($prefix . "gd_contact_methods")->where("id", $delete_contact)->get(1)->getRow();
    gd_assert("soft delete da pessoa preserva linha", $deleted_person && (int) $deleted_person->deleted === 1);
    gd_assert("exclusão da pessoa encerra vínculo sem apagar conta", $ended_on_delete->status === "ended" && $account_service->get($company["id"]) !== null);
    gd_assert("exclusão da pessoa preserva contato logicamente", (int) $contact_on_delete->deleted === 1 && $contact_on_delete->status === "inactive");
    gd_assert("contato rejeita pessoa soft-deleted", gd_throws(fn() => $contact_service->save(["person_id" => $person_to_delete["id"], "contact_type" => "phone", "value" => "11911111111", "status" => "active"]), "gd_invalid_person"));

    echo "# Fase 2A — endereços e duplicidade\n";
    $address_one = $address_service->save(["account_id" => $family["id"], "address_type" => "residential", "postal_code" => "01.234-567", "street" => "Rua Teste", "number" => "10", "city" => "São Paulo", "is_primary" => 1, "status" => "active"]);
    $address_model = model("grupo_donato_gestao\\Models\\Gd_addresses_model");
    gd_assert("cria endereço e normaliza CEP", $address_model->get_scoped($address_one, $unit_id)->postal_code_normalized === "01234567");
    gd_assert("alerta endereço duplicado", gd_throws(fn() => $address_service->save(["account_id" => $family["id"], "address_type" => "other", "postal_code" => "01234-567", "street" => "Rua Teste", "number" => "10", "status" => "active"]), "gd_duplicate_address"));
    $address_override = $address_service->save(["account_id" => $family["id"], "address_type" => "other", "postal_code" => "01234-567", "street" => "Rua Teste", "number" => "10", "status" => "active"], 0, true);
    gd_assert("permite endereço duplicado confirmado", $address_override > 0);
    $address_two = $address_service->save(["account_id" => $family["id"], "address_type" => "billing", "postal_code" => "99999-000", "street" => "Avenida Teste", "number" => "20", "is_primary" => 1, "status" => "active"]);
    gd_assert("troca endereço principal transacionalmente", (int) $address_model->get_scoped($address_one, $unit_id)->is_primary === 0 && (int) $address_model->get_scoped($address_two, $unit_id)->is_primary === 1);
    $address_service->save(["account_id" => $family["id"], "address_type" => "billing", "postal_code" => "99999-000", "street" => "Avenida Teste Atualizada", "number" => "20", "is_primary" => 1, "status" => "active"], $address_two);
    gd_assert("atualiza endereço", $address_model->get_scoped($address_two, $unit_id)->street === "Avenida Teste Atualizada");
    gd_assert("endereço rejeita conta de outra unidade", gd_throws(fn() => $address_service->save(["account_id" => $other_account["id"], "address_type" => "other", "status" => "active"]), "gd_invalid_account"));

    $person_duplicates = $person_service->duplicates(["full_name" => "Pessoa Beta Teste", "contact_values" => ["11966665555"]]);
    gd_assert("duplicidade por telefone retorna contrato padrão", !empty($person_duplicates) && isset($person_duplicates[0]["record_id"], $person_duplicates[0]["entity_type"], $person_duplicates[0]["confidence"], $person_duplicates[0]["matched_fields"], $person_duplicates[0]["display_summary"]));
    gd_assert("nome semelhante gera confiança baixa", !empty($person_service->duplicates(["full_name" => "Pessoa Beto Teste"])[0]["confidence"]));
    $override_audit = $db->table($prefix . "gd_audit_logs")->where("unit_id", $unit_id)->where("action", "duplicate_override")->countAllResults();
    gd_assert("duplicidades ignoradas são auditadas", $override_audit >= 3, $override_audit . " eventos");

    echo "# Fase 2A — segurança e pesquisa\n";
    $xss = $account_service->save(["account_type" => "other", "display_name" => "<script>alert('x')</script>", "document_type" => "none", "status" => "active", "unit_id" => $other_unit_id, "deleted" => 1]);
    $xss_row = $account_service->get($xss["id"]);
    gd_assert("mass assignment não altera unidade ou deleted", (int) $xss_row->unit_id === $unit_id && (int) $xss_row->deleted === 0);
    gd_assert("XSS armazenado é neutralizável na saída", str_contains(htmlspecialchars($xss_row->display_name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"), "&lt;script&gt;"));
    $account_search = model("grupo_donato_gestao\\Models\\Gd_customer_accounts_model")->get_details(["unit_id" => $unit_id, "search_by" => "Pessoa Beta Teste", "order_by" => "DROP TABLE users", "limit" => 25]);
    gd_assert("pesquisa de conta encontra pessoa vinculada", $account_search["recordsFiltered"] >= 1);
    $people_search = model("grupo_donato_gestao\\Models\\Gd_people_model")->get_details(["unit_id" => $unit_id, "search_by" => "Família Teste", "order_by" => "DROP TABLE users", "limit" => 25]);
    gd_assert("pesquisa de pessoa encontra conta vinculada", $people_search["recordsFiltered"] >= 1);
    gd_assert("ordenação arbitrária usa whitelist", $db->tableExists($prefix . "users") && is_array($account_search["data"]));
    gd_assert("documento é mascarado para listagem", DataPrivacyService::maskDocument("123.456.789-01") === "*******8901");
    $personal_audits = $db->table($prefix . "gd_audit_logs")->where("unit_id", $unit_id)->whereIn("entity_type", ["customer_account", "contact_method", "address"])->get()->getResult();
    $audit_blob = json_encode($personal_audits);
    gd_assert("auditoria não contém documento/telefone/endereço completos", !str_contains($audit_blob, "12345678901") && !str_contains($audit_blob, "11988887777") && !str_contains($audit_blob, "Rua Teste"), $audit_blob);

    echo "# Permissões e rotas\n";
    $admin = (object) ["is_admin" => 1, "user_type" => "staff", "permissions" => []];
    $viewer = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => ["gd_dashboard_view" => "1"]];
    $manager = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => ["gd_units_manage" => "1"]];
    $customer_manager = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => ["gd_customer_accounts_manage" => "1"]];
    $contact_manager = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => ["gd_contacts_manage" => "1"]];
    $relation_manager = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => ["gd_customer_relations_manage" => "1"]];
    $denied = (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => []];
    gd_assert("admin possui acesso total", (new AccessService($admin))->can("gd_audit_view"));
    gd_assert("staff autorizado acessa permissão concedida", (new AccessService($viewer))->can("gd_dashboard_view"));
    gd_assert("manage implica view", (new AccessService($manager))->can("gd_units_view"));
    gd_assert("manage de contas implica visualização", (new AccessService($customer_manager))->can("gd_customer_accounts_view"));
    gd_assert("manage de contatos implica visualização de pessoas", (new AccessService($contact_manager))->can("gd_people_view"));
    gd_assert("manage de relações implica visualizar contas e pessoas", (new AccessService($relation_manager))->can("gd_customer_accounts_view") && (new AccessService($relation_manager))->can("gd_people_view"));
    gd_assert("staff sem permissão é bloqueado no backend", !(new AccessService($denied))->can("gd_dashboard_view"));

    $routes = service("routes");
    $get_routes = $routes->getRoutes("GET");
    $post_routes = $routes->getRoutes("POST");
    gd_assert("dashboard existe apenas como GET", isset($get_routes["grupo_donato/dashboard"]) && !isset($post_routes["grupo_donato/dashboard"]));
    gd_assert("save existe apenas como POST", isset($post_routes["grupo_donato/settings/units/save"]) && !isset($get_routes["grupo_donato/settings/units/save"]));
    $has_customer_view = count(array_filter(array_keys($get_routes), fn($route) => str_starts_with((string) $route, "grupo_donato/customers/view/"))) === 1;
    gd_assert("lista e detalhe de contas existem como GET", isset($get_routes["grupo_donato/customers"]) && $has_customer_view);
    gd_assert("escrita da Fase 2A existe apenas como POST", isset($post_routes["grupo_donato/customers/save"]) && isset($post_routes["grupo_donato/people/save"]) && !isset($get_routes["grupo_donato/customers/save"]));
    $post_options = $routes->getRoutesOptions("grupo_donato/settings/units/save", "POST");
    $route_filters = (array) get_array_value($post_options, "filter");
    gd_assert("rotas do plugin aplicam filtro CSRF", in_array("csrf", $route_filters, true), json_encode($post_options));
    $customer_post_options = $routes->getRoutesOptions("grupo_donato/customers/save", "POST");
    gd_assert("CSRF também protege escrita da Fase 2A", in_array("csrf", (array) get_array_value($customer_post_options, "filter"), true));

    echo "# Idioma\n";
    service("language")->setLocale((string) (get_setting("language") ?: "english"));
    gd_assert("chave de idioma do plugin resolve", app_lang("gd_app_title") === "Grupo Donato", app_lang("gd_app_title"));
    gd_assert("nova mensagem de validação resolve", app_lang("gd_invalid_business_area") !== "gd_invalid_business_area");
    gd_assert("idioma da Fase 2A resolve", app_lang("gd_customer_accounts") === "Contas de clientes" && app_lang("gd_role_financial_responsible") !== "gd_role_financial_responsible");
    $lang_source = (string) file_get_contents(__DIR__ . "/../Language/portuguese/default_lang.php");
    preg_match_all('/"(gd_[A-Za-z0-9_]+)"\s*=>/', $lang_source, $lang_matches);
    gd_assert("idioma não contém chaves gd_* duplicadas", count($lang_matches[1]) === count(array_unique($lang_matches[1])));
    $dynamic_language_keys = [];
    foreach ([
        "gd_account_type_" => Constants::ACCOUNT_TYPES,
        "gd_role_" => Constants::ACCOUNT_PERSON_ROLES,
        "gd_contact_type_" => Constants::CONTACT_TYPES,
        "gd_address_type_" => Constants::ADDRESS_TYPES,
        "gd_cc_type_" => Constants::COST_CENTER_TYPES,
        "gd_resource_type_" => Constants::RESOURCE_TYPES,
        "gd_product_type_" => Constants::PRODUCT_TYPES_SELECTABLE,
        "gd_billing_mode_" => Constants::BILLING_MODES,
        "gd_uom_" => Constants::UNITS_OF_MEASURE,
        "gd_exception_type_" => Constants::AVAILABILITY_EXCEPTION_TYPES,
        "gd_block_type_" => Constants::RESOURCE_BLOCK_TYPES,
        "gd_status_" => array_unique(array_merge(Constants::ACCOUNT_STATUSES, Constants::PERSON_STATUSES, Constants::RELATION_STATUSES, Constants::PRODUCT_CATEGORY_STATUSES, Constants::PRODUCT_STATUSES, Constants::VARIANT_STATUSES, Constants::PRICE_LIST_STATUSES, Constants::PRICE_STATUSES, Constants::AVAILABILITY_RULE_STATUSES, Constants::AVAILABILITY_EXCEPTION_STATUSES, Constants::RESOURCE_BLOCK_STATUSES)),
    ] as $prefix_key => $values) {
        foreach ($values as $value) { $dynamic_language_keys[] = $prefix_key . $value; }
    }
    $missing_dynamic_keys = array_values(array_filter($dynamic_language_keys, static fn($key) => app_lang($key) === $key));
    gd_assert("chaves dinâmicas de idioma resolvem", !$missing_dynamic_keys, implode(",", $missing_dynamic_keys));

    /* ===================== Fase 2B — Catálogo, recursos e preços ===================== */
    $U = $def_unit_id;
    $catSvc = fn($u = null) => new \grupo_donato_gestao\Services\ProductCategoryService($u ?: $U, 0, null);
    $resSvc = fn($u = null) => new \grupo_donato_gestao\Services\ResourceService($u ?: $U, 0, null);
    $prodSvc = fn($u = null) => new \grupo_donato_gestao\Services\ProductService($u ?: $U, 0, null);
    $varSvc = fn($u = null) => new \grupo_donato_gestao\Services\ProductVariantService($u ?: $U, 0, null);
    $listSvc = fn($u = null) => new \grupo_donato_gestao\Services\PriceListService($u ?: $U, 0, null);
    $priceSvc = fn($u = null) => new \grupo_donato_gestao\Services\PricingService($u ?: $U, 0, null);

    // unidade secundária (para testes de escopo cruzado)
    $unit2_data = ["name" => "__selftest_unit2__", "status" => "active", "is_default" => 0, "deleted" => 0];
    $unit2_id = (int) $units->ci_save($unit2_data);

    echo "# Categorias\n";
    $catRoot = $catSvc()->save(["code" => "CAT_ROOT", "name" => "Raiz"]);
    gd_assert("categoria raiz criada", !empty($catRoot["saved"]) && $catRoot["id"] > 0);
    $catChild = $catSvc()->save(["code" => "CAT_CHILD", "name" => "Filha", "parent_id" => $catRoot["id"]]);
    gd_assert("subcategoria criada", !empty($catChild["saved"]));
    gd_assert("código de categoria duplicado bloqueado", gd_throws(fn() => $catSvc()->save(["code" => "CAT_ROOT", "name" => "Outra"]), "gd_duplicate_code"));
    gd_assert("autorreferência bloqueada", gd_throws(fn() => $catSvc()->save(["code" => "CAT_ROOT", "name" => "Raiz", "parent_id" => $catRoot["id"]], $catRoot["id"]), "gd_category_self_parent"));
    gd_assert("ciclo indireto bloqueado", gd_throws(fn() => $catSvc()->save(["code" => "CAT_ROOT", "name" => "Raiz", "parent_id" => $catChild["id"]], $catRoot["id"]), "gd_category_cycle"));
    gd_assert("excluir categoria com subcategoria ativa bloqueado", gd_throws(fn() => $catSvc()->delete($catRoot["id"]), "gd_category_has_subcategories"));
    gd_assert("categoria de outra unidade rejeitada em produto", gd_throws(fn() => $prodSvc($unit2_id)->save(["code" => "X", "name" => "X", "product_type" => "service", "billing_mode" => "one_time", "unit_of_measure" => "unit", "category_id" => $catRoot["id"]]), "gd_invalid_category"));

    echo "# Recursos\n";
    gd_assert("recurso de seed Q2 existe", $resSvc()->get((int) $db->table($prefix . "gd_resources")->where("unit_id", $U)->where("code", "Q2")->get(1)->getRow()->id) !== null);
    $resCreate = $resSvc()->save(["code" => "SALA1", "name" => "Sala 1", "resource_type" => "room", "is_bookable" => 1, "is_active" => 1]);
    gd_assert("recurso criado", !empty($resCreate["saved"]));
    $resource_detail = model("grupo_donato_gestao\\Models\\Gd_resources_model")->get_details(["unit_id" => $U, "id" => $resCreate["id"], "limit" => 1])["data"];
    gd_assert("detalhe de recurso respeita o id solicitado", count($resource_detail) === 1 && (int) $resource_detail[0]->id === (int) $resCreate["id"]);
    gd_assert("recurso código duplicado bloqueado", gd_throws(fn() => $resSvc()->save(["code" => "SALA1", "name" => "Outra", "resource_type" => "room"]), "gd_duplicate_code"));
    gd_assert("tipo de recurso inválido bloqueado", gd_throws(fn() => $resSvc()->save(["code" => "SALA2", "name" => "x", "resource_type" => "spaceship"]), "gd_invalid_resource_type"));
    gd_assert("capacidade negativa bloqueada", gd_throws(fn() => $resSvc()->save(["code" => "SALA3", "name" => "x", "resource_type" => "room", "capacity" => "-3"]), "gd_invalid_capacity"));
    gd_assert("área de negócio inválida bloqueada", gd_throws(fn() => $resSvc()->save(["code" => "SALA4", "name" => "x", "resource_type" => "room", "business_area_id" => 999999]), "gd_invalid_business_area"));
    gd_assert("metadata JSON inválida bloqueada", gd_throws(fn() => $resSvc()->save(["code" => "SALA5", "name" => "x", "resource_type" => "room", "metadata" => "{nope"]), "gd_invalid_json"));
    gd_assert("recurso soft delete", (function () use ($resSvc, $db, $prefix) { $r = $resSvc()->save(["code" => "DELME", "name" => "x", "resource_type" => "room"]); $resSvc()->delete($r["id"]); $row = $db->table($prefix . "gd_resources")->where("id", $r["id"])->get(1)->getRow(); return $row && (int) $row->deleted === 1; })());

    echo "# Produtos\n";
    $svc = $prodSvc()->save(["code" => "PROD_SVC", "name" => "Mensalidade Escola", "product_type" => "service", "billing_mode" => "recurring", "unit_of_measure" => "month", "status" => "active", "category_id" => $catRoot["id"], "allows_variants" => 1, "track_stock" => 1]);
    gd_assert("serviço criado", !empty($svc["saved"]));
    gd_assert("flag track_stock normalizada (serviço não controla estoque)", (int) $prodSvc()->get($svc["id"])->track_stock === 0);
    $phys = $prodSvc()->save(["code" => "PROD_PHY", "name" => "Camiseta", "product_type" => "physical", "billing_mode" => "one_time", "unit_of_measure" => "unit", "status" => "active", "allows_variants" => 1, "track_stock" => 1]);
    gd_assert("produto físico com estoque permitido", (int) $prodSvc()->get($phys["id"])->track_stock === 1);
    $rental = $prodSvc()->save(["code" => "PROD_LOC", "name" => "Locação de quadra", "product_type" => "rental", "billing_mode" => "per_hour", "unit_of_measure" => "hour", "status" => "active", "requires_resource" => 1]);
    gd_assert("locação criada", !empty($rental["saved"]));
    $product_detail = model("grupo_donato_gestao\\Models\\Gd_products_model")->get_details(["unit_id" => $U, "id" => $rental["id"], "limit" => 1])["data"];
    gd_assert("detalhe de produto respeita o id solicitado", count($product_detail) === 1 && (int) $product_detail[0]->id === (int) $rental["id"]);
    gd_assert("tipo de produto inválido (credit) bloqueado", gd_throws(fn() => $prodSvc()->save(["code" => "PC", "name" => "x", "product_type" => "credit", "billing_mode" => "one_time", "unit_of_measure" => "unit"]), "gd_invalid_product_type"));
    gd_assert("modo de cobrança inválido bloqueado", gd_throws(fn() => $prodSvc()->save(["code" => "PB", "name" => "x", "product_type" => "service", "billing_mode" => "weekly", "unit_of_measure" => "unit"]), "gd_invalid_billing_mode"));
    gd_assert("código de produto duplicado bloqueado", gd_throws(fn() => $prodSvc()->save(["code" => "PROD_SVC", "name" => "y", "product_type" => "service", "billing_mode" => "one_time", "unit_of_measure" => "unit"]), "gd_duplicate_code"));
    gd_assert("vínculo Rise inválido bloqueado", gd_throws(fn() => $prodSvc()->save(["code" => "PR", "name" => "x", "product_type" => "service", "billing_mode" => "one_time", "unit_of_measure" => "unit", "rise_item_id" => 99999999]), "gd_invalid_rise_link"));

    echo "# Variações\n";
    $v1 = $varSvc()->save(["product_id" => $svc["id"], "code" => "V1", "name" => "Tam P", "attributes" => '{"size":"P"}', "is_default" => 1]);
    $v2 = $varSvc()->save(["product_id" => $svc["id"], "code" => "V2", "name" => "Tam M", "attributes" => '{"size":"M"}', "is_default" => 1]);
    gd_assert("variações criadas", !empty($v1["saved"]) && !empty($v2["saved"]));
    gd_assert("código de variação duplicado bloqueado", gd_throws(fn() => $varSvc()->save(["product_id" => $svc["id"], "code" => "V1", "name" => "z"]), "gd_duplicate_code"));
    gd_assert("variação em produto sem allows_variants bloqueada", gd_throws(fn() => $varSvc()->save(["product_id" => $rental["id"], "code" => "VR", "name" => "z"]), "gd_product_no_variants"));
    gd_assert("attributes JSON inválido bloqueado", gd_throws(fn() => $varSvc()->save(["product_id" => $svc["id"], "code" => "VJ", "name" => "z", "attributes" => "{bad"]), "gd_invalid_json"));
    $def_after = $db->table($prefix . "gd_product_variants")->where("product_id", $svc["id"])->where("is_default", 1)->where("deleted", 0)->countAllResults();
    gd_assert("apenas uma variação padrão ativa (troca transacional)", $def_after === 1, $def_after . " padrões");
    gd_assert("variação padrão é a última marcada", (int) $varSvc()->get($v2["id"])->is_default === 1 && (int) $varSvc()->get($v1["id"])->is_default === 0);

    echo "# Tabelas de preço\n";
    $list = $listSvc()->save(["code" => "L1", "name" => "Lista 1", "currency" => "BRL", "priority" => 10, "status" => "active", "is_default" => 0]);
    gd_assert("tabela de preço criada", !empty($list["saved"]));
    gd_assert("código de tabela duplicado bloqueado", gd_throws(fn() => $listSvc()->save(["code" => "L1", "name" => "y", "currency" => "BRL"]), "gd_duplicate_code"));
    gd_assert("moeda inválida bloqueada", gd_throws(fn() => $listSvc()->save(["code" => "L2", "name" => "y", "currency" => "REAL"]), "gd_invalid_currency"));
    gd_assert("período inválido bloqueado", gd_throws(fn() => $listSvc()->save(["code" => "L3", "name" => "y", "currency" => "BRL", "valid_from" => "2026-12-31", "valid_until" => "2026-01-01"]), "gd_invalid_date_range"));
    $listSvc()->save(["code" => "LD1", "name" => "Padrão A", "currency" => "BRL", "status" => "active", "is_default" => 1]);
    $listSvc()->save(["code" => "LD2", "name" => "Padrão B", "currency" => "BRL", "status" => "active", "is_default" => 1]);
    $def_lists = $db->table($prefix . "gd_price_lists")->where("unit_id", $U)->where("is_default", 1)->where("deleted", 0)->where("status", "active")->countAllResults();
    gd_assert("apenas uma tabela padrão ativa por unidade", $def_lists === 1, $def_lists . " padrões");

    echo "# Preços\n";
    $pid = $svc["id"];
    $base = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "amount" => "100.00", "minimum_quantity" => "1"]);
    $pvar = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "variant_id" => $v1["id"], "amount" => "120.00", "minimum_quantity" => "1"]);
    $qres_id = (int) $db->table($prefix . "gd_resources")->where("unit_id", $U)->where("code", "Q2")->get(1)->getRow()->id;
    $pres = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "resource_id" => $qres_id, "amount" => "150.00", "minimum_quantity" => "1"]);
    $pvr = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "variant_id" => $v1["id"], "resource_id" => $qres_id, "amount" => "175.00", "minimum_quantity" => "1"]);
    $ptier = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "amount" => "90.00", "minimum_quantity" => "10"]);
    gd_assert("4 escopos + tier criados", !empty($base["saved"]) && !empty($pvar["saved"]) && !empty($pres["saved"]) && !empty($pvr["saved"]) && !empty($ptier["saved"]));
    gd_assert("valor decimal preservado", (string) $priceSvc()->get($base["id"])->amount === "100.00");
    gd_assert("valor negativo rejeitado", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "amount" => "-5", "minimum_quantity" => "1"]), "gd_negative_amount"));
    gd_assert("custo de referência negativo rejeitado", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "amount" => "5", "reference_cost" => "-1", "minimum_quantity" => "1"]), "gd_negative_amount"));
    gd_assert("quantidade mínima zero rejeitada", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "amount" => "5", "minimum_quantity" => "0"]), "gd_invalid_quantity"));
    gd_assert("comparação decimal preserva precisão sem float", DataNormalizationService::decimalCompare("999999999999.999", "999999999999.998") > 0 && DataNormalizationService::decimalCompare("1.010", "1.01") === 0);
    $pricing_source = (string) file_get_contents(__DIR__ . "/../Services/PricingService.php");
    gd_assert("cálculo de preço não converte decimal para float", !preg_match('/\(float\)|floatval|doubleval/i', $pricing_source));
    gd_assert("vigência inválida rejeitada", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "amount" => "5", "minimum_quantity" => "1", "valid_from" => "2026-05-01", "valid_until" => "2026-01-01"]), "gd_invalid_date_range"));
    gd_assert("escopo cruzado (variação de outro produto) rejeitado", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "variant_id" => $v1["id"], "amount" => "5", "minimum_quantity" => "1"]), "gd_invalid_variant"));
    gd_assert("sobreposição de período rejeitada", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "amount" => "110.00", "minimum_quantity" => "1"]), "gd_price_overlap"));
    // soft-deleted não bloqueia novo preço de mesmo escopo
    $priceSvc()->delete($base["id"]);
    $base2 = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $pid, "amount" => "105.00", "minimum_quantity" => "1"]);
    gd_assert("preço soft-deletado não bloqueia novo de mesmo escopo", !empty($base2["saved"]));
    $price_detail = model("grupo_donato_gestao\\Models\\Gd_prices_model")->get_details(["unit_id" => $U, "id" => $pvr["id"], "limit" => 1])["data"];
    gd_assert("detalhe de preço respeita o id solicitado", count($price_detail) === 1 && (int) $price_detail[0]->id === (int) $pvr["id"]);

    echo "# Resolução de preço\n";
    $rv = fn($params) => $priceSvc()->resolve(array_merge(["price_list_id" => $list["id"], "product_id" => $pid, "quantity" => "1"], $params));
    gd_assert("precedência: variação+recurso", ($r = $rv(["variant_id" => $v1["id"], "resource_id" => $qres_id]))["found"] && $r["matched_scope"] === "variant_resource" && $r["amount"] === "175.00");
    gd_assert("precedência: produto+recurso", ($r = $rv(["resource_id" => $qres_id]))["found"] && $r["matched_scope"] === "product_resource" && $r["amount"] === "150.00");
    gd_assert("precedência: variação", ($r = $rv(["variant_id" => $v1["id"]]))["found"] && $r["matched_scope"] === "variant" && $r["amount"] === "120.00");
    gd_assert("precedência: produto base", ($r = $rv([]))["found"] && $r["matched_scope"] === "product_base" && $r["amount"] === "105.00");
    gd_assert("quantidade mínima (tier) aplicada", ($r = $rv(["quantity" => "15"]))["found"] && $r["amount"] === "90.00");
    gd_assert("moeda retornada", $rv([])["currency"] === "BRL");
    gd_assert("quantidade zero é rejeitada sem fallback silencioso", gd_throws(fn() => $rv(["quantity" => "0"]), "gd_invalid_quantity"));

    $varSvc()->save(["product_id" => $pid, "code" => "V1", "name" => "Tam P", "attributes" => '{"size":"P"}', "status" => "inactive"], $v1["id"]);
    $inactive_variant_result = $rv(["variant_id" => $v1["id"]]);
    gd_assert("variação inativa não resolve nem cai para preço-base", !$inactive_variant_result["found"] && $inactive_variant_result["reason"] === "variant_not_resolvable");
    $varSvc()->save(["product_id" => $pid, "code" => "V1", "name" => "Tam P", "attributes" => '{"size":"P"}', "status" => "active"], $v1["id"]);

    $q2 = $resSvc()->get($qres_id);
    $resSvc()->save(["code" => $q2->code, "name" => $q2->name, "resource_type" => $q2->resource_type, "is_bookable" => $q2->is_bookable, "is_active" => 0], $qres_id);
    $inactive_resource_result = $rv(["resource_id" => $qres_id]);
    gd_assert("recurso inativo não resolve nem cai para preço-base", !$inactive_resource_result["found"] && $inactive_resource_result["reason"] === "resource_not_resolvable");
    $resSvc()->save(["code" => $q2->code, "name" => $q2->name, "resource_type" => $q2->resource_type, "is_bookable" => $q2->is_bookable, "is_active" => 1], $qres_id);

    $dated_product = $prodSvc()->save(["code" => "PROD_DATE", "name" => "Produto por vigência", "product_type" => "service", "billing_mode" => "one_time", "unit_of_measure" => "unit", "status" => "active"]);
    $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $dated_product["id"], "amount" => "50.00", "minimum_quantity" => "1", "valid_from" => "2025-01-01", "valid_until" => "2025-12-31"]);
    $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $dated_product["id"], "amount" => "60.00", "minimum_quantity" => "1", "valid_from" => "2026-01-01", "valid_until" => "2026-12-31"]);
    gd_assert("data de referência seleciona a vigência aplicável", $priceSvc()->resolve(["price_list_id" => $list["id"], "product_id" => $dated_product["id"], "quantity" => "1", "reference_date" => "2025-06-01"])["amount"] === "50.00" && $priceSvc()->resolve(["price_list_id" => $list["id"], "product_id" => $dated_product["id"], "quantity" => "1", "reference_date" => "2026-06-01"])["amount"] === "60.00");

    $unpriced_product = $prodSvc()->save(["code" => "PROD_NOPRICE", "name" => "Produto sem preço aplicável", "product_type" => "service", "billing_mode" => "one_time", "unit_of_measure" => "unit", "status" => "active"]);
    $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $unpriced_product["id"], "amount" => "70.00", "minimum_quantity" => "1", "status" => "inactive"]);
    $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $unpriced_product["id"], "amount" => "80.00", "minimum_quantity" => "1", "valid_from" => "2000-01-01", "valid_until" => "2000-12-31"]);
    gd_assert("preço inativo ou expirado não resolve", $priceSvc()->resolve(["price_list_id" => $list["id"], "product_id" => $unpriced_product["id"], "quantity" => "1", "reference_date" => "2026-06-01"])["found"] === false);

    $inactive_list = $listSvc()->save(["code" => "LINACTIVE", "name" => "Lista inativa", "currency" => "BRL", "status" => "inactive"]);
    $expired_list = $listSvc()->save(["code" => "LEXPIRED", "name" => "Lista expirada", "currency" => "BRL", "status" => "active", "valid_until" => "2000-12-31"]);
    gd_assert("tabela explícita inativa é rejeitada", $priceSvc()->resolve(["price_list_id" => $inactive_list["id"], "product_id" => $pid, "quantity" => "1"])["reason"] === "price_list_invalid");
    gd_assert("tabela explícita expirada é rejeitada", $priceSvc()->resolve(["price_list_id" => $expired_list["id"], "product_id" => $pid, "quantity" => "1"])["reason"] === "price_list_invalid");
    // sem lista explícita usa a tabela padrão da unidade (LD2), que não tem preço deste produto
    gd_assert("tabela padrão usada quando lista omitida (sem preço próprio → ausente)", $priceSvc()->resolve(["product_id" => $pid, "quantity" => "1"])["found"] === false);
    gd_assert("produto inexistente → sem preço", $priceSvc()->resolve(["price_list_id" => $list["id"], "product_id" => 99999999, "quantity" => "1"])["found"] === false);
    // produto inativo → sem preço
    $prodSvc()->save(["code" => "PROD_SVC", "name" => "Mensalidade Escola", "product_type" => "service", "billing_mode" => "recurring", "unit_of_measure" => "month", "status" => "inactive", "category_id" => $catRoot["id"], "allows_variants" => 1], $pid);
    gd_assert("produto inativo → sem preço", $rv([])["found"] === false);

    echo "# Segurança / escopo (Fase 2B)\n";
    gd_assert("get de produto de outra unidade retorna null (IDOR)", $prodSvc($unit2_id)->get($pid) === null);
    gd_assert("preço com lista de outra unidade rejeitado", gd_throws(fn() => $priceSvc($unit2_id)->save(["price_list_id" => $list["id"], "product_id" => $pid, "amount" => "1", "minimum_quantity" => "1"]), "gd_invalid_price_list"));
    gd_assert("JSON excessivo rejeitado", gd_throws(fn() => $resSvc()->save(["code" => "BIGJSON", "name" => "x", "resource_type" => "room", "metadata" => '{"k":"' . str_repeat("a", 60001) . '"}']), "gd_json_too_large"));
    gd_assert("valor monetário malformado rejeitado", gd_throws(fn() => $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $phys["id"], "amount" => "1,2,3", "minimum_quantity" => "1"]), "gd_invalid_amount"));

    echo "# Concorrência / invariantes de banco (Fase 2B)\n";
    // Mesmo que a aplicação fosse contornada (corrida), os índices únicos
    // normalizados rejeitam um 2º padrão ativo ou escopo de preço sobreposto.
    $cnt_def_var = fn() => $db->table($prefix . "gd_product_variants")->where("product_id", $svc["id"])->where("is_default", 1)->where("deleted", 0)->where("status", "active")->countAllResults();
    $before_dv = $cnt_def_var();
    try { $db->query("INSERT IGNORE INTO `{$prefix}gd_product_variants` (unit_id,product_id,code,name,is_default,sort_order,status,deleted,created_at) VALUES (?,?,?,?,1,0,'active',0,UTC_TIMESTAMP())", [$U, $svc["id"], "VDUP", "Dup"]); } catch (\Throwable $e) {}
    gd_assert("índice único bloqueia 2ª variação padrão", $cnt_def_var() === 1 && $before_dv === 1, $cnt_def_var() . " padrões");

    $cnt_def_list = fn() => $db->table($prefix . "gd_price_lists")->where("unit_id", $U)->where("is_default", 1)->where("deleted", 0)->where("status", "active")->countAllResults();
    try { $db->query("INSERT IGNORE INTO `{$prefix}gd_price_lists` (unit_id,code,name,currency,priority,is_default,status,deleted,created_at) VALUES (?,?,?,?,0,1,'active',0,UTC_TIMESTAMP())", [$U, "LDUP", "Dup", "BRL"]); } catch (\Throwable $e) {}
    gd_assert("índice único bloqueia 2ª tabela padrão da unidade", $cnt_def_list() === 1, $cnt_def_list() . " padrões");

    $cnt_scope = fn() => $db->table($prefix . "gd_prices")->where("price_list_id", $list["id"])->where("product_id", $pid)->where("variant_id IS NULL", null, false)->where("resource_id IS NULL", null, false)->where("minimum_quantity", "1.000")->where("deleted", 0)->where("status", "active")->countAllResults();
    $before_scope = $cnt_scope();
    try { $db->query("INSERT IGNORE INTO `{$prefix}gd_prices` (unit_id,price_list_id,product_id,amount,minimum_quantity,status,deleted,created_at) VALUES (?,?,?,?,1.000,'active',0,UTC_TIMESTAMP())", [$U, $list["id"], $pid, "99.00"]); } catch (\Throwable $e) {}
    gd_assert("índice único bloqueia preço de escopo+data duplicado", $cnt_scope() === $before_scope && $before_scope === 1, $cnt_scope() . " no escopo");

    echo "# Permissões & rotas (Fase 2B)\n";
    $pm = fn($keys) => (object) ["is_admin" => 0, "user_type" => "staff", "permissions" => array_fill_keys((array) $keys, "1")];
    gd_assert("products_manage implica catalog_view", (new AccessService($pm("gd_products_manage")))->can("gd_catalog_view"));
    gd_assert("resources_manage implica resources_view", (new AccessService($pm("gd_resources_manage")))->can("gd_resources_view"));
    gd_assert("price_lists_manage implica price_lists_view", (new AccessService($pm("gd_price_lists_manage")))->can("gd_price_lists_view"));
    gd_assert("prices_manage implica leitura de catálogo/recursos/tabelas", (new AccessService($pm("gd_prices_manage")))->can("gd_catalog_view") && (new AccessService($pm("gd_prices_manage")))->can("gd_resources_view") && (new AccessService($pm("gd_prices_manage")))->can("gd_price_lists_view"));
    gd_assert("price_lists_manage NÃO concede gestão de produtos", !(new AccessService($pm("gd_price_lists_manage")))->can("gd_products_manage"));
    gd_assert("catálogo: páginas GET e escrita POST", isset($get_routes["grupo_donato/catalog/products"]) && isset($post_routes["grupo_donato/catalog/products/save"]) && !isset($get_routes["grupo_donato/catalog/products/save"]));
    gd_assert("preços: escrita só POST; resolver GET; resolve POST", isset($post_routes["grupo_donato/pricing/prices/save"]) && isset($get_routes["grupo_donato/pricing/resolver"]) && isset($post_routes["grupo_donato/pricing/resolve"]));
    gd_assert("CSRF aplicado às rotas de catálogo", in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/catalog/products/save", "POST"), "filter"), true));

    /* ===================== Fase 3A — disponibilidade e calendário-base ===================== */
    echo "# Fase 3A — tempo e regras semanais\n";
    $time = new \grupo_donato_gestao\Services\TemporalService($U);
    gd_assert("timezone da unidade é IANA válido", in_array($time->timezoneName(), \DateTimeZone::listIdentifiers(), true));
    $local_noon_utc = $time->localToUtc("2026-06-22", "09:00");
    gd_assert("horário local converte para UTC e retorna ao mesmo wall time", $time->utcToLocal($local_noon_utc)->format("Y-m-d H:i") === "2026-06-22 09:00");
    gd_assert("intervalo invertido é rejeitado", gd_throws(fn() => $time->validateRange("2026-06-22 13:00:00", "2026-06-22 12:00:00"), "gd_invalid_datetime_range"));
    $db->table($prefix . "gd_units")->where("id", $unit2_id)->update(["timezone" => "America/New_York"]);
    $dst_time = new \grupo_donato_gestao\Services\TemporalService($unit2_id);
    gd_assert("horário DST inexistente é rejeitado", gd_throws(fn() => $dst_time->localToUtc("2026-03-08", "02:30"), "gd_invalid_local_datetime"));
    gd_assert("horário DST ambíguo é rejeitado", gd_throws(fn() => $dst_time->localToUtc("2026-11-01", "01:30"), "gd_ambiguous_local_time"));

    $rid = (int) $resCreate["id"];
    $ruleSvc = new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($U, 0, null);
    $exceptionSvc = new \grupo_donato_gestao\Services\ResourceAvailabilityExceptionService($U, 0, null);
    $blockSvc = new \grupo_donato_gestao\Services\ResourceBlockService($U, 0, null);
    $availability = new \grupo_donato_gestao\Services\AvailabilityService($U);
    $rule1 = $ruleSvc->save(["resource_id"=>$rid,"weekday"=>1,"start_time"=>"09:00","end_time"=>"12:00","status"=>"active"]);
    $rule2 = $ruleSvc->save(["resource_id"=>$rid,"weekday"=>1,"start_time"=>"12:00","end_time"=>"13:00","status"=>"active"]);
    gd_assert("regras semanais adjacentes são permitidas", !empty($rule1["saved"]) && !empty($rule2["saved"]));
    gd_assert("sobreposição semanal é bloqueada", gd_throws(fn() => $ruleSvc->save(["resource_id"=>$rid,"weekday"=>1,"start_time"=>"11:30","end_time"=>"12:30"]), "gd_weekly_rule_overlap"));
    gd_assert("intervalo sem flag overnight não cruza meia-noite", gd_throws(fn() => $ruleSvc->save(["resource_id"=>$rid,"weekday"=>5,"start_time"=>"22:00","end_time"=>"02:00"]), "gd_invalid_weekly_interval"));
    $overnight = $ruleSvc->save(["resource_id"=>$rid,"weekday"=>5,"start_time"=>"22:00","end_time"=>"02:00","spans_next_day"=>1]);
    gd_assert("regra semanal atravessa meia-noite", !empty($overnight["saved"]));
    $inside = $availability->check($rid,"2026-06-22 12:30:00","2026-06-22 13:00:00");
    gd_assert("regra semanal disponibiliza intervalo contido", $inside["available"] && $inside["source"] === "weekly_rule" && $inside["reason_code"] === "available_weekly_rule");
    $adjacent_union = $availability->check($rid,"2026-06-22 14:30:00","2026-06-22 15:30:00");
    gd_assert("regras adjacentes cobrem intervalo contínuo", $adjacent_union["available"] && count($adjacent_union["matched_rule_ids"]) === 2);
    $overnight_check = $availability->check($rid,"2026-06-27 03:30:00","2026-06-27 04:00:00");
    gd_assert("disponibilidade overnight cobre o dia seguinte", $overnight_check["available"] && $overnight_check["source"] === "weekly_rule");
    gd_assert("fora das regras permanece indisponível", !$availability->check($rid,"2026-06-23 12:00:00","2026-06-23 13:00:00")["available"]);
    gd_assert("regra de recurso de outra unidade é rejeitada", gd_throws(fn() => (new \grupo_donato_gestao\Services\ResourceAvailabilityRuleService($unit2_id))->save(["resource_id"=>$rid,"weekday"=>1,"start_time"=>"09:00","end_time"=>"10:00"]), "gd_invalid_resource"));

    echo "# Fase 3A — exceções, bloqueios e precedência\n";
    $open = $exceptionSvc->save(["resource_id"=>$rid,"exception_type"=>"open","starts_at_utc"=>"2026-06-21 13:00:00","ends_at_utc"=>"2026-06-21 14:00:00","title"=>"Abertura especial"]);
    gd_assert("exceção open disponibiliza fora da regra semanal", $availability->check($rid,"2026-06-21 13:15:00","2026-06-21 13:45:00")["source"] === "open_exception");
    $closed = $exceptionSvc->save(["resource_id"=>$rid,"exception_type"=>"closed","starts_at_utc"=>"2026-06-22 12:45:00","ends_at_utc"=>"2026-06-22 13:15:00","title"=>"Fechamento"]);
    $closed_result = $availability->check($rid,"2026-06-22 12:50:00","2026-06-22 13:00:00");
    gd_assert("closed exception prevalece sobre regra semanal", !$closed_result["available"] && $closed_result["source"] === "closed_exception");
    $overlap_alert = $exceptionSvc->save(["resource_id"=>$rid,"exception_type"=>"closed","starts_at_utc"=>"2026-06-22 13:00:00","ends_at_utc"=>"2026-06-22 13:30:00","title"=>"Fechamento 2"]);
    gd_assert("exceção do mesmo tipo sobreposta exige confirmação", empty($overlap_alert["saved"]) && !empty($overlap_alert["overlap_confirmation_required"]));
    $overlap_saved = $exceptionSvc->save(["resource_id"=>$rid,"exception_type"=>"closed","starts_at_utc"=>"2026-06-22 13:00:00","ends_at_utc"=>"2026-06-22 13:30:00","title"=>"Fechamento 2"],0,true);
    gd_assert("override de exceção sobreposta é salvo", !empty($overlap_saved["saved"]));
    gd_assert("duplicata exata de exceção é bloqueada", gd_throws(fn() => $exceptionSvc->save(["resource_id"=>$rid,"exception_type"=>"open","starts_at_utc"=>"2026-06-21 13:00:00","ends_at_utc"=>"2026-06-21 14:00:00","title"=>"Duplicada"]), "gd_duplicate_exact_interval"));
    gd_assert("bloqueio administrativo exige motivo", gd_throws(fn() => $blockSvc->save(["resource_id"=>$rid,"block_type"=>"administrative","starts_at_utc"=>"2026-06-22 16:00:00","ends_at_utc"=>"2026-06-22 17:00:00","title"=>"Admin"]), "gd_reason_required"));
    $block = $blockSvc->save(["resource_id"=>$rid,"block_type"=>"maintenance","starts_at_utc"=>"2026-06-21 13:20:00","ends_at_utc"=>"2026-06-21 13:40:00","title"=>"Manutenção","reason"=>"Preventiva"]);
    $blocked_result = $availability->check($rid,"2026-06-21 13:25:00","2026-06-21 13:35:00");
    gd_assert("bloqueio ativo prevalece sobre open exception", !$blocked_result["available"] && $blocked_result["source"] === "block" && $blocked_result["matched_block_ids"] === [(int)$block["id"]]);
    $block_overlap = $blockSvc->save(["resource_id"=>$rid,"block_type"=>"cleaning","starts_at_utc"=>"2026-06-21 13:30:00","ends_at_utc"=>"2026-06-21 13:50:00","title"=>"Limpeza"]);
    gd_assert("bloqueio sobreposto exige confirmação explícita", empty($block_overlap["saved"]) && !empty($block_overlap["overlap_confirmation_required"]));
    $block_override = $blockSvc->save(["resource_id"=>$rid,"block_type"=>"cleaning","starts_at_utc"=>"2026-06-21 13:30:00","ends_at_utc"=>"2026-06-21 13:50:00","title"=>"Limpeza"],0,true);
    gd_assert("override de bloqueio é salvo", !empty($block_override["saved"]));
    gd_assert("duplicata exata de bloqueio é bloqueada", gd_throws(fn() => $blockSvc->save(["resource_id"=>$rid,"block_type"=>"maintenance","starts_at_utc"=>"2026-06-21 13:20:00","ends_at_utc"=>"2026-06-21 13:40:00","title"=>"Outra","reason"=>"Preventiva"]), "gd_duplicate_exact_interval"));
    $adjacent_block = $blockSvc->save(["resource_id"=>$rid,"block_type"=>"other","starts_at_utc"=>"2026-06-22 11:00:00","ends_at_utc"=>"2026-06-22 12:00:00","title"=>"Antes"]);
    gd_assert("bloqueio adjacente não conflita com início da disponibilidade", !empty($adjacent_block["saved"]) && $availability->check($rid,"2026-06-22 12:00:00","2026-06-22 12:30:00")["available"]);
    $audit_override_count=$db->table($prefix."gd_audit_logs")->where("unit_id",$U)->where("action","overlap_override")->whereIn("entity_type",["resource_availability_exception","resource_block"])->countAllResults();
    gd_assert("overrides de sobreposição são auditados", $audit_override_count>=2, $audit_override_count." eventos");

    echo "# Fase 3A — lote, calendário, permissões e rotas\n";
    $batch=$availability->checkMany([$rid,$qres_id],"2026-06-22 12:00:00","2026-06-22 12:30:00");
    gd_assert("checkMany retorna contrato para múltiplos recursos", count($batch)===2 && isset($batch[$rid]["matched_rule_ids"],$batch[$qres_id]["reason_code"]));
    $resSvc()->save(["code"=>"SALA1","name"=>"Sala 1","resource_type"=>"room","is_bookable"=>0,"is_active"=>1],$rid);
    gd_assert("recurso não reservável fica indisponível antes das regras", $availability->check($rid,"2026-06-22 12:00:00","2026-06-22 12:30:00")["reason_code"]==="resource_not_bookable");
    $resSvc()->save(["code"=>"SALA1","name"=>"Sala 1","resource_type"=>"room","is_bookable"=>1,"is_active"=>1],$rid);
    $calendar=new \grupo_donato_gestao\Services\CalendarService($U);$events=$calendar->events("2026-06-20T00:00:00Z","2026-06-30T00:00:00Z",[$rid],["weekly_rule","open_exception","closed_exception","block"]);
    gd_assert("calendário retorna regras, exceções e bloqueios filtrados", count($events)>3 && !array_filter($events,static fn($e)=>(int)$e["extendedProps"]["resource_id"]!==$rid));
    gd_assert("calendário não expõe cliente, reserva ou preço", !str_contains(json_encode($events),"client") && !str_contains(json_encode($events),"reservation") && !str_contains(json_encode($events),"price"));
    gd_assert("calendário rejeita janela acima do limite", gd_throws(fn()=>$calendar->events("2026-01-01T00:00:00Z","2026-06-01T00:00:00Z"),"gd_calendar_range_too_large"));
    $availabilityAccess=new AccessService($pm("gd_resource_availability_manage"));$blockAccess=new AccessService($pm("gd_resource_blocks_manage"));
    gd_assert("gestão de disponibilidade implica calendário e leitura de recurso",$availabilityAccess->can("gd_calendar_view")&&$availabilityAccess->can("gd_resources_view"));
    gd_assert("gestão de bloqueios implica calendário e leitura de recurso",$blockAccess->can("gd_calendar_view")&&$blockAccess->can("gd_resources_view"));
    gd_assert("gestão temporal NÃO implica gestão de recursos",!$availabilityAccess->can("gd_resources_manage")&&!$blockAccess->can("gd_resources_manage"));
    gd_assert("rotas de calendário são GET e escritas temporais são POST",isset($get_routes["grupo_donato/calendar"],$get_routes["grupo_donato/calendar/events"])&&isset($post_routes["grupo_donato/resources/availability/save"],$post_routes["grupo_donato/resources/exceptions/save"],$post_routes["grupo_donato/resources/blocks/save"])&&!isset($get_routes["grupo_donato/resources/blocks/save"]));
    gd_assert("CSRF protege escrita da Fase 3A",in_array("csrf",(array)get_array_value($routes->getRoutesOptions("grupo_donato/resources/blocks/save","POST"),"filter"),true));
    gd_assert("idioma da Fase 3A resolve",app_lang("gd_menu_calendar")==="Agenda"&&app_lang("gd_block_type_maintenance")!=="gd_block_type_maintenance");

    require __DIR__ . "/booking_selftest.php";
    require __DIR__ . "/series_selftest.php";
    require __DIR__ . "/court_rental_selftest.php";
    require __DIR__ . "/school_selftest.php";
    require __DIR__ . "/finance_selftest.php";
    // Protótipo (Cenário 2): o módulo de importação está oculto e NÃO foi continuado.
    // As tabelas gd_import_* permanecem (validadas acima), porém o importador não
    // integra os 9 fluxos do protótipo, então seu self-test não é executado aqui.
    // require __DIR__ . "/import_selftest.php";
    $transaction_ok = $db->transStatus();
    $db->transRollback();
    gd_assert("bateria transacional concluiu sem falha de banco", $transaction_ok !== false);

    echo "\n==== RESULTADO: {$GLOBALS["gd_pass"]} PASS / {$GLOBALS["gd_fail"]} FAIL ====\n";
    exit($GLOBALS["gd_fail"] ? 1 : 0);
}

echo "Tarefa desconhecida: $task\n";
exit(2);
