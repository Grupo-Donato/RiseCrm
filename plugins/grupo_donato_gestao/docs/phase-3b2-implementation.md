# Fase 3B2 — implementação

## Resultado

Fase concluída na versão 0.6.0, schema/marker 029 e 29 tabelas. Séries diárias, semanais e mensais geram reservas normais e preservam disponibilidade, conflitos, buffers, ciclo de vida, auditoria, eventos e privacidade da Fase 3B1.

## Schema

- V025: `gd_booking_series`.
- V026: `gd_booking_series_resources`.
- V027: vínculo e chave idempotente em `gd_bookings`.
- V028: `gd_booking_series_exceptions` append-only.
- V029: `gd_booking_series_events` append-only e ledger `gd_booking_series_generation_runs`.

O ledger de geração materializa a 29ª tabela exigida e registra execução efetiva, idempotente ou falha. Não representa uma ocorrência paralela: a ocorrência continua sendo exclusivamente uma linha completa de `gd_bookings`.

Migrations 001–024 não foram alteradas. Todas as novas estruturas usam DBPrefix, InnoDB e evolução aditiva/idempotente.

## Componentes

- `BookingSeriesService`: definição, preview, lista, detalhe e atualização integral.
- `RecurrenceGeneratorService`: datas locais, término, horizonte, DST e overnight.
- `BookingSeriesOccurrenceService`: geração, políticas de conflito, destaque, cancelamento e regeneração.
- `BookingSeriesLifecycleService`: pausa, retomada, encerramento e cancelamento.
- `BookingSeriesSplitService`: alteração desta e das próximas com sucessora transacional.
- `BookingService`: ponto interno confiável para criar ocorrências sem ampliar o payload HTTP.

Locks de série usam `gd:series:{unit_id}:{series_id}`; criação usa hash estável do payload. Locks de recursos continuam ordenados pelo serviço de reservas. O unique `(series_id, series_occurrence_key)` é a barreira final contra duplicidade.

## Regras entregues

- Dia mensal inexistente: mês ignorado.
- Série aberta: somente até o horizonte configurado.
- Limite: 366 ocorrências por operação.
- Ocorrências: apenas `pending_confirmation` ou `confirmed`; nunca hold.
- `reject_series`: rollback de todas as ocorrências da geração quando qualquer candidata falha.
- `skip_conflicts`: cria válidas e registra `conflict_skipped`.
- Alteração única: ocorrência destacada e revalidada.
- Alteração futura: série anterior encerrada, sucessora criada e futuro modificável substituído.
- Alteração integral: histórico preservado; apenas futuro modificável é cancelado/destacado e regenerado.
- `in_progress`, `completed` e `no_show` não são reescritas.
- Cancelamentos são lógicos; eventos e exceções permanecem históricos.

## Segurança

As rotas administrativas estendem `Gd_Controller`. Toda escrita é POST sob CSRF. Unidade, clientes, contatos, recursos, série e reserva são revalidados no backend. Controllers montam whitelists explícitas; campos de vínculo da série são aceitos somente pelo método interno confiável de `BookingService`.

Permissões novas: `gd_booking_series_view`, `gd_booking_series_manage` e `gd_booking_series_status_manage`. Elas implicam apenas as leituras necessárias e não concedem gestão de reservas, clientes ou recursos.

## Homologação

- Lint: 192/192 no candidato pré-documentação; contagem final validada novamente pelo `verify-full`.
- Self-test: 328 PASS / 0 FAIL.
- Geração concorrente: 1 efetiva, 1 idempotente, 3 reservas, 0 duplicidades.
- Regressões de sequência, temporal e booking aprovadas.
- Install repetido, uninstall 29/29, sistema legado/core, `CHECK TABLE` e logs aprovados.

Não houve alteração de core ou sistema legado, edição de migrations 001–024 ou exclusão física em fluxo de domínio. Contratos, valores, cobranças, financeiro e Fase 3C não foram iniciados.
