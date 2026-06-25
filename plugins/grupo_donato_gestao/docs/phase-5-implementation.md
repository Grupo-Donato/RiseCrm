# Fase 5 — implementação

A fase adiciona um financeiro básico operacional sem integração bancária. O desenho separa cobrança, item snapshot, pagamento, alocação e movimento de caixa para suportar pagamentos parciais, múltiplos e estornos sem apagar histórico.

## Schema

- 039: contas financeiras.
- 040: contas a receber.
- 041: itens de cobrança.
- 042: pagamentos e alocações.
- 043: despesas.
- 044: movimentos de caixa append-only.
- 045: índices financeiros complementares.

## Invariantes

- Números vêm de `SequenceService`.
- Uma origem/referência não recebe cobrança duplicada.
- Itens preservam quantidade, valor unitário e total da geração.
- Alocações não superam pagamento nem saldo.
- Saldos são recalculados dentro da transação.
- Estornos criam movimento inverso e preservam registros.
- Unidade e IDs são validados no backend; escritas usam POST/CSRF.
- Nenhum fluxo financeiro executa exclusão física.

Detalhes operacionais: [finance.md](finance.md).
