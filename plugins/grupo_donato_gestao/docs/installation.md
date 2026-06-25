# Instalação, ativação e recuperação

## Requisitos

Rise 3.9.6+, PHP 8.1+, MySQL/MariaDB com InnoDB, transações, `GET_LOCK` e permissão de `CREATE`/`ALTER`. Homologado em PHP 8.2.12 e MariaDB 10.4.32.

## Procedimento normal

1. Disponibilize a pasta como `plugins/grupo_donato_gestao`.
2. Entre no Rise como administrador e abra **Configurações → Plugins**.
3. Instale/indexe o plugin e clique em **Ativar**.
4. Confirme o menu Grupo Donato e o dashboard.
5. Configure permissões nos papéis do Rise.
6. Em Grupo Donato → Configurações, defina o país padrão opcional se desejado.
7. Confirme Produtos e serviços, Recursos e Tabelas de preço; valide Q2–Q6 e a lista `DEFAULT` antes de cadastrar dados comerciais.

Não edite `app/Config/activated_plugins.json` manualmente. Ele é gerado por `save_plugins_config()` e representa o estado operacional de ativação, não código-fonte do plugin.

## Atualização

Use a ação Atualizar do gerenciador. O hook executa o `SchemaRunner`, reconcilia colunas/índices não destrutivos das versões concluídas e aplica versões pendentes em ordem. Depois executa seeds idempotentes. Se schema ou lock falhar, a instalação/ativação lança erro administrativo genérico e não prossegue com seeds.

## Desativação, uninstall e reinstalação

Desativar remove hooks, menu e rotas no request seguinte; tabelas e dados permanecem. O hook de uninstall apenas registra auditoria e também preserva banco e uploads. O gerenciador nativo do Rise, ao excluir um pacote, remove os arquivos da pasta depois do hook; reinstale o pacote para reativar. O schema existente é reconhecido e os seeds não duplicam.

Não existe purge nesta fase. Não execute `DROP TABLE`/`TRUNCATE` como procedimento de suporte.

## Diagnóstico

- Execute `php plugins/grupo_donato_gestao/Tests/cli.php install`.
- Execute o self-test e confira `gd_schema_versions`.
- Verifique `writable/logs/`, log do Apache/PHP e o log do banco.
- Confirme permissões de escrita em `writable/` para `gd_schema_version.txt`.
- Confirme DBPrefix e as 29 tabelas físicas; o alvo, setting e marker devem ser 029.
- Se uma versão estiver `failed`, corrija a causa e reexecute a atualização; o runner troca para `running` e tenta novamente.
- Se o marker estiver ausente, o primeiro request do plugin o recria após verificar o schema.

Não exponha stack trace, credenciais ou conteúdo de headers em chamados. O erro persistido pelo runner é sanitizado e limitado.

A versão homologada atual é 0.6.0. O uninstall é não destrutivo e deve retornar `before=29 after=29 preserved=yes`; reinstalação não cria horários, reservas ou séries.

## Recuperação segura

Antes de qualquer intervenção manual, faça backup do banco. Não altere uma linha `completed` para forçar reexecução: as versões concluídas já reconciliam mudanças não destrutivas. Em falha de DDL, preserve `error_message`, corrija o requisito do banco e rode `install`/Atualizar novamente.

## Atualização 0.4.0 / schema 021

Instalar/ativar/atualizar aplica 001–021 e preserva dados existentes. O resultado esperado é 21 tabelas, marker/settings `021` e nenhuma linha failed. V019–V021 não criam horários nem dados operacionais. Antes da atualização, faça dump do banco e backup do diretório do plugin; depois execute install, selftest, concorrência e uninstallcheck.

## Atualização 0.5.0 / schema 024

Instalar/ativar/atualizar aplica V022–V024 sem alterar V001–V021. O resultado esperado é 24 tabelas e marker/settings `024`. Nenhuma reserva é seedada. Após backup, execute install, selftest, as duas concorrências e uninstallcheck. Configure cron do Rise para que `app_hook_after_cron_run` expire holds; a correção de conflito não depende do cron.

## Atualização 0.6.0 / schema 029

Aplica V025–V029 sem alterar V001–V024. O resultado esperado é 29 tabelas e marker/settings `029`. Nenhuma série ou ocorrência é seedada. Após backup, execute `verify-full`.
