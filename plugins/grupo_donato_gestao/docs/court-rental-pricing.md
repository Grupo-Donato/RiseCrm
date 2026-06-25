# Preço e snapshot da locação comercial

A Fase 3C **reutiliza** o `PricingService` (Fase 2B) apenas como **sugestão** e registra a negociação como um **snapshot imutável**. Nenhum preço gera cobrança; nenhum snapshot é título financeiro ou parcela.

## Resolução de preço (sugestão)

`CourtRentalService::resolvePrice()` delega a `PricingService::resolve()` com produto, variação, recurso, tabela de preço, quantidade e data de referência. O resultado expõe ao operador:

- preço sugerido (`amount`) e moeda;
- origem do preço (`matched_scope`: variação+recurso, produto+recurso, variação, produto base);
- vigência (`valid_from`/`valid_until`).

Regras:

- **Ausência de preço não retorna zero** — devolve `found = false` com a razão. O rascunho pode existir sem preço.
- A resolução é uma sugestão; o operador informa o **valor negociado**.

## Valor negociado, desconto e diferença

Campos comerciais na locação:

- `list_amount` — preço de tabela/sugerido capturado.
- `negotiated_amount` — valor efetivamente contratado (não pode ser negativo).
- `discount_amount` + `discount_reason` — desconto com **motivo obrigatório**; o desconto não pode superar o valor-base (`list_amount` ou, na ausência, `negotiated_amount`).
- A diferença exibida é `list_amount − negotiated_amount`.

## Snapshot comercial (`gd_court_rental_price_items`)

Quando há valor definido, o backend grava um item de snapshot com:

- produto/variação/recurso/preço usados, descrição, quantidade, `unit_amount`, `discount_amount`, `total_amount`, moeda e um JSON (`snapshot`) com os dados comerciais da negociação.
- `total_amount = quantidade × valor unitário − desconto`, calculado em **centavos inteiros** (sem `float`), com arredondamento meio-para-cima.

### Imutabilidade

- Alterações futuras de preço no catálogo **não** alteram snapshots já gravados.
- A **reprecificação** (`CourtRentalService::reprice`) é uma ação **explícita e auditada**: marca os itens atuais como `deleted = 1` (preservando o histórico) e grava um novo item. Um override sobre o valor negociado/desconto exige **motivo** e a permissão `gd_court_rentals_price_override`; reprecificações geram os eventos `price_overridden`/`commercial_terms_changed`.

## Ativação sem valor

A ativação exige valor contratado **ou** justificativa formal. Sem valor, é necessário `gd_court_rentals_price_override` e uma justificativa (registrada no evento de ativação).
