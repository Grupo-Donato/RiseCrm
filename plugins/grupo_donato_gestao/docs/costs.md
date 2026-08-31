# Módulo central de Custos

## Fonte de verdade

`gd_expenses` continua sendo a entidade principal. A evolução foi feita apenas com as versões V054–V062; nenhuma versão anterior foi alterada. O registro guarda competência, emissão, vencimento, valor bruto, descontos, juros, multas, valor final, pago, saldo, natureza, comportamento, categoria, subcategoria, área, centro, recurso, recorrência e parcelamento.

O valor final é calculado no serviço (`bruto - desconto + juros + multa`) usando aritmética decimal. O frontend exibe uma prévia, mas nunca define o valor persistido. O status pago só é derivado do ledger de pagamentos; o usuário não marca um custo diretamente como pago.

## Ledger e caixa

`gd_expense_payments` é o ledger de pagamentos de custos. Cada pagamento confirmado cria exatamente um movimento `out` em `gd_cash_movements` com `source_type=expense_payment`. O estorno mantém o pagamento histórico com status `reversed` e cria exatamente um movimento `in` com `source_type=expense_payment_reversal`.

Pagamentos usam chave de idempotência e são limitados ao saldo. Custos pagos parcialmente não podem ser cancelados; custos pagos integralmente também precisam de estorno antes de qualquer ajuste crítico.

Custos realizados são apurados por competência (`final_amount`). Saídas de caixa são apuradas pela data e status dos pagamentos (`confirmed`/`legacy_migrated`). A tela e os dashboards mantêm as duas leituras separadas.

## Estruturas adicionais

- `gd_expense_categories`: catálogo global hierárquico e categorias customizadas por unidade.
- `gd_expense_allocations`: rateio por área, centro ou recurso; percentuais somam 100% e valores somam o valor final.
- `gd_expense_recurrences`: templates sem impacto contábil até a geração da ocorrência.
- `gd_expense_attachments`: metadados e arquivo privado em `writable/uploads/gd_costs`, com MIME real, extensão, tamanho, SHA-256 e acesso escopado.
- `gd_cost_budgets`: orçamento mensal geral, por categoria, área ou centro, com chave determinística.

Parcelas são custos independentes e dividem o total em centavos, distribuindo o resto de forma determinística. Recorrências e parcelas usam `occurrence_key` único por unidade para retry seguro.

## Tela e rotas

A tela oficial é `grupo_donato/finance/costs`, renderizada por `Controllers/Costs.php`, com filtros server-side, exportação CSV, cards, gráficos de competência, categoria, centro e orçamento, modais nativos do Rise e detalhe com pagamentos/anexos.

`grupo_donato/finance/expenses` permanece como alias de compatibilidade: o GET redireciona para Custos e os POST antigos são adaptados ao novo serviço. Um POST legado com `status=paid` cria um pagamento no ledger, em vez de uma segunda saída de caixa.

## Instalação e verificação

Este checkout não possui `spark`. Use:

```powershell
php plugins/grupo_donato_gestao/Tests/cli.php install
php plugins/grupo_donato_gestao/Tests/cli.php costs-selftest
```

O selftest de Custos cria fixtures em uma unidade temporária e as remove ao terminar. A bateria validada contém 60 asserções, incluindo migração legada, anexos válidos/inválidos, idempotência e isolamento.

O módulo não implementa transferência entre contas como custo, gateway bancário, NF-e, juros automáticos, fornecedor estruturado ou DRE completa. Esses pontos ficam fora do escopo atual e não devem ser simulados como custos.
