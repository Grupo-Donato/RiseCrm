# Preços — tabelas e valores (Fase 2B)

Define **valores** de produtos/variações/recursos por tabela de preço, com
vigência e quantidade mínima. Não gera cobrança, cupom, desconto percentual,
imposto ou parcelamento.

## Valores monetários
- `amount` e `reference_cost`: `DECIMAL(15,2)` — **nunca** `FLOAT`/`DOUBLE`.
- `minimum_quantity`: `DECIMAL(15,3)`.
- Nas regras de negócio os valores são tratados como **string decimal normalizada**
  (`DataNormalizationService::decimal`), sem float — entrada inválida, negativa,
  fora de faixa ou com casas decimais além da escala é rejeitada (sem
  arredondamento silencioso). Comparações usam `decimalCompare`; `float` não
  participa da seleção ou validação de preços. A camada de apresentação usa o
  helper `to_currency` do Rise somente para formatação.

## Tabelas de preço — `gd_price_lists`
Campos: `code`, `name`, `description`, `currency` (ISO 4217, padrão `BRL`),
`priority`, `valid_from`, `valid_until`, `is_default`, `status`
(`draft`/`active`/`inactive`/`archived`).

Regras (`PriceListService`):
- `code` único por unidade entre não excluídos; `currency` validada (3 letras).
- `valid_until` não pode ser anterior a `valid_from`.
- **Apenas uma tabela padrão ativa por unidade** — índice normalizado
  `default_list_key` + troca transacional `mark_as_default()` (`GET_LOCK`).
- Tabela expirada/inativa **não** é usada na resolução.
- Não exclui logicamente com preço ativo vinculado.

Seed: uma tabela padrão `DEFAULT` (“Preço padrão”, BRL, padrão) por unidade,
idempotente, criada apenas para o funcionamento mínimo da resolução.
`priority` é persistida e ordena listas na administração. Como a resolução
atual escolhe uma única lista explícita ou a única padrão ativa, candidatas do
mesmo cálculo têm a mesma prioridade; ela fica reservada para evolução
compatível, sem provocar fallback entre listas.

## Preços — `gd_prices`
Campos: `price_list_id`, `product_id`, `variant_id` (opc.), `resource_id` (opc.),
`amount`, `reference_cost` (opc.), `minimum_quantity`, `valid_from`,
`valid_until`, `status` (`active`/`inactive`/`archived`).

### Escopos permitidos
| Escopo | produto | variação | recurso |
|---|---|---|---|
| Produto base | ✓ | — | — |
| Variação | ✓ | ✓ | — |
| Produto por recurso | ✓ | — | ✓ |
| Variação por recurso | ✓ | ✓ | ✓ |

### Regras (`PricingService::save`)
- Tabela e produto obrigatórios e da unidade; variação (se informada) pertence ao
  produto; recurso (se informado) pertence à unidade.
- `amount >= 0`, `reference_cost >= 0`, `minimum_quantity > 0`, vigência válida.
- **Duplicidade exata** (mesmo escopo + mesma `valid_from`) bloqueada pelo índice
  único normalizado `active_scope_key`.
- **Sobreposição de período** (mesmo lista/produto/variação/recurso/min_qty)
  rejeitada por verificação dentro de transação com `GET_LOCK` por escopo
  (`gd_price_overlap`). NULL de variação/recurso tratado de forma null-aware.
- Registro **soft-deleted não bloqueia** nova definição do mesmo escopo.
- Alteração de valor/vigência é auditada com **valor anterior e novo, moeda,
  vigência, produto, variação e recurso** (sem payload bruto; metadata mascarada
  e limitada).

## Concorrência
A unicidade do padrão (variação/tabela) e a não-duplicidade de escopo de preço
são garantidas no **banco** pelos índices únicos sobre colunas geradas
`PERSISTENT` (que só assumem valor quando `deleted=0`/ativo), aproveitando que o
MariaDB permite múltiplos `NULL` em índice único. As trocas de padrão e a criação
de preço usam `GET_LOCK` + transação. O self-test verifica que um 2º padrão ou
escopo duplicado é rejeitado mesmo via `INSERT` direto.

Ver a precedência da consulta em [price-resolution.md](price-resolution.md).
