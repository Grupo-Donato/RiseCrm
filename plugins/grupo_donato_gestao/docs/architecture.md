# Arquitetura — grupo_donato_gestao

> **Estado em 18/06/2026:** o núcleo administrativo da Fase 1, o cadastro central da Fase 2A e Catálogo, Recursos e Pricing da Fase 2B estão implementados.
> Agenda/reservas e os demais módulos, eventos, jobs, integrações e tabelas indicados como futuros não existem no código/banco. A implementação real usa `Config`, `Controllers`,
> `Models`, `Services`, `Database/Schema`, `Views`, `Language` e `Tests`; não há ainda
> `Repositories`, `Integrations`, `Jobs`, EventBus, Money ou DTOs.

## 1. Princípios

1. **Modular, não monolítico.** Um controller por agregado (vs. o `Bombeiros.php` de 5k
   linhas do sistema legado). Lógica de negócio em **Services**; acesso a dados em **Models**
   (estendendo `Crud_model`) com Repositories só quando a query for complexa o bastante.
2. **Integrar o Rise, não duplicá-lo.** Reusar `clients`, `users`, `items`, `invoices`,
   `orders`, `expenses`, `settings`, `custom_fields` onde houver ganho; criar tabelas
   próprias (`gd_*`) para o que o núcleo não cobre.
3. **Imutabilidade financeira.** Pagamento ≠ cobrança ≠ recibo. Lançamentos financeiros
   nunca são editados in-place: corrige-se por estorno/contralançamento.
4. **Multiunidade e multi-área desde o núcleo.** Toda entidade operacional carrega
   `unidade_id` e (quando faz sentido) `centro_resultado_id`.
5. **Auditável por padrão.** Mutações implementadas passam explicitamente pelo `AuditService`;
   hooks globais e eventos de domínio permanecem futuros.
6. **Camadas finas e testáveis.** Controllers orquestram HTTP; Services contêm regras;
   Models isolam SQL.

### Dependências realizadas até a Fase 2B

```text
Clientes/Pessoas
      │
Fundação
      │
Catálogo ── Recursos ── Pricing
      │
Agenda futura
      │
Escola / Quadras / Eventos / Financeiro
```

Catálogo não depende de Clientes/Pessoas para cadastrar produtos. Pricing depende
de catálogo e pode especializar valor por recurso. Recursos são apenas cadastro
físico nesta fase; agenda futura os consumirá. Nenhuma seta para agenda significa
que disponibilidade ou reserva já exista.

## 2. Camadas

```
HTTP (rotas)
  └─ Controllers/            orquestração, validação de request, resposta JSON/HTML
       └─ Services/          regras de negócio, transações, eventos de domínio
            ├─ Models/        CRUD + queries (estende Crud_model do Rise)
            ├─ Repositories/  (opcional) queries de leitura complexas/relatórios
            └─ Integrations/  adaptadores externos (gateways, mensageria, fiscal)
  Support/  DTOs, Enums (status), Validators, Money, Result/Response helpers
  Jobs/     tarefas de cron (recorrência, lembretes, fechamentos)
  Events/   eventos de domínio + listeners (auditoria, estoque, caixa)
```

> Repositories são adotados **apenas** nos módulos de leitura pesada (Relatórios, Agenda,
> Financeiro), seguindo o padrão do projeto (Rise não tem camada de repositório nativa).
> Para CRUD simples, o `Crud_model` já é suficiente — não criar abstração desnecessária.

## 3. Mapa-alvo de módulos (diagrama textual; itens futuros não estão implementados)

```
                         ┌──────────────────────────────┐
                         │  NÚCLEO ADMINISTRATIVO (core) │
                         │ unidades · áreas de negócio · │
                         │ centros de resultado · config │
                         │ sequências · auditoria · files│
                         └──────────────┬───────────────┘
                                        │ (todos dependem do núcleo)
        ┌───────────────────────────────┼───────────────────────────────┐
        │                               │                               │
┌───────▼────────┐            ┌─────────▼─────────┐           ┌─────────▼─────────┐
│ CLIENTES &     │            │   CATÁLOGO        │           │ RECURSOS FÍSICOS  │
│ PESSOAS        │            │ categorias·produtos│           │ & AGENDA          │
│ contas·pessoas │            │ variações·preços  │           │ recursos·disponib.│
│ vínculos·docs  │            │ pacotes           │           │ bloqueios·reservas│
│ consentimentos │            └─────────┬─────────┘           │ séries·ocorrências│
└───┬────────┬───┘                      │                     └───┬──────────┬────┘
    │        │                          │                         │          │
    │        │        ┌─────────────────┴───────────┐             │          │
    │        │        │ OPERAÇÕES (consomem agenda + catálogo)     │          │
    │        │        ├───────────┬───────────┬──────┴────┬────────┴───┐      │
    │        │   ┌────▼───┐  ┌────▼────┐  ┌───▼────┐  ┌───▼────┐  ┌────▼───┐  │
    │        │   │ ESCOLA │  │ PERSONAL│  │ QUADRAS│  │ EVENTOS│  │CAMPEON.│  │
    │        │   │ progr. │  │ sessões │  │ locação│  │ festas │  │ copas  │  │
    │        │   │ turmas │  │         │  │ avulsa │  │ caução │  │ amist. │  │
    │        │   │ matríc.│  └────┬────┘  │ mensal │  │ checkl.│  └───┬────┘  │
    │        │   │ aulas  │       │       └───┬────┘  └───┬────┘      │       │
    │        │   │ presen.│       │           │           │          │       │
    │        │   │ crédit.│       │           │           │          │       │
    │        │   └───┬────┘       │           │           │          │       │
    │        │       └─────┬──────┴─────┬─────┴─────┬─────┴────┬─────┘       │
    │        │             │            │           │          │             │
    │   ┌────▼─────────────▼────────────▼───────────▼──────────▼─────┐       │
    │   │ COMERCIAL: orçamentos · contratos · itens · versões        │       │
    │   └───────────────────────────┬────────────────────────────────┘       │
    │                               │                                         │
    │   ┌───────────────────────────▼─────────────────────────────────┐      │
    │   │ FINANCEIRO                                                   │      │
    │   │ cobranças → pagamentos → (alocações N:N) → recibos          │◄─────┘
    │   │ rateios (centros de resultado) · créditos · contas          │
    │   │ caixa (sessões/movimentos) · despesas · contas a pagar      │
    │   │ cobrança/negociação/promessas                               │
    │   └───────────────┬─────────────────────────┬───────────────────┘
    │                   │                         │
    │            ┌──────▼──────┐          ┌───────▼────────┐
    │            │ BAR & PDV   │          │ ESTOQUE        │
    │            │ comandas    │◄────────►│ locais·movim.  │
    │            │ pedidos     │ baixa    │ fornecedores   │
    │            │ pagamentos  │ estoque  │ compras·invent.│
    │            └─────────────┘          └────────────────┘
    │
    └─► IMPORTAÇÕES (lotes·linhas·correspondências·erros) → alimenta vários módulos
        RELATÓRIOS (lê tudo, não escreve)
        INTEGRAÇÕES (gateways de pagamento, mensageria, fiscal, calendário)
        AUDITORIA (transversal — escuta eventos de todos os módulos)
```

## 4. Catálogo de módulos

| # | Módulo | Controllers (ex.) | Services principais | Tabelas (`gd_…`) |
|---|--------|-------------------|---------------------|------------------|
| 1 | **Núcleo administrativo** | `Unidades`, `Areas_negocio`, `Centros_resultado`, `Config` | `SequenceService`, `ConfigService` | `unidades`, `areas_negocio`, `centros_resultado`, `config`, `sequencias`, `auditoria`, `arquivos` |
| 2 | **Clientes & Pessoas** | `Contas`, `Pessoas` | `PessoaService`, `ConsentService` | `contas`, `pessoas`, `conta_pessoa`, `enderecos`, `contatos`, `documentos`, `consentimentos` |
| 3 | **Catálogo e pricing — implementado** | `Product_categories`, `Products`, `Product_variants`, `Price_lists`, `Prices` | `ProductCategoryService`, `ProductService`, `ProductVariantService`, `PriceListService`, `PricingService` | `product_categories`, `products`, `product_variants`, `price_lists`, `prices` |
| 4 | **Recursos implementados; agenda futura** | `Resources`; futuros `Agenda`, `Reservas`, `Bloqueios` | `ResourceService`; futuros `AgendaService`, `RecorrenciaService`, `ConflitoService` | `resources`; futuras `disponibilidades`, `bloqueios`, `reserva_series`, `reservas`, `reserva_recursos`, `reserva_historico` |
| 5 | **Escola** | `Programas`, `Turmas`, `Matriculas`, `Aulas`, `Chamada` | `MatriculaService`, `ChamadaService`, `CreditoAulaService` | `programas`, `turmas`, `horarios`, `profissionais`, `matriculas`, `matricula_historico`, `aulas`, `presencas`, `creditos_pacote`, `creditos_movimentos` |
| 6 | **Personal** | `Personal` | `PersonalService` | reusa agenda (`reservas`) + `personal_sessoes` |
| 7 | **Quadras** | `Locacoes` | `LocacaoService` | reusa agenda (`reservas`/`recursos`) + `locacao_contratos` |
| 8 | **Comercial/Contratos** | `Orcamentos`, `Contratos` | `ContratoService`, `OrcamentoService` | `orcamentos`, `orcamento_itens`, `contratos`, `contrato_itens`, `contrato_versoes`, `contrato_eventos` |
| 9 | **Cobranças** | `Cobrancas` | `CobrancaService`, `RecorrenciaCobrancaService` | `cobrancas`, `cobranca_itens` |
| 10 | **Pagamentos** | `Pagamentos` | `PagamentoService`, `AlocacaoService` | `pagamentos`, `pagamento_alocacoes`, `recibos` |
| 11 | **Rateios** | `Rateios` | `RateioService` | `rateios`, `rateio_linhas` |
| 12 | **Caixa** | `Caixa` | `CaixaService` | `contas_financeiras`, `caixas`, `caixa_sessoes`, `caixa_movimentos` |
| 13 | **Despesas** | `Despesas`, `Contas_pagar` | `DespesaService` | `despesas`, `contas_pagar` |
| 14 | **Estoque** | `Estoque`, `Compras`, `Inventarios` | `EstoqueService` | `locais_estoque`, `estoque_movimentos`, `fornecedores`, `compras`, `compra_itens`, `inventarios`, `inventario_itens` |
| 15 | **Bar & PDV** | `Comandas`, `Pdv` | `ComandaService`, `PdvService` | `comandas`, `pedidos`, `pedido_itens`, `pedido_pagamentos` |
| 16 | **Eventos** | `Eventos` | `EventoService` | `eventos`, `evento_checklist`, `evento_adicionais`, `evento_vistorias`, `evento_caucoes` |
| 17 | **Campeonatos** | `Competicoes` | `CompeticaoService` | `competicoes`, `competicao_equipes`, `competicao_participantes`, `competicao_custos` |
| 18 | **Importações** | `Importacoes` | `ImportService`, `MatchService` | `import_lotes`, `import_linhas`, `import_correspondencias`, `import_erros` |
| 19 | **Relatórios** | `Relatorios` | `RelatorioService` (read-only) | — (lê) |
| 20 | **Auditoria** | `Auditoria` | `AuditService` (listener) | `auditoria` (núcleo) |
| 21 | **Integrações** | `Integracoes` | adaptadores | `integracao_config`, `integracao_log` |

## 5. Componentes transversais (Support)

- **Enums de status** (PHP enums/const) por agregado — evita strings mágicas do sistema legado.
- **Money** — wrapper para `decimal(15,2)` + helpers `to_currency`/`unformat_currency`.
- **DTOs** de entrada/saída por Service (sem arrays associativos soltos).
- **Result/Response** — formato único de resposta AJAX (`{success, message, data}`).
- **DomainEvent + EventBus** — `CobrancaPaga`, `ReservaConfirmada`, `PedidoFechado`,
  `EstoqueBaixado`, etc. Listeners alimentam Auditoria, Caixa e Estoque.

## 6. Eventos, Jobs e Commands

- **Events:** `ReservaConfirmada`, `CobrancaGerada`, `PagamentoRegistrado`,
  `PagamentoEstornado`, `CaixaFechado`, `PedidoFechado`, `EstoqueMovimentado`,
  `MatriculaCriada`, `MatriculaCancelada`, `EventoAgendado`.
- **Jobs (cron, via `app_hook_after_cron_run`):** geração de cobranças recorrentes;
  materialização de ocorrências de reservas recorrentes (janela deslizante);
  lembretes de vencimento/aula; expiração de bloqueios/reservas não confirmadas;
  marcação de inadimplência; alerta de estoque mínimo.
- **Commands (CLI `spark`):** `gd:install`, `gd:seed-demo`, `gd:reconcile-financeiro`,
  `gd:rebuild-agenda`, `gd:recalc-saldos`.

## 7. Dependências entre módulos

```
core            → (nenhuma)            [fundação]
clientes        → core
catalogo        → core
recursos        → core
pricing         → core, catalogo, recursos(opcional)
agenda (futura) → core, clientes, recursos
escola          → core, clientes, catalogo, agenda
personal        → core, clientes, agenda
quadras         → core, clientes, agenda, catalogo
comercial       → core, clientes, catalogo
cobrancas       → core, clientes, catalogo, comercial, (escola|quadras|eventos)
pagamentos      → core, clientes, cobrancas, caixa
rateios         → core, centros_resultado, cobrancas|pagamentos
caixa           → core, contas_financeiras
despesas        → core, catalogo(?), centros_resultado
estoque         → core, catalogo, fornecedores
bar/pdv         → core, clientes, catalogo, estoque, caixa, agenda(quadra/evento)
eventos         → core, clientes, agenda, comercial, pagamentos
campeonatos     → core, clientes, agenda, financeiro
importacoes     → core, + módulo alvo
relatorios      → lê todos
auditoria       → transversal (escuta todos)
integracoes     → pagamentos, clientes (conforme adaptador)
```

A ordem de implementação derivada dessas dependências está em
[implementation-roadmap.md](implementation-roadmap.md).

## 8. Como a arquitetura corrige o sistema legado

| Anti-padrão sistema legado | Decisão arquitetural |
|---------------------|----------------------|
| Controller monolítico | 1 controller por agregado + Services |
| Pagamento dentro da cobrança | `cobrancas` + `pagamentos` + `pagamento_alocacoes` (N:N) |
| Presença por aluno+data | presença referencia `aula_id` (ocorrência) |
| turma/horário texto no aluno | `programas`/`turmas`/`horarios`/`matriculas` |
| Responsável 1:1 | `conta_pessoa` N:N com papéis |
| SQL embutido / strings mágicas | Models + Repositories + Enums + DTOs |
| Sem auditoria | módulo Auditoria + eventos de domínio |
| Valores hard-coded | `config`/`settings` + seeds |

## Arquitetura realizada na Fase 3A

As versões 019–021 adicionam regras semanais, exceções e bloqueios. `TemporalService` centraliza UTC/timezone/DST; `AvailabilityService` aplica precedência e lote sem N+1; `CalendarService` projeta dados para o FullCalendar nativo. Controllers e Models permanecem separados por agregado temporal, com Services responsáveis por lock, transação, validação e auditoria. Reservas e recorrência continuam fora da arquitetura executável e pertencem à Fase 3B.

## Arquitetura realizada na Fase 3B1

V022–V024 adicionam reserva única, recursos/ocupações e eventos append-only. `BookingService` orquestra validação, `AvailabilityService`, conflito, número, locks e lock version; `BookingLifecycleService` controla estados; `BookingHoldService` expira lotes; `BookingConflictService` consulta ocupações em lote. Controllers continuam finos. Recorrência e financeiro permanecem fora da arquitetura executável.

## Arquitetura realizada na Fase 3B2

V025–V029 adicionam séries, recursos padrão, vínculo idempotente em bookings, exceções, eventos e runs de geração. `RecurrenceGeneratorService` calcula datas civis; `BookingSeriesOccurrenceService` materializa pelo `BookingService`; serviços de lifecycle e split preservam histórico e escopos. Locks de série antecedem locks ordenados de recurso. Financeiro permanece fora da arquitetura executável.
