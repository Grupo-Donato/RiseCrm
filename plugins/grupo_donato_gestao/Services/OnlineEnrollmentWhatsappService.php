<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Entrega o contrato usando o motor de mídia já instalado no projeto. */
final class OnlineEnrollmentWhatsappService
{
    private BaseConnection $db;
    private string $deliveriesTable;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: db_connect();
        $this->deliveriesTable = $this->db->prefixTable("gd_online_contract_deliveries");
    }

    /** Nunca lança erro de provider para não desfazer uma matrícula já criada. */
    public function deliver(array $contract, array $payload): array
    {
        $contractId = (int) ($contract["id"] ?? 0);
        $studentId = (int) ($contract["student_id"] ?? 0);
        $phone = preg_replace("/\D+/", "", (string) ($payload["responsavel_whats"] ?? ""));
        if ($contractId < 1 || $studentId < 1 || $phone === "") {
            return ["status" => "failed", "message" => "O telefone do responsável não está disponível para o envio."];
        }

        $lockName = "gd_online_whatsapp_" . $contractId;
        $lock = $this->db->query("SELECT GET_LOCK(?, 5) AS lock_value", [$lockName])->getRow();
        if (!$lock || (int) ($lock->lock_value ?? 0) !== 1) {
            return ["status" => "pending", "message" => "O envio está em processamento e será tentado novamente."];
        }

        try {
            $delivery = $this->getOrCreateDelivery($contractId, (int) ($contract["draft_id"] ?? 0), $studentId, $phone);
            if ((string) ($delivery["status"] ?? "") === "sent") {
                return ["status" => "sent", "delivery_id" => (int) $delivery["id"]];
            }

            $attempts = (int) ($delivery["attempts"] ?? 0) + 1;
            $this->db->table($this->deliveriesTable)->where("id", (int) $delivery["id"])->update([
                "status" => "sending",
                "attempts" => $attempts,
                "last_error" => null,
                "updated_at" => gmdate("Y-m-d H:i:s"),
            ]);

            try {
                $result = $this->sendUsingExistingMediaEngine($contract, $payload, $phone);
                $this->db->table($this->deliveriesTable)->where("id", (int) $delivery["id"])->update([
                    "provider_instance_id" => (int) ($result["instance_id"] ?? 0) ?: null,
                    "conversation_id" => (int) ($result["conversation_id"] ?? 0) ?: null,
                    "message_id" => (int) ($result["message_id"] ?? 0) ?: null,
                    "external_message_id" => ($result["external_message_id"] ?? null) ?: null,
                    "status" => "sent",
                    "sent_at" => gmdate("Y-m-d H:i:s"),
                    "next_attempt_at" => null,
                    "updated_at" => gmdate("Y-m-d H:i:s"),
                ]);
                return ["status" => "sent", "delivery_id" => (int) $delivery["id"]];
            } catch (\Throwable $e) {
                $message = mb_substr($e->getMessage() ?: "O WhatsApp não confirmou o envio.", 0, 1000);
                $nextAttempt = date("Y-m-d H:i:s", time() + min(86400, max(300, $attempts * 900)));
                $this->db->table($this->deliveriesTable)->where("id", (int) $delivery["id"])->update([
                    "status" => "failed",
                    "last_error" => $message,
                    "next_attempt_at" => $nextAttempt,
                    "updated_at" => gmdate("Y-m-d H:i:s"),
                ]);
                log_message("error", "Matrícula online: falha no WhatsApp do contrato " . $contractId . ": " . $message);
                return ["status" => "failed", "delivery_id" => (int) $delivery["id"]];
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(?)", [$lockName]);
        }
    }

    public function getDelivery(int $contractId): ?array
    {
        if ($contractId < 1) return null;
        return $this->db->table($this->deliveriesTable)->where("contract_id", $contractId)->where("deleted", 0)->get(1)->getRowArray() ?: null;
    }

    private function getOrCreateDelivery(int $contractId, int $draftId, int $studentId, string $phone): array
    {
        $existing = $this->getDelivery($contractId);
        if ($existing) return $existing;
        $now = gmdate("Y-m-d H:i:s");
        $this->db->table($this->deliveriesTable)->insert([
            "contract_id" => $contractId,
            "draft_id" => $draftId,
            "student_id" => $studentId,
            "recipient_phone" => $phone,
            "status" => "pending",
            "attempts" => 0,
            "deleted" => 0,
            "created_at" => $now,
            "updated_at" => $now,
        ]);
        return $this->getDelivery($contractId) ?: throw new RuntimeException("Não foi possível registrar a entrega do contrato.");
    }

    private function sendUsingExistingMediaEngine(array $contract, array $payload, string $phone): array
    {
        if (!class_exists("Chatwoot_plugin\\Services\\Media_service")) {
            throw new RuntimeException("A integração de WhatsApp não está disponível.");
        }

        $instances = new \Chatwoot_plugin\Models\Chat_instances_model();
        $result = $instances->paginate_instances(["active" => true, "connection_status" => "connected"], 1, 50);
        $available = (array) ($result["data"] ?? []);
        $instance = $available[0] ?? null;
        if (!is_array($instance)) {
            throw new RuntimeException("Nenhum canal de WhatsApp conectado está disponível.");
        }

        $contractPath = (string) ($contract["pdf_path"] ?? "");
        $absolutePath = (new OnlineContractService())->absolutePath($contractPath);
        if (!is_file($absolutePath)) {
            throw new RuntimeException("O PDF do contrato não está disponível para envio.");
        }

        $conversations = new \Chatwoot_plugin\Models\Chat_conversations_model();
        $remoteJid = $phone . "@s.whatsapp.net";
        $caption = "Olá, " . (string) ($payload["responsavel_nome"] ?? "") . "!\n\n"
            . "A matrícula de " . (string) ($payload["nome_aluno"] ?? "") . " na Escola de Futebol Camisa 9 foi concluída com sucesso. ⚽\n\n"
            . "Segue em anexo uma via do contrato de prestação de serviço assinado durante a matrícula.\n\n"
            . "Escola de Futebol Camisa 9\n"
            . "Contrato nº " . (string) ($contract["contract_number"] ?? "");
        $conversationId = $conversations->upsert_conversation((int) $instance["id"], $remoteJid, [
            "phone_number" => $phone,
            "contact_name" => (string) ($payload["responsavel_nome"] ?? ""),
            "status" => "open",
            "archived" => 0,
            "conversation_type" => "direct",
            "last_message_preview" => $caption,
            "last_message_at" => gmdate("Y-m-d H:i:s"),
        ]);

        $media = new \Chatwoot_plugin\Models\Chat_media_model();
        $mediaId = $media->create_record([
            "conversation_id" => null,
            "instance_id" => (int) $instance["id"],
            "storage_driver" => "local",
            "storage_path" => $contractPath,
            "original_name" => "Contrato_" . preg_replace("/[^A-Za-z0-9_-]+/", "_", (string) ($contract["contract_number"] ?? "matricula")) . ".pdf",
            "mime_type" => "application/pdf",
            "media_type" => "document",
            "file_size" => filesize($absolutePath),
            "sha256" => (string) ($contract["pdf_sha256"] ?? hash_file("sha256", $absolutePath)),
            "created_by" => 0,
        ]);

        $message = (new \Chatwoot_plugin\Services\Media_service())->sendCampaignMedia(
            $conversationId,
            $mediaId,
            $caption,
            "gd-online-contract-" . (int) $contract["id"],
            0
        );

        return [
            "instance_id" => (int) $instance["id"],
            "conversation_id" => $conversationId,
            "message_id" => (int) ($message["id"] ?? 0),
            "external_message_id" => $message["external_message_id"] ?? null,
        ];
    }
}
