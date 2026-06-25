# Locação comercial de quadras

A locação comercial (Fase 3C) é a camada de negócio sobre reservas e séries: dá significado comercial à ocupação da agenda (quem aluga, em que condições, por qual valor contratado). **Não** implementa financeiro (título, cobrança, pagamento, caixa, conciliação) — o dia de vencimento é apenas uma condição comercial.

## Modelo de dados

| Tabela | Papel |
|---|---|
| `gd_court_rentals` | Acordo comercial: número (`LOC-AAAA-NNNNNN`), conta, contato, tipo, ciclo, estado, vigência, dia de vencimento, valores e referências de preço. |
| `gd_court_rental_schedule_links` | Vínculo a uma reserva (`booking_id`) **ou** a uma série (`booking_series_id`), classificado como `primary`/`replacement`/`historical`. |
| `gd_court_rental_price_items` | Snapshot comercial da negociação (valor contratado), imutável após criação. |
| `gd_court_rental_events` | Histórico append-only (sem update/delete). |

### Tipos, ciclos e estados

- `rental_type`: `single` (avulso, ciclo `one_time`) ou `recurring` (mensalista, ciclo `monthly`).
- `status`: `draft → active`/`cancelled`; `active → suspended`/`cancelled`/`completed`; `suspended → active`/`cancelled`; `completed`/`cancelled → archived` (terminais).
- `preferred_due_day` (1–31) existe apenas para mensalistas; aceita qualquer dia, e o tratamento de meses sem o dia será definido no financeiro.

## Invariantes

- Conta obrigatória, ativa e da mesma unidade; contato opcional, mas vinculado à conta.
- Produto/lista/preço (opcionais) precisam pertencer à unidade; produto precisa ser compatível com locação (`rental`, `service` ou `fee`).
- Vigência final não pode anteceder a inicial.
- Dinheiro em `DECIMAL(15,2)`; `total_amount` do snapshot é calculado no backend (centavos inteiros, sem `float`).
- **Uma reserva ou série não pode pertencer a duas locações ativas**: garantido por colunas-guarda (`active_booking_guard`, `active_series_guard`) com `UNIQUE (unit_id, guarda)`. Links históricos/excluídos têm guarda nula e não colidem.
- `lock_version` (compare-and-swap) protege edição e transições concorrentes; locks nomeados por locação, por recurso e por série serializam operações críticas.

## Fluxos

- **Avulso integrado** (`CourtRentalService::createWithBooking`): cria a reserva única pelo `BookingService` (transação externa, locks já adquiridos), a locação, o vínculo `primary` e o snapshot — tudo em uma transação, com auditoria e eventos.
- **Mensalista integrado** (`createWithSeries`): cria a série pelo `BookingSeriesService` (reutiliza o gerador de recorrência), depois a locação, o vínculo e o snapshot.
- **Rascunho** (`createDraft`): locação `draft` sem vínculo nem valor obrigatórios.
- **Vínculo existente** (`linkExisting`): vincula uma reserva/série existente quando mesma unidade, mesma conta, status compatível, não-excluída e ainda não vinculada a outra locação ativa.

## Ciclo de vida

`CourtRentalLifecycleService` aplica as transições com lock, compare-and-swap, eventos e auditoria.

- **Ativação** exige conta válida, ≥1 vínculo operacional, consistência comercial e **valor OU justificativa formal** (a ausência de valor exige permissão `gd_court_rentals_price_override` + justificativa).
- **Suspensão/cancelamento** não apagam série nem reservas; sempre pausam a série vinculada (impede geração futura) e aplicam uma **política explícita** às ocorrências futuras — `keep` (mantém), `cancel` (cancela), `pause_series` (mantém, série pausada) — registrada na auditoria. Cancelamento exige motivo e não gera multa nem crédito.
- **Retomada** reativa a locação e a série vinculada.

## Permissões

`gd_court_rentals_view`, `gd_court_rentals_manage`, `gd_court_rentals_status_manage`, `gd_court_rentals_price_override`. A leitura/gestão implica apenas **leitura** de reservas, séries, calendário, recursos, contas, pessoas, catálogo e preços — sem conceder a gestão desses cadastros.

## Interface

Menu **Grupo Donato → Locações de quadras**: lista de locações, assistente avulso, assistente mensalista, detalhe comercial (valores, vínculos, histórico, ações de status, reprecificação) e a [lista de mensalistas](monthly-court-renters.md).

Ver também: [preço e snapshot](court-rental-pricing.md) e [mensalistas](monthly-court-renters.md).
