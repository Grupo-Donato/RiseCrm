# Resolução de preço (Fase 2B)

`PricingService::resolve()` resolve o **preço aplicável** para uma combinação. É
somente leitura: não gera cobrança, item de venda, cupom, desconto percentual,
imposto ou parcelamento, e **nunca** altera dados.

## Entrada
```
unit_id          (resolvido no backend)
price_list_id    (opcional)
product_id
variant_id       (opcional)
resource_id      (opcional)
quantity
reference_date   (padrão: hoje, UTC)
```

## Escolha da tabela
- Se `price_list_id` for informado: valida unidade, status `active` e vigência na
  data. Inválida → retorno explícito de **preço ausente** (`price_list_invalid`).
- Senão: usa a **tabela padrão ativa** da unidade. Sem tabela padrão válida →
  preço ausente (`no_default_price_list`).
- Tabela expirada/inativa nunca é usada.

## Precedência (maior → menor especificidade)
```
1. variação + recurso
2. produto base + recurso
3. variação (sem recurso)
4. produto base (sem recurso)
```
A variação/recurso informados precisam estar **ativos**, pertencer à unidade e,
no caso da variação, ao produto. Referência inválida ou inativa retorna preço
ausente (`variant_not_resolvable`/`resource_not_resolvable`); não existe fallback
silencioso para o preço-base.

Dentro do mesmo nível, o desempate é determinístico:
1. maior `minimum_quantity` que não ultrapasse a quantidade solicitada;
2. vigência aplicável (mais recente `valid_from`);
3. prioridade da tabela (todas as candidatas têm a mesma na seleção atual,
   pois uma única lista explícita ou padrão é escolhida);
4. maior `id` apenas como critério técnico final.

Não se usa “último registro” sem regra explícita.

## Resultado
Quando encontra:
```
found = true
price_id, price_list_id, product_id, variant_id, resource_id,
amount, reference_cost, currency, matched_scope,
minimum_quantity, valid_from, valid_until
```
Quando **não** há preço:
```
found = false
reason  (product_not_resolvable | variant_not_resolvable |
          resource_not_resolvable | price_list_invalid |
          no_default_price_list | no_matching_price)
```
Nunca retorna zero automaticamente. Quantidade zero/negativa ou entrada decimal
malformada é erro de validação (`gd_invalid_quantity`/`gd_invalid_amount`), não é
convertida para quantidade 1. Para uma entrada válida sem candidato, o contrato
é sempre `found=false`, sem efeito colateral.

## Demonstração (extraída do self-test)
Com base=100, variação=120, produto+recurso=150, variação+recurso=175 e um tier
base min_qty=10 = 90, na mesma tabela:

| Consulta | Escopo resolvido | Valor |
|---|---|---|
| sem variação, sem recurso, qtd 1 | `product_base` | 100,00 |
| variação V1, qtd 1 | `variant` | 120,00 |
| recurso Q2, qtd 1 | `product_resource` | 150,00 |
| variação V1 + recurso Q2, qtd 1 | `variant_resource` | 175,00 |
| sem variação/recurso, qtd 15 | `product_base` (tier) | 90,00 |
| produto inexistente/inativo | — | preço ausente |
| variação/recurso informado e inativo | — | preço ausente, sem fallback |

## Ferramenta de teste
`/grupo_donato/pricing/resolver` permite informar produto, variação, recurso,
quantidade, data e tabela e exibe **qual preço seria resolvido** — sem gravar
nada. Exige permissão de leitura (`gd_prices_manage` / `gd_price_lists_view`).

Limites: não calcula desconto, imposto, parcela, estoque, disponibilidade ou
cobrança; não procura automaticamente outra tabela quando a selecionada é
inválida ou não contém preço.
