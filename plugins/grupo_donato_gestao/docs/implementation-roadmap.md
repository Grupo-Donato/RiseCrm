# Roadmap de Implementação

> Estado em 18/06/2026: **Fase 0 concluída; Fase 1 concluída com restrições; Fase 2A
> concluída com restrições; Fases 2B e 3A concluídas com restrições não bloqueadoras.** Reservas da Fase 3B e operações comerciais continuam futuras.

## 1. Visão de fases

| Fase | Nome | Entrega central | Depende de |
|------|------|-----------------|-----------|
| **0 — concluída** | Auditoria & Arquitetura | documentação de referência | — |
| **1 — concluída com restrições** | Fundação | plugin, controller base, schema 001–007, unidades/áreas/centros/settings/sequências/auditoria e permissões | 0 |
| **2A — concluída com restrições** | Cadastro central | contas/pessoas/relações/contatos/endereços/duplicidade | 1 |
| **2B — concluída com restrições não bloqueadoras** | Catálogo, recursos e pricing | categorias, produtos/serviços, variações, recursos físicos, listas, preços e resolução | 2A |
| **3 — não iniciada** | Agenda & motor de reservas | disponibilidade, bloqueios, reservas, séries (recorrência) e conflito sobre recursos existentes | 1, 2B |
| **4** | Escola | programas/turmas/horários/matrículas/aulas/presenças/créditos | 2, 3 |
| **5** | Quadras & Personal | locação avulsa/mensal, sessões de personal (sobre agenda) | 2, 3 |
| **6** | Financeiro Núcleo | cobranças, pagamentos, alocações, recibos, contas financeiras | 2, 4, 5 |
| **7** | Caixa & PDV & Bar | sessões de caixa, comandas, pedidos, baixa de estoque | 6, 8 |
| **8** | Estoque | locais, movimentos, fornecedores, compras, inventários | 2 |
| **9** | Comercial & Eventos | orçamentos, contratos, versões; eventos/caução/vistoria | 3, 6 |
| **10** | Campeonatos | competições, equipes, participantes, custos | 3, 6 |
| **11** | Despesas & Rateios | despesas, contas a pagar, rateios por centro de resultado | 1, 6 |
| **12** | Cobrança & Negociação | inadimplência, negociações, promessas, recorrência | 6 |
| **13** | Importações | lotes/linhas/correspondências/erros (preview → confirmar) | 2, 4, 6 |
| **14** | Relatórios & Dashboard | painel, DRE por centro de resultado, indicadores | 6, 11 |
| **15** | Integrações | gateways de pagamento, mensageria, fiscal/PDF, calendário | 6 |

## 2. Grafo de dependências entre fases

```
0 → 1 ┬→ 2 ┬→ 4 ┬→ 6 ┬→ 7
      │    │   │    │   └→ (precisa de 8)
      ├→ 3 ┘   │    ├→ 9
      │        │    ├→ 10
      │        │    ├→ 11 → 14
      │        │    ├→ 12
      │        │    └→ 15
      ├→ 8 ────┘
      └→ (permissões em 1)
              2 + 4 + 6 → 13
```

Caminho crítico atualizado: **0 → 1 → 2A → 2B → 3**. Estoque deve preceder Bar/PDV.

## 3. Critérios de aceite por fase

### Fase 1 — Fundação
- Entregue: instalação/ativação/desativação/atualização idempotente e menu por permissão.
- Entregue: `Gd_Controller`, autorização backend e unidade ativa revalidada.
- Entregue: sete tabelas de fundação, schema 001–007 e seeds idempotentes.
- Entregue: sequência atômica por (unidade, tipo), reset anual, prefixo e padding.
- Entregue: auditoria explícita dos CRUDs, mascarada e append-only.
- Restrições: sem teste destrutivo de falha DDL em banco isolado e sem automação de console JS.

### Fase 2A — Cadastro central
- Entregue: conta com **N pessoas**, pessoa em várias contas e múltiplos papéis.
- Entregue: contatos/endereço principal transacionais, pesquisa e duplicidade assistiva.
- Entregue: escopo de unidade backend, auditoria mascarada e soft delete.
- Restrições: mesmas limitações de banco isolado/browser/Git descritas no relatório da fase.

### Fase 2B — Catálogo, recursos e pricing
- Entregue: categorias, produtos/serviços, variações, recursos, listas e preços por vigência/quantidade.
- Entregue: resolução determinística por lista, escopo, quantidade e data; Q2–Q6 e `DEFAULT` idempotentes.
- Restrições: sem automação do console do navegador e sem falha DDL induzida em banco isolado.
- Pacotes, documentos/consentimentos, estoque, agenda, venda e financeiro não foram incorporados ao escopo.

### Fase 3 — Agenda & Recursos
- Reutilizar `gd_resources`; não recriar o cadastro físico entregue na Fase 2B.
- Criar reserva única e **série recorrente**; ocorrências materializadas por job.
- **Detecção de conflito** impede dupla ocupação do mesmo recurso/intervalo.
- Bloqueios (manutenção/feriado) impedem reserva sobreposta.
- Reserva pode ocupar **vários recursos** (`reserva_recursos`).

### Fase 4 — Escola
- Turma vinculada a quadra **gera reservas/bloqueios** na agenda (escola bloqueia quadra).
- Participante em **várias turmas**; sem turma/horário como texto.
- **Presença por aula** (`aula_id`+`matricula_id`); participante pode ter 2 aulas no dia.
- Créditos de aula: saldo derivado de movimentos, nunca negativo.

### Fase 5 — Quadras & Personal
- Locação avulsa cria reserva + cobrança avulsa; mensal cria série + cobrança recorrente.
- Sessão de personal usa agenda e respeita conflito de recurso/profissional.

### Fase 6 — Financeiro Núcleo
- Cobrança **imutável** após emissão; valor só muda via cancelamento/contralançamento.
- **1 pagamento quita N cobranças** e **1 cobrança recebe N pagamentos** (alocações).
- `valor_pago` da cobrança = Σ alocações (com tolerância de arredondamento).
- Pagamento **nunca é deletado**; estorno gera lançamento negativo vinculado.
- Recibo imutável com snapshot e numeração por sequência.
- Toda mutação financeira auditada (antes/depois).

### Fase 7 — Caixa & PDV & Bar
- Pagamento só registra em **sessão de caixa aberta**; 1 sessão aberta por caixa.
- Fechamento concilia (apurado × esperado), calcula diferença, é auditado.
- Comanda vinculável a **quadra ou evento**; PDV baixa estoque do **local** correto.

### Fase 8 — Estoque
- Saldo por **(local, produto, variação)** derivado de movimentos.
- Compra recebida gera entrada; inventário fechado gera ajuste pela diferença.

### Fase 9 — Comercial & Eventos
- Orçamento → contrato com **versionamento imutável** (aditivo cria versão).
- Evento gera reservas de **montagem e limpeza** (bloqueia o espaço além do horário).
- Caução controlada (retido/devolvido/executado); vistorias de entrada/saída.

### Fase 10 — Campeonatos
- Competição agenda quadras/datas e gera cobranças de inscrição por equipe/participante.

### Fase 11 — Despesas & Rateios
- Despesa por centro de resultado; **rateio** distribui receita/custo (Σ% = 100, Σvalor = total).
- **Receita por centro de resultado** disponível para relatório.

### Fase 12 — Cobrança & Negociação
- Inadimplência marcada por job; negociação com promessas (aberta/cumprida/quebrada).

### Fase 13 — Importações
- Fluxo **preview → confirmar** em transação; lote com contagem ok/erro e relatório.
- Correspondência (de/para) e log de erro por linha.

### Fase 14 — Relatórios & Dashboard
- DRE por centro de resultado/área/unidade; indicadores de escola, agenda, caixa, bar.
- Todas as listas com **paginação server-side**.

### Fase 15 — Integrações
- Adaptadores isolados, com contrato explícito e degradação graciosa; sem acoplar domínio.

## 4. Critérios de aceite transversais (todas as fases)

- Nenhum controller > ~300 linhas; lógica em Services; SQL só em Models/Repositories.
- Sem strings de status mágicas (Enums); sem valores do cliente hard-coded (config/seed).
- Operações multiunidade respeitam escopo (row-level) e são auditadas quando sensíveis.
- Testes de aceite cobrindo os fluxos-chave da fase antes do "done".

## Estado após a Fase 3A

A fundação de disponibilidade foi realizada: regras semanais, exceções, bloqueios, motor de consulta e calendário-base. A antiga “Fase 3 — agenda e reservas” fica dividida:

- **3A realizada:** disponibilidade e calendário sem ocupações.
- **3B1 realizada:** reservas únicas, múltiplos recursos, buffers, holds, conflitos, estados, histórico e calendário.
- **3B2 realizada:** séries simples diárias/semanais/mensais, ocorrências materializadas, exceções e alterações por escopo.
- **3C futura:** locação comercial, contratos, preços, cobranças e check-in/out comercial.
