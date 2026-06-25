# Plano de Banco de Dados

> **Estado:** as Fases 1, 2A, 2B e 3A criaram vinte e uma tabelas. As tabelas não listadas
> nas estruturas físicas realizadas continuam sendo plano futuro e não existem no banco. O DBPrefix
> é obtido do framework; `rise_` aparece aqui apenas como resultado observado.

Implementado: `gd_schema_versions`, `gd_units`, `gd_business_areas`, `gd_cost_centers`,
`gd_settings`, `gd_sequences`, `gd_audit_logs`, `gd_customer_accounts`, `gd_people`,
`gd_account_people`, `gd_contact_methods`, `gd_addresses`, `gd_product_categories`,
`gd_resources`, `gd_products`, `gd_product_variants`, `gd_price_lists`, `gd_prices`,
`gd_resource_availability_rules`, `gd_resource_availability_exceptions` e `gd_resource_blocks`. Nomes físicos no ambiente homologado:
`rise_gd_*`. Todas usam InnoDB e `utf8_general_ci`; JSON da auditoria/settings fica em
`MEDIUMTEXT`. `gd_business_areas` e `gd_settings` possuem `unit_scope_id` gerado para
unicidade quando `unit_id` é nulo. Relações/contatos/endereços usam chaves geradas para
garantir principais ativos. Existe calendário-base de disponibilidade, sem reservas ou
ocupações. Não existe venda, cobrança, pagamento, estoque, outbox ou arquivos.

Os nomes em português abaixo são o modelo conceitual original da Fase 0. Quando uma
entidade for implementada, o schema versionado e o nome real em inglês passam a ser a
fonte da verdade.

## Estrutura física realizada na Fase 2A

| Version | Tabela lógica | Finalidade | Integridade principal |
|---|---|---|---|
| 008 | `gd_customer_accounts` | titular comercial por unidade | índices de tipo/status/nome/documento/Rise |
| 009 | `gd_people` | indivíduo humano por unidade | nome normalizado, nascimento e vínculos Rise indexados |
| 010 | `gd_account_people` | relação N:N e papel | unique da combinação ativa e de principal ativo por conta |
| 011 | `gd_contact_methods` | contatos oficiais da pessoa | unique de principal ativo por pessoa+tipo |
| 012 | `gd_addresses` | endereços da conta | unique de principal ativo por conta |

Não foram adicionadas FKs físicas para evitar acoplamento destrutivo ao host; Services
validam existência, soft delete e unidade, enquanto índices gerados protegem invariantes
concorrentes de principal/duplicidade ativa.

## Estrutura física realizada na Fase 2B

Todas as seis tabelas abaixo têm `id`, `unit_id NOT NULL`, autoria/timestamps e
`deleted`; usam InnoDB, DBPrefix e soft delete. Relações são validadas nos Services
e joins repetem unidade/deleted defensivamente.

### V013 — `gd_product_categories`

- **Finalidade:** hierarquia de categorias.
- **Colunas principais:** `parent_id`, `code`, `name`, `description`, `sort_order`, `status`.
- **Índices:** unidade, pai, status e `UNIQUE(active_code_key)`; a coluna gerada
  normaliza `unit_id+code` somente quando `deleted=0`.
- **Regras:** pai na mesma unidade, sem autorreferência/ciclo; dependências ativas
  bloqueiam soft delete.

### V014 — `gd_resources`

- **Finalidade:** recursos físicos potencialmente reserváveis.
- **Colunas principais:** área/centro opcionais, `code`, `name`, `resource_type`,
  `description`, `capacity`, `is_bookable`, `is_active`, `sort_order`, `metadata`.
- **Índices:** unidade, área, centro, tipo, ativo/ordem e
  `UNIQUE(active_code_key)` por unidade para não excluídos.
- **Regras:** área/centro globais ou da unidade, capacidade não negativa, metadata
  JSON validada em `MEDIUMTEXT`; preço específico ativo bloqueia soft delete.

### V015 — `gd_products`

- **Finalidade:** cadastro unificado de produtos e serviços.
- **Colunas principais:** categoria/área/centro opcionais, `code`, `name`,
  `product_type`, `billing_mode`, `unit_of_measure`, flags, `status`,
  `rise_item_id`, `metadata`.
- **Índices:** unidade, relações, tipo/status, Rise e `UNIQUE(active_code_key)`.
- **Regras:** enums/flags validados; `rise_item_id` precisa existir; variação não
  arquivada ou preço ativo bloqueia soft delete.

### V016 — `gd_product_variants`

- **Finalidade:** variações opcionais de produto, sem quantidade/saldo.
- **Colunas principais:** `product_id`, `code`, `name`, `barcode`, `attributes`,
  `is_default`, `sort_order`, `status`.
- **Índices:** produto, status, barcode, `UNIQUE(active_code_key)` por produto e
  `UNIQUE(default_variant_key)` para uma padrão ativa.
- **Regras:** produto da unidade e com variações habilitadas; attributes JSON em
  `MEDIUMTEXT`; preço ativo bloqueia soft delete.

### V017 — `gd_price_lists`

- **Finalidade:** contexto, moeda e vigência de preços.
- **Colunas principais:** `code`, `name`, `description`, `currency`, `priority`,
  `valid_from`, `valid_until`, `is_default`, `status`.
- **Índices:** unidade, status, prioridade, `UNIQUE(active_code_key)` e
  `UNIQUE(default_list_key)` para uma padrão ativa por unidade.
- **Regras:** moeda de três letras, intervalo válido; preço ativo bloqueia soft delete.

### V018 — `gd_prices`

- **Finalidade:** valor por lista/produto e escopos opcionais de variação/recurso.
- **Colunas principais:** `price_list_id`, `product_id`, `variant_id`, `resource_id`,
  `amount DECIMAL(15,2)`, `reference_cost DECIMAL(15,2)`,
  `minimum_quantity DECIMAL(15,3)`, vigência e status.
- **Índices:** lista/produto/variação/recurso/status/vigência e
  `UNIQUE(active_scope_key)` para escopo+quantidade+início ativos.
- **Regras:** relações na mesma unidade, decimal exato, quantidade positiva,
  sobreposição do mesmo escopo bloqueada sob `GET_LOCK`; soft-deleted não bloqueia
  nova definição.

## Convenções padrão (aplicam-se a TODAS as tabelas)

- **PK:** `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`.
- **Soft delete:** `deleted TINYINT(1) NOT NULL DEFAULT 0` (compatível com `Crud_model`).
- **Auditoria de linha:** `created_at DATETIME`, `updated_at DATETIME`,
  `created_by BIGINT NULL`, `updated_by BIGINT NULL` (→ `rise_users.id`).
- **Multiunidade:** `unidade_id BIGINT NULL` (→ `gd_unidades.id`) em toda entidade
  operacional; índice `idx_unidade`.
- **Dinheiro:** `DECIMAL(15,2)`. **Quantidade:** `DECIMAL(15,3)`. **Percentual:** `DECIMAL(7,4)`.
- **Datas:** `DATE`/`DATETIME`; datetime em **UTC** (exibição convertida — ver decisions.md).
- **Status:** `VARCHAR(30)` validado por Enum PHP (não `ENUM` de banco, p/ evolução).
- **FK:** modeladas logicamente; constraints físicas conforme `migration-strategy.md`
  (`ON DELETE RESTRICT` em financeiro; `RESTRICT`/`SET NULL` conforme regra abaixo).
- **Referência polimórfica:** par (`*_tipo VARCHAR(30)`, `*_id BIGINT`) + índice composto.

Em cada tabela abaixo lista-se apenas os **campos de domínio** (os padrão acima são
implícitos). Formato: **Finalidade · Campos · Chaves/Índices · Relacionamentos ·
Exclusão · Auditoria · Integridade.**

---

## A. NÚCLEO ADMINISTRATIVO

### `gd_unidades`
- **Finalidade:** filiais/locais do complexo.
- **Campos:** `nome VARCHAR(150)`, `slug VARCHAR(80) UNIQUE`, `cidade VARCHAR(120)`,
  `endereco VARCHAR(255)`, `is_default TINYINT(1)`, `timezone VARCHAR(64)`,
  `status VARCHAR(30)` {ativa, inativa}.
- **Índices:** `uniq_slug`, `idx_status`.
- **Relacionamentos:** raiz; referida por quase tudo.
- **Exclusão:** soft delete; bloquear se houver entidades vinculadas ativas.
- **Auditoria:** sim (config sensível).
- **Integridade:** exatamente 1 `is_default=1`.

### `gd_areas_negocio`
- **Finalidade:** classificação operacional (escola, locação, bar, eventos…).
- **Campos:** `unidade_id`, `nome VARCHAR(120)`, `codigo VARCHAR(30)`, `tipo VARCHAR(30)`,
  `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `uniq(unidade_id,codigo)`.
- **Relacionamentos:** referida por reservas, cobranças, contratos, despesas.
- **Exclusão:** soft delete; bloquear com vínculos.
- **Auditoria:** sim. **Integridade:** `codigo` único por unidade.

### `gd_centros_resultado`
- **Finalidade:** dimensão financeira de apuração (receita/custo por centro).
- **Campos:** `unidade_id`, `area_negocio_id NULL`, `nome VARCHAR(120)`,
  `codigo VARCHAR(30)`, `tipo VARCHAR(30)` {receita, custo, misto}, `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `uniq(unidade_id,codigo)`, `idx_area`.
- **Relacionamentos:** `→ gd_areas_negocio`; referido por cobrança/pagamento/despesa/rateio.
- **Exclusão:** soft delete; **RESTRICT** se houver lançamentos.
- **Auditoria:** sim. **Integridade:** imutável após ter lançamentos (apenas inativar).

### `gd_config`
- **Finalidade:** parâmetros do plugin (chave/valor) por escopo.
- **Campos:** `escopo VARCHAR(30)` {global, unidade}, `unidade_id NULL`,
  `chave VARCHAR(120)`, `valor MEDIUMTEXT`, `tipo VARCHAR(20)`.
- **Índices:** `uniq(escopo,unidade_id,chave)`.
- **Relacionamentos:** —. **Exclusão:** soft delete. **Auditoria:** sim (mudança de regra).
- **Integridade:** chave única no escopo. (Complementa `rise_settings`, não substitui.)

### `gd_sequencias`
- **Finalidade:** numeração de documentos (recibo, contrato, pedido) por unidade/ano.
- **Campos:** `unidade_id`, `tipo VARCHAR(40)`, `ano SMALLINT`, `prefixo VARCHAR(20)`,
  `proximo BIGINT`, `formato VARCHAR(60)`.
- **Índices:** `uniq(unidade_id,tipo,ano)`.
- **Relacionamentos:** consumida pelo `SequenceService`.
- **Exclusão:** não exclui (histórico). **Auditoria:** sim.
- **Integridade:** incremento atômico (`SELECT … FOR UPDATE`/`UPDATE … RETURNING`),
  garante numeração sem buracos por (unidade,tipo,ano).

### `gd_auditoria`
- **Finalidade:** trilha de alterações (especialmente financeiras).
- **Campos:** `unidade_id NULL`, `usuario_id BIGINT`, `entidade VARCHAR(60)`,
  `entidade_id BIGINT`, `acao VARCHAR(30)` {create,update,delete,estorno,…},
  `dados_antes JSON/MEDIUMTEXT`, `dados_depois JSON/MEDIUMTEXT`, `ip VARCHAR(45)`,
  `contexto VARCHAR(60)`, `created_at`.
- **Índices:** `idx_entidade(entidade,entidade_id)`, `idx_usuario`, `idx_created_at`.
- **Relacionamentos:** `→ rise_users`, alvo polimórfico.
- **Exclusão:** **append-only** (nunca atualiza/deleta). **Auditoria:** é a própria.
- **Integridade:** imutável; alimentada por listener dos hooks `app_hook_data_*` + eventos.

### `gd_arquivos`
- **Finalidade:** anexos polimórficos (contratos, comprovantes, vistorias, documentos).
- **Campos:** `entidade VARCHAR(60)`, `entidade_id BIGINT`, `nome_original VARCHAR(255)`,
  `caminho VARCHAR(255)`, `mime VARCHAR(120)`, `tamanho BIGINT`, `hash CHAR(64)`,
  `visibilidade VARCHAR(20)`.
- **Índices:** `idx_entidade(entidade,entidade_id)`, `idx_hash`.
- **Relacionamentos:** usa helpers `app_files_helper` do Rise (pasta `files/`).
- **Exclusão:** soft delete + `delete_app_file()`. **Auditoria:** sim.
- **Integridade:** não permitir caminho fora de `files/`.

---

## B. CLIENTES & PESSOAS

### `gd_contas`
- **Finalidade:** entidade pagadora/contratante (família, empresa, organizador).
- **Campos:** `unidade_id`, `tipo VARCHAR(20)` {pessoa, organizacao}, `nome VARCHAR(180)`,
  `documento VARCHAR(20)` (CPF/CNPJ), `rise_client_id BIGINT NULL` (→ `rise_clients.id`
  quando espelhado), `status VARCHAR(30)`.
- **Índices:** `idx_documento`, `idx_rise_client`, `idx_unidade`.
- **Relacionamentos:** N:N com `gd_pessoas` via `gd_conta_pessoa`; origem de cobranças.
- **Exclusão:** soft delete; **RESTRICT** com financeiro aberto.
- **Auditoria:** sim. **Integridade:** `documento` deduplicado (não-único hard p/ casos legados).

### `gd_pessoas`
- **Finalidade:** indivíduo (participante, responsável, atleta, contato).
- **Campos:** `nome VARCHAR(180)`, `nascimento DATE`, `sexo VARCHAR(10)`,
  `cpf VARCHAR(14)`, `rg VARCHAR(20)`, `email VARCHAR(160)`, `telefone VARCHAR(20)`,
  `telefone_normalizado VARCHAR(20)`, `foto_arquivo_id NULL`, `obs TEXT`.
- **Índices:** `idx_cpf`, `idx_tel_norm`, `idx_nome`.
- **Relacionamentos:** N:N com contas; referida por matrículas, presenças, equipes.
- **Exclusão:** soft delete; **RESTRICT** se houver matrícula/financeiro ativo.
- **Auditoria:** sim. **Integridade:** normalização de telefone no Service (não em SQL).

### `gd_conta_pessoa`
- **Finalidade:** vínculo N:N conta×pessoa **com papel** (resolve "1 responsável, vários
  filhos" e "pessoa em várias contas").
- **Campos:** `conta_id`, `pessoa_id`, `papel VARCHAR(30)` {titular, responsavel,
  participante, dependente, contato_emergencia, financeiro}, `principal TINYINT(1)`,
  `inicio DATE`, `fim DATE NULL`.
- **Índices:** `uniq(conta_id,pessoa_id,papel)`, `idx_pessoa`, `idx_conta`.
- **Relacionamentos:** `→ gd_contas`, `→ gd_pessoas`.
- **Exclusão:** soft delete (encerra vínculo via `fim`). **Auditoria:** sim.
- **Integridade:** ao menos 1 papel financeiro por conta com cobrança.

### `gd_enderecos`
- **Finalidade:** endereços de contas/pessoas.
- **Campos:** `owner_tipo VARCHAR(20)` {conta,pessoa}, `owner_id BIGINT`,
  `tipo VARCHAR(20)`, `cep`, `logradouro`, `numero`, `complemento`, `bairro`,
  `cidade`, `uf VARCHAR(2)`, `principal TINYINT(1)`.
- **Índices:** `idx_owner(owner_tipo,owner_id)`.
- **Exclusão:** soft delete. **Auditoria:** opcional. **Integridade:** 1 principal por owner.

### `gd_contatos`
- **Finalidade:** canais de contato (telefone, e-mail, whatsapp) por owner.
- **Campos:** `owner_tipo`, `owner_id`, `canal VARCHAR(20)`, `valor VARCHAR(160)`,
  `valor_normalizado VARCHAR(160)`, `principal TINYINT(1)`, `verificado TINYINT(1)`.
- **Índices:** `idx_owner`, `idx_valor_norm`.
- **Exclusão:** soft delete. **Integridade:** dedup por (owner,canal,valor_normalizado).

### `gd_documentos`
- **Finalidade:** documentos da pessoa/conta (RG, atestado médico, contrato assinado).
- **Campos:** `owner_tipo`, `owner_id`, `tipo VARCHAR(40)`, `numero VARCHAR(60)`,
  `arquivo_id NULL`, `validade DATE NULL`, `sensivel TINYINT(1)`.
- **Índices:** `idx_owner`, `idx_tipo`.
- **Exclusão:** soft delete. **Auditoria:** sim (dados sensíveis/médicos — LGPD).
- **Integridade:** `sensivel=1` exige permissão específica para leitura.

### `gd_consentimentos`
- **Finalidade:** consentimentos LGPD (imagem, dados, comunicação).
- **Campos:** `pessoa_id`, `finalidade VARCHAR(60)`, `base_legal VARCHAR(40)`,
  `concedido TINYINT(1)`, `data_consentimento DATETIME`, `revogado_em DATETIME NULL`,
  `evidencia_arquivo_id NULL`.
- **Índices:** `idx_pessoa`, `idx_finalidade`.
- **Exclusão:** **append-only** (revoga via `revogado_em`). **Auditoria:** sim.
- **Integridade:** histórico imutável de consentimento.

---

## C. CATÁLOGO (modelo conceitual original; para as entidades realizadas prevalece V013–V018 acima)

### `gd_categorias`
- **Finalidade:** categorias de produtos/serviços.
- **Campos:** `nome VARCHAR(120)`, `tipo VARCHAR(30)` {servico, produto, uniforme,
  bar, taxa}, `parent_id NULL`, `ordem INT`.
- **Índices:** `idx_tipo`, `idx_parent`. **Exclusão:** soft delete (RESTRICT com produtos).
- **Integridade:** árvore sem ciclo.

### `gd_produtos`
- **Finalidade:** itens vendáveis/cobráveis (aula, locação-hora, camisa, bebida, taxa).
- **Campos:** `categoria_id`, `rise_item_id BIGINT NULL` (→ `rise_items.id`),
  `nome VARCHAR(180)`, `sku VARCHAR(60)`, `tipo VARCHAR(30)` {servico, produto, pacote},
  `unidade_medida VARCHAR(20)`, `controla_estoque TINYINT(1)`, `tributavel TINYINT(1)`,
  `ativo TINYINT(1)`.
- **Índices:** `idx_categoria`, `idx_sku`, `idx_rise_item`.
- **Relacionamentos:** 1—N variações/preços; usado por pedidos, cobranças, estoque.
- **Exclusão:** soft delete (RESTRICT com movimento). **Auditoria:** sim.

### `gd_variacoes`
- **Finalidade:** variações do produto (tamanho de uniforme, modalidade).
- **Campos:** `produto_id`, `nome VARCHAR(120)`, `sku VARCHAR(60)`, `atributos JSON`,
  `ativo TINYINT(1)`.
- **Índices:** `idx_produto`, `idx_sku`. **Exclusão:** soft delete.
- **Integridade:** SKU único quando presente.

### `gd_precos`
- **Finalidade:** preço por produto/variação, vigência, unidade e tabela.
- **Campos:** `produto_id`, `variacao_id NULL`, `unidade_id NULL`, `tabela VARCHAR(40)`,
  `valor DECIMAL(15,2)`, `vigencia_inicio DATE`, `vigencia_fim DATE NULL`,
  `min_qtd DECIMAL(15,3) NULL`.
- **Índices:** `idx_produto`, `idx_vigencia`, `idx(unidade_id,tabela)`.
- **Exclusão:** soft delete (encerra por vigência). **Auditoria:** sim (mudança de preço).
- **Integridade:** sem sobreposição de vigência na mesma (produto,variação,tabela,unidade).

### `gd_pacotes`
- **Finalidade:** pacotes/combos (8 aulas, combo festa, kit uniforme).
- **Campos:** `nome VARCHAR(160)`, `tipo VARCHAR(30)` {credito_aula, combo, assinatura},
  `qtd_creditos INT NULL`, `validade_dias INT NULL`, `valor DECIMAL(15,2)`.
- **Índices:** `idx_tipo`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_pacote_itens`
- **Finalidade:** composição do pacote.
- **Campos:** `pacote_id`, `produto_id`, `variacao_id NULL`, `quantidade DECIMAL(15,3)`.
- **Índices:** `idx_pacote`, `idx_produto`. **Exclusão:** cascade lógico do pacote.

---

## D. RECURSOS & AGENDA

### `gd_recursos`
- **Finalidade:** itens agendáveis: quadras Q2–Q6, professores/personal, espaço de festa.
- **Campos:** `unidade_id`, `tipo VARCHAR(30)` {quadra, profissional, espaco, equipamento},
  `nome VARCHAR(120)`, `codigo VARCHAR(30)`, `capacidade INT NULL`,
  `profissional_id NULL` (quando tipo=profissional → `gd_profissionais.id`),
  `cor VARCHAR(10)`, `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `idx_tipo`, `uniq(unidade_id,codigo)`.
- **Relacionamentos:** N:N com reservas; 1—N disponibilidades/bloqueios.
- **Exclusão:** soft delete (RESTRICT com reservas futuras). **Auditoria:** sim.

### `gd_disponibilidades`
- **Finalidade:** janelas regulares de funcionamento do recurso.
- **Campos:** `recurso_id`, `dia_semana TINYINT` (0–6), `hora_inicio TIME`,
  `hora_fim TIME`, `vigencia_inicio DATE NULL`, `vigencia_fim DATE NULL`.
- **Índices:** `idx_recurso`, `idx_dia`. **Exclusão:** soft delete.
- **Integridade:** `hora_fim > hora_inicio`; sem sobreposição no mesmo recurso/dia.

### `gd_bloqueios`
- **Finalidade:** indisponibilidades pontuais (manutenção, feriado, montagem, limpeza).
- **Campos:** `recurso_id NULL` (NULL = unidade inteira), `unidade_id`,
  `inicio DATETIME`, `fim DATETIME`, `motivo VARCHAR(60)`,
  `origem_tipo VARCHAR(30) NULL`, `origem_id BIGINT NULL` (ex.: evento que gerou),
  `tipo VARCHAR(30)` {manutencao, feriado, montagem, limpeza, reserva_interna}.
- **Índices:** `idx_recurso`, `idx_periodo(inicio,fim)`, `idx_origem`.
- **Relacionamentos:** gerado por eventos (montagem/limpeza) e escola.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** `fim > inicio`; participa da checagem de conflito.

### `gd_reserva_series`
- **Finalidade:** **regra** de recorrência (gera ocorrências).
- **Campos:** `unidade_id`, `conta_id NULL`, `area_negocio_id`, `recurso_id`,
  `rrule VARCHAR(255)` (padrão iCal RRULE), `data_inicio DATE`, `data_fim DATE NULL`,
  `hora_inicio TIME`, `hora_fim TIME`, `status VARCHAR(30)`,
  `origem_tipo VARCHAR(30) NULL`, `origem_id BIGINT NULL` (turma, locação mensal).
- **Índices:** `idx_unidade`, `idx_recurso`, `idx_origem`. **Exclusão:** soft delete
  (encerra série + ocorrências futuras). **Auditoria:** sim.
- **Integridade:** alterar série não muda ocorrências passadas (imutáveis).

### `gd_reservas`  *(ocorrências)*
- **Finalidade:** uso concreto de recurso(s) num intervalo.
- **Campos:** `unidade_id`, `serie_id NULL`, `conta_id NULL`, `pessoa_id NULL`,
  `area_negocio_id`, `inicio DATETIME`, `fim DATETIME`, `status VARCHAR(30)`
  {pendente, confirmada, realizada, cancelada, falta}, `origem_tipo VARCHAR(30)`,
  `origem_id BIGINT NULL` (aula, locacao, personal, evento, competicao),
  `valor_previsto DECIMAL(15,2) NULL`, `obs TEXT`.
- **Índices:** `idx_unidade`, `idx_periodo(inicio,fim)`, `idx_serie`, `idx_origem`,
  `idx_status`.
- **Relacionamentos:** N:N recursos via `gd_reserva_recursos`; 1—N histórico.
- **Exclusão:** soft delete (cancelamento mantém registro). **Auditoria:** sim.
- **Integridade:** sem conflito de recurso no mesmo intervalo (ver `ConflitoService`);
  `fim > inicio`.

### `gd_reserva_recursos`
- **Finalidade:** recursos usados por uma reserva (1 reserva pode ocupar várias quadras).
- **Campos:** `reserva_id`, `recurso_id`, `papel VARCHAR(20)` {principal, adicional}.
- **Índices:** `uniq(reserva_id,recurso_id)`, `idx_recurso`.
- **Exclusão:** cascade lógico da reserva. **Integridade:** base da detecção de conflito.

### `gd_reserva_historico`
- **Finalidade:** trilha de alterações da reserva (remarcações, status).
- **Campos:** `reserva_id`, `usuario_id`, `acao VARCHAR(30)`, `de JSON`, `para JSON`,
  `created_at`.
- **Índices:** `idx_reserva`. **Exclusão:** append-only. **Auditoria:** é a própria.

---

## E. ESCOLA

### `gd_profissionais`
- **Finalidade:** professores/treinadores/personais.
- **Campos:** `unidade_id`, `pessoa_id NULL`, `rise_user_id BIGINT NULL` (→ `rise_users`),
  `nome VARCHAR(160)`, `especialidade VARCHAR(80)`, `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `idx_pessoa`, `idx_rise_user`. **Exclusão:** soft delete.
- **Auditoria:** sim.

### `gd_programas`
- **Finalidade:** oferta pedagógica (Escola de Futebol, Treino Noturno, Grupo de Pais).
- **Campos:** `unidade_id`, `area_negocio_id`, `nome VARCHAR(160)`,
  `modalidade VARCHAR(60)`, `faixa_etaria VARCHAR(40)`, `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `idx_area`. **Exclusão:** soft delete (RESTRICT com turmas).

### `gd_turmas`
- **Finalidade:** turma de um programa (grupo + professor + quadra + recorrência).
- **Campos:** `programa_id`, `unidade_id`, `profissional_id`, `recurso_id NULL` (quadra),
  `nome VARCHAR(120)`, `capacidade INT`, `serie_id NULL` (→ `gd_reserva_series`),
  `status VARCHAR(30)` {ativa, encerrada}.
- **Índices:** `idx_programa`, `idx_profissional`, `idx_recurso`, `idx_status`.
- **Relacionamentos:** 1—N horários/aulas; N:N participantes via matrículas; gera
  reservas/bloqueios na quadra.
- **Exclusão:** soft delete (RESTRICT com matrículas ativas). **Auditoria:** sim.
- **Integridade:** matrículas ativas ≤ capacidade (regra de Service).

### `gd_horarios`
- **Finalidade:** grade horária da turma (substitui o campo texto `horario` do sistema legado).
- **Campos:** `turma_id`, `dia_semana TINYINT`, `hora_inicio TIME`, `hora_fim TIME`,
  `recurso_id NULL`.
- **Índices:** `idx_turma`, `idx_dia`. **Exclusão:** soft delete.
- **Integridade:** `hora_fim > hora_inicio`.

### `gd_matriculas`
- **Finalidade:** vínculo participante×turma (várias atividades por participante).
- **Campos:** `unidade_id`, `pessoa_id` (participante), `turma_id`, `conta_id` (pagadora),
  `data_matricula DATE`, `data_inicio DATE`, `data_fim DATE NULL`,
  `status VARCHAR(30)` {ativa, trancada, concluida, cancelada, inadimplente},
  `plano VARCHAR(40)`, `valor_mensal DECIMAL(15,2)`, `dia_vencimento TINYINT`,
  `origem VARCHAR(30)`.
- **Índices:** `idx_pessoa`, `idx_turma`, `idx_conta`, `idx_status`,
  `uniq(pessoa_id,turma_id,data_inicio)`.
- **Relacionamentos:** `→ pessoas/turmas/contas`; 1—N histórico/presenças; origem de
  cobranças recorrentes.
- **Exclusão:** soft delete (cancelamento via status + histórico). **Auditoria:** sim.
- **Integridade:** não duplicar matrícula ativa na mesma turma.

### `gd_matricula_historico`
- **Finalidade:** histórico de status/eventos da matrícula (substitui campos de
  cancelamento espalhados do sistema legado).
- **Campos:** `matricula_id`, `usuario_id`, `de_status VARCHAR(30)`,
  `para_status VARCHAR(30)`, `motivo VARCHAR(120)`, `obs TEXT`, `created_at`.
- **Índices:** `idx_matricula`. **Exclusão:** append-only. **Auditoria:** é a própria.

### `gd_aulas`
- **Finalidade:** **ocorrência** de uma turma numa data (chave correta da presença).
- **Campos:** `turma_id`, `unidade_id`, `data DATE`, `hora_inicio TIME`, `hora_fim TIME`,
  `profissional_id NULL`, `recurso_id NULL`, `reserva_id NULL`,
  `status VARCHAR(30)` {prevista, realizada, cancelada, feriado}, `obs TEXT`.
- **Índices:** `idx_turma`, `idx_data`, `uniq(turma_id,data,hora_inicio)`, `idx_reserva`.
- **Relacionamentos:** 1—N presenças; ligada a reserva na agenda.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** presença sempre por `aula_id` (nunca por aluno+data).

### `gd_presencas`
- **Finalidade:** presença do participante numa aula.
- **Campos:** `aula_id`, `matricula_id`, `pessoa_id`, `status VARCHAR(30)`
  {presente, falta, justificada, reposicao}, `checkin_em DATETIME NULL`,
  `registrado_por BIGINT`, `obs VARCHAR(255)`.
- **Índices:** `uniq(aula_id,matricula_id)`, `idx_pessoa`, `idx_status`.
- **Relacionamentos:** `→ aulas/matriculas/pessoas`.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** chave única (aula,matrícula) — permite participante em 2 aulas no
  mesmo dia (turmas diferentes).

### `gd_creditos_pacote`
- **Finalidade:** saldo de créditos de aula (aula avulsa/pacote) por participante.
- **Campos:** `pessoa_id`, `conta_id`, `pacote_id NULL`, `origem_tipo`, `origem_id`,
  `qtd_total INT`, `qtd_consumida INT`, `validade DATE NULL`, `status VARCHAR(30)`.
- **Índices:** `idx_pessoa`, `idx_validade`, `idx_status`.
- **Relacionamentos:** 1—N movimentos.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** `qtd_consumida ≤ qtd_total`; saldo derivado de movimentos.

### `gd_creditos_movimentos`
- **Finalidade:** lançamentos de crédito (compra, consumo numa aula, expiração, estorno).
- **Campos:** `credito_id`, `tipo VARCHAR(20)` {entrada, consumo, expiracao, estorno},
  `quantidade INT`, `aula_id NULL`, `referencia VARCHAR(60)`, `created_at`.
- **Índices:** `idx_credito`, `idx_aula`. **Exclusão:** append-only. **Auditoria:** sim.
- **Integridade:** saldo nunca negativo (validação no Service).

---

## F. COMERCIAL / CONTRATOS

### `gd_orcamentos`
- **Finalidade:** proposta (locação mensal, evento, pacote escola).
- **Campos:** `unidade_id`, `conta_id`, `area_negocio_id`, `numero VARCHAR(40)`,
  `data DATE`, `validade DATE`, `status VARCHAR(30)` {rascunho, enviado, aceito,
  recusado, expirado}, `total DECIMAL(15,2)`, `obs TEXT`.
- **Índices:** `idx_conta`, `idx_status`, `uniq(unidade_id,numero)`.
- **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_orcamento_itens`
- **Campos:** `orcamento_id`, `produto_id NULL`, `descricao VARCHAR(255)`,
  `quantidade DECIMAL(15,3)`, `valor_unit DECIMAL(15,2)`, `total DECIMAL(15,2)`, `ordem INT`.
- **Índices:** `idx_orcamento`. **Exclusão:** cascade lógico.

### `gd_contratos`
- **Finalidade:** contrato firmado (mensal de quadra, evento, matrícula formal).
- **Campos:** `unidade_id`, `conta_id`, `area_negocio_id`, `orcamento_id NULL`,
  `numero VARCHAR(40)`, `tipo VARCHAR(30)`, `data_inicio DATE`, `data_fim DATE NULL`,
  `valor DECIMAL(15,2)`, `periodicidade VARCHAR(20)`, `status VARCHAR(30)`
  {ativo, suspenso, encerrado, rescindido}, `versao_atual INT`.
- **Índices:** `idx_conta`, `idx_status`, `uniq(unidade_id,numero)`.
- **Relacionamentos:** origem de cobranças recorrentes; 1—N itens/versões/eventos.
- **Exclusão:** soft delete (encerra via status). **Auditoria:** sim.

### `gd_contrato_itens`
- **Campos:** `contrato_id`, `produto_id NULL`, `descricao`, `quantidade`,
  `valor_unit`, `total`, `centro_resultado_id NULL`.
- **Índices:** `idx_contrato`. **Exclusão:** versionado (ver versões).

### `gd_contrato_versoes`
- **Finalidade:** versionamento imutável do contrato (cada aditivo cria versão).
- **Campos:** `contrato_id`, `versao INT`, `snapshot JSON`, `motivo VARCHAR(120)`,
  `vigente_de DATE`, `vigente_ate DATE NULL`, `created_by`, `created_at`.
- **Índices:** `uniq(contrato_id,versao)`. **Exclusão:** append-only. **Auditoria:** sim.

### `gd_contrato_eventos`
- **Finalidade:** linha do tempo do contrato (assinatura, aditivo, suspensão, rescisão).
- **Campos:** `contrato_id`, `tipo VARCHAR(30)`, `data DATE`, `descricao VARCHAR(255)`,
  `arquivo_id NULL`, `usuario_id`.
- **Índices:** `idx_contrato`. **Exclusão:** append-only.

---

## G. FINANCEIRO

### `gd_cobrancas`
- **Finalidade:** valor devido (imutável após emissão). **Separada do pagamento.**
- **Campos:** `unidade_id`, `conta_id`, `centro_resultado_id`, `area_negocio_id`,
  `numero VARCHAR(40)`, `origem_tipo VARCHAR(30)` {matricula, locacao, contrato,
  evento, pedido, competicao, avulso}, `origem_id BIGINT`, `competencia CHAR(7)`
  (`YYYY-MM`), `emissao DATE`, `vencimento DATE`, `valor DECIMAL(15,2)`,
  `valor_pago DECIMAL(15,2) DEFAULT 0`, `status VARCHAR(30)` {aberta, parcial, paga,
  vencida, cancelada, isenta, renegociada}, `rise_invoice_id BIGINT NULL`.
- **Índices:** `idx_conta`, `idx_origem(origem_tipo,origem_id)`, `idx_vencimento`,
  `idx_status`, `idx_competencia`, `idx_centro`, `uniq(unidade_id,numero)`.
- **Relacionamentos:** 1—N itens; N:N pagamentos via alocações.
- **Exclusão:** **não edita valor após emissão**; cancela via status + contralançamento.
  Soft delete proibido após pagamento (apenas cancelamento auditado).
- **Auditoria:** **obrigatória** (financeiro). **Integridade:** `valor_pago` derivado da
  soma das alocações (com tolerância de arredondamento — ver decisions.md).

### `gd_cobranca_itens`
- **Campos:** `cobranca_id`, `produto_id NULL`, `descricao`, `quantidade`,
  `valor_unit`, `total`, `centro_resultado_id NULL`.
- **Índices:** `idx_cobranca`. **Exclusão:** imutável após emissão da cobrança.

### `gd_pagamentos`
- **Finalidade:** valor recebido (imutável). Pode quitar várias cobranças.
- **Campos:** `unidade_id`, `conta_id`, `caixa_sessao_id NULL`, `conta_financeira_id`,
  `numero VARCHAR(40)`, `data DATETIME`, `valor DECIMAL(15,2)`,
  `forma VARCHAR(30)` {dinheiro, pix, debito, credito, boleto, transferencia, credito_casa},
  `status VARCHAR(30)` {confirmado, estornado, pendente}, `estorno_de_id BIGINT NULL`,
  `referencia_externa VARCHAR(80)`, `idempotency_key VARCHAR(64) NULL`.
- **Índices:** `idx_conta`, `idx_data`, `idx_status`, `idx_caixa`,
  `uniq(idempotency_key)`, `uniq(unidade_id,numero)`.
- **Relacionamentos:** 1—N alocações; 1—1 recibo.
- **Exclusão:** **nunca deleta**; estorno cria pagamento negativo vinculado.
- **Auditoria:** **obrigatória**. **Integridade:** soma das alocações ≤ `valor`;
  `idempotency_key` evita duplicidade em retries.

### `gd_pagamento_alocacoes`
- **Finalidade:** N:N pagamento×cobrança — resolve "1 pagamento → N cobranças" e
  "N pagamentos → 1 cobrança".
- **Campos:** `pagamento_id`, `cobranca_id`, `valor DECIMAL(15,2)`,
  `tipo VARCHAR(20)` {quitacao, estorno, ajuste}.
- **Índices:** `idx_pagamento`, `idx_cobranca`, `uniq(pagamento_id,cobranca_id)`.
- **Exclusão:** append-only (estorno gera linha de sinal oposto). **Auditoria:** sim.
- **Integridade:** Σ alocações por cobrança = `cobrancas.valor_pago`;
  Σ alocações por pagamento ≤ `pagamentos.valor`.

### `gd_recibos`
- **Finalidade:** comprovante imutável (substitui `grupo_donato_comprovantes`).
- **Campos:** `unidade_id`, `pagamento_id`, `numero VARCHAR(40)`, `emissao DATETIME`,
  `valor DECIMAL(15,2)`, `snapshot JSON` (dados do pagador/itens no momento),
  `arquivo_id NULL`, `conferido_por NULL`, `conferido_em NULL`.
- **Índices:** `uniq(unidade_id,numero)`, `idx_pagamento`.
- **Exclusão:** **append-only/imutável**. **Auditoria:** sim.
- **Integridade:** numeração via `gd_sequencias`; nunca reescrever snapshot.

### `gd_creditos` (carteira financeira da conta)
- **Finalidade:** crédito monetário a favor da conta (troco, antecipação, estorno).
- **Campos:** `conta_id`, `unidade_id`, `saldo DECIMAL(15,2)`, `status VARCHAR(30)`.
- **Índices:** `idx_conta`. **Exclusão:** soft delete (zera via movimentos).
- **Auditoria:** sim. **Integridade:** `saldo` = Σ movimentos.

*(movimentos de crédito monetário reaproveitam o padrão de `gd_creditos_movimentos`
com `referencia` financeira — ou tabela `gd_credito_financeiro_mov` se preferível na
implementação.)*

### `gd_contas_financeiras`
- **Finalidade:** contas onde o dinheiro entra/sai (caixa físico, conta bancária, PIX).
- **Campos:** `unidade_id`, `nome VARCHAR(120)`, `tipo VARCHAR(30)` {caixa, banco,
  gateway}, `saldo_inicial DECIMAL(15,2)`, `ativo TINYINT(1)`.
- **Índices:** `idx_unidade`, `idx_tipo`. **Exclusão:** soft delete (RESTRICT com
  movimento). **Auditoria:** sim.

### `gd_caixas`
- **Finalidade:** definição de um caixa operável (PDV/bar/recepção).
- **Campos:** `unidade_id`, `conta_financeira_id`, `nome VARCHAR(120)`, `ativo`.
- **Índices:** `idx_unidade`. **Exclusão:** soft delete.

### `gd_caixa_sessoes`
- **Finalidade:** abertura/fechamento de caixa (turno do operador).
- **Campos:** `caixa_id`, `unidade_id`, `aberto_por BIGINT`, `aberto_em DATETIME`,
  `valor_abertura DECIMAL(15,2)`, `fechado_por BIGINT NULL`, `fechado_em DATETIME NULL`,
  `valor_fechamento DECIMAL(15,2) NULL`, `valor_apurado DECIMAL(15,2) NULL`,
  `diferenca DECIMAL(15,2) NULL`, `status VARCHAR(30)` {aberta, fechada, conferida}.
- **Índices:** `idx_caixa`, `idx_status`, `idx_aberto_em`.
- **Relacionamentos:** 1—N movimentos; referida por pagamentos.
- **Exclusão:** não deleta. **Auditoria:** **obrigatória**.
- **Integridade:** 1 sessão aberta por caixa; pagamentos só em sessão aberta.

### `gd_caixa_movimentos`
- **Finalidade:** entradas/saídas/sangrias/suprimentos da sessão.
- **Campos:** `caixa_sessao_id`, `tipo VARCHAR(30)` {entrada, saida, sangria, suprimento,
  pagamento, estorno}, `origem_tipo VARCHAR(30) NULL`, `origem_id BIGINT NULL`,
  `valor DECIMAL(15,2)`, `forma VARCHAR(30)`, `descricao VARCHAR(255)`, `created_at`.
- **Índices:** `idx_sessao`, `idx_origem`. **Exclusão:** append-only. **Auditoria:** sim.
- **Integridade:** saldo da sessão = abertura + Σ movimentos.

### `gd_despesas`
- **Finalidade:** custos/saídas (substitui `grupo_donato_custos_unidade`; pode espelhar
  `rise_expenses`).
- **Campos:** `unidade_id`, `centro_resultado_id`, `area_negocio_id NULL`,
  `categoria_id NULL`, `fornecedor_id NULL`, `rise_expense_id BIGINT NULL`,
  `descricao VARCHAR(255)`, `valor DECIMAL(15,2)`, `data DATE`, `competencia CHAR(7)`,
  `status VARCHAR(30)` {prevista, paga, cancelada}, `forma_pagamento VARCHAR(30)`.
- **Índices:** `idx_unidade`, `idx_centro`, `idx_competencia`, `idx_status`,
  `idx_fornecedor`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_contas_pagar`
- **Finalidade:** obrigações a pagar (parcelas de despesa/compra).
- **Campos:** `unidade_id`, `fornecedor_id NULL`, `origem_tipo VARCHAR(30)`,
  `origem_id BIGINT`, `vencimento DATE`, `valor DECIMAL(15,2)`,
  `valor_pago DECIMAL(15,2)`, `status VARCHAR(30)`, `centro_resultado_id`.
- **Índices:** `idx_vencimento`, `idx_status`, `idx_fornecedor`. **Exclusão:** soft
  delete. **Auditoria:** sim.

### `gd_negociacoes`
- **Finalidade:** renegociação de inadimplência (substitui "cobranças realizadas").
- **Campos:** `unidade_id`, `conta_id`, `usuario_id`, `data DATETIME`,
  `canal VARCHAR(30)`, `resultado VARCHAR(30)` {promessa, acordo, sem_acordo, contato},
  `obs TEXT`.
- **Índices:** `idx_conta`, `idx_data`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_promessas`
- **Finalidade:** promessa de pagamento dentro de uma negociação.
- **Campos:** `negociacao_id`, `conta_id`, `data_prometida DATE`, `valor DECIMAL(15,2)`,
  `status VARCHAR(30)` {aberta, cumprida, quebrada}, `cobranca_id NULL`.
- **Índices:** `idx_negociacao`, `idx_status`, `idx_data_prometida`.
- **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_rateios`
- **Finalidade:** cabeçalho de rateio de uma receita/custo entre centros de resultado.
- **Campos:** `origem_tipo VARCHAR(30)` {cobranca, pagamento, despesa},
  `origem_id BIGINT`, `valor_total DECIMAL(15,2)`, `criterio VARCHAR(40)`.
- **Índices:** `idx_origem`. **Exclusão:** append-only. **Auditoria:** sim.

### `gd_rateio_linhas`
- **Finalidade:** distribuição por centro de resultado (receita por centro).
- **Campos:** `rateio_id`, `centro_resultado_id`, `percentual DECIMAL(7,4)`,
  `valor DECIMAL(15,2)`.
- **Índices:** `idx_rateio`, `idx_centro`. **Exclusão:** append-only.
- **Integridade:** Σ `valor` das linhas = `gd_rateios.valor_total`; Σ percentuais = 100.

---

## H. BAR & PDV

### `gd_comandas`
- **Finalidade:** comanda aberta, vinculável a quadra/evento/avulso.
- **Campos:** `unidade_id`, `numero VARCHAR(40)`, `origem_tipo VARCHAR(30)`
  {quadra, evento, avulso, conta}, `origem_id BIGINT NULL`, `conta_id NULL`,
  `aberta_em DATETIME`, `fechada_em DATETIME NULL`, `status VARCHAR(30)` {aberta,
  fechada, cancelada}, `total DECIMAL(15,2)`.
- **Índices:** `idx_unidade`, `idx_origem`, `idx_status`.
- **Relacionamentos:** 1—N pedidos.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** atende "bar vinculado à quadra ou evento" via `origem_tipo/_id`.

### `gd_pedidos`
- **Finalidade:** venda do bar/PDV (pode espelhar `rise_orders`).
- **Campos:** `comanda_id NULL`, `unidade_id`, `caixa_sessao_id NULL`,
  `rise_order_id BIGINT NULL`, `data DATETIME`, `total DECIMAL(15,2)`,
  `desconto DECIMAL(15,2)`, `status VARCHAR(30)` {aberto, pago, cancelado}.
- **Índices:** `idx_comanda`, `idx_unidade`, `idx_status`, `idx_caixa`.
- **Relacionamentos:** 1—N itens/pagamentos.
- **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_pedido_itens`
- **Campos:** `pedido_id`, `produto_id`, `variacao_id NULL`, `local_estoque_id NULL`,
  `descricao`, `quantidade DECIMAL(15,3)`, `valor_unit DECIMAL(15,2)`,
  `total DECIMAL(15,2)`.
- **Índices:** `idx_pedido`, `idx_produto`. **Exclusão:** cascade lógico.
- **Integridade:** gera `gd_estoque_movimentos` (saída) quando produto controla estoque.

### `gd_pedido_pagamentos`
- **Campos:** `pedido_id`, `pagamento_id NULL`, `forma VARCHAR(30)`,
  `valor DECIMAL(15,2)`.
- **Índices:** `idx_pedido`, `idx_pagamento`. **Exclusão:** append-only.
- **Integridade:** Σ pagamentos = `pedidos.total`.

---

## I. ESTOQUE

### `gd_locais_estoque`
- **Finalidade:** locais de estoque (bar, almoxarifado, loja de uniformes).
- **Campos:** `unidade_id`, `nome VARCHAR(120)`, `tipo VARCHAR(30)`, `ativo`.
- **Índices:** `idx_unidade`. **Exclusão:** soft delete (RESTRICT com saldo).

### `gd_estoque_movimentos`
- **Finalidade:** **fonte da verdade do saldo, por local** (entrada/saída/ajuste/transf).
- **Campos:** `local_estoque_id`, `produto_id`, `variacao_id NULL`,
  `tipo VARCHAR(20)` {entrada, saida, ajuste, transferencia}, `quantidade DECIMAL(15,3)`,
  `custo_unit DECIMAL(15,2) NULL`, `origem_tipo VARCHAR(30)`, `origem_id BIGINT NULL`,
  `created_at`.
- **Índices:** `idx_local`, `idx_produto`, `idx_origem`, `idx(local_estoque_id,produto_id)`.
- **Exclusão:** append-only (correção via novo movimento). **Auditoria:** sim.
- **Integridade:** saldo por (local, produto, variação) = Σ movimentos → **estoque por local**.

### `gd_fornecedores`
- **Campos:** `nome VARCHAR(180)`, `documento VARCHAR(20)`, `contato VARCHAR(160)`,
  `rise_client_id BIGINT NULL`, `ativo`.
- **Índices:** `idx_documento`. **Exclusão:** soft delete (RESTRICT com compras).

### `gd_compras`
- **Campos:** `unidade_id`, `fornecedor_id`, `local_estoque_id`, `numero VARCHAR(40)`,
  `data DATE`, `total DECIMAL(15,2)`, `status VARCHAR(30)` {pendente, recebida,
  cancelada}.
- **Índices:** `idx_fornecedor`, `idx_status`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_compra_itens`
- **Campos:** `compra_id`, `produto_id`, `variacao_id NULL`, `quantidade DECIMAL(15,3)`,
  `custo_unit DECIMAL(15,2)`, `total DECIMAL(15,2)`.
- **Índices:** `idx_compra`. **Exclusão:** cascade lógico.
- **Integridade:** recebimento gera `gd_estoque_movimentos` (entrada).

### `gd_inventarios`
- **Campos:** `unidade_id`, `local_estoque_id`, `data DATE`, `status VARCHAR(30)`
  {aberto, fechado}, `responsavel_id`.
- **Índices:** `idx_local`, `idx_status`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_inventario_itens`
- **Campos:** `inventario_id`, `produto_id`, `variacao_id NULL`,
  `qtd_sistema DECIMAL(15,3)`, `qtd_contada DECIMAL(15,3)`,
  `diferenca DECIMAL(15,3)`.
- **Índices:** `idx_inventario`, `idx_produto`. **Exclusão:** cascade lógico.
- **Integridade:** fechamento gera movimento de ajuste pela diferença.

---

## J. EVENTOS

### `gd_eventos`
- **Finalidade:** festas/eventos no espaço.
- **Campos:** `unidade_id`, `conta_id`, `contrato_id NULL`, `tipo VARCHAR(40)`,
  `data_inicio DATETIME`, `data_fim DATETIME`, `montagem_inicio DATETIME NULL`,
  `limpeza_fim DATETIME NULL`, `num_convidados INT`, `valor DECIMAL(15,2)`,
  `status VARCHAR(30)` {orcado, confirmado, realizado, cancelado}.
- **Índices:** `idx_conta`, `idx_periodo(data_inicio,data_fim)`, `idx_status`.
- **Relacionamentos:** gera `gd_reservas`/`gd_bloqueios` (espaço + montagem + limpeza);
  1—N checklist/adicionais/vistorias/cauções; origem de cobranças.
- **Exclusão:** soft delete. **Auditoria:** sim.
- **Integridade:** bloqueios de montagem/limpeza cobrem `[montagem_inicio, limpeza_fim]`.

### `gd_evento_checklist`
- **Campos:** `evento_id`, `item VARCHAR(160)`, `concluido TINYINT(1)`,
  `responsavel_id NULL`, `prazo DATE NULL`.
- **Índices:** `idx_evento`. **Exclusão:** cascade lógico.

### `gd_evento_adicionais`
- **Campos:** `evento_id`, `produto_id NULL`, `descricao VARCHAR(160)`,
  `quantidade DECIMAL(15,3)`, `valor_unit DECIMAL(15,2)`, `total DECIMAL(15,2)`.
- **Índices:** `idx_evento`. **Exclusão:** cascade lógico.

### `gd_evento_vistorias`
- **Campos:** `evento_id`, `momento VARCHAR(20)` {entrada, saida}, `data DATETIME`,
  `responsavel_id`, `observacoes TEXT`, `arquivo_id NULL`.
- **Índices:** `idx_evento`. **Exclusão:** append-only. **Auditoria:** sim.

### `gd_evento_caucoes`
- **Finalidade:** caução/garantia do evento.
- **Campos:** `evento_id`, `valor DECIMAL(15,2)`, `forma VARCHAR(30)`,
  `recebido_em DATETIME NULL`, `devolvido_em DATETIME NULL`,
  `status VARCHAR(30)` {pendente, retido, devolvido, executado},
  `pagamento_id NULL`.
- **Índices:** `idx_evento`, `idx_status`. **Exclusão:** não deleta. **Auditoria:** sim.

---

## K. CAMPEONATOS

### `gd_competicoes`
- **Campos:** `unidade_id`, `nome VARCHAR(160)`, `tipo VARCHAR(30)` {campeonato, copa,
  amistoso}, `data_inicio DATE`, `data_fim DATE NULL`, `valor_inscricao DECIMAL(15,2)`,
  `status VARCHAR(30)`.
- **Índices:** `idx_unidade`, `idx_status`. **Exclusão:** soft delete. **Auditoria:** sim.
- **Relacionamentos:** gera reservas (quadras/datas) e cobranças (inscrições).

### `gd_competicao_equipes`
- **Campos:** `competicao_id`, `conta_id NULL`, `nome VARCHAR(120)`,
  `responsavel_pessoa_id NULL`, `status VARCHAR(30)`.
- **Índices:** `idx_competicao`. **Exclusão:** soft delete.

### `gd_competicao_participantes`
- **Campos:** `competicao_id`, `equipe_id NULL`, `pessoa_id`, `papel VARCHAR(30)`.
- **Índices:** `idx_competicao`, `idx_equipe`, `idx_pessoa`. **Exclusão:** soft delete.

### `gd_competicao_custos`
- **Campos:** `competicao_id`, `descricao VARCHAR(160)`, `valor DECIMAL(15,2)`,
  `centro_resultado_id`, `despesa_id NULL`.
- **Índices:** `idx_competicao`. **Exclusão:** soft delete. **Auditoria:** sim.

---

## L. IMPORTAÇÃO

### `gd_import_lotes`
- **Finalidade:** um arquivo/execução de importação.
- **Campos:** `unidade_id`, `tipo VARCHAR(40)` {pessoas, matriculas, cobrancas,
  produtos, …}, `arquivo_id NULL`, `status VARCHAR(30)` {recebido, validado, confirmado,
  erro}, `total_linhas INT`, `total_ok INT`, `total_erro INT`, `usuario_id`.
- **Índices:** `idx_tipo`, `idx_status`. **Exclusão:** soft delete. **Auditoria:** sim.

### `gd_import_linhas`
- **Campos:** `lote_id`, `numero_linha INT`, `dados_brutos JSON`,
  `dados_normalizados JSON`, `status VARCHAR(30)` {pendente, ok, erro, ignorada},
  `entidade_alvo VARCHAR(60) NULL`, `entidade_id BIGINT NULL`.
- **Índices:** `idx_lote`, `idx_status`. **Exclusão:** cascade lógico do lote.

### `gd_import_correspondencias`
- **Finalidade:** de/para entre valores do arquivo e entidades do sistema.
- **Campos:** `lote_id`, `linha_id NULL`, `campo VARCHAR(60)`, `valor_origem VARCHAR(255)`,
  `entidade_alvo VARCHAR(60)`, `entidade_id BIGINT NULL`, `acao VARCHAR(20)` {match,
  criar, ignorar}.
- **Índices:** `idx_lote`, `idx_campo`. **Exclusão:** cascade lógico.

### `gd_import_erros`
- **Campos:** `lote_id`, `linha_id NULL`, `codigo VARCHAR(40)`, `mensagem VARCHAR(255)`,
  `contexto JSON`.
- **Índices:** `idx_lote`, `idx_linha`. **Exclusão:** cascade lógico.

---

## Resumo de tabelas por módulo

| Módulo | Tabelas |
|--------|---------|
| Núcleo | unidades, areas_negocio, centros_resultado, config, sequencias, auditoria, arquivos |
| Clientes | contas, pessoas, conta_pessoa, enderecos, contatos, documentos, consentimentos |
| Catálogo | categorias, produtos, variacoes, precos, pacotes, pacote_itens |
| Agenda | recursos, disponibilidades, bloqueios, reserva_series, reservas, reserva_recursos, reserva_historico |
| Escola | profissionais, programas, turmas, horarios, matriculas, matricula_historico, aulas, presencas, creditos_pacote, creditos_movimentos |
| Comercial | orcamentos, orcamento_itens, contratos, contrato_itens, contrato_versoes, contrato_eventos |
| Financeiro | cobrancas, cobranca_itens, pagamentos, pagamento_alocacoes, recibos, creditos, contas_financeiras, caixas, caixa_sessoes, caixa_movimentos, despesas, contas_pagar, negociacoes, promessas, rateios, rateio_linhas |
| Bar/PDV | comandas, pedidos, pedido_itens, pedido_pagamentos |
| Estoque | locais_estoque, estoque_movimentos, fornecedores, compras, compra_itens, inventarios, inventario_itens |
| Eventos | eventos, evento_checklist, evento_adicionais, evento_vistorias, evento_caucoes |
| Campeonatos | competicoes, competicao_equipes, competicao_participantes, competicao_custos |
| Importação | import_lotes, import_linhas, import_correspondencias, import_erros |

> Regras transversais de exclusão, imutabilidade financeira, idempotência, concorrência e
> auditoria estão consolidadas em [decisions.md](decisions.md).

## Estrutura física realizada na Fase 3A

| Version | Tabela lógica | Finalidade |
|---|---|---|
| 019 | `gd_resource_availability_rules` | janelas semanais locais, vigência e overnight |
| 020 | `gd_resource_availability_exceptions` | aberturas/fechamentos pontuais em UTC |
| 021 | `gd_resource_blocks` | indisponibilidades operacionais em UTC |

As três tabelas possuem unidade/recurso, autoria, timestamps, soft delete, índices de período/status e chave gerada para duplicidade ativa exata. O schema realizado soma 21 tabelas. A antiga seção conceitual `gd_disponibilidades`/`gd_bloqueios` não é a fonte da verdade; prevalecem os nomes, campos e regras V019–V021. Tabelas de reservas/séries permanecem futuras.

## Estrutura física realizada na Fase 3B1

| Version | Tabela lógica | Finalidade |
|---|---|---|
| 022 | `gd_bookings` | reservas únicas, número, cliente opcional, UTC, status e lock version |
| 023 | `gd_booking_resources` | recursos, buffers e ocupação efetiva indexada |
| 024 | `gd_booking_events` | histórico operacional append-only |

O schema realizado soma 24 tabelas. `gd_bookings` usa unique `(unit_id, booking_number)`. `gd_booking_resources` usa unique gerada para uma relação ativa por reserva/recurso e índices por recurso/ocupação. `gd_booking_events` não possui soft delete. As tabelas conceituais de série permanecem futuras e não foram criadas.

## Estrutura física realizada na Fase 3B2

| Version | Estrutura | Finalidade |
|---|---|---|
| 025 | `gd_booking_series` | definição, término, horizonte, estado e lock version |
| 026 | `gd_booking_series_resources` | recursos e buffers padrão |
| 027 | extensão de `gd_bookings` | vínculo, chave local e flags de exceção/destaque |
| 028 | `gd_booking_series_exceptions` | histórico append-only de desvios |
| 029 | `gd_booking_series_events`, `gd_booking_series_generation_runs` | eventos append-only e ledger operacional |

O schema realizado soma 29 tabelas. A ocorrência não possui tabela paralela: continua sendo uma reserva completa. O unique `(series_id, series_occurrence_key)` protege idempotência.
