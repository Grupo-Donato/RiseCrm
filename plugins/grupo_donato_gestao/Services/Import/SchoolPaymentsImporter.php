<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services\Import;

use grupo_donato_gestao\Services\DataNormalizationService;
use grupo_donato_gestao\Services\FinanceService;
use grupo_donato_gestao\Services\PersonService;
use grupo_donato_gestao\Services\SchoolEnrollmentService;
use grupo_donato_gestao\Services\SchoolStudentService;

/** Importa alunos e pagamentos antigos (planilha PAGAMENTOS). */
final class SchoolPaymentsImporter extends AbstractImporter
{
    private ?array $classCache = null;

    public function type(): string { return "school_payments"; }

    public function headerDefs(): array
    {
        return [
            ["field" => "student_name", "label" => "gd_import_col_student", "aliases" => ["aluno", "nome", "nome do aluno", "estudante"], "required" => true],
            ["field" => "guardian_name", "label" => "gd_import_col_guardian", "aliases" => ["responsavel", "responsável", "pai", "mae", "mãe"], "required" => false],
            ["field" => "contact", "label" => "gd_import_col_contact", "aliases" => ["telefone", "celular", "contato", "whatsapp", "fone"], "required" => false],
            ["field" => "class_name", "label" => "gd_import_col_class", "aliases" => ["turma", "classe", "modalidade"], "required" => false],
            ["field" => "reference_month", "label" => "gd_import_col_reference", "aliases" => ["mes", "mês", "referencia", "referência", "competencia", "competência"], "required" => false],
            ["field" => "amount", "label" => "gd_import_col_amount", "aliases" => ["valor", "mensalidade", "valor pago", "pago"], "required" => true],
            ["field" => "due_date", "label" => "gd_import_col_due", "aliases" => ["vencimento", "data vencimento"], "required" => false],
            ["field" => "payment_date", "label" => "gd_import_col_payment_date", "aliases" => ["data", "pagamento", "data pagamento", "pago em"], "required" => false],
            ["field" => "payment_method", "label" => "gd_import_col_method", "aliases" => ["forma", "forma pagamento", "forma de pagamento", "metodo", "método"], "required" => false],
        ];
    }

    public function primaryTargetTypes(): array { return ["receivable"]; }

    public function validateRow(array $row): array
    {
        $issues = [];
        $name = DataNormalizationService::text($row["student_name"] ?? "");
        $amountRaw = (string) ($row["amount"] ?? "");
        $amount = $this->files->amount($amountRaw);
        $paymentRaw = (string) ($row["payment_date"] ?? "");
        $paymentDate = $paymentRaw === "" ? null : $this->files->date($paymentRaw);
        $reference = $this->files->referenceMonth($row["reference_month"] ?? "");
        if ($reference === null && $paymentDate) { $reference = substr($paymentDate, 0, 7); }
        $dueRaw = (string) ($row["due_date"] ?? "");
        $dueDate = $dueRaw === "" ? null : $this->files->date($dueRaw);
        $methodRaw = (string) ($row["payment_method"] ?? "");
        $method = $methodRaw === "" ? null : \grupo_donato_gestao\Config\Constants::normalizePaymentMethod($methodRaw);
        $hasPayment = $paymentDate !== null && $amount !== null;
        $classId = 0; $className = DataNormalizationService::text($row["class_name"] ?? "");
        if ($className !== "") { $classId = $this->resolveClass($className); if ($classId === 0) { $issues[] = $this->issue("missing_class", "warning", "gd_import_issue_missing_class", ["class" => $className]); } }

        $status = "valid";
        if ($name === "") { $issues[] = $this->issue("missing_required", "error", "gd_import_issue_student_required"); $status = "invalid"; }
        if ($amount === null) { $issues[] = $this->issue("invalid_amount", "error", "gd_import_issue_invalid_amount", ["value" => $amountRaw]); $status = "invalid"; }
        if ($paymentRaw !== "" && $paymentDate === null) { $issues[] = $this->issue("invalid_date", "error", "gd_import_issue_invalid_date", ["value" => $paymentRaw]); $status = "invalid"; }
        if ($reference === null) { $issues[] = $this->issue("inconsistent_month", "review", "gd_import_issue_reference_required"); $status = $status === "invalid" ? "invalid" : "needs_review"; }
        if ($name !== "" && $this->hasMultiplePeople($name)) { $issues[] = $this->issue("multiple_people_in_cell", "review", "gd_import_issue_multiple_people", ["value" => $name]); $status = $status === "invalid" ? "invalid" : "needs_review"; }
        if ($hasPayment && $method === null) { $issues[] = $this->issue("unknown_payment_method", "review", "gd_import_issue_unknown_method", ["value" => $methodRaw]); $status = $status === "invalid" ? "invalid" : "needs_review"; }
        if ($name !== "" && $status === "valid") {
            $dups = (new PersonService($this->unit_id, $this->actor_id, $this->login_user))->duplicates(["full_name" => $name]);
            if (array_filter($dups, static fn($d) => in_array($d["confidence"], ["exact", "high"], true))) { $issues[] = $this->issue("probable_duplicate", "review", "gd_import_issue_probable_duplicate", ["value" => $name]); $status = "needs_review"; }
        }

        $normalized = [
            "student_name" => $name, "guardian_name" => DataNormalizationService::text($row["guardian_name"] ?? ""),
            "contact" => DataNormalizationService::text($row["contact"] ?? ""), "class_id" => $classId, "class_name" => $className,
            "reference_month" => $reference, "amount" => $amount, "due_date" => $dueDate, "payment_date" => $paymentDate,
            "payment_method" => $method, "has_payment" => $hasPayment,
        ];
        $source_key = $this->files->sourceKey("school_payment", [$name, (string) $reference, (string) $amount, (string) $paymentDate]);
        return ["status" => $status, "normalized" => $normalized, "issues" => $issues, "source_key" => $source_key];
    }

    public function importRow(array $n, string $source_key, int $row_number, int $row_id): array
    {
        $links = [];
        $student_key = $this->files->sourceKey("school_student", [$n["student_name"], $n["guardian_name"]]);
        $profile_id = $this->find($student_key, "school_profile");
        $family_id = 0;
        if ($profile_id === null) {
            $contactType = str_contains($n["contact"], "@") ? "email" : "phone";
            $result = (new SchoolStudentService($this->unit_id, $this->actor_id, $this->login_user))->save([
                "full_name" => $n["student_name"],
                "new_family_name" => $n["guardian_name"] !== "" ? $n["guardian_name"] : $n["student_name"] . " (família)",
                "contact_value" => $n["contact"] ?: null, "contact_type" => $contactType, "status" => "active",
            ]);
            if (empty($result["saved"])) { throw new \DomainException("gd_import_issue_probable_duplicate"); }
            $profile_id = (int) $result["id"]; $family_id = (int) $result["family_account_id"];
            $links[] = $this->link($student_key, "person", (int) $result["person_id"]);
            $links[] = $this->link($student_key, "customer_account", $family_id);
            $links[] = $this->link($student_key, "school_profile", $profile_id);
        } else {
            $profile = $this->db->table($this->db->prefixTable("gd_school_profiles"))->select("family_account_id")->where("id", $profile_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
            $family_id = (int) ($profile->family_account_id ?? 0);
            $links[] = $this->link($student_key, "school_profile", $profile_id);
        }

        if ($n["class_id"] > 0) {
            $ench_key = $this->files->sourceKey("school_enrollment", [$student_key, (string) $n["class_id"]]);
            $eid = $this->find($ench_key, "enrollment");
            if ($eid === null) {
                try {
                    $saved = (new SchoolEnrollmentService($this->unit_id, $this->actor_id, $this->login_user))->save([
                        "class_id" => $n["class_id"], "school_profile_id" => $profile_id,
                        "starts_on" => ($n["reference_month"] ?: gmdate("Y-m")) . "-01",
                    ]);
                    $eid = (int) $saved["id"];
                } catch (\Throwable $e) { $eid = null; }
            }
            if ($eid) { $links[] = $this->link($ench_key, "enrollment", $eid); }
        }

        $reference = (string) $n["reference_month"];
        $issue = $reference . "-01";
        $due = $n["due_date"] ?: $reference . "-10";
        if ($due < $issue) { $due = $issue; }
        $rec_id = $this->find($source_key, "receivable");
        if ($rec_id === null) {
            $rec = (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->createReceivable([
                "source_type" => "manual", "customer_account_id" => $family_id, "reference_month" => $reference,
                "description" => "Mensalidade " . $n["student_name"], "issue_date" => $issue, "due_date" => $due,
                "original_amount" => $n["amount"], "unit_amount" => $n["amount"], "quantity" => "1",
            ]);
            $rec_id = (int) $rec["id"];
        }
        $links[] = $this->link($source_key, "receivable", $rec_id);

        if ($n["has_payment"] && $this->find($source_key, "payment") === null) {
            $account = $this->defaultAccount();
            $pay = (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->registerPayment([
                "allocations" => [$rec_id => $n["amount"]], "amount" => $n["amount"], "payment_method" => $n["payment_method"],
                "financial_account_id" => $account, "payment_date" => $n["payment_date"],
            ]);
            $links[] = $this->link($source_key, "payment", (int) $pay["id"]);
        }
        return ["links" => $links];
    }

    private function resolveClass(string $name): int
    {
        if ($this->classCache === null) {
            $this->classCache = [];
            foreach ($this->db->table($this->db->prefixTable("gd_classes"))->select("id,name")->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->get()->getResult() as $c) {
                $this->classCache[DataNormalizationService::name($c->name)] = (int) $c->id;
            }
        }
        return $this->classCache[DataNormalizationService::name($name)] ?? 0;
    }

    private function defaultAccount(): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_financial_accounts"))->select("id")->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->orderBy("account_type = 'cash'", "DESC", false)->orderBy("id")->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_import_no_financial_account"); }
        return (int) $row->id;
    }
}
