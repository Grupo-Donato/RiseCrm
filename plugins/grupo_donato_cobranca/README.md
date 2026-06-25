# Grupo Donato — Cobrança

Plugin separado para Rise CRM que adiciona o menu **Cobrança** e conecta o financeiro do Grupo Donato a um banco/gateway.

## Escopo entregue

- painel de cobrança;
- geração de PIX rastreável e cobrança em cartão por uma interface de conector;
- listagem e histórico de cobranças;
- regras de cobrança recorrente;
- tokenização hospedada de cartão;
- webhook público com validação delegada ao adaptador;
- baixa automática pelo `FinanceService` existente;
- reversão financeira de estorno integral confirmado;
- permissões por função;
- tabelas e instalador não destrutivos.

## Pré-requisito

O plugin `grupo_donato_gestao` deve estar instalado e ativo, com o módulo financeiro e as tabelas `gd_receivables`, `gd_customer_accounts` e `gd_financial_accounts` disponíveis.

## Instalação

1. Compacte ou envie a pasta `grupo_donato_cobranca` para o gerenciador de plugins do Rise.
2. Instale e ative o plugin.
3. Conceda as permissões de Cobrança às funções necessárias.
4. Acesse **Cobrança → Integração**.
5. Informe o código do provedor implementado pelo adaptador.
6. Selecione a conta financeira que receberá as baixas.
7. Teste a conexão antes de habilitar cobranças automáticas.

Sem adaptador registrado, o plugin usa `NullBillingConnector` e não envia nenhuma cobrança.

## Integração bancária

O responsável pelo banco deve implementar `BillingConnectorInterface` e registrar a classe em um hook específico do provedor.

Contrato completo: [`docs/integration-contract.md`](docs/integration-contract.md).

## Segurança

O plugin não possui campos para número completo do cartão, CVV, chave de API ou segredo de webhook. Segredos devem ficar no ambiente seguro do servidor/adaptador.

## Limites desta versão

- não inclui adaptador de banco/gateway;
- não envia PIX por WhatsApp, e-mail ou SMS;
- não possui tela de conciliação manual avançada;
- estornos parciais ficam em revisão;
- a integração ainda precisa ser homologada no ambiente real do Rise e no sandbox do provedor escolhido.

## Verificação local

```bash
php Tests/verify.php
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```
