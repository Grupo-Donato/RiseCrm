# Fase 3C — Implementação

Operação comercial de locação de quadras. Versão 0.7.0, schema/marker 033, 33 tabelas, self-test 385/0. Decisões de domínio e escopo em [docs/tasks/phase-3c.md](tasks/phase-3c.md); documentação temática em [court-rentals.md](court-rentals.md), [court-rental-pricing.md](court-rental-pricing.md) e [monthly-court-renters.md](monthly-court-renters.md).

## Banco (migrations 030–033, aditivas e idempotentes)

- `V030_create_court_rentals` → `gd_court_rentals`.
- `V031_create_court_rental_schedule_links` → `gd_court_rental_schedule_links` (colunas-guarda + unique de exclusividade ativa).
- `V032_create_court_rental_price_items` → `gd_court_rental_price_items` (snapshot).
- `V033_create_court_rental_events` → `gd_court_rental_events` (append-only).

Migrations 001–029 inalteradas. `SCHEMA_TARGET` e `PLUGIN_VERSION` em `Config/Constants.php`; o bump de versão para 0.7.0 ocorreu somente após `verify-full`.

## Camadas

- **Models**: `Gd_court_rentals_model`/`..._schedule_links_model`/`..._price_items_model` (com `get_scoped`/`optimistic_update`/helpers); `Gd_court_rental_events_model` (append-only — `add()` + bloqueio de `ci_save`/`update`/`delete`).
- **Services**:
  - `CourtRentalService` (estende `CatalogDataService`): `normalizeCommercial`, `resolvePrice`, `createDraft`, `createWithBooking`, `createWithSeries`, `linkExisting`, `reprice`, `listPage`, `monthlyRentersList`, `get`. Reaproveita `BookingService::save(external_transaction, locks_already_held)`, `BookingSeriesService::create/preview`, `PricingService::resolve`, `SequenceService`. Total do snapshot em centavos inteiros (sem `float`).
  - `CourtRentalLifecycleService`: `activate`, `suspend`, `resume`, `cancel`, `complete`, `archive` com lock por locação, compare-and-swap de `lock_version`, eventos + auditoria, e `applyFuturePolicy` (pausa a série e trata ocorrências futuras conforme `keep|cancel|pause_series`).
  - `CourtRentalLockService` (lock nomeado por locação) e `CourtRentalEventService` (histórico append-only mascarado).
- **Controller**: `Court_rentals` (fino, estende `Gd_Controller`): exige `gd_court_rentals_view`; escrita com `gd_court_rentals_manage`; status com `gd_court_rentals_status_manage`; override/reprice com `gd_court_rentals_price_override`. Whitelist de campos, filtros e transições; reutiliza os helpers de cliente/contato/disponibilidade do `BookingService`.
- **Views** `Views/court_rentals/`: `index`, `monthly`, `view`, `single_modal`, `monthly_modal`, `link_modal`.

## Config e integração

- `Config/Permissions.php`: 4 chaves novas; `gd_court_rentals_manage ⇒ view`; as chaves de court rentals foram adicionadas às listas de leitura de reservas, séries, calendário, recursos, contas, pessoas, catálogo e preços (a checagem em `AccessService::can` não é transitiva); novo grupo na tela de papéis.
- `Config/Routes.php`: bloco `court-rentals` (GET leitura, POST escrita, filtro `csrf`).
- `index.php`: item de menu `Locações de quadras` e cadeia de landing.
- `Language/portuguese/default_lang.php`: bloco gd_* da Fase 3C (a versão inglesa reexporta a portuguesa).

## Testes

- `Tests/court_rental_selftest.php` incluído no `selftest` (cli.php), reaproveitando as fixtures (contas, pessoas, recursos, produto de locação, tabela de preço); contagens 29→33 e `uninstallcheck` atualizados.
- `Tests/court_rental_concurrency.ps1/.sh` + tarefas `rentalracesetup/rentalactivate/rentallink/rentaloverride/rentalcreate/rentalraceinspect/rentalracecleanup` no cli.php; passo registrado em `verify-full`.
