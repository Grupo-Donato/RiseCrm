# Schema runner

## Funcionamento

`SchemaRunner` descobre `Database/Schema/Versions/V*.php`, instancia apenas subclasses de `SchemaVersion`, limita ao `Constants::SCHEMA_TARGET`, ordena pelo identificador e exige que o alvo exista sem versões duplicadas.

Antes de executar, adquire `GET_LOCK` com nome derivado do banco e DBPrefix. Um lock ocupado por cinco segundos retorna `skipped_lock`; o ciclo de instalação trata isso como falha. O lock é liberado em `finally`.

## Estados e retry

Cada versão possui `running`, `completed` ou `failed`, horários e erro sanitizado. Uma versão só vira `completed` depois de `up()` retornar sem exceção. Na falha, versões posteriores não rodam. Uma nova execução retenta linhas `running`/`failed` e reconcilia versões `completed` por meio de operações idempotentes.

V001 faz o bootstrap de `gd_schema_versions`. O maior `completed` é gravado em `gd_settings.schema_version` e em `writable/gd_schema_version.txt`. O marker evita consulta ao banco em toda request; ausência do marker dispara o runner. Instalação/ativação/atualização sempre chamam o runner diretamente.

## Transações e DDL

MariaDB/MySQL faz commit implícito em grande parte do DDL. Por isso o runner não promete rollback atômico de `CREATE`/`ALTER`: cada versão é pequena, idempotente e registra estado antes/depois. Transações são usadas nos serviços de dados; o schema usa lock, passos pequenos e retry seguro.

## Idempotência

`ensureTable`, `ensureColumn` e `ensureIndex` verificam o catálogo antes de alterar. Versões 003 e 005 também reconciliam as chaves normalizadas de escopo global. Rodar instalação repetidamente deve resultar em `ran=[]`, sete versões e seeds sem duplicidade.

## Nova versão

1. Crie `VNNN_descricao.php`, com namespace exato e classe estendendo `SchemaVersion`.
2. Implemente `version()`, `description()` e `up(BaseConnection $db, string $prefix)`.
3. Use `$prefix`/`prefixTable`; nunca escreva `rise_` no SQL operacional.
4. Faça a versão pequena, aditiva e reexecutável; não use `DROP`, `TRUNCATE` ou transformação destrutiva automática.
5. Atualize `SCHEMA_TARGET`, documentação e testes.
6. Teste instalação limpa e retry em banco isolado antes de produção.

Não renumere nem remova versões publicadas. Mudança destrutiva exige migração própria, backup e aprovação explícita.

## Compatibilidade e limites

Homologado em MariaDB 10.4.32/InnoDB. Usa `GET_LOCK`, `SHOW COLUMNS`, `SHOW INDEX`, coluna gerada persistente e SQL MySQL/MariaDB. Não é portável para SQLite/PostgreSQL sem adaptador. Falha de conexão é capturada no check por request e registrada; no ciclo administrativo ela interrompe a ativação/atualização.

