# Modelo de Domínio

Visão conceitual das entidades e seus relacionamentos. O detalhamento físico (campos,
tipos, índices) está em [database-plan.md](database-plan.md).

> Estado realizado na Fase 2A: `customer_account 1—N account_people N—1 person`,
> `person 1—N contact_methods` e `customer_account 1—N addresses`. Documentos e
> consentimentos de pessoas continuam futuros; contatos não pertencem diretamente à conta
> no modelo de indivíduos. Conta e pessoa sempre pertencem à mesma unidade da relação.

> Estado realizado na Fase 2B: `ProductCategory`, `Product`, `ProductVariant`,
> `Resource`, `PriceList` e `Price`. Não existem estoque, saldo, agenda, reserva,
> venda ou cobrança. Nomes físicos e regras do catálogo estão em 013–018; disponibilidade em 019–021.

```text
ProductCategory 1 ── N Product
Product         1 ── N ProductVariant
PriceList       1 ── N Price
Product         1 ── N Price
ProductVariant  0..1 ── N Price
Resource        0..1 ── N Price
BusinessArea    0..1 ── N Product / Resource
CostCenter      0..1 ── N Product / Resource
```

Todos pertencem à unidade ativa, exceto área/centro que também podem ser globais.
`Price` sempre referencia produto e lista; variação e recurso são especializações
opcionais do escopo.

## 1. Conceitos centrais

- **Unidade** — filial física do Grupo Donato (a operação é única, mas há separação por
  local quando necessário).
- **Área de negócio** — escola, treinamentos, personal, locação, bar, eventos,
  campeonatos, produtos. Classifica receita/despesa para gestão.
- **Centro de resultado** — dimensão financeira para apuração de resultado (pode
  coincidir com área ou ser mais granular: "Q2", "Bar", "Festas").
- **Conta** — entidade pagadora/contratante (família, empresa, organizador de evento).
- **Pessoa** — indivíduo (responsável, participante, atleta, contato). Uma pessoa pode
  ser participante **e** responsável.
- **Recurso** — cadastro físico potencialmente reservável: quadra (Q2–Q6), espaço,
  equipamento, sala etc. Profissional não é um tipo implementado de recurso.
- **Reserva (ocorrência)** — uso de um recurso num intervalo. Pode pertencer a uma
  **série** (recorrência) e bloquear outros usos.
- **Cobrança** — valor devido (o que se espera receber). **Imutável** após emissão.
- **Pagamento** — valor recebido (o que entrou). **Imutável**; ajustes via estorno.
- **Alocação** — vínculo N:N entre pagamento e cobrança (quanto de cada pagamento quita
  cada cobrança).

## 2. Diagrama de entidades (textual)

Legenda: `1—N` um-para-muitos · `N—N` muitos-para-muitos · `→` referência.

```
NÚCLEO
  unidades 1—N areas_negocio
  unidades 1—N centros_resultado
  unidades 1—N (toda entidade operacional carrega unidade_id)
  sequencias  (numeração por unidade+tipo de documento)
  auditoria   (registra mutações; → usuario, → entidade alvo)
  arquivos    (anexos polimórficos: arquivo → {entidade, id})

CLIENTES & PESSOAS
  contas 1—N enderecos
  contas 1—N contatos
  pessoas 1—N documentos
  pessoas 1—N consentimentos            (LGPD: finalidade, base legal, data)
  contas  N—N pessoas  VIA conta_pessoa (papel: titular, responsável,
                                          participante, dependente, contato_emergência)
        └── habilita: 1 responsável com vários filhos
                      1 pessoa em várias contas / vários papéis

CATÁLOGO
  categorias 1—N produtos
  produtos   1—N variacoes              (tamanho de uniforme, modalidade)
  produtos/variacoes 1—N precos         (preço por tabela/vigência/unidade)
  pacotes    N—N produtos VIA pacote_itens   (ex.: pacote de 8 aulas, combo festa)

RECURSOS & AGENDA
  recursos 1—N disponibilidades         (janelas semanais por recurso)
  recursos 1—N bloqueios                (manutenção, feriado, montagem/limpeza)
  reserva_series 1—N reservas           (recorrência → ocorrências materializadas)
  reservas   N—N recursos VIA reserva_recursos   (1 reserva pode usar vários recursos)
  reservas   1—N reserva_historico      (auditoria de alterações da reserva)
  reservas   → conta/pessoa (cliente), → area_negocio, → origem (escola/quadra/evento)
        └── habilita: reservas recorrentes; escola bloqueando quadras;
                      eventos bloqueando montagem e limpeza

ESCOLA
  programas 1—N turmas                  (programa = "Escola de Futebol Sub-11")
  turmas    1—N horarios                (dias/horários da turma)
  turmas    → profissionais (professor responsável)
  turmas    → recurso (quadra) → gera reservas/bloqueios na agenda
  participante(pessoa) N—N turmas VIA matriculas
  matriculas 1—N matricula_historico    (status: ativa, trancada, concluída, cancelada)
  matriculas 1—N aulas? não:
  turmas    1—N aulas                   (aula = ocorrência de uma turma numa data)
  aulas     1—N presencas               (presença → matricula/participante + aula)
  matriculas → creditos_pacote 1—N creditos_movimentos  (aulas avulsas/pacote)
        └── habilita: 1 participante em várias atividades;
                      presença por AULA (não por aluno+data)

PERSONAL
  personal_sessoes → profissional, → participante(pessoa), → reserva (agenda)

QUADRAS (LOCAÇÃO)
  locacao_contratos → conta, → recurso(quadra), tipo {avulsa, mensal}
  locacao mensal 1—N reservas (série) + 1—N cobrancas (recorrente)

COMERCIAL
  orcamentos 1—N orcamento_itens
  orcamentos 1—1 contratos (conversão)
  contratos  1—N contrato_itens
  contratos  1—N contrato_versoes       (versionamento imutável)
  contratos  1—N contrato_eventos       (assinatura, aditivo, rescisão)
  contratos  → conta, → area_negocio

FINANCEIRO
  cobrancas  1—N cobranca_itens
  cobrancas  → origem polimórfica (matricula | locacao | contrato | evento | pedido | avulso)
  cobrancas  → conta (devedor), → centro_resultado
  pagamentos 1—N pagamento_alocacoes N—1 cobrancas
        └── habilita: 1 pagamento quitando várias cobranças
                      1 cobrança recebendo vários pagamentos (parcial)
  pagamentos → conta_financeira / caixa_sessao
  pagamentos 1—1 recibos                 (comprovante imutável; snapshot serializado)
  rateios    1—N rateio_linhas → centro_resultado   (split de receita/custo)
        └── habilita: receita por centro de resultado
  creditos   (créditos financeiros/carteira da conta) 1—N movimentos
  contas_financeiras 1—N caixas 1—N caixa_sessoes 1—N caixa_movimentos
  despesas / contas_pagar → centro_resultado, → fornecedor
  negociacoes 1—N promessas              (renegociação de inadimplência)

BAR & PDV
  comandas   1—N pedidos                 (comanda → vínculo a quadra/evento/avulso)
  pedidos    1—N pedido_itens → produto/variacao
  pedidos    1—N pedido_pagamentos       (→ pagamento/caixa)
  pedido_itens → estoque_movimentos      (baixa de estoque por local)
        └── habilita: bar vinculado à quadra ou evento

ESTOQUE
  locais_estoque 1—N estoque_movimentos  (saldo por produto POR LOCAL)
  fornecedores 1—N compras 1—N compra_itens → estoque_movimentos (entrada)
  inventarios 1—N inventario_itens → ajuste (estoque_movimentos)
        └── habilita: estoque por local

EVENTOS
  eventos 1—N evento_checklist
  eventos 1—N evento_adicionais          (itens/serviços extras)
  eventos 1—N evento_vistorias           (entrada/saída do espaço)
  eventos 1—N evento_caucoes             (caução/garantia)
  eventos → reservas (espaço + montagem + limpeza), → contrato, → cobrancas

CAMPEONATOS
  competicoes 1—N competicao_equipes 1—N competicao_participantes(pessoa)
  competicoes 1—N competicao_custos
  competicoes → reservas (quadras/datas), → cobrancas (inscrições)

IMPORTAÇÃO
  import_lotes 1—N import_linhas 1—N import_erros
  import_linhas 1—N import_correspondencias → entidade alvo (de/para)

AUDITORIA (transversal)
  auditoria → {entidade, id}, usuario, ação, antes/depois (JSON), timestamp
        └── habilita: auditoria de alterações financeiras
```

## 3. Decisões de modelagem-chave (resumo; detalhe em decisions.md)

| Tema | Decisão |
|------|---------|
| Pessoa × responsável | N:N via `conta_pessoa` com `papel` → 1 responsável, vários filhos |
| Participação | N:N participante×turma via `matriculas` → várias atividades |
| Cobrança × pagamento | separadas + `pagamento_alocacoes` N:N |
| Presença | chave por **aula** (`aula_id` + `matricula_id`), nunca aluno+data |
| Recorrência | `reserva_series` (regra) → `reservas` (ocorrências materializadas) |
| Bloqueio de agenda | `bloqueios` + ocupação por `reservas`; montagem/limpeza = blocos extra |
| Origem da cobrança | referência polimórfica (`origem_tipo`,`origem_id`) |
| Receita por centro | `centro_resultado_id` em cobrança/pagamento + `rateios` para splits |
| Imutabilidade | cobrança/pagamento/recibo não editáveis; ajuste via estorno/versão |

## 4. Verificação dos requisitos do enunciado (VALIDAÇÃO)

| Requisito | Como o modelo atende |
|-----------|----------------------|
| Um responsável com vários filhos | `conta_pessoa` (N:N, papel=responsável/dependente) |
| Um participante em várias atividades | `matriculas` (N:N pessoa×turma) + `reservas` personal |
| Um pagamento quitando várias cobranças | `pagamento_alocacoes` (1 pagamento → N cobranças) |
| Uma cobrança recebendo vários pagamentos | `pagamento_alocacoes` (N pagamentos → 1 cobrança) |
| Reservas recorrentes | `reserva_series` → `reservas` |
| Escola bloqueando quadras | turma → recurso(quadra) → `reservas`/`bloqueios` |
| Eventos bloqueando montagem e limpeza | `eventos` geram `reservas` extra (setup/teardown) |
| Bar vinculado à quadra ou evento | `comandas.origem_tipo` ∈ {quadra, evento, avulso} |
| Estoque por local | saldo derivado de `estoque_movimentos` por `local_id` |
| Receita por centro de resultado | `centro_resultado_id` + `rateios`/`rateio_linhas` |
| Auditoria de alterações financeiras | módulo `auditoria` + eventos de domínio |

## Modelo realizado na Fase 3A

`resource` possui N regras semanais, N exceções e N bloqueios, sempre na mesma unidade. Regra semanal descreve horário civil recorrente; exceção altera abertura em instantes concretos; bloqueio representa impedimento operacional. Todos usam intervalo semiaberto. Não há entidade reserva, cliente da agenda, série ou ocorrência materializada nesta fase.

## Modelo realizado na Fase 3B1

`booking 1—N booking_resources N—1 resource` representa uma única ocupação. A reserva pode apontar opcionalmente para conta e pessoa vinculada. Cada relação calcula buffers e intervalo ocupado; `booking 1—N booking_events` mantém histórico append-only. Não existe `booking_series` nem ocorrência recorrente.
