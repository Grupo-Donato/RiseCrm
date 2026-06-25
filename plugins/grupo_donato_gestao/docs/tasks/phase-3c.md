# Tarefa — Fase 3C

## Objetivo e limites

Entregar a **operação comercial de locação de quadras** sobre o domínio homologado até a Fase 3B2: contas/pessoas, catálogo/recursos/preços, disponibilidade, reservas únicas e séries recorrentes. A locação comercial é a camada de negócio que dá significado às reservas — quem aluga, em que condições, por qual valor.

A entrega **não inclui** financeiro nem qualquer fase futura: título a receber, cobrança mensal, pagamento, baixa, recibo, caixa, inadimplência, multa, juros, crédito, caução, nota fiscal, contrato jurídico, assinatura eletrônica, integração bancária, importação de PDF/planilhas, bar, estoque ou PDV. O **dia de vencimento é apenas uma condição comercial** nesta fase.

## Baseline obrigatório

- Plugin 0.6.0, schema e marker 029, 29 tabelas.
- Self-test 328 PASS / 0 FAIL antes das mudanças.
- Migrations 001–029, core Rise e sistema legado preservados byte a byte.
- Backup e `CHECK TABLE` antes das novas migrations.

## Schema adotado (030–033, aditivo e idempotente)

As migrations usam o DBPrefix do framework, mantêm InnoDB/charset do host e seguem o padrão `SchemaVersion::ensureTable`. Dinheiro em `DECIMAL(15,2)`, quantidade em `DECIMAL(15,3)`; nunca `float`.

1. **`gd_court_rentals`** — cadastro comercial: número (`SequenceService`, `LOC-AAAA-NNNNNN`), conta, contato, tipo (`single|recurring`), ciclo (`one_time|monthly`), estado (`draft|active|suspended|cancelled|completed|archived`), `preferred_due_day` (1–31, só mensalista), vigência (`effective_from/until`), moeda, valores (`list/negotiated/discount_amount`, `discount_reason`), referências sugestivas (`product_id/price_list_id/price_id`), `commercial_notes`, `metadata`, `lock_version`, carimbos de transição e autoria, `deleted`.
2. **`gd_court_rental_schedule_links`** — vínculo da locação a `booking_id` **ou** `booking_series_id` (exatamente um), `link_kind` (`primary|replacement|historical`), autoria, `deleted`. A invariante "uma reserva/série não pertence a duas locações ativas" é protegida no banco por colunas-guarda (`active_booking_guard`, `active_series_guard`) mantidas pelo Service com `UNIQUE (unit_id, guard)` — NULLs múltiplos não colidem, então links históricos/excluídos não bloqueiam novos vínculos.
3. **`gd_court_rental_price_items`** — snapshot comercial da negociação: produto/variação/recurso/preço, descrição, quantidade, `unit_amount`, `discount_amount`, `total_amount` (calculado no backend), moeda, `snapshot` (JSON dos dados usados), autoria, `deleted`. Não é título financeiro nem parcela; alteração de preço futuro não muda o snapshot.
4. **`gd_court_rental_events`** — histórico **append-only** (sem `deleted`, sem update/delete): `created, updated, activated, suspended, resumed, cancelled, completed, schedule_linked, schedule_replaced, price_resolved, price_overridden, commercial_terms_changed`.

Marker final: **033**, 33 tabelas.

## Reuso (não reimplementar)

- **Reserva única**: `BookingService::save(external_transaction, locks_already_held)` cria a reserva avulsa dentro da transação da locação.
- **Série**: `BookingSeriesService::create()`/`preview()` — o gerador de recorrência **não é duplicado**.
- **Preço**: `PricingService::resolve()` produz a **sugestão**; ausência de preço retorna `found=false`, **nunca zero**.
- **Número**: `SequenceService` (doc type `court_rental`), nunca vindo do navegador.
- **Concorrência**: locks nomeados por locação (`CourtRentalLockService`), por recurso (`BookingResourceLockService`, ordem numérica) e por série (`BookingSeriesLockService`); `lock_version` compare-and-swap.
- **Tempo/escopo/auditoria/privacidade**: `TemporalService`, `UnitContextService`, `AuditService`/`DataPrivacyService`, soft delete `deleted`.

## Fluxos

- **Avulso integrado** (`createWithBooking`): conta/contato → data/horário/recurso/buffers → disponibilidade → preço sugerido → valor negociado (motivo se diferente) → cria reserva única → cria locação → vínculo `primary` + snapshot → auditoria, tudo na mesma transação.
- **Mensalista integrado** (`createWithSeries`): conta/contato → padrão recorrente → preview → política de conflito existente → vigência + dia de vencimento → condições comerciais → cria série (serviço existente) → cria locação → vincula série → snapshot + histórico.
- **Vínculo existente** (`linkExisting`): permite vincular reserva/série existente quando mesma unidade, mesma conta, status compatível, não-excluída e ainda não vinculada a outra locação ativa.
- **Rascunho**: locação `draft` pode existir sem preço e sem vínculo. Ativação exige conta válida, ≥1 vínculo operacional, condições consistentes e **valor OU justificativa formal** (com permissão).

## Estados e transições

`draft→active|cancelled`; `active→suspended|cancelled|completed`; `suspended→active|cancelled`; `cancelled/completed/archived` terminais (+ `→archived`).

- **Suspender**: não apaga série/reservas; impede geração futura adicional; exige política explícita de ocorrências futuras (`keep|cancel|pause_series`), auditada.
- **Cancelar**: exige motivo; oferece tratamento das reservas futuras; não altera reservas concluídas/históricas; não gera multa nem crédito.

## Pricing

Exibe preço sugerido, valor negociado, diferença, desconto, origem do preço e vigência. Preço negociado não-negativo; desconto ≤ valor-base. Reprecificação é ação **explícita** e auditada (`price_overridden`/`commercial_terms_changed`), exige permissão `gd_court_rentals_price_override` e **não** altera snapshots históricos.

## Permissões

`gd_court_rentals_view`, `gd_court_rentals_manage`, `gd_court_rentals_status_manage`, `gd_court_rentals_price_override`. Leitura do módulo implica leitura de reservas, séries, calendário, recursos, contas, pessoas, catálogo e preços — **sem** conceder edição desses cadastros (implicações declaradas em `Config/Permissions.php`).

## Interface

Menu `Grupo Donato → Locações de quadras`. Entregar: lista de locações, assistente avulso (modal reaproveitando reserva), assistente mensalista (modal reaproveitando série), detalhe comercial, histórico, vínculos, ações de status, reprecificação explícita e **lista de mensalistas** (colunas: cliente, contato, recurso(s), dia(s) da semana, horário local, vigência, dia de vencimento, valor contratado, status, próxima ocorrência; filtros: recurso, dia da semana, status, cliente, vigência). Sem importar dados nesta fase.

## Testes e homologação

- `Tests/court_rental_selftest.php` incluído no `selftest`, reaproveitando fixtures (contas, pessoas, recursos `bookA..C`/Q2, produto `rental`, lista de preço). Casos: avulso/mensalista completos, conta inválida, contato fora da conta, produto incompatível, preço inexistente/sugerido, override autorizado/sem motivo, snapshot imutável, vencimento 1 e 31, vigência inválida, vínculo booking/série/duplicado, ativação/suspensão/retomada/cancelamento/conclusão, política de futuras, `lock_version`, IDOR/CSRF/unidade/permissões, e ausência de título/pagamento.
- Atualizar contagens `29→33` no `selftest` e no `uninstallcheck`.
- Concorrência (`court_rental_concurrency.ps1/.sh` + tarefas no `cli.php`): duas ativações simultâneas, duas locações na mesma série, dois overrides concorrentes, `lock_version` desatualizado, criação integrada concorrente no mesmo recurso → nenhuma dupla ocupação, nenhum vínculo duplicado, nenhum overwrite silencioso.
- `verify-fast` durante o desenvolvimento; `verify-full` antes da entrega. Bump de versão (0.7.0) **somente após** `verify-full` aprovado.

## Documentação

`docs/phase-3c-implementation.md`, `docs/court-rentals.md`, `docs/court-rental-pricing.md`, `docs/monthly-court-renters.md`; atualizar `docs/agent/CURRENT_STATE.md`, `docs/agent/ROADMAP.md`, `docs/agent/HANDOFF.md`, `docs/reports/phase-3c.md` e o ponteiro no `README.md`.
