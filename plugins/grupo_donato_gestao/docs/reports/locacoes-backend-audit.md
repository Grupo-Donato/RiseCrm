# Auditoria técnica — backend das locações (pré-correção)

**Plugin:** `grupo_donato_gestao` · **Versão de partida:** `0.9.2` · **Schema:** `049` (49 tabelas)
**Escopo:** somente locações de quadras (agenda, reservas, séries, locação comercial, financeiro vinculado). Alunos/escola/caixa/pagamentos gerais preservados.

Este documento registra o estado do backend **antes** das correções, conforme exigido pela Etapa 1. As correções estão em [locacoes-backend-validation.md](locacoes-backend-validation.md).

## 1. Migrations 001–049
Todas presentes em `Database/Schema/Versions/V001..V049` e íntegras. Runner (`Database/Schema/SchemaRunner.php`) só carrega versões `<= Constants::SCHEMA_TARGET` ("049"); reexecuta `up()` de versões já `completed` para reconciliação não-destrutiva; marca versão aplicada em `writable/gd_schema_version.txt`, `gd_settings.schema_version` e `gd_schema_versions`. **Nenhuma tabela nova é necessária** para o escopo (ver §Tabelas).

## 2. Tabelas e índices relevantes
- `gd_bookings` (V022) + `gd_booking_resources` (V023) + eventos (V024).
- `gd_booking_series` (V025) + `gd_booking_series_resources` (V026) + exceções/eventos (V028/V029).
- `gd_court_rentals` (V030): `rental_type`, `status`, `preferred_due_day`, `effective_from/until`, `currency`, `list_amount`, `negotiated_amount`, `discount_amount/reason`, `product_id`, `price_list_id`, `price_id`, `customer_account_id`, `contact_person_id`, `title`, `lock_version`.
- `gd_court_rental_schedule_links` (V031): `booking_id`, `booking_series_id`, `link_kind`, guards `active_booking_guard`/`active_series_guard` com UNIQUE por unidade (link ativo único).
- `gd_court_rental_price_items` (V032): snapshot comercial (`snapshot` JSON, `deleted` p/ histórico).
- `gd_court_rental_events` (V033): histórico append-only.
- `gd_receivables` (V040): `source_type`, `source_id`, `reference_month` + **UNIQUE `uniq_receivable_source (unit_id, source_type, source_id, reference_month, deleted)`** — chave de idempotência. `gd_payments`/`gd_payment_allocations` (V042).

## 3. Rotas do novo front e permissões
Todas em `Config/Routes.php` no grupo `grupo_donato` com filtro `csrf`. Permissões via `AccessService` no construtor + inline por ação.

| Endpoint | Permissão |
|---|---|
| `GET calendar/events` | `gd_calendar_view` |
| `POST court-rentals/list-data`, `monthly-data`, `view` | `gd_court_rentals_view` (construtor) |
| `POST court-rentals/save-single`, `save-monthly`, `reprice`, `resolve-price`, `check-availability`, `preview`, `customer-options`, `contact-options` | `gd_court_rentals_manage` |
| `POST court-rentals/{id}/activate|suspend|resume|cancel|complete` | `gd_court_rentals_status_manage` |
| `POST bookings/list-data` | `gd_bookings_view` |
| `POST booking-series/list-data` | `gd_booking_series_view` |
| `POST finance/generate-rental` | `gd_receivables_manage` |
| `POST finance/payment-modal`, `payments/save` | `gd_payments_manage` |

## 4. Formato real das respostas JSON (gap)
- `Gd_Controller::json_success/json_error` usam `echo json_encode(...)` **sem `Content-Type: application/json`** (default text/html) e **sem status HTTP** (sempre 200). Risco: warning/HTML do PHP contamina o corpo.
- `gd_fail()` traduz só chaves `gd_*` e responde 200 mesmo em conflito/validação. **Não há 400/404/409/422.**
- `AccessService::require()` já responde **403** JSON (raw `http_response_code`+`echo`+`exit`), porém sem `Content-Type` nem `error_code`.
- Endpoints de leitura (`list-data`, `customer-options`) já usam `response->setJSON()` (Content-Type correto).

## 5. Timezone (gap)
`TemporalService` (canônico) converte UTC↔local pela coluna `gd_units.timezone`. Porém `CourtRentalService::scheduleSummary()` monta o horário de **avulsa** via `substr()` do UTC (linha ~565) — bug de fuso. Há um "conserto temporário" no controller (`Court_rentals::scheduleDisplay()`) só para a página de detalhe; a lista e o "próximo horário" ainda consomem o valor com substring.

## 6. Filtros de listagem (gap)
`CourtRentalService::queryList()` filtro `resource_id` só cobre **séries** (`gd_booking_series_resources`); **ignora avulsas** (`gd_booking_resources`). Contagem filtrada e query de dados compartilham o mesmo closure `$base()` (consistente); total é intencionalmente sem filtro (padrão DataTables).

## 7. Fluxo de criação avulsa
`save-single` → `CourtRentalService::createWithBooking()`: lock comercial + locks de quadra → `transBegin` → `BookingService::save` → sequência `LOC-` → insere locação (draft) → insere link → snapshot (se houver preço) → eventos + auditoria → commit → ativa opcional. Locks liberados em `finally`. Whitelist de entrada cobre o contrato §7.

## 8. Fluxo de criação recorrente
`save-monthly` → `createWithSeries()`: reusa `BookingSeriesService::create` (recorrência não duplicada) dentro da transação; demais passos análogos à avulsa. Whitelist cobre o contrato §9.

## 9. Geração financeira
- **Avulsa** `generateCourtRental()`: lê cliente/source/descrição da **locação** (não do request); só `amount`/`due_date` são override; `reference_month=''`; idempotência por `uniq_receivable_source`; em duplicata retorna `duplicate=true`+id (sem inserir). Controller ainda responde "record_saved" mesmo em duplicata (melhorar mensagem).
- **Mensal** `preview()/generateMonth()`: filtra recorrentes+ativas+vigentes no mês+unidade; clampa `preferred_due_day` 29/30/31 ao último dia; idempotente por `reference_month`.
- **N+1:** a lista de mensalistas calcula a situação financeira com **1 query por linha** (`Court_rentals::monthlyFinance`) + 1 query de contato por linha (`monthlyContact`). Não há método de saldo agregado por `source_id`.

## 10. Concorrência e transações
Locks avançados MySQL (`GET_LOCK`) em `CourtRentalLockService`/`BookingResourceLockService`/`BookingSeriesLockService`; `optimistic_update` (`WHERE lock_version=?` + `affectedRows()===1`) nos models; conflito lança `\DomainException('gd_*_edit_conflict')`; `release()` sempre em `finally`. Cobertura por `Tests/*_concurrency.sh`.

## 11. Lifecycle
`CourtRentalLifecycleService`: mapa `ALLOWED` de transições; `reason` exigido no cancel; `future_policy` (`keep`/`cancel`/`pause_series`) em suspend/cancel; evento append-only + auditoria; retorna a linha recarregada (status+lock_version). Não altera ocorrências passadas (`cancelFuture`).

## 12. Calendário (gap)
`CalendarService::events()`: eventos de booking já trazem `booking_id`, `status`, `resource_id`, mas **não** `court_rental_id` nem `booking_type`; `gd_court_rental_schedule_links` não é consultada. Quando `types` vem vazio, o fallback inclui `weekly_rule` (disponibilidade padrão) mesmo sem ser solicitado.

## 13. Select2 de produto/tabela de preço (gap)
`customer-options`/`contact-options` já existem. **Não existem** endpoints AJAX de busca de produto e de tabela de preço; os formulários usam IDs manuais na área avançada.

## 14. Gaps encontrados (resumo → correções)
1. Filtro `resource_id` ignora avulsas (§6) → **2.1**.
2. `scheduleSummary` usa substring UTC (§5) → **2.2**.
3. JSON sem Content-Type/status/error_code (§4) → **2.3**.
4. Calendário sem `court_rental_id`/`booking_type`; disponibilidade padrão indevida (§12) → **2.4**.
5. Falta Select2 de produto e tabela de preço (§13) → **2.5**.
6. `resolve-price` OK; validar cross-unit (§9) → **2.6**.
7. Situação financeira N+1 (§9) → **2.7**.
8. Ações financeiras não contextualizadas (§9) → **2.8**.
9/10. Concorrência/lifecycle OK; garantir 409 e cobrir por teste (§10/§11) → **2.9/2.10**.
