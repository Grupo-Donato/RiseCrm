# Roadmap resumido

## Dependência principal

```text
Fundação
  -> Cadastro central
  -> Catálogo e recursos
  -> Disponibilidade
  -> Reservas únicas
  -> Recorrência
  -> Operações comerciais
  -> Financeiro/estoque/PDV
  -> Relatórios e integrações
```

## Concluído

| Fase | Definição objetiva |
|---|---|
| 0 | Auditoria do legado, arquitetura, modelo e decisões iniciais. |
| 1 | Plugin, unidade, áreas, settings, sequências, auditoria, permissões e schema runner. |
| 2A | Contas, pessoas, relações N:N, contatos, endereços e duplicidade assistiva. |
| 2B | Categorias, produtos, variações, recursos, tabelas de preço, preços e resolução. |
| 3A | Disponibilidade semanal, exceções, bloqueios, motor em lote e calendário. |
| 3B1 | Reservas únicas, múltiplos recursos, buffers, holds, conflitos, estados e histórico. |
| 3B2 | Séries diárias/semanais/mensais, ocorrências materializadas, exceções e alterações por escopo. |
| 3C | Operação comercial de locação de quadras: avulso, mensalista, negociação, snapshot de valor contratado, dia de vencimento, vigência, estados e visão de mensalistas. Sem financeiro. |
| 4 | Protótipo funcional de escola e personal: alunos, famílias, responsáveis, turmas, agenda integrada, matrículas, chamada e frequência. Sem financeiro. |
| 5 | Financeiro básico: cobranças, pagamentos/alocações, estornos, despesas, caixa e indicadores integrados. |

## Operações futuras

| Fase | Depende de | Definição objetiva |
|---|---|---|
| 6 — Evolução financeira | 5 | Conciliação, regras avançadas e integrações somente quando autorizadas. |
| 7 — Caixa, PDV e bar | 6 + estoque | Sessões de caixa, comandas, pedidos e pagamentos operacionais. |
| 8 — Estoque | catálogo | Locais, movimentos, fornecedores, compras e inventários. |
| 9 — Comercial e eventos | agenda + financeiro | Orçamentos, contratos versionados, eventos, vistorias e caução. |
| 10 — Campeonatos | agenda + financeiro | Competições, equipes, participantes, custos e inscrições. |
| 11 — Despesas e rateios | financeiro | Despesas, contas a pagar e rateio por centro de resultado. |
| 12 — Cobrança e negociação | financeiro | Inadimplência, negociações, promessas e automações controladas. |
| 13 — Importações | módulos-alvo estáveis | Preview, correspondências, confirmação e relatório de erros. |
| 14 — Relatórios | dados operacionais/financeiros | Indicadores e DRE por unidade/área/centro. |
| 15 — Integrações | contratos definidos | Gateways, mensageria, fiscal e calendários por adaptadores isolados. |

Nenhuma fase futura deve ser antecipada por tabelas vazias ou comportamento implícito.
