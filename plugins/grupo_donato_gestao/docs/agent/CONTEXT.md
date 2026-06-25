# Contexto permanente do plugin

## Identidade e ambiente

- Plugin: `plugins/grupo_donato_gestao`.
- Namespace: `grupo_donato_gestao`.
- Prefixo HTTP: `/grupo_donato`.
- Rise CRM homologado: 3.9.6.
- CodeIgniter: 4.6.3.
- PHP homologado: 8.2.12.
- MariaDB homologado: 10.4.32/InnoDB.
- DBPrefix observado: `rise_`; o código usa sempre o prefixo fornecido pelo framework.
- Charset/collation compatíveis com o host: `utf8`/`utf8_general_ci`.

## Estrutura técnica

```text
Config/                  constantes, permissões e rotas
Controllers/             HTTP, autorização e composição de resposta
Services/                regras de domínio, transações, locks e auditoria
Models/                  queries, escopo, paginação e soft delete
Database/Schema/         runner e versões aditivas
Database/Seeds/          fundação, permissões e catálogo mínimo
Views/                   páginas, modais e DataTables do Rise
Language/                chaves `gd_*`
Tests/                   harness CLI e concorrência entre processos
docs/                    arquitetura, domínio, operação e relatórios
```

O fluxo padrão é `Controller -> Service -> Model`. Controllers são finos: recebem apenas campos permitidos, exigem permissão, resolvem a unidade ativa e delegam. Services validam referências, escopo, estados e concorrência. Models concentram SQL/query builder, joins defensivos e paginação.

`Gd_Controller` estende `Security_Controller`, restringe acesso a staff e fornece `AccessService`, `UnitContextService`, `AuditService`, renderização e JSON padronizado. Páginas usam `Template->rander`; fragmentos usam `Template->view`, conforme o Rise instalado.

## Padrões do Rise efetivamente usados

- Hooks de instalação, ativação, atualização e uninstall no `index.php`.
- `app_filter_staff_left_menu` para menu condicionado.
- Hooks nativos de permissões de papéis.
- `Security_Controller` e `$this->login_user` para autenticação staff.
- `Crud_model` por meio de `Gd_Model`.
- `deleted` para soft delete.
- `appTable` para listagens server-side.
- `modal_anchor`, `appForm`, `ajax_anchor` e CSRF nativo.
- `app_lang()` e `Language/*/default_lang.php`.
- FullCalendar 5.5.1 já distribuído pelo Rise.
- `app_hook_after_cron_run` para expiração limitada de holds.
- Helpers de URI, data e apresentação, sem copiar bibliotecas para o plugin.

Não existe `spark` nesta distribuição. O ponto operacional CLI é `Tests/cli.php`.

## Schema runner

O Rise não executa migrations CI4 de plugins. `SchemaRunner` descobre `VNNN_*.php`, limita ao `Constants::SCHEMA_TARGET`, ordena versões e usa `GET_LOCK` por banco/prefixo.

Cada versão:

- estende `SchemaVersion`;
- é pequena, aditiva e idempotente;
- usa DBPrefix recebido;
- registra `running`, `completed` ou `failed`;
- pode reconciliar colunas/índices não destrutivos em nova execução;
- nunca promete rollback de DDL MariaDB.

O schema aplicado é registrado em `gd_schema_versions`, em `gd_settings.schema_version` e no marker `writable/gd_schema_version.txt`. Instalação executa versões, depois seeds idempotentes. Uninstall não remove tabelas.

## Unidade e escopo

Entidades operacionais possuem `unit_id`. `UnitContextService` revalida a unidade ativa no backend; IDs de unidade recebidos do navegador não concedem acesso. Services e Models repetem `unit_id` em consultas e joins.

Não existe ACL usuário x unidade adicional. Usuário com permissão do módulo opera a unidade ativa validada. Essa limitação deve ser considerada em futuras fases multiunidade.

## Auditoria e histórico

`gd_audit_logs` é append-only e recebe mutações por `AuditService`. Antes/depois e metadata são mascarados por `DataPrivacyService`; request bruto, cookies, authorization, tokens e segredos não são persistidos.

Reservas também possuem `gd_booking_events`, histórico operacional append-only. Eventos não substituem a auditoria geral.

## Permissões

Permissões são chaves `gd_*` armazenadas no mecanismo nativo de papéis do Rise. `AccessService` é a fonte de decisão para menu e backend. Admin possui acesso total; staff precisa da chave ou de uma implicação declarada em `Config/Permissions.php`.

Gestão implica apenas as leituras necessárias. Exemplo: `gd_bookings_manage` implica visualizar reservas, calendário, recursos, contas e pessoas, mas não concede gestão desses cadastros. `gd_booking_status_manage` controla as transições da reserva.

## Tempo e intervalos

Instantes concretos são `DATETIME` UTC. A unidade fornece o timezone IANA; o browser não escolhe o fuso persistido. `TemporalService` converte horário civil, rejeita DST inexistente/ambíguo e limita intervalos.

Intervalos são semiabertos `[start,end)`. Sobreposição canônica:

```text
existing_start < new_end AND existing_end > new_start
```

Regras semanais usam `TIME` local e weekday; exceções, bloqueios, reservas e ocupações usam UTC.

## Soft delete e imutabilidade

Cadastros e reservas aplicáveis usam `deleted=0/1`. Exclusão física não pertence aos fluxos de domínio. Auditoria e booking events não possuem soft delete nem endpoints de alteração/exclusão.

Estados históricos terminais são preservados. Uninstall preserva todas as tabelas e dados.

## Entidades implementadas

Fundação:

- schema versions, unidades, áreas de negócio, centros de resultado;
- settings, sequências concorrentes e auditoria.

Cadastro central:

- contas de clientes;
- pessoas;
- relações conta-pessoa;
- métodos de contato;
- endereços.

Catálogo e pricing cadastral:

- categorias, produtos, variações e recursos;
- tabelas de preço e preços;
- resolução determinística de preço, sem gerar cobrança.

Disponibilidade:

- regras semanais;
- exceções de abertura/fechamento;
- bloqueios operacionais;
- motor em lote e calendário.

Reservas únicas:

- booking com número por sequência e `lock_version`;
- múltiplos recursos e buffers individuais;
- ocupação efetiva calculada no backend;
- holds, pending, confirmed, in progress e estados terminais;
- conflito em lote e locks ordenados por recurso;
- ciclo de vida, eventos e calendário com privacidade.

Séries de reservas:

- recorrência diária, semanal e mensal simples em timezone da unidade;
- preview e ocorrências materializadas como reservas normais;
- recursos/buffers padrão, políticas de conflito e geração idempotente;
- alterações e cancelamentos por ocorrência, futuro ou série inteira;
- eventos, exceções e ledger de geração, com locks de série.

## Limites atuais

Não estão implementados: RRULE arbitrária, mensalistas comerciais, contratos, cobrança, pagamento, sinal, crédito, estoque, PDV/bar, escola operacional, eventos comerciais, portal público ou integrações externas.

Leitura inicial recomendada para qualquer agente:

1. `docs/agent/GUARDRAILS.md`.
2. `docs/agent/CURRENT_STATE.md`.
3. `docs/agent/HANDOFF.md`.
4. documentação temática relevante à tarefa.
5. código real antes de propor mudanças.
