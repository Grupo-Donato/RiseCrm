<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Orquestra o ciclo público: formulário, aceite, assinatura e efetivação. */
final class OnlineEnrollmentService
{
    private BaseConnection $db;
    private string $draftsTable;
    private string $contractsTable;
    private OnlineContractService $contractFiles;
    private LegacyOnlineStudentService $students;
    private OnlineEnrollmentWhatsappService $whatsapp;

    public function __construct(
        ?BaseConnection $db = null,
        ?OnlineContractService $contractFiles = null,
        ?LegacyOnlineStudentService $students = null,
        ?OnlineEnrollmentWhatsappService $whatsapp = null
    ) {
        $this->db = $db ?: db_connect();
        $this->draftsTable = $this->db->prefixTable("gd_online_enrollment_drafts");
        $this->contractsTable = $this->db->prefixTable("gd_online_contracts");
        $this->contractFiles = $contractFiles ?: new OnlineContractService();
        $this->students = $students ?: new LegacyOnlineStudentService($this->db);
        $this->whatsapp = $whatsapp ?: new OnlineEnrollmentWhatsappService($this->db);
    }

    /** @return array<string,mixed> */
    public function createDraft(int $unitId, array $input): array
    {
        $this->assertActiveUnit($unitId);
        $payload = $this->normalizePayload($input, $unitId);
        $payloadHash = hash("sha256", $this->canonicalJson($payload));
        $clientKey = trim((string) ($input["idempotency_key"] ?? ""));
        if ($clientKey === "") {
            $clientKey = bin2hex(random_bytes(16));
        }
        if (!preg_match("/^[A-Za-z0-9._:-]{8,191}$/", $clientKey)) {
            throw new RuntimeException("Não foi possível identificar esta tentativa de matrícula.", 422);
        }
        $idempotencyHash = hash("sha256", $clientKey);
        $now = gmdate("Y-m-d H:i:s");
        $lockName = "gd_online_create_" . substr($payloadHash, 0, 32);
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS lock_value", [$lockName])->getRow();
        if (!$lock || (int) ($lock->lock_value ?? 0) !== 1) {
            throw new RuntimeException("A matrícula está sendo preparada. Tente novamente em instantes.", 409);
        }

        try {
            $existingByKey = $this->db->table($this->draftsTable)
                ->where("idempotency_key", $idempotencyHash)
                ->where("deleted", 0)
                ->get(1)->getRowArray();
            if ($existingByKey) {
                if (!hash_equals((string) $existingByKey["payload_hash"], $payloadHash)) {
                    throw new RuntimeException("Esta tentativa já está vinculada a outros dados. Recarregue a página para iniciar outra.", 409);
                }
                return $this->stateResponse($existingByKey);
            }

            // Também cobre chamadas repetidas que não enviaram a chave de
            // idempotência, mantendo uma única matrícula durante a validade.
            $existingByPayload = $this->db->table($this->draftsTable)
                ->where("unit_id", $unitId)
                ->where("payload_hash", $payloadHash)
                ->where("deleted", 0)
                ->where("expires_at >=", $now)
                ->where("status !=", "cancelled")
                ->orderBy("id", "DESC")
                ->get(1)->getRowArray();
            if ($existingByPayload) {
                return $this->stateResponse($existingByPayload);
            }

            $token = bin2hex(random_bytes(32));
            $expiresAt = gmdate("Y-m-d H:i:s", time() + 30 * 86400);
            $payloadJson = $this->canonicalJson($payload);
            $inserted = $this->db->table($this->draftsTable)->insert([
                "unit_id" => $unitId,
                "public_token_hash" => hash("sha256", $token),
                "public_token" => $token,
                "idempotency_key" => $idempotencyHash,
                "payload_hash" => $payloadHash,
                "status" => "contract_pending",
                "payload_json" => $payloadJson,
                "contract_version" => $this->contractFiles->contractVersion(),
                "expires_at" => $expiresAt,
                "deleted" => 0,
                "created_at" => $now,
                "updated_at" => $now,
            ]);
            if (!$inserted) {
                throw new RuntimeException("Não foi possível guardar os dados temporários da matrícula.");
            }
            $draft = $this->draftById((int) $this->db->insertID());
            if (!$draft) {
                throw new RuntimeException("Não foi possível recuperar a matrícula preparada.");
            }
            return $this->stateResponse($draft);
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    /** @return array<string,mixed> */
    public function state(string $token, int $unitId): array
    {
        $draft = $this->requireDraft($token, $unitId);
        return $this->stateResponse($draft);
    }

    /** @return array<string,mixed> */
    public function accept(string $token, int $unitId, bool $accepted, string $ip, string $userAgent): array
    {
        $draft = $this->requireDraft($token, $unitId);
        if ((string) $draft["status"] === "completed") return $this->stateResponse($draft);
        if (!$accepted) {
            throw new RuntimeException("Marque que leu e concorda com o contrato para continuar.", 422);
        }
        if (!in_array((string) $draft["status"], ["contract_pending", "contract_accepted"], true)) {
            if ((string) $draft["status"] === "signed") return $this->stateResponse($draft);
            throw new RuntimeException("Esta matrícula não está mais disponível para aceite.", 409);
        }

        if ((string) $draft["status"] === "contract_pending") {
            $this->db->table($this->draftsTable)->where("id", (int) $draft["id"])->update([
                "status" => "contract_accepted",
                "accepted_at" => gmdate("Y-m-d H:i:s"),
                "accepted_ip" => mb_substr(trim($ip), 0, 45),
                "accepted_user_agent" => mb_substr(trim($userAgent), 0, 500),
                "updated_at" => gmdate("Y-m-d H:i:s"),
            ]);
        }
        return $this->stateResponse($this->draftById((int) $draft["id"]));
    }

    /** @return array<string,mixed> */
    public function saveSignature(string $token, int $unitId, string $signatureData): array
    {
        $draft = $this->requireDraft($token, $unitId);
        if ((string) $draft["status"] === "completed") return $this->stateResponse($draft);
        if ((string) $draft["status"] === "signed" && !empty($draft["signature_path"])) {
            return $this->stateResponse($draft);
        }
        if ((string) $draft["status"] !== "contract_accepted" || empty($draft["accepted_at"])) {
            throw new RuntimeException("Leia e aceite o contrato antes de assinar.", 422);
        }

        $signature = $this->contractFiles->storeSignature($signatureData, (int) $draft["id"]);
        $signedAt = gmdate("Y-m-d H:i:s");
        $updated = $this->db->table($this->draftsTable)->where("id", (int) $draft["id"])->update([
            "status" => "signed",
            "signed_at" => $signedAt,
            "signature_path" => $signature["path"],
            "signature_sha256" => $signature["sha256"],
            "updated_at" => $signedAt,
        ]);
        if (!$updated) {
            throw new RuntimeException("Não foi possível registrar sua assinatura.");
        }
        return $this->stateResponse($this->draftById((int) $draft["id"]));
    }

    /** @return array<string,mixed> */
    public function finalize(string $token, int $unitId): array
    {
        $draft = $this->requireDraft($token, $unitId);
        $draftId = (int) $draft["id"];
        if ((string) $draft["status"] === "completed") return $this->stateResponse($draft);
        if ((string) $draft["status"] !== "signed" || empty($draft["signature_path"]) || empty($draft["accepted_at"])) {
            throw new RuntimeException("Aceite o contrato e registre sua assinatura antes de finalizar.", 422);
        }

        $lockName = "gd_online_finalize_" . $draftId;
        $lock = $this->db->query("SELECT GET_LOCK(?, 10) AS lock_value", [$lockName])->getRow();
        if (!$lock || (int) ($lock->lock_value ?? 0) !== 1) {
            throw new RuntimeException("A matrícula já está sendo finalizada. Aguarde um instante.", 409);
        }

        $generatedFiles = [];
        $committed = false;
        try {
            $draft = $this->requireDraft($token, $unitId);
            if ((string) $draft["status"] === "completed") return $this->stateResponse($draft);
            $payload = $this->decodePayload($draft);
            $payload = $this->normalizeStoredPayload($payload, $unitId);
            $signedAt = (string) ($draft["signed_at"] ?? "");
            if (!preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $signedAt)) {
                $signedAt = gmdate("Y-m-d H:i:s");
            }
            $parts = $this->localSignedParts($signedAt);
            $payload["signed_day"] = $parts["day"];
            $payload["signed_month"] = $parts["month"];
            $payload["signed_year"] = $parts["year"];
            $contractNumber = "C9-" . $this->contractNumberDate($signedAt) . "-" . str_pad((string) $draftId, 6, "0", STR_PAD_LEFT);
            $generatedFiles = $this->contractFiles->createFinalFiles(
                $payload,
                $draftId,
                $signedAt,
                $contractNumber,
                (string) $draft["signature_path"]
            );

            $this->db->transStart();
            $lockedDraft = $this->draftByTokenHash((string) $draft["public_token_hash"], $unitId, true);
            if (!$lockedDraft) throw new RuntimeException("Matrícula não encontrada.", 404);
            if ((string) $lockedDraft["status"] === "completed") {
                $this->db->transComplete();
                $committed = true;
                return $this->stateResponse($lockedDraft);
            }

            $student = $this->students->create($payload, $unitId, $contractNumber, false);
            $now = gmdate("Y-m-d H:i:s");
            $inserted = $this->db->table($this->contractsTable)->insert([
                "draft_id" => $draftId,
                "unit_id" => $unitId,
                "student_id" => $student["student_id"],
                "contract_number" => $contractNumber,
                "version" => $this->contractFiles->contractVersion(),
                "content_html" => $generatedFiles["html"],
                "html_path" => $generatedFiles["html_path"],
                "pdf_path" => $generatedFiles["pdf_path"],
                "signature_path" => $generatedFiles["signature_path"],
                "pdf_sha256" => $generatedFiles["pdf_sha256"],
                "accepted_at" => $lockedDraft["accepted_at"],
                "signed_at" => $signedAt,
                "acceptance_ip" => $lockedDraft["accepted_ip"],
                "acceptance_user_agent" => $lockedDraft["accepted_user_agent"],
                "generated_at" => $now,
                "status" => "signed",
                "deleted" => 0,
                "created_at" => $now,
                "updated_at" => $now,
            ]);
            if (!$inserted) throw new RuntimeException("Não foi possível vincular o contrato ao aluno.");
            $contractId = (int) $this->db->insertID();
            $this->db->table($this->draftsTable)->where("id", $draftId)->update([
                "status" => "completed",
                "student_id" => $student["student_id"],
                "contract_id" => $contractId,
                "completed_at" => $now,
                "last_error" => null,
                "updated_at" => $now,
            ]);
            if ($this->db->transStatus() === false) throw new RuntimeException("Não foi possível concluir a matrícula.");
            $this->db->transComplete();
            if ($this->db->transStatus() === false) throw new RuntimeException("Não foi possível concluir a matrícula.");
            $committed = true;

            $contract = $this->contractById($contractId);
            $delivery = $contract ? $this->whatsapp->deliver($contract, $payload) : ["status" => "failed"];
            $finalDraft = $this->draftById($draftId);
            $response = $this->stateResponse($finalDraft ?: $draft);
            $response["delivery_status"] = (string) ($delivery["status"] ?? "failed");
            return $response;
        } catch (\Throwable $e) {
            if (!$committed) {
                $this->db->transRollback();
                foreach (["html_path", "pdf_path"] as $key) {
                    $relative = (string) ($generatedFiles[$key] ?? "");
                    if ($relative !== "") @unlink($this->contractFiles->absolutePath($relative));
                }
                try {
                    $this->db->table($this->draftsTable)->where("id", $draftId)->update([
                        "last_error" => mb_substr($e->getMessage(), 0, 1000),
                        "updated_at" => gmdate("Y-m-d H:i:s"),
                    ]);
                } catch (\Throwable $ignored) {
                    // O erro original é o que deve voltar ao usuário.
                }
            }
            throw $e;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    /** Reprocessa somente a entrega externa, sem tocar no aluno já criado. */
    public function retryWhatsapp(string $token, int $unitId): array
    {
        $draft = $this->requireDraft($token, $unitId);
        if ((string) $draft["status"] !== "completed" || (int) ($draft["contract_id"] ?? 0) < 1) {
            throw new RuntimeException("A matrícula ainda não está concluída.", 409);
        }
        $payload = $this->decodePayload($draft);
        $contract = $this->contractById((int) $draft["contract_id"]);
        if (!$contract) throw new RuntimeException("Contrato não encontrado.", 404);
        $delivery = $this->whatsapp->deliver($contract, $payload);
        $state = $this->stateResponse($draft);
        $state["delivery_status"] = (string) ($delivery["status"] ?? "failed");
        return $state;
    }

    /** @return array{path:string,filename:string,sha256:string} */
    public function download(string $token, int $unitId): array
    {
        $draft = $this->requireDraft($token, $unitId);
        $contract = $this->contractById((int) ($draft["contract_id"] ?? 0));
        if (!$contract || (string) ($draft["status"] ?? "") !== "completed") {
            throw new RuntimeException("O contrato ainda não está disponível.", 404);
        }
        $path = $this->contractFiles->absolutePath((string) $contract["pdf_path"]);
        if (!is_file($path)) throw new RuntimeException("O contrato ainda não está disponível.", 404);
        return [
            "path" => $path,
            "filename" => "Contrato_" . preg_replace("/[^A-Za-z0-9_-]+/", "_", (string) $contract["contract_number"]) . ".pdf",
            "sha256" => (string) $contract["pdf_sha256"],
        ];
    }

    private function assertActiveUnit(int $unitId): array
    {
        if ($unitId < 1) throw new RuntimeException("Unidade de matrícula inválida.", 404);
        $table = $this->db->prefixTable("grupo_donato_unidades");
        $unit = $this->db->table($table)->where("id", $unitId)->where("deleted", 0)->where("status", "Ativo")->get(1)->getRowArray();
        if (!$unit) throw new RuntimeException("Unidade não encontrada ou inativa.", 404);
        return $unit;
    }

    private function requireDraft(string $token, int $unitId): array
    {
        $this->assertActiveUnit($unitId);
        $hash = $this->tokenHash($token);
        if (!$hash) throw new RuntimeException("Esta matrícula não foi encontrada.", 404);
        $draft = $this->draftByTokenHash($hash, $unitId);
        if (!$draft) throw new RuntimeException("Esta matrícula não foi encontrada.", 404);
        if ((string) $draft["status"] !== "completed" && strtotime((string) $draft["expires_at"]) < time()) {
            $this->db->table($this->draftsTable)->where("id", (int) $draft["id"])->update(["status" => "expired", "updated_at" => gmdate("Y-m-d H:i:s")]);
            throw new RuntimeException("O prazo desta matrícula expirou. Comece novamente pelo formulário.", 410);
        }
        return $draft;
    }

    private function draftById(int $id): ?array
    {
        if ($id < 1) return null;
        return $this->db->table($this->draftsTable)->where("id", $id)->where("deleted", 0)->get(1)->getRowArray() ?: null;
    }

    private function draftByTokenHash(string $hash, int $unitId, bool $forUpdate = false): ?array
    {
        if ($forUpdate) {
            $sql = "SELECT * FROM `{$this->draftsTable}` WHERE public_token_hash=? AND unit_id=? AND deleted=0 LIMIT 1 FOR UPDATE";
            return $this->db->query($sql, [$hash, $unitId])->getRowArray() ?: null;
        }
        return $this->db->table($this->draftsTable)->where("public_token_hash", $hash)->where("unit_id", $unitId)->where("deleted", 0)->get(1)->getRowArray() ?: null;
    }

    private function contractById(int $id): ?array
    {
        if ($id < 1) return null;
        return $this->db->table($this->contractsTable)->where("id", $id)->where("deleted", 0)->get(1)->getRowArray() ?: null;
    }

    /** @return array<string,mixed> */
    private function stateResponse(?array $draft): array
    {
        if (!$draft) throw new RuntimeException("Esta matrícula não foi encontrada.", 404);
        $payload = $this->decodePayload($draft);
        $completed = (string) ($draft["status"] ?? "") === "completed";
        $contract = $completed ? $this->contractById((int) ($draft["contract_id"] ?? 0)) : null;
        $delivery = $contract ? $this->whatsapp->getDelivery((int) $contract["id"]) : null;
        $step = $completed ? "success" : ((string) ($draft["status"] ?? "") === "signed" ? "signature" : ((string) ($draft["status"] ?? "") === "contract_accepted" ? "signature" : "contract"));
        return [
            "token" => (string) ($draft["public_token"] ?? ""),
            "status" => (string) ($draft["status"] ?? ""),
            "step" => $step,
            "contract_version" => (string) ($draft["contract_version"] ?? $this->contractFiles->contractVersion()),
            "contract_html" => $completed ? "" : $this->contractFiles->renderPreview($payload),
            "accepted" => !empty($draft["accepted_at"]),
            "signature_registered" => !empty($draft["signature_path"]),
            "summary" => [
                "student_name" => (string) ($payload["nome_aluno"] ?? ""),
                "responsible_name" => (string) ($payload["responsavel_nome"] ?? ""),
                "class_name" => (string) ($payload["horario"] ?? "Não informado"),
                "monthly_value" => number_format((float) ($payload["valor_mensalidade"] ?? 237), 2, ",", "."),
                "whatsapp" => $this->formatPhone((string) ($payload["responsavel_whats"] ?? "")),
            ],
            "contract_number" => $contract["contract_number"] ?? null,
            "contract_hash" => $contract["pdf_sha256"] ?? null,
            "student_id" => $completed ? (int) ($draft["student_id"] ?? 0) : null,
            "download_url" => $completed ? $this->publicUrl("matricula-online/" . $this->unitSlug((int) ($draft["unit_id"] ?? 0)) . "/contrato/" . (string) ($draft["public_token"] ?? "")) : null,
            "delivery_status" => $delivery["status"] ?? null,
        ];
    }

    private function decodePayload(array $draft): array
    {
        $payload = json_decode((string) ($draft["payload_json"] ?? ""), true);
        if (!is_array($payload)) throw new RuntimeException("Os dados temporários da matrícula estão inválidos.");
        return $payload;
    }

    private function normalizeStoredPayload(array $payload, int $unitId): array
    {
        return $this->normalizePayload($payload, $unitId, true);
    }

    private function normalizePayload(array $input, int $unitId, bool $stored = false): array
    {
        $unit = $this->assertActiveUnit($unitId);
        $text = static function (string $key, int $max, string $fallback = "") use ($input): string {
            $value = trim(strip_tags((string) ($input[$key] ?? $fallback)));
            return mb_substr($value, 0, $max);
        };
        $date = function (string $key, bool $required = false) use ($input): ?string {
            $value = trim((string) ($input[$key] ?? ""));
            if ($value === "") {
                if ($required) throw new RuntimeException("Preencha a data solicitada.", 422);
                return null;
            }
            $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (!$date || ($errors !== false && ($errors["warning_count"] > 0 || $errors["error_count"] > 0)) || $date->format("Y-m-d") !== $value) {
                throw new RuntimeException("Uma das datas informadas é inválida.", 422);
            }
            return $value;
        };

        $responsibleName = $text("responsavel_nome", 255);
        $studentName = $text("nome_aluno", 255);
        if ($responsibleName === "" || $studentName === "") throw new RuntimeException("Informe o nome do responsável e do aluno.", 422);

        $phone = preg_replace("/\D+/", "", (string) ($input["responsavel_whats"] ?? ""));
        if (strlen($phone) < 10 || strlen($phone) > 15) throw new RuntimeException("Informe um WhatsApp válido com DDD.", 422);
        $studentBirth = $date("nascimento_aluno", true);
        if ($studentBirth > date("Y-m-d")) throw new RuntimeException("A data de nascimento do aluno não pode estar no futuro.", 422);
        $responsibleBirth = $date("responsavel_nascimento");
        if ($responsibleBirth && $responsibleBirth > date("Y-m-d")) throw new RuntimeException("A data de nascimento do responsável não pode estar no futuro.", 422);
        $startDate = $date("data_inicio") ?: date("Y-m-d");
        $email = $text("responsavel_email", 255);
        if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException("Informe um e-mail válido.", 422);
        $cpf = substr(preg_replace("/\D+/", "", $text("responsavel_cpf", 30)), 0, 11);
        $studentCpf = substr(preg_replace("/\D+/", "", $text("cpf_aluno", 30)), 0, 11);
        $horario = $text("horario", 50);
        if (function_exists("bombeiros_turmas_grouped")) {
            $available = (array) bombeiros_turmas_grouped(false, "");
            if ($horario !== "" && !array_key_exists($horario, $available)) throw new RuntimeException("Selecione um horário de turma válido.", 422);
        }

        return [
            "responsavel_nome" => $responsibleName,
            "responsavel_whats" => $phone,
            "responsavel_email" => $email,
            "responsavel_cpf" => $cpf,
            "responsavel_rg" => $text("responsavel_rg", 50),
            "responsavel_nascimento" => $responsibleBirth,
            "responsavel_endereco" => $text("responsavel_endereco", 500),
            "responsavel_numero" => $text("responsavel_numero", 20),
            "responsavel_complemento" => $text("responsavel_complemento", 255),
            "responsavel_bairro" => $text("responsavel_bairro", 255),
            "responsavel_cep" => substr(preg_replace("/\D+/", "", $text("responsavel_cep", 20)), 0, 8),
            "responsavel_cidade" => $text("responsavel_cidade", 255),
            "responsavel_celular" => substr(preg_replace("/\D+/", "", $text("responsavel_celular", 20)), 0, 15),
            "responsavel_recado" => substr(preg_replace("/\D+/", "", $text("responsavel_recado", 20)), 0, 15),
            "nome_aluno" => $studentName,
            "nascimento_aluno" => $studentBirth,
            "cpf_aluno" => $studentCpf,
            "rg_aluno" => $text("rg_aluno", 50),
            "horario" => $horario,
            "tamanho_camisa" => $text("tamanho_camisa", 50),
            "melhor_horario_ligacao" => $text("melhor_horario_ligacao", 20),
            "data_inicio" => $startDate,
            "data_primeira_parcela" => $startDate,
            "data_inscricao" => $stored && $text("data_inscricao", 10) !== "" ? (string) $date("data_inscricao") : date("Y-m-d"),
            "cidade_assinatura" => $text("cidade_assinatura", 255, (string) ($unit["cidade"] ?? "São Bernardo do Campo")) ?: ((string) ($unit["cidade"] ?? "São Bernardo do Campo")),
            "estado_assinatura" => strtoupper($text("estado_assinatura", 2, "SP")) ?: "SP",
            "curso_nome" => "ACADEMIA DE TREINAMENTO MIRIM",
            "num_parcelas" => 12,
            "valor_mensalidade" => 237.00,
            "valor_inscricao" => 100.00,
        ];
    }

    private function canonicalJson(array $payload): string
    {
        ksort($payload);
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function tokenHash(string $token): ?string
    {
        $token = trim($token);
        return preg_match("/^[a-f0-9]{64}$/i", $token) ? hash("sha256", $token) : null;
    }

    private function publicUrl(string $path): string
    {
        return function_exists("get_uri") ? get_uri($path) : "/" . ltrim($path, "/");
    }

    private function unitSlug(int $unitId): string
    {
        $table = $this->db->prefixTable("grupo_donato_unidades");
        $row = $this->db->table($table)->select("slug")->where("id", $unitId)->get(1)->getRowArray();
        return trim((string) ($row["slug"] ?? "")) ?: "sao_bernardo_do_campo";
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace("/\D+/", "", $phone);
        if (strlen($digits) >= 10) {
            return "(" . substr($digits, -11, 2) . ") " . substr($digits, -9, 5) . "-" . substr($digits, -4);
        }
        return $digits;
    }

    /** @return array{day:string,month:string,year:string} */
    private function localSignedParts(string $utc): array
    {
        $date = new DateTimeImmutable($utc, new DateTimeZone("UTC"));
        $date = $date->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $months = [1 => "janeiro", 2 => "fevereiro", 3 => "março", 4 => "abril", 5 => "maio", 6 => "junho", 7 => "julho", 8 => "agosto", 9 => "setembro", 10 => "outubro", 11 => "novembro", 12 => "dezembro"];
        return ["day" => $date->format("d"), "month" => $months[(int) $date->format("n")] ?? $date->format("m"), "year" => $date->format("Y")];
    }

    private function contractNumberDate(string $utc): string
    {
        $date = new DateTimeImmutable($utc, new DateTimeZone("UTC"));
        return $date->setTimezone(new DateTimeZone(date_default_timezone_get()))->format("Ymd");
    }
}
