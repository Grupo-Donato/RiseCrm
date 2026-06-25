# Estratégia de schema

O Rise não fornece migrations de plugin no fluxo nativo. A implementação usa `SchemaRunner` e vinte e nove classes `V001`–`V029`, registradas nos hooks de instalação, ativação e atualização. As versões 008–012 criam contas/pessoas; 013–018 catálogo/preços; 019–021 disponibilidade; 022–024 reservas únicas; e 025–029 séries. O alvo atual é 029; versões concluídas 001–024 não foram alteradas pela Fase 3B2.

Cada versão é pequena, aditiva e idempotente. O runner usa DBPrefix, `GET_LOCK`, estados `running/completed/failed`, erro sanitizado, interrupção na primeira falha, retry e marker em `writable/`. Versões concluídas reconciliam colunas/índices não destrutivos. DDL MariaDB não é transacional de forma confiável; a recuperação depende de passos idempotentes, não de rollback fictício.

Ordem: versões → marker/settings → seeds. `FoundationSeeder` mantém unidade padrão, sete áreas e settings técnicos; a Fase 2A não cria pessoas/contas fictícias; `CatalogSeeder` mantém Q2–Q6 e uma tabela `DEFAULT` BRL, sem produtos ou preços comerciais. Instalação repetida mantém 18 versões e não sobrescreve edições administrativas dos seeds.

As versões 013–018 usam colunas geradas `PERSISTENT` e uniques normalizados para código ativo, uma variação padrão ativa por produto, uma lista padrão ativa por unidade e escopo de preço ativo. O código operacional usa DBPrefix; dinheiro é `DECIMAL(15,2)` e quantidade `DECIMAL(15,3)`.

O uninstall é não destrutivo e não existe purge. Alteração futura exige nova versão ordenada, backup, testes em banco isolado e atualização do alvo. Não é permitido editar `activated_plugins.json`, marcar versão manualmente como concluída, usar prefixo físico hard-coded ou introduzir `DROP`/`TRUNCATE` automático.

Detalhes operacionais: [schema-runner.md](schema-runner.md) e [installation.md](installation.md).

## Fase 3A

V019, V020 e V021 criam uma tabela cada e são integralmente idempotentes. O alvo é 021, sem alteração das versões 001–018. Não há seed de horários, exceções ou bloqueios; Q2–Q6 permanecem sem disponibilidade inventada. A instalação final deve resultar em 21 linhas completed e 21 tabelas.

## Fase 3B1

V022, V023 e V024 criam, respectivamente, bookings, booking resources e booking events. São aditivas, idempotentes, usam DBPrefix/InnoDB e não alteram 001–021. O alvo é 024, com 24 linhas completed e 24 tabelas. Não existe migration de série, recorrência ou financeiro.

## Fase 3B2

V025–V029 criam definição/recursos da série, estendem bookings e adicionam exceções, eventos e ledger de geração. São aditivas, idempotentes e não alteram 001–024. O alvo é 029, com 29 linhas completed e 29 tabelas. Não existe migration de contrato, cobrança ou financeiro.
