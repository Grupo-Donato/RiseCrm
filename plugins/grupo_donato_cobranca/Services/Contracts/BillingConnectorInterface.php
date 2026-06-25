<?php

declare(strict_types=1);

namespace grupo_donato_cobranca\Services\Contracts;

/**
 * Porta entre o plugin e o banco/gateway.
 *
 * Nenhum método recebe número completo de cartão ou CVV. O conector deve usar
 * checkout hospedado ou SDK/tokenização do próprio provedor.
 */
interface BillingConnectorInterface
{
    public function code(): string;

    /** @return array{pix:bool,credit_card:bool,hosted_tokenization:bool,cancel:bool,sync:bool} */
    public function capabilities(): array;

    /** @return array{success:bool,message:string,details?:array} */
    public function healthCheck(array $context): array;

    /** @return array{success:bool,external_customer_id?:string,message?:string} */
    public function upsertCustomer(array $customer): array;

    /** @return array Normalizado conforme docs/integration-contract.md */
    public function createPixCharge(array $request): array;

    /** @return array Normalizado conforme docs/integration-contract.md */
    public function createCardCharge(array $request): array;

    /** @return array{success:bool,checkout_url?:string,client_token?:string,session_id?:string,message?:string} */
    public function createPaymentMethodSession(array $request): array;

    /** @return array Normalizado conforme docs/integration-contract.md */
    public function getCharge(array $request): array;

    /** @return array Normalizado conforme docs/integration-contract.md */
    public function cancelCharge(array $request): array;

    /** @return array Evento normalizado conforme docs/integration-contract.md */
    public function parseWebhook(array $request): array;
}
