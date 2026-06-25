# Relatório — Fase 3B2

## Estado

- Status: concluída com restrições ambientais não bloqueadoras.
- Versão: 0.6.0.
- Schema/marker: 029.
- Tabelas: 29.
- Self-test: 328 PASS / 0 FAIL.

## Entrega

Séries diárias, semanais e mensais simples; preview; geração idempotente; políticas de conflito; múltiplos recursos/buffers; alterações e cancelamentos por escopo; ciclo de vida; histórico; interface, permissões e calendário privado.

Ocorrências permanecem reservas normais. Não existem holds recorrentes, override automático, exclusão física de domínio ou efeito financeiro.

## Homologação

- `verify-fast`: PASS.
- `verify-full`: PASS.
- Concorrência de série: 1 geração efetiva, 1 idempotente, 0 duplicidades.
- Install/idempotência e uninstall 29/29: PASS.
- Sequência, temporal e booking concorrente: PASS.
- sistema legado, core Rise e migrations 001–024: preservados.
- `CHECK TABLE` e logs novos relevantes: PASS.

## Restrições

Sem smoke autenticado automatizado e sem falha DDL induzida em clone. A raiz não possui Git e usa manifests/hashes. RRULE arbitrária não faz parte da recorrência simples entregue.

Contratos, mensalistas comerciais, preços aplicados, cobranças, financeiro e Fase 3C não foram iniciados.
