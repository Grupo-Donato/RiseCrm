# Contrato do conector bancário — plugin Cobrança

## 1. Objetivo

O plugin `grupo_donato_cobranca` é o orquestrador entre o financeiro do `grupo_donato_gestao` e um banco/gateway.

O conector externo deve:

1. criar ou localizar o cliente no provedor;
2. gerar PIX rastreável;
3. cobrar cartão tokenizado;
4. abrir sessão hospedada para tokenização de cartão;
5. consultar e cancelar cobranças;
6. validar e normalizar webhooks.

O conector **não deve** criar pagamentos diretamente nas tabelas financeiras. Após um evento normalizado como `paid`, o próprio plugin chama `grupo_donato_gestao\Services\FinanceService::registerPayment()`.

## 2. Registro do adaptador

O código configurado em **Cobrança → Integração → Código do provedor** deve coincidir com o sufixo do hook.

Exemplo para o código `meu_banco`:

```php
use grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface;

app_hooks()->add_filter('gdc_filter_billing_connector_meu_banco', static function ($current) {
    return new MeuBancoConnector();
});
```

A classe retornada precisa implementar:

```php
grupo_donato_cobranca\Services\Contracts\BillingConnectorInterface
```

Arquivo de referência: `Services/Contracts/BillingConnectorInterface.php`.

## 3. Credenciais e segredos

Credenciais não são recebidas pela interface e não são salvas em `gdc_settings`.

O adaptador deve obter os segredos por um mecanismo seguro, por exemplo:

- variáveis de ambiente;
- arquivo de configuração fora do diretório público;
- secret manager;
- cofre de certificados do servidor.

Não armazenar nem registrar em log:

- número completo do cartão/PAN;
- CVV;
- senha bancária;
- chave privada;
- access token completo;
- segredo de assinatura do webhook;
- corpo bruto do webhook.

O plugin armazena apenas token/referência opaca do cartão, bandeira, quatro últimos dígitos, validade e hash SHA-256 do payload do evento.

## 4. Interface obrigatória

### `code(): string`

Retorna exatamente o código do provedor, por exemplo `meu_banco`.

### `capabilities(): array`

```php
[
    'pix' => true,
    'credit_card' => true,
    'hosted_tokenization' => true,
    'cancel' => true,
    'sync' => true,
]
```

Quando uma capacidade for `false`, o plugin bloqueia a operação correspondente.

### `healthCheck(array $context): array`

Entrada:

```php
[
    'unit_id' => 1,
    'environment' => 'sandbox', // sandbox|production
    'financial_account_id' => 3,
]
```

Saída:

```php
[
    'success' => true,
    'message' => 'Conexão válida.',
    'details' => ['account' => 'Conta principal'], // opcional e não sensível
]
```

### `upsertCustomer(array $customer): array`

Entrada normalizada:

```php
[
    'local_customer_id' => '123',
    'name' => 'Responsável financeiro',
    'document_type' => 'cpf',
    'document' => '00000000000',
    'email' => 'responsavel@exemplo.com',
    'phone' => '5511999999999',
    'metadata' => [
        'unit_id' => 1,
        'customer_account_id' => 123,
    ],
]
```

Saída:

```php
[
    'success' => true,
    'external_customer_id' => 'cus_abc123',
]
```

A operação deve ser idempotente para o mesmo cliente local.

## 5. Criação de cobrança

`createPixCharge()` e `createCardCharge()` recebem a mesma estrutura base:

```php
[
    'idempotency_key' => 'gdc:1:uuid',
    'local_charge_id' => 55,
    'local_charge_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    'external_customer_id' => 'cus_abc123',
    'amount' => '237.00',
    'currency' => 'BRL',
    'due_date' => '2026-07-10',
    'description' => 'Mensalidade ...',
    'payer' => [/* payload do cliente */],
    'metadata' => [
        'unit_id' => 1,
        'receivable_id' => 88,
        'receivable_number' => 'REC-2026-000088',
        'charge_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    ],
]
```

No cartão, também é enviado:

```php
'payment_method_ref' => 'token_opaco_do_provedor'
```

### Resposta normalizada de cobrança

Campos comuns aceitos pelo plugin:

```php
[
    'success' => true,
    'status' => 'pending',
    'external_charge_id' => 'charge_123',
    'external_payment_id' => null,
    'paid_amount' => '0.00',
    'paid_at' => null,
    'expires_at' => '2026-07-10 23:59:59',
    'message' => 'Cobrança criada.',
    'error_code' => null,
]
```

Para PIX, devolver também:

```php
[
    'pix_txid' => 'TXID_DO_PROVEDOR',
    'pix_copy_paste' => '00020126...',
    'pix_qr_code_url' => 'https://provedor.example/qr/charge_123',
]
```

O `pix_qr_code_url` deve ser HTTPS e permanecer acessível pelo período da cobrança. O adaptador também pode publicar uma URL interna assinada, desde que não exponha credenciais.

Para cartão aprovado de forma síncrona, a resposta pode vir diretamente como:

```php
[
    'success' => true,
    'status' => 'paid',
    'external_charge_id' => 'charge_123',
    'external_payment_id' => 'payment_456',
    'paid_amount' => '237.00',
    'paid_at' => '2026-07-05 14:32:10',
]
```

Valores monetários devem ser strings decimais com duas casas e ponto, nunca `float`.

## 6. Tokenização hospedada de cartão

### `createPaymentMethodSession(array $request): array`

Entrada:

```php
[
    'external_customer_id' => 'cus_abc123',
    'customer' => [/* cliente normalizado */],
    'success_url' => 'https://rise.example/cobranca/payment-methods',
    'cancel_url' => 'https://rise.example/cobranca/payment-methods',
    'webhook_url' => 'https://rise.example/cobranca/webhook/meu_banco',
    'metadata' => [
        'unit_id' => 1,
        'customer_account_id' => 123,
    ],
]
```

Formato preferencial para o front atual:

```php
[
    'success' => true,
    'checkout_url' => 'https://checkout.provedor.example/session/abc',
    'session_id' => 'session_abc',
]
```

Também é permitido retornar `client_token`, mas nesse caso o adaptador deverá fornecer o JavaScript/SDK que abre o componente hospedado. O plugin não contém formulário próprio para cartão.

Após tokenização, o provedor deve enviar webhook com `event_kind = payment_method`.

## 7. Consulta e cancelamento

### `getCharge(array $request): array`

Entrada:

```php
[
    'external_charge_id' => 'charge_123',
    'local_charge_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
]
```

Retorna a resposta normalizada de cobrança descrita na seção 5.

### `cancelCharge(array $request): array`

Mesma entrada de consulta. Retorno mínimo:

```php
[
    'success' => true,
    'status' => 'cancelled',
    'external_charge_id' => 'charge_123',
    'message' => 'Cobrança cancelada.',
]
```

Não retornar sucesso quando o provedor apenas recebeu a solicitação, mas ainda não confirmou o cancelamento. Nesse caso, retornar `status = processing` ou `pending` conforme a situação real.

## 8. Webhook

Endpoint:

```text
POST /cobranca/webhook/{provider_code}
```

O método `parseWebhook()` recebe:

```php
[
    'provider_code' => 'meu_banco',
    'headers' => [/* nomes em minúsculo */],
    'body' => '{...}',
    'payload_hash' => 'sha256...',
]
```

O adaptador é responsável por:

1. validar assinatura com comparação segura;
2. validar timestamp e janela contra replay;
3. validar ambiente/conta destinatária;
4. rejeitar payload alterado ou origem inválida;
5. normalizar o evento;
6. retornar `success = false` quando a autenticidade não for comprovada.

O endpoint limita o corpo a 1 MB e não persiste o corpo bruto.

### Evento de cobrança

```php
[
    'success' => true,
    'event_kind' => 'charge',
    'provider_event_id' => 'evt_123',
    'external_charge_id' => 'charge_123',
    // local_charge_uuid pode substituir external_charge_id quando necessário
    'local_charge_uuid' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    'status' => 'paid',
    'external_payment_id' => 'payment_456',
    'paid_amount' => '237.00',
    'paid_at' => '2026-07-05 14:32:10',
    'occurred_at' => '2026-07-05 14:32:11',
    'message' => 'Pagamento confirmado.',
    'payload_hash' => 'sha256...',
]
```

Para estorno integral:

```php
[
    'success' => true,
    'event_kind' => 'charge',
    'provider_event_id' => 'evt_refund_123',
    'external_charge_id' => 'charge_123',
    'status' => 'refunded',
    'refund_type' => 'full',
    'refunded_amount' => '237.00',
    'occurred_at' => '2026-07-08 10:00:00',
]
```

Somente estorno integral confirmado é revertido automaticamente no financeiro. Estorno parcial, chargeback ambíguo ou evento sem valor confirmado fica com status `review`.

### Evento de cartão tokenizado

```php
[
    'success' => true,
    'event_kind' => 'payment_method',
    'provider_event_id' => 'evt_card_123',
    'unit_id' => 1,
    'customer_account_id' => 123,
    'external_customer_id' => 'cus_abc123',
    'payment_method_ref' => 'pm_token_opaco',
    'brand' => 'Visa',
    'last4' => '4242',
    'exp_month' => 12,
    'exp_year' => 2030,
    'holder_name_masked' => 'T*** F***',
    'is_default' => true,
]
```

Nunca devolver PAN, CVV ou trilha magnética.

## 9. Status normalizados

| Status | Uso |
|---|---|
| `processing` | solicitação em processamento |
| `pending` | cobrança criada e aguardando pagamento |
| `paid` | pagamento confirmado e liquidável |
| `partially_paid` | pagamento parcial; não baixa integralmente |
| `failed` | falha definitiva da tentativa |
| `expired` | cobrança vencida/expirada no provedor |
| `cancelled` | cobrança cancelada |
| `refunded` | estorno confirmado pelo provedor |
| `review` | inconsistência que exige conciliação manual |

O adaptador deve converter todos os status proprietários para essa lista. Status desconhecido deve ser normalizado como `review`.

## 10. Idempotência e duplicidade

- Repassar `idempotency_key` ao provedor sempre que a API suportar.
- Uma repetição da mesma criação deve devolver a mesma cobrança externa.
- `provider_event_id` deve ser único e estável para cada evento.
- Webhooks repetidos devem retornar o mesmo evento normalizado.
- O plugin evita segunda baixa quando `gd_payment_id` já está preenchido.
- A recorrência limita tentativas por conta a receber e respeita `retry_interval_days`.

## 11. Fluxo financeiro

```text
gd_receivables
    ↓ gerar cobrança
gdc_charges
    ↓ webhook paid
FinanceService::registerPayment()
    ↓
gd_payments + gd_payment_allocations + gd_cash_movements
```

No estorno integral:

```text
webhook refunded
    ↓
FinanceService::reversePayment()
    ↓
pagamento/alocação revertidos + movimento de saída no caixa
```

O conector não deve escrever diretamente em nenhuma tabela `gd_*` ou `gdc_*`.

## 12. Critérios de aceite do conector

- [ ] `healthCheck()` funciona em sandbox e produção.
- [ ] PIX cria `txid`, copia e cola e identificador externo.
- [ ] Cartão usa tokenização hospedada; nenhum dado bruto passa pelo servidor do Rise.
- [ ] Criação é idempotente.
- [ ] Consulta e cancelamento retornam status normalizados.
- [ ] Assinatura e timestamp do webhook são validados.
- [ ] Evento repetido não gera nova baixa.
- [ ] Pagamento aprovado gera uma única baixa no financeiro.
- [ ] Estorno integral reverte a baixa; parcial vai para revisão.
- [ ] Logs mascaram tokens, documentos e informações pessoais.
- [ ] Testes cobrem timeout, resposta 5xx, assinatura inválida e replay.
