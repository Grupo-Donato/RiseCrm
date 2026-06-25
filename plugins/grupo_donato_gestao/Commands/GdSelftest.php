<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use grupo_donato_gestao\Database\Schema\SchemaRunner;
use grupo_donato_gestao\Database\Seeds\FoundationSeeder;
use grupo_donato_gestao\Services\SequenceService;
use grupo_donato_gestao\Services\AuditService;
use grupo_donato_gestao\Services\UnitContextService;
use grupo_donato_gestao\Config\Constants;

/**
 * Bateria de verificação da fundação (integração executável).
 */
class GdSelftest extends BaseCommand
{
    protected $group = "Grupo Donato";
    protected $name = "gd:selftest";
    protected $description = "Roda a bateria de verificação da fundação e do cadastro central (Fases 1 e 2A).";

    private int $passed = 0;
    private int $failed = 0;

    public function run(array $params)
    {
        $db = db_connect();
        $prefix = $db->getPrefix();

        $this->section("Schema & tabelas");
        $expected = ["gd_schema_versions", "gd_units", "gd_business_areas", "gd_cost_centers", "gd_settings", "gd_sequences", "gd_audit_logs", "gd_customer_accounts", "gd_people", "gd_account_people", "gd_contact_methods", "gd_addresses"];
        foreach ($expected as $t) {
            $this->assert("tabela {$prefix}{$t} existe", $db->tableExists($prefix . $t));
        }
        $sv = model("grupo_donato_gestao\\Models\\Gd_schema_versions_model");
        $this->assert("12 versões aplicadas (completed)", $sv->count_by_status(Constants::SCHEMA_STATUS_COMPLETED) === 12, $sv->count_by_status(Constants::SCHEMA_STATUS_COMPLETED) . " completed");
        $this->assert("nenhuma versão com falha", !$sv->has_failed());
        $this->assert("versão aplicada == alvo (" . Constants::SCHEMA_TARGET . ")", $sv->get_applied_version() === Constants::SCHEMA_TARGET, "aplicada=" . $sv->get_applied_version());

        $this->section("Seeds");
        $units = model("grupo_donato_gestao\\Models\\Gd_units_model");
        $areas = model("grupo_donato_gestao\\Models\\Gd_business_areas_model");
        $this->assert("1 unidade padrão", (int) $units->count_active() === 1, $units->count_active() . " unidades");
        $this->assert("7 áreas de negócio", (int) $areas->count_active() === 7, $areas->count_active() . " áreas");

        $this->section("Idempotência");
        $before_areas = (int) $areas->count_active();
        (new FoundationSeeder(0))->run();
        $this->assert("seeds re-rodados sem duplicar áreas", (int) $areas->count_active() === $before_areas, $areas->count_active() . " áreas");
        $rerun = (new SchemaRunner())->run();
        $this->assert("schema re-rodado sem novas versões", count($rerun["ran"]) === 0, "ran=" . implode(",", $rerun["ran"]));
        $dups = $db->query("SELECT version, COUNT(*) c FROM `{$prefix}gd_schema_versions` GROUP BY version HAVING c > 1")->getResult();
        $this->assert("sem versões duplicadas", count($dups) === 0);

        $this->section("Soft delete");
        $tmp = ["name" => "__selftest_unit__", "status" => "active", "is_default" => 0, "deleted" => 0];
        $tmp_id = (int) $units->ci_save($tmp);
        $this->assert("unidade temporária criada", $tmp_id > 0);
        $units->delete($tmp_id);
        $row = $db->table($prefix . "gd_units")->where("id", $tmp_id)->get()->getRow();
        $this->assert("soft delete marca deleted=1", $row && (int) $row->deleted === 1);
        $found = $units->get_details(["id" => $tmp_id])->getRow();
        $this->assert("registro deletado não aparece na listagem", $found === null);

        $this->section("Auditoria + mascaramento");
        $audit = new AuditService(null);
        $audit->log("selftest", "selftest", 1, ["password" => "supersecret", "api_key" => "abc123", "name" => "ok"], ["token" => "zzz", "value" => 42]);
        $last = $db->table($prefix . "gd_audit_logs")->orderBy("id", "DESC")->get(1)->getRow();
        $this->assert("evento de auditoria gravado", $last && $last->action === "selftest");
        $before_json = $last ? (string) $last->before_data : "";
        $this->assert("segredo NÃO aparece em claro", strpos($before_json, "supersecret") === false && strpos($before_json, "abc123") === false, $before_json);
        $this->assert("chave sensível mascarada (***)", strpos($before_json, "***") !== false);
        $this->assert("campo não sensível preservado", strpos($before_json, "ok") !== false);

        $this->section("Sequências (unicidade in-process)");
        $seq = new SequenceService();
        $default_unit = $units->get_default();
        $unit_id = $default_unit ? (int) $default_unit->id : 1;
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
        $this->assert("30 números estritamente crescentes e únicos", $ok);

        $this->section("Contexto de unidade");
        try {
            $uc = new UnitContextService(null);
            $this->assert("acesso à unidade padrão", $uc->user_can_access_unit($unit_id));
            $this->assert("rejeita unidade inexistente", !$uc->user_can_access_unit(999999));
        } catch (\Throwable $e) {
            $this->assert("contexto de unidade utilizável em CLI", false, $e->getMessage());
        }

        // resumo
        CLI::newLine();
        CLI::write("==== RESULTADO: {$this->passed} PASS / {$this->failed} FAIL ====", $this->failed ? "red" : "green");
        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function section(string $title): void
    {
        CLI::newLine();
        CLI::write("# " . $title, "cyan");
    }

    private function assert(string $label, bool $condition, string $detail = ""): void
    {
        if ($condition) {
            $this->passed++;
            CLI::write("  [PASS] " . $label, "green");
        } else {
            $this->failed++;
            CLI::write("  [FAIL] " . $label . ($detail ? " — " . $detail : ""), "red");
        }
    }
}
