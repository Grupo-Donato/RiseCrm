# Fase 1 — Implementação da Fundação

Status: **CONCLUÍDA COM RESTRIÇÕES**. Fase 2: **não iniciada**.

Ambiente homologado: Rise 3.9.6, CodeIgniter 4.6.3, PHP 8.2.12, MariaDB 10.4.32, banco `rise_crm`, DBPrefix `rise_`, InnoDB, `utf8`/`utf8_general_ci` e timezone do Rise `UTC`.

## Escopo entregue

- Plugin instalável, ativável, desativável e atualizável pelos hooks do Rise.
- Schema runner próprio, idempotente, com lock e versões 001–007.
- Uma unidade padrão, sete áreas de negócio e configurações técnicas seguras.
- CRUD administrativo de unidades, áreas de negócio e centros de resultado.
- Configuração geral não secreta e troca validada da unidade ativa.
- Permissões nativas do Rise, menu condicionado e bloqueio backend.
- Dashboard técnico com dados reais.
- Auditoria dedicada, mascarada e append-only no model e nas rotas públicas.
- Sequências transacionais com `SELECT ... FOR UPDATE`, primeiro uso concorrente, prefixo, padding e reset anual.
- Harness CLI, teste paralelo Windows/Bash e documentação da fundação.

## Arquitetura implementada

`Gd_Controller` estende `Security_Controller` e centraliza staff-only, serviços, render e JSON. Controllers fazem validação HTTP e whitelist; Services concentram acesso, unidade, settings, auditoria e sequência; Models usam `Crud_model` e DBPrefix; o schema fica em versões pequenas e idempotentes.

Arquivos principais:

- `index.php`: metadados, autoload, hooks, menu, permissões e ciclo de vida.
- `Config/{Constants,Permissions,Routes}.php`.
- `Database/Schema/{SchemaRunner,SchemaVersion}.php` e `Versions/V001..V007`.
- `Database/Seeds/{FoundationSeeder,PermissionsRegistrar}.php`.
- `Services/{AccessService,UnitContextService,SettingsService,AuditService,SequenceService}.php`.
- Controllers: `Dashboard`, `Settings`, `Units`, `Business_areas`, `Cost_centers`, `Audit`.
- Views: dashboard, configurações, modais, auditoria e componentes.

## Banco

| Versão | Tabela lógica | Tabela física | Finalidade |
|---|---|---|---|
| 001 | `gd_schema_versions` | `rise_gd_schema_versions` | estado do runner |
| 002 | `gd_units` | `rise_gd_units` | unidades |
| 003 | `gd_business_areas` | `rise_gd_business_areas` | áreas de negócio |
| 004 | `gd_cost_centers` | `rise_gd_cost_centers` | centros de resultado |
| 005 | `gd_settings` | `rise_gd_settings` | configurações tipadas não secretas |
| 006 | `gd_sequences` | `rise_gd_sequences` | numeração concorrente |
| 007 | `gd_audit_logs` | `rise_gd_audit_logs` | auditoria append-only |

As tabelas usam InnoDB e `utf8_general_ci`. JSON é serializado em `MEDIUMTEXT`. As tabelas com escopo global usam `unit_scope_id` gerado para que a unicidade também valha quando `unit_id IS NULL`.

Seeds finais: uma `Unidade Principal`, sete códigos (`school`, `court_rental`, `events`, `bar`, `store`, `sports_events`, `personal`) e três settings técnicos (`schema_version`, `plugin_version`, `installed_at`). Não há segredo nem dado comercial fictício.

## Menus, rotas e permissões

O menu raiz “Grupo Donato” contém Visão geral e Configurações conforme permissão. A navegação interna expõe somente abas autorizadas. Prefixo real: `/grupo_donato`.

Permissões: `gd_dashboard_view`, `gd_settings_view/manage`, `gd_units_view/manage`, `gd_business_areas_view/manage`, `gd_cost_centers_view/manage`, `gd_audit_view`. `manage` implica `view`; admin tem acesso total. Todos os endpoints repetem a checagem no backend.

Rotas de consulta são GET; listagens, modais e mutações são POST. O grupo aplica o filtro `csrf`. Não há rota de edição/exclusão da auditoria nem endpoint de fase futura.

## Dashboard

Exibe versão do plugin, schema aplicado/alvo, falha ou pendência, unidade ativa, contagens reais de unidades/áreas/centros e auditoria recente quando autorizada. Não contém KPI comercial fictício.

## Hardening executado na continuação

- Instalação passou a falhar fechada quando lock/schema falha.
- Filtro CSRF foi aplicado explicitamente ao grupo de rotas.
- Unidade inativa passou a ser rejeitada; IDs de unidade e área são revalidados.
- Updates/deletes rejeitam IDs inexistentes ou soft-deleted.
- Unidade padrão não pode ser desativada; alteração de padrão usa lock/transação.
- Saídas e opções provenientes do banco passaram a ser escapadas.
- Auditoria bloqueia update/delete também no model e trata UTF-8/JSON inválido com segurança.
- Sequência fechou a corrida de primeira criação com `INSERT IGNORE` + unique.
- Settings validam chave/tipo/unidade, recusam segredo e serializam escrita por lock.
- Unicidade global foi reforçada por coluna de escopo gerada e unique normalizado.
- Ordenação da auditoria passou a usar whitelist.
- Permissões desserializadas pelo plugin não aceitam classes PHP.

## Critérios de aceite e resultado

Lint: 53 arquivos PHP, zero erro. Self-test ampliado: 46 cenários esperados, zero falha. Concorrência: 100 números/100 distintos. HTTP real: seis páginas 200, CSRF sem token 303 e com token 200, CRUDs/modal/auditoria 200, unidade inválida rejeitada, checkboxes de papel renderizados e persistidos, URL direta sem permissão redirecionada para `forbidden`. Desativação/reativação preservou dados; uninstall hook manteve sete tabelas.

## Limitações

- O ambiente não possui `spark`; o harness `Tests/cli.php` é a interface executável.
- Não foi criada uma cópia isolada do banco para instalação limpa e falha induzida/recuperação do DDL.
- O smoke web foi executado por HTTP e inspeção de respostas; console JavaScript não foi automatizado.
- ACL usuário×unidade ainda não existe. Nesta fase, uma permissão administrativa do módulo autoriza as unidades ativas; o backend continua rejeitando IDs inexistentes/inativos.
- Não há armazenamento de segredos, outbox, purge ou tabela própria de arquivos.

## Fora do escopo

Clientes, pessoas, alunos, responsáveis, catálogo comercial, agenda, reservas, escola, turmas, matrículas, contratos, cobranças, pagamentos, caixa, estoque, bar, eventos, campeonatos e integrações externas. Nenhum desses módulos foi iniciado.
