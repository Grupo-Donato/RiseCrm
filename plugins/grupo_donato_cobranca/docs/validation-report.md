# Relatório de validação — versão 0.1.0

## Validado neste pacote

- 46 arquivos PHP passaram em `php -l`.
- `Tests/verify.php`: PASS.
- `Tests/pure_unit.php`: PASS.
- menu principal definido como **Cobrança**;
- rotas internas e webhook declarados;
- contrato `BillingConnectorInterface` incluído;
- integração com `FinanceService::registerPayment()` e `reversePayment()` presente;
- reconciliação de webhook repetido por referência externa;
- schema sem campos para PAN, CVV, access token ou segredo de API;
- PIX limitado a URL HTTPS para QR Code;
- idempotência local por UUID, chave de idempotência, evento do provedor e vínculo `gd_payment_id`;
- recorrência respeita dia local da unidade, intervalo e limite de tentativas.

## Não validado neste ambiente

- instalação pelo gerenciador de plugins do Rise;
- execução das queries em uma cópia real do banco do cliente;
- compatibilidade visual no tema específico da instalação;
- webhook público atrás do Nginx/Cloudflare;
- conexão com uma API bancária real;
- fluxo 3DS, antifraude e tokenização do provedor;
- homologação contábil e operacional em produção.

Esses itens precisam ser testados no ambiente de homologação depois que o adaptador bancário for implementado.
