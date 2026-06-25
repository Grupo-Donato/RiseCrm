<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Controllers;

use grupo_donato_gestao\Config\Constants;
use grupo_donato_gestao\Services\FinanceService;

/**
 * Visão geral operacional do protótipo.
 *
 * Mostra apenas dados REAIS (sem KPIs fictícios), escopados pela unidade ativa,
 * mais atalhos para os fluxos do dia a dia. Informações técnicas (versão/schema)
 * ficam numa seção discreta — o detalhe completo está em Configurações.
 */
class Dashboard extends Gd_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->access->require("gd_dashboard_view");
    }

    public function index()
    {
        $schema_versions = $this->gd_model("Gd_schema_versions_model");
        $applied = $schema_versions->get_applied_version();
        $active_unit = $this->unit_context->get_active_unit();
        $unit_id = (int) ($this->active_unit_id() ?? 0);

        $can_finance = $this->access->can("gd_finance_view");

        $finance = ["open" => "0.00", "overdue" => "0.00", "received" => "0.00", "balance" => "0.00"];
        if ($can_finance && $unit_id) {
            $f = (new FinanceService($unit_id, $this->user_id(), $this->login_user))->dashboard();
            $finance = ["open" => $f["open"], "overdue" => $f["overdue"], "received" => $f["received"], "balance" => $f["balance"]];
        }

        $recent_audit = [];
        if ($this->access->can("gd_audit_view")) {
            $recent_audit = $this->gd_model("Gd_audit_logs_model")->get_details(["limit" => 6])->getResult();
        }

        $view_data = [
            // KPIs operacionais (dados reais escopados pela unidade)
            "kpi" => $this->operational_kpis($unit_id),
            "finance" => $finance,
            "can_finance" => $can_finance,
            // Atalhos por permissão
            "can_students" => $this->access->can("gd_students_manage"),
            "can_classes" => $this->access->can("gd_classes_manage"),
            "can_attendance" => $this->access->can("gd_attendance_manage"),
            "can_bookings" => $this->access->can("gd_bookings_manage"),
            "can_court_rentals" => $this->access->can("gd_court_rentals_manage"),
            "can_receivables" => $this->access->can("gd_receivables_manage"),
            "can_payments" => $this->access->can("gd_payments_manage"),
            "can_expenses" => $this->access->can("gd_expenses_manage"),
            // Seção técnica discreta
            "plugin_version" => Constants::PLUGIN_VERSION,
            "schema_applied" => $applied ?: "-",
            "schema_target" => Constants::SCHEMA_TARGET,
            "schema_pending" => $applied !== Constants::SCHEMA_TARGET,
            "schema_failed" => $schema_versions->has_failed(),
            "active_unit" => $active_unit,
            "can_view_audit" => $this->access->can("gd_audit_view"),
            "recent_audit" => $recent_audit,
        ];

        return $this->gd_render("dashboard/index", $view_data);
    }

    /** Contagens operacionais reais, escopadas pela unidade ativa (queries read-only). */
    private function operational_kpis(int $unit_id): array
    {
        $zero = ["students" => 0, "classes" => 0, "classes_today" => 0, "bookings_today" => 0, "monthly_renters" => 0];
        if (!$unit_id) {
            return $zero;
        }
        $db = db_connect();
        $p = $db->getPrefix();
        $weekday = (int) date("N"); // 1=segunda .. 7=domingo

        $count = static function (string $sql, array $binds) use ($db): int {
            return (int) ($db->query($sql, $binds)->getRow()->c ?? 0);
        };

        return [
            "students" => $count("SELECT COUNT(*) c FROM `{$p}gd_school_profiles` WHERE unit_id=? AND status='active' AND deleted=0", [$unit_id]),
            "classes" => $count("SELECT COUNT(*) c FROM `{$p}gd_classes` WHERE unit_id=? AND status='active' AND deleted=0", [$unit_id]),
            "classes_today" => $count("SELECT COUNT(*) c FROM `{$p}gd_classes` WHERE unit_id=? AND status='active' AND deleted=0 AND weekdays IS NOT NULL AND FIND_IN_SET(?, weekdays)", [$unit_id, $weekday]),
            "bookings_today" => $count("SELECT COUNT(*) c FROM `{$p}gd_bookings` WHERE unit_id=? AND deleted=0 AND DATE(starts_at_utc)=UTC_DATE() AND status IN ('hold','pending_confirmation','confirmed','in_progress')", [$unit_id]),
            "monthly_renters" => $count("SELECT COUNT(*) c FROM `{$p}gd_court_rentals` WHERE unit_id=? AND rental_type='recurring' AND status='active' AND deleted=0", [$unit_id]),
        ];
    }
}
