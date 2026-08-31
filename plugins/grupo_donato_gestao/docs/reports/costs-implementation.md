# Relatório de implementação — Custos

Data da validação: 31/08/2026.

## Entregue

- Schema versionado V054–V062, com seis tabelas novas e extensão aditiva de `gd_expenses`.
- Seed global idempotente de categorias e migração de despesas antigas pagas para `gd_expense_payments` com status `legacy_migrated`.
- Serviços separados para custos, pagamentos/estornos, recorrências, orçamentos e anexos.
- Tela única `Custos`, rotas canônicas e aliases para `/finance/expenses`.
- Integração dos cards/tendências dos dashboards ao ledger de saídas pagas.
- Controle por unidade, permissões específicas, CSRF nas escritas, auditoria, lock_version e soft delete.

## Validação executada

```text
cli.php install        OK — schema alvo 062, 59 tabelas gd_*
cli.php costs-selftest OK — 60 PASS / 0 FAIL
cli.php selftest       543 PASS / 5 FAIL
```

As cinco falhas restantes pertencem a verificações antigas fora do módulo de Custos: uma regra de disponibilidade, uma expectativa de saldo acumulado do financeiro legado, duas expectativas de autorização baseada em cargo e um teste de caminho da foto de aluno. O selftest direcionado de Custos passa integralmente.

Não há `spark` neste checkout; o harness suportado é `Tests/cli.php`.
