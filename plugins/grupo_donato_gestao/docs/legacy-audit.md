# Auditoria do Plugin sistema legado Gerencial

> **READ-ONLY.** Nenhum arquivo do sistema legado foi alterado. Este documento mapeia o plugin
> `plugins/módulo legado (removido)` como **referência de integração** e como
> **catálogo de anti-padrões** a evitar.

## 1. Estrutura física

```
módulo legado (removido)/
├── index.php                       (~36 KB — hooks, menu, install/SQL)
├── Config/Routes.php               (~100 rotas, 1 grupo)
├── Controllers/Bombeiros.php       (~5.116 linhas — controller MONOLÍTICO)
├── Models/  (10 models Bombeiros_*)
├── Views/   (24 views: listas, modais, comprovante, importação, público)
├── Database/comprovantes_table.sql
└── Language/portuguese/default_lang.php
```

Prefixo interno de classes/models: `Bombeiros_*`; prefixo de tabelas: `grupo_donato_*`.
**Ambos proibidos de reuso** (restrição #2).

## 2. Hooks e instalação (`index.php`)

- `app_filter_staff_left_menu` → injeta 13 itens (dashboard, alunos, cancelados,
  concluídos, responsáveis, presença, pagamentos, inadimplência, custos, materiais,
  captação, mensagens, unidades). Posição inicial fixa `$position = 3` (hard-coded).
- `app_filter_app_csrf_exclude_uris` → isenta `matricula-online.*` e
  `salvar_matricula_publica.*` (matrícula pública sem CSRF).
- Schema criado por `bombeiros_install_or_update()` com SQL idempotente
  (`tableExists` + `CREATE TABLE IF NOT EXISTS` + `SHOW COLUMNS`/`ALTER TABLE`).
- Registro: `register_installation_hook("plugin_legado", "bombeiros_install_or_update")`.

## 3. Tabelas criadas (9)

| Tabela | Finalidade | Observação crítica |
|--------|-----------|--------------------|
| `grupo_donato_unidades` | unidades/filiais | base de multiunidade |
| `grupo_donato_responsaveis` | responsável financeiro | `whats` como chave de deduplicação |
| `grupo_donato_alunos` | aluno | **fortemente denormalizada** (ver §5) |
| `grupo_donato_cobrancas` | cobrança/mensalidade | **mistura cobrança e pagamento** |
| `grupo_donato_custos_unidade` | custos da unidade | categoria como texto |
| `grupo_donato_presenca` | presença | **`UNIQUE(aluno_id, data_aula)`** |
| `grupo_donato_comprovantes` | comprovantes/recibos | dados do aluno/responsável **copiados** (snapshot manual) |
| `grupo_donato_person_unit_access` | acesso por unidade | 5 papéis + 12 bits `can_*` |
| `grupo_donato_leads_palestra` | captação/leads | normalização de telefone em SQL |

### Relacionamentos
`alunos → responsaveis (responsavel_id)`, `alunos → unidades (unidade_id)`,
`cobrancas → alunos/responsaveis/unidades`, `presenca → alunos (UNIQUE aluno+data)`,
`comprovantes → alunos/responsaveis/cobrancas`, `person_unit_access → unidades`.

## 4. Controller (`Bombeiros.php`) — 75 métodos públicos

Único controller para **tudo**: CRUD de aluno/responsável/unidade/custo/lead, presença,
pagamentos, geração de mensalidades, importação CSV/JSON, comprovantes (PDF), captação,
mensagens (IARA) e dashboard.

Métodos mais longos (sinal de lógica de negócio no controller):

| Método | Linhas | Problema |
|--------|-------:|----------|
| `save_aluno` | ~222 | validação + upsert de responsável + matrícula + transação + cancelamento, tudo inline |
| `importar_csv` | ~122 | parsing + matching + criação de aluno/cobranças em laço |
| `gerar_comprovante` | ~89 | render de template + persistência + PDF |
| `toggle_pagamento_mensal` | ~70 | alterna status de pagamento com regras embutidas |
| `criar_cobranca_mensal_aluno` | ~65 | geração de cobrança |
| `converter_lead_em_aluno` | ~64 | conversão de lead |

Há **60+ métodos privados auxiliares** (`_gerar_cobrancas_matricula`,
`_normalizar_status_aluno`, `_normalizar_payload_importacao`, `_usuario_tem_acesso_unidade`,
`_active_unit_id`, etc.) — lógica de negócio acoplada à camada HTTP. Respostas JSON são
montadas à mão, sem formato unificado. Transações são abertas manualmente
(`db_connect()->transStart()`) em vários pontos.

## 5. Limitações estruturais (NÃO copiar)

1. **`turma`/`horario`/`pelotao` como texto no aluno** — impede múltiplas turmas por
   aluno, preço por turma, validação e relatórios confiáveis. → exige tabelas
   `programas`/`turmas`/`horarios`/`matriculas`.
2. **Pagamento na mesma tabela da cobrança** — `grupo_donato_cobrancas` guarda `status='Pago'`,
   `data_pagamento`, `forma_pagamento` no próprio registro da cobrança. Sem histórico de
   pagamentos, sem 1 pagamento → N cobranças, sem N pagamentos → 1 cobrança, sem trilha
   de auditoria financeira. → exige `cobrancas` + `pagamentos` + `pagamento_alocacoes`.
3. **Presença por `aluno + data`** — `UNIQUE(aluno_id, data_aula)` assume uma aula por
   aluno por dia; quebra com 2 atividades no mesmo dia e não amarra a aula/turma. →
   presença deve referenciar **a ocorrência de aula** (`aula_id`).
4. **Responsável 1:1 com aluno** — `responsavel_id NOT NULL` no aluno; não há vínculo
   muitos-para-muitos, papéis (pai/mãe/responsável legal/contato de emergência) nem aluno
   sem responsável (adulto). → exige tabela de vínculo N:N.
5. **Materiais/uniforme espalhados em ~6 colunas no aluno** (`camiseta`, `material_01`,
   `material_02` + `*_status` + `*_data`) — não escala para catálogo dinâmico. → exige
   tabela de entregas referenciando o catálogo.
6. **Comprovante com snapshot manual** (copia nome/CPF do aluno e do responsável) — a
   ideia de snapshot é boa (imutabilidade), mas feita coluna a coluna; → padronizar com
   recibo imutável + payload serializado.
7. **Sem trilha de auditoria** das mutações (exceto campos de cancelamento). → módulo de
   Auditoria dedicado (alimentado pelos hooks `app_hook_data_*`).
8. **Valores e textos hard-coded** específicos do cliente sistema legado: slug
   `sao_bernardo_do_campo`, curso `ACADEMIA DE TREINAMENTO MIRIM`, mensalidade `237.00`,
   inscrição `100.00`, `12` parcelas, posição de menu `3`. **Regras que não devem ser
   copiadas** (restrição: "regras que não devem ser copiadas").
9. **SQL embutido com interpolação** nos models (`WHERE $table.id=$id`, subqueries
   montadas por concatenação, normalização de telefone em SQL) — risco de manutenção.
10. **Matrícula pública sem verificação** (isenta de CSRF, cria responsável por telefone
    sem confirmação) — risco de abuso; precisa de OTP/captcha/rate-limit se reaproveitada.
11. **Unidade ativa só em sessão**, sem revalidação de acesso por request — risco de
    acesso indevido com sessão velha.
12. **Sem paginação server-side** nos `get_details` (carrega tudo; DataTables pagina no
    cliente) — não escala.

## 6. Integração IARA e multiunidade

- `Bombeiros_iara_adapter_model` detecta uma arquitetura externa "IARA"
  (`iara_core.set_current_unit`, views `dbo.*`) e **degrada graciosamente** para as
  tabelas `grupo_donato_*` locais quando indisponível. Bem-intencionado, porém o limite entre
  "IARA obrigatório" e "opcional" é nebuloso. → No novo plugin, integrações externas
  ficam **isoladas** num módulo de Integrações com contrato explícito.
- Multiunidade: `grupo_donato_person_unit_access` (papéis `owner/director/manager/staff/viewer`
  + 12 bits `can_*`) + unidade ativa em sessão + `_usuario_tem_acesso_unidade()`. **Bom
  conceito**, mas a aplicação é inconsistente (alguns deletes não checam permissão).

## 7. Fluxos mapeados (resumo)

- **Matrícula:** form (público ou modal) → `save_aluno` → upsert responsável → cria aluno
  + matrícula + gera inscrição e 12 mensalidades (`_gerar_cobrancas_matricula`).
- **Mensalidade/pagamento:** lista filtrada por mês/ano/turma → `baixar_pagamento`/
  `toggle_pagamento_mensal` altera o **próprio** registro de cobrança.
- **Presença (chamada):** grade de alunos ativos → `salvar_presenca` (UPSERT por
  `aluno+data`).
- **Custos:** CRUD em `grupo_donato_custos_unidade`.
- **Importação:** CSV (`;`) ou JSON aninhado → preview → confirmação em transação →
  relatório de contagens.
- **Comprovante:** busca dados → `gerar_comprovante` → grava recibo + caminho do PDF.
- **Captação:** leads com matching por telefone normalizado → `converter_lead_em_aluno`.
- **Dashboard:** `financeiro_resumo` agrega receita (cobranças pagas) − custos.

## 8. Pontos reaproveitáveis (conceitos, não código)

- Multiunidade com papéis + bits de permissão por unidade.
- Instalação idempotente via SQL no hook (mecanismo correto do Rise).
- Snapshot de dados no comprovante (imutabilidade) — desde que padronizado.
- Preview-antes-de-confirmar na importação.
- Normalização/deduplicação de contatos por telefone (mover para serviço, não SQL).
- Integração externa com degradação graciosa (isolar em módulo de Integrações).

> A lista detalhada de **reaproveitar vs. refazer** está em
> [reuse-vs-rebuild.md](reuse-vs-rebuild.md).
