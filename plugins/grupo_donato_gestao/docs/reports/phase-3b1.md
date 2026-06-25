# Relatório consolidado — Fase 3B1

Status: **CONCLUÍDA COM RESTRIÇÕES**.

- Versão final: 0.5.0.
- Schema/marker: 024.
- Tabelas: 24.
- Self-test: 288 PASS / 0 FAIL.
- Concorrências anteriores e de reservas: aprovadas.
- CHECK TABLE: 24/24 OK.
- Uninstall: 24/24 preservadas.
- sistema legado, core e migrations 001–021: inalterados.
- Reservas, eventos e dados técnicos residuais: zero.
- Fora do escopo preservado: recorrência, contratos e financeiro.

Restrição: smoke autenticado em navegador não foi repetido por ausência de sessão staff reutilizável e automação; bloqueio anônimo/CSRF, rotas, serviços e permissões foram validados pelos demais harnesses.

O relatório detalhado e as evidências permanecem em [`../phase-3b1-implementation.md`](../phase-3b1-implementation.md). Os conceitos permanentes estão em [`../bookings.md`](../bookings.md), [`../booking-conflicts.md`](../booking-conflicts.md), [`../booking-lifecycle.md`](../booking-lifecycle.md) e [`../booking-holds.md`](../booking-holds.md).

