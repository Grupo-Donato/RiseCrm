<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services\Import;

use grupo_donato_gestao\Services\CourtRentalService;
use grupo_donato_gestao\Services\CustomerAccountService;
use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\PersonService;
use grupo_donato_gestao\Services\TemporalService;

/**
 * Importa mensalistas de quadra (PDF convertido para CSV/XLSX). Cria a locação
 * recorrente como RASCUNHO; séries conflitantes nunca são confirmadas
 * automaticamente (conflito é registrado e ignorado pela política skip_conflicts).
 */
final class CourtRentersImporter extends AbstractImporter
{
    private const WEEKDAYS = [
        "seg" => 1, "segunda" => 1, "ter" => 2, "terca" => 2, "qua" => 3, "quarta" => 3,
        "qui" => 4, "quinta" => 4, "sex" => 5, "sexta" => 5, "sab" => 6, "sabado" => 6, "dom" => 7, "domingo" => 7,
    ];

    public function type(): string { return "court_renters"; }

    public function headerDefs(): array
    {
        return [
            ["field" => "customer_name", "label" => "gd_import_col_customer", "aliases" => ["cliente", "nome", "mensalista"], "required" => true],
            ["field" => "contact", "label" => "gd_import_col_contact", "aliases" => ["telefone", "contato", "celular", "whatsapp"], "required" => false],
            ["field" => "resource_code", "label" => "gd_import_col_resource", "aliases" => ["quadra", "recurso", "q"], "required" => true],
            ["field" => "weekday", "label" => "gd_import_col_weekday", "aliases" => ["dia", "dia da semana"], "required" => true],
            ["field" => "start_time", "label" => "gd_import_col_start_time", "aliases" => ["hora", "horario", "horário", "inicio", "início"], "required" => true],
            ["field" => "end_time", "label" => "gd_import_col_end_time", "aliases" => ["fim", "termino", "término", "hora fim"], "required" => false],
            ["field" => "due_day", "label" => "gd_import_col_due_day", "aliases" => ["vencimento", "dia vencimento"], "required" => false],
            ["field" => "amount", "label" => "gd_import_col_amount", "aliases" => ["valor", "mensalidade"], "required" => false],
        ];
    }

    public function primaryTargetTypes(): array { return ["court_rental"]; }

    public function validateRow(array $row): array
    {
        $issues = [];
        $name = DataNormalizationService::text($row["customer_name"] ?? "");
        $resourceCode = strtoupper(DataNormalizationService::text($row["resource_code"] ?? ""));
        $resourceId = $resourceCode === "" ? 0 : $this->resolveResource($resourceCode);
        $weekday = $this->parseWeekday($row["weekday"] ?? "");
        $start = $this->parseTime($row["start_time"] ?? "");
        $end = $this->parseTime($row["end_time"] ?? "");
        if ($start !== null && $end === null) { $end = $this->addHour($start); }
        $dueRaw = trim((string) ($row["due_day"] ?? ""));
        $dueDay = preg_match('/^\d{1,2}$/', $dueRaw) && (int) $dueRaw >= 1 && (int) $dueRaw <= 31 ? (int) $dueRaw : null;
        $amount = $this->files->amount($row["amount"] ?? "");

        $status = "valid";
        $complete = true;
        if ($name === "") { $issues[] = $this->issue("missing_required", "error", "gd_import_issue_customer_required"); $status = "invalid"; }
        elseif ($this->hasMultiplePeople($name)) { $issues[] = $this->issue("multiple_people_in_cell", "review", "gd_import_issue_multiple_people", ["value" => $name]); $status = "needs_review"; }
        if ($resourceCode !== "" && $resourceId === 0) { $issues[] = $this->issue("missing_resource", "review", "gd_import_issue_missing_resource", ["code" => $resourceCode]); $complete = false; }
        if ($resourceId === 0 || $weekday === null || $start === null) { $issues[] = $this->issue("incomplete", "review", "gd_import_issue_incomplete_renter"); $complete = false; }
        if ($name !== "" && $status === "valid") {
            $dups = (new PersonService($this->unit_id, $this->actor_id, $this->login_user))->duplicates(["full_name" => $name]);
            if (array_filter($dups, static fn($d) => in_array($d["confidence"], ["exact", "high"], true))) { $issues[] = $this->issue("probable_duplicate", "review", "gd_import_issue_probable_duplicate", ["value" => $name]); }
        }

        $normalized = [
            "customer_name" => $name, "contact" => DataNormalizationService::text($row["contact"] ?? ""),
            "resource_id" => $resourceId, "resource_code" => $resourceCode, "weekday" => $weekday,
            "start_time" => $start, "end_time" => $end, "due_day" => $dueDay, "amount" => $amount, "complete" => $complete,
        ];
        $source_key = $this->files->sourceKey("court_renter", [$name, $resourceCode, (string) $weekday, (string) $start]);
        return ["status" => $status, "normalized" => $normalized, "issues" => $issues, "source_key" => $source_key];
    }

    public function importRow(array $n, string $source_key, int $row_number, int $row_id): array
    {
        $links = [];
        if ($this->find($source_key, "court_rental") !== null) { return ["links" => $links]; }
        $customer_key = $this->files->sourceKey("court_customer", [$n["customer_name"]]);
        $account_id = $this->find($customer_key, "customer_account");
        if ($account_id === null) {
            $acc = (new CustomerAccountService($this->unit_id, $this->actor_id, $this->login_user))->save([
                "account_type" => "individual", "display_name" => $n["customer_name"], "document_type" => "none", "status" => "active",
            ], 0, true);
            if (empty($acc["saved"])) { throw new \DomainException("gd_import_issue_probable_duplicate"); }
            $account_id = (int) $acc["id"];
            $links[] = $this->link($customer_key, "customer_account", $account_id);
        }

        $rental = new CourtRentalService($this->unit_id, $this->actor_id, $this->login_user);
        $commercial = [
            "rental_type" => "recurring", "title" => "Mensalista " . $n["customer_name"], "customer_account_id" => $account_id,
            "preferred_due_day" => $n["due_day"], "negotiated_amount" => $n["amount"],
        ];
        if (!empty($n["complete"])) {
            $today = (new \DateTimeImmutable("today", new \DateTimeZone((new TemporalService($this->unit_id))->timezoneName())))->format("Y-m-d");
            try {
                $created = $rental->createWithSeries($commercial + [
                    "frequency" => "weekly", "interval_value" => 1, "weekdays" => [$n["weekday"]],
                    "local_start_time" => $n["start_time"], "local_end_time" => $n["end_time"], "starts_on" => $today,
                    "ends_mode" => "open_ended", "generation_horizon_days" => 30, "default_booking_status" => "pending_confirmation",
                    "conflict_policy" => "skip_conflicts", "resources" => [["resource_id" => $n["resource_id"], "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
                ]);
                $links[] = $this->link($source_key, "court_rental", (int) $created["id"]);
                if (!empty($created["series_id"])) { $links[] = $this->link($source_key, "booking_series", (int) $created["series_id"]); }
                return ["links" => $links];
            } catch (\Throwable $e) {
                // Conflito/indisponibilidade → cai para rascunho simples (revisão manual).
            }
        }
        $draft = $rental->createDraft($commercial, "recurring");
        $links[] = $this->link($source_key, "court_rental", (int) $draft["id"]);
        return ["links" => $links];
    }

    private function resolveResource(string $code): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_resources"))->select("id")->where("unit_id", $this->unit_id)->where("code", $code)->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->get(1)->getRow();
        return $row ? (int) $row->id : 0;
    }

    private function parseWeekday($value): ?int
    {
        $value = DataNormalizationService::name((string) $value);
        if ($value === "") { return null; }
        if (preg_match('/^[1-7]$/', $value)) { return (int) $value; }
        foreach (self::WEEKDAYS as $key => $iso) { if (str_starts_with($value, $key)) { return $iso; } }
        return null;
    }

    private function parseTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === "") { return null; }
        if (preg_match('/^(\d{1,2})[:hH.]?(\d{2})?/', $value, $m)) {
            $h = (int) $m[1]; $min = (int) ($m[2] ?? 0);
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) { return str_pad((string) $h, 2, "0", STR_PAD_LEFT) . ":" . str_pad((string) $min, 2, "0", STR_PAD_LEFT); }
        }
        return null;
    }

    private function addHour(string $time): string
    {
        return (new \DateTimeImmutable("2000-01-01 " . $time))->modify("+1 hour")->format("H:i");
    }
}
