# Tarefa — Fase 3B2

## Objetivo e limites

Implementar séries de reservas e ocorrências materializadas sobre o domínio homologado da Fase 3B1. A entrega não inclui contratos, mensalistas comerciais, preços aplicados, cobranças, pagamentos, financeiro ou qualquer item da Fase 3C.

## Baseline obrigatório

- Plugin 0.5.0, schema e marker 024, 24 tabelas.
- Self-test 288 PASS / 0 FAIL antes das mudanças.
- Migrations 001–024, core Rise e sistema legado preservados byte a byte.
- Backup e `CHECK TABLE` antes das novas migrations.

## Schema adotado

As migrations 025–029 são aditivas, idempotentes, usam o DBPrefix e mantêm InnoDB/charset do host:

1. `gd_booking_series`: definição, término, horizonte, estado, timezone, `lock_version` e auditoria de autoria.
2. `gd_booking_series_resources`: recursos e buffers padrão, com unique por série/recurso.
3. extensão de `gd_bookings`: vínculo, chave local idempotente e flags de exceção/destaque.
4. `gd_booking_series_exceptions`: skip, cancel, detach, override, split e conflict_skipped.
5. `gd_booking_series_events`: histórico append-only da série.

A chave de ocorrência é a data local `YYYY-MM-DD`; o unique `(series_id, series_occurrence_key)` impede duplicidade. O campo de série pode ser nulo nas reservas avulsas existentes.

## Recorrência

- Frequências: diária, semanal e mensal por dia do mês.
- Intervalo inteiro positivo e limite por operação.
- Semanal exige dias ISO 1–7; mensal exige dia 1–31.
- Dia inexistente em um mês é ignorado e não é deslocado.
- Término por data, contagem ou aberto com horizonte.
- Preview usa exatamente o mesmo gerador de datas e não persiste.
- Datas são calculadas no timezone persistido da unidade; instantes são convertidos para UTC pelo `TemporalService`.
- Horário final menor ou igual ao inicial representa overnight no dia local seguinte.
- Horários civis inexistentes ou ambíguos por DST são rejeitados para a ocorrência.

## Geração e atomicidade

`RecurrenceGeneratorService` produz candidatos locais. `BookingSeriesOccurrenceService` prepara disponibilidade e conflitos e cria reservas pelo fluxo central confiável de `BookingService`, incluindo auditoria e booking events.

`BookingSeriesService` coordena criação/edição e `BookingSeriesLifecycleService` coordena estado e cancelamentos. `BookingSeriesSplitService` implementa “esta e as próximas”.

- Lock de série: `gd:series:{unit}:{series}`; criação usa chave temporária derivada do payload normalizado.
- Locks de recursos continuam ordenados pelo `BookingResourceLockService`.
- `reject_series`: pré-valida todos os candidatos dentro da operação e faz rollback total em qualquer conflito.
- `skip_conflicts`: cria os válidos e registra `conflict_skipped` para os demais.
- Geração repetida retorna ocorrências existentes como idempotentes.
- Holds nunca são gerados por série.

## Alterações e cancelamentos

- Uma ocorrência: revalida a reserva, marca exceção e `detached_from_series`, preservando a série.
- Esta e futuras: encerra a definição anterior antes da data de corte, cria série sucessora, cancela logicamente apenas futuras modificáveis e registra split.
- Série inteira: atualiza por compare-and-swap, preserva histórico e regenera apenas futuras modificáveis.
- Cancelamentos: ocorrência, esta e futuras ou série inteira, sempre pelo ciclo de vida existente.
- `in_progress`, `completed` e `no_show` não são reescritas; canceladas permanecem históricas.
- Nenhum fluxo faz exclusão física.

## HTTP, UI e segurança

- Controllers finos sob `Gd_Controller`; todas as escritas são POST no grupo CSRF.
- Unidade vem somente do contexto backend e toda referência é revalidada na mesma unidade.
- Whitelists explícitas impedem mass assignment.
- Permissões: `gd_booking_series_view`, `gd_booking_series_manage` e `gd_booking_series_status_manage`, com apenas implicações mínimas de leitura.
- Menu, lista, detalhe, modal, preview, geração, ciclo de vida e escopos de alteração/cancelamento.
- Calendário mantém as ocorrências como reservas e expõe apenas um indicador de série; usuários sem leitura de reservas continuam vendo somente “Ocupado”.

## Verificação

- Testes de recorrência, término, horizonte, preview, idempotência, DST, overnight, recursos, buffers, políticas, destaques, split, cancelamentos, imutabilidade histórica, lock version, unidade, IDOR e privacidade.
- Harness concorrente PowerShell/Bash chama tarefas do CLI existente e comprova uma geração efetiva, outra idempotente e zero duplicidades.
- `verify-fast` durante o desenvolvimento e `verify-full` antes do bump.
- Resultado esperado após homologação: versão 0.6.0, schema/marker 029 e 29 tabelas.

