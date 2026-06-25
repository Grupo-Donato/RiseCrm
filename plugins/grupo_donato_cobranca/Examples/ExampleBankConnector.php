<?php

declare(strict_types=1);

namespace App\Billing;

use grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface;

/**
 * Esqueleto para o responsável pela integração bancária.
 *
 * Não use esta classe em produção sem implementar autenticação, HTTP,
 * idempotência, assinatura do webhook, timeouts e normalização dos status.
 */
final class ExampleBankConnector implements BillingConnectorInterface
{
    public function code(): string
    {
        return 'example_bank';
    }

    public function capabilities(): array
    {
        return [
            'pix' => true,
            'credit_card' => true,
            'hosted_tokenization' => true,
            'cancel' => true,
            'sync' => true,
        ];
    }

    public function healthCheck(array $context): array
    {
        return $this->notImplemented();
    }

    public function upsertCustomer(array $customer): array
    {
        return $this->notImplemented();
    }

    public function createPixCharge(array $request): array
    {
        // Repassar $request['idempotency_key'] ao banco/gateway.
        return $this->notImplemented();
    }

    public function createCardCharge(array $request): array
    {
        // Usar somente $request['payment_method_ref']; nunca receber PAN/CVV.
        return $this->notImplemented();
    }

    public function createPaymentMethodSession(array $request): array
    {
        // Preferir checkout_url hospedada pelo provedor.
        return $this->notImplemented();
    }

    public function getCharge(array $request): array
    {
        return $this->notImplemented();
    }

    public function cancelCharge(array $request): array
    {
        return $this->notImplemented();
    }

    public function parseWebhook(array $request): array
    {
        // 1. Validar assinatura e timestamp usando $request['headers'] e ['body'].
        // 2. Rejeitar replay/origem inválida.
        // 3. Devolver evento normalizado conforme docs/integration-contract.md.
        return ['success' => false, 'message' => 'Webhook adapter not implemented.'];
    }

    private function notImplemented(): array
    {
        return ['success' => false, 'message' => 'Bank adapter not implemented.'];
    }
}
