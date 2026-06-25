<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services;

use grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface;

final class NullBillingConnector implements BillingConnectorInterface
{
    private string $providerCode;

    public function __construct(string $providerCode = 'unconfigured')
    {
        $this->providerCode = $providerCode ?: 'unconfigured';
    }

    public function code(): string { return $this->providerCode; }
    public function capabilities(): array { return ['pix' => false, 'credit_card' => false, 'hosted_tokenization' => false, 'cancel' => false, 'sync' => false]; }
    public function healthCheck(array $context): array { return $this->fail(); }
    public function upsertCustomer(array $customer): array { return $this->fail(); }
    public function createPixCharge(array $request): array { return $this->fail(); }
    public function createCardCharge(array $request): array { return $this->fail(); }
    public function createPaymentMethodSession(array $request): array { return $this->fail(); }
    public function getCharge(array $request): array { return $this->fail(); }
    public function cancelCharge(array $request): array { return $this->fail(); }
    public function parseWebhook(array $request): array { return $this->fail(); }

    private function fail(): array
    {
        return ['success' => false, 'message' => 'gdc_connector_not_configured'];
    }
}
