# Arquitetura do módulo Cobrança

## Responsabilidade

O plugin `grupo_donato_cobranca` não substitui o financeiro. Ele é uma camada de cobrança e conciliação ligada a `gd_receivables`.

## Telas

1. **Painel** — pendências, recebido no mês, falhas e recorrências.
2. **Cobranças** — geração manual de PIX/cartão, status, sincronização, cancelamento e histórico.
3. **Recorrências** — regra por matrícula, locação ou outra origem financeira.
4. **Cartões** — cartões tokenizados e mascarados.
5. **Integração** — provedor, ambiente, conta financeira de destino e automação.

## Tabelas próprias

- `gdc_settings`
- `gdc_subscriptions`
- `gdc_payment_methods`
- `gdc_charges`
- `gdc_charge_events`
- `gdc_schema_versions`

## Dependências utilizadas

- `gd_customer_accounts`
- `gd_account_people`
- `gd_people`
- `gd_contact_methods`
- `gd_receivables`
- `gd_financial_accounts`
- `grupo_donato_gestao\Services\FinanceService`

## Regras principais

- Uma conta a receber só pode ter uma cobrança ativa por vez.
- Cartão precisa pertencer ao mesmo cliente financeiro da conta a receber.
- O conector externo não recebe permissão para escrever no financeiro.
- A baixa ocorre somente em `paid` e é idempotente pelo `gd_payment_id`.
- Divergência de valor, estorno parcial ou dependência ausente gera `review`.
- O cron cria cobranças apenas depois do dia configurado, ou imediatamente quando o título está vencido.
- Retentativas respeitam intervalo e limite por conta a receber.
