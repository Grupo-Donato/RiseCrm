<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Auditoria append-only do plugin.
 *
 * Registra mutações sensíveis com antes/depois (mascarando chaves sensíveis),
 * autor, request_id, IP e user-agent. Nunca persiste senha/token/cookie/segredo.
 */
class AuditService
{
    private $model;
    private ?object $login_user;
    private static ?string $request_id = null;

    public function __construct(?object $login_user = null)
    {
        $this->model = model("grupo_donato_gestao\\Models\\Gd_audit_logs_model");
        $this->login_user = $login_user;
    }

    public static function request_id(): string
    {
        if (self::$request_id === null) {
            try {
                self::$request_id = bin2hex(random_bytes(8));
            } catch (\Throwable $e) {
                self::$request_id = (string) uniqid("gd", true);
            }
        }
        return self::$request_id;
    }

    /**
     * Registra um evento de auditoria.
     *
     * @param array|null $before
     * @param array|null $after
     * @param array|null $metadata
     */
    public function log(string $action, string $entity_type, $entity_id = null, ?array $before = null, ?array $after = null, ?array $metadata = null, ?int $unit_id = null): int
    {
        $request = \Config\Services::request();

        $actor_type = "system";
        $actor_id = null;
        if ($this->login_user && isset($this->login_user->id) && $this->login_user->id) {
            $actor_type = $this->login_user->user_type ?? "staff";
            $actor_id = (int) $this->login_user->id;
        }

        $user_agent = "";
        try {
            $user_agent = (string) $request->getUserAgent()->getAgentString();
        } catch (\Throwable $e) {
            $user_agent = "";
        }

        $data = [
            "unit_id" => $unit_id,
            "actor_type" => $actor_type,
            "actor_id" => $actor_id,
            "action" => mb_substr($action, 0, 40),
            "entity_type" => mb_substr($entity_type, 0, 60),
            "entity_id" => $entity_id !== null ? (int) $entity_id : null,
            "before_data" => $before !== null ? $this->encode($this->mask($before)) : null,
            "after_data" => $after !== null ? $this->encode($this->mask($after)) : null,
            "metadata" => $metadata !== null ? $this->encode($this->mask($metadata)) : null,
            "request_id" => self::request_id(),
            "ip_address" => mb_substr((string) $request->getIPAddress(), 0, 45),
            "user_agent" => mb_substr($user_agent, 0, 255),
            "created_at" => function_exists("get_current_utc_time") ? get_current_utc_time() : gmdate("Y-m-d H:i:s"),
        ];

        try {
            return $this->model->add($data);
        } catch (\Throwable $e) {
            log_message("error", "GD AuditService: falha ao gravar auditoria: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mascara recursivamente valores cujas chaves sejam sensíveis.
     */
    private function mask(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if ($this->is_sensitive_key((string) $key)) {
                $masked[$key] = "***";
                continue;
            }
            $masked[$key] = is_array($value) ? $this->mask($value) : $value;
        }
        return $masked;
    }

    private function is_sensitive_key(string $key): bool
    {
        $lower = strtolower($key);
        foreach (Constants::SENSITIVE_KEYS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function encode(array $data): string
    {
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        return is_string($encoded) ? $encoded : "null";
    }
}
