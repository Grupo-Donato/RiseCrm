# Testes e homologação

## Pré-condições

Plugin ativo, banco acessível, PHP no PATH e backup quando o ambiente possuir dados reais. Este Rise não contém `spark`; use `Tests/cli.php`. O self-test executa os dados de domínio em transação revertida. O smoke HTTP usa registros técnicos e termina todos por soft delete; auditoria permanece append-only.

## Comandos

Lint PowerShell:

```powershell
Get-ChildItem plugins/grupo_donato_gestao -Recurse -Filter *.php |
  ForEach-Object { php -l $_.FullName }
```

Instalação/idempotência e self-test:

```powershell
php plugins/grupo_donato_gestao/Tests/cli.php install
php plugins/grupo_donato_gestao/Tests/cli.php selftest
php plugins/grupo_donato_gestao/Tests/cli.php uninstallcheck
```

Concorrência:

```powershell
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/concurrency.ps1
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/booking_concurrency.ps1
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/series_concurrency.ps1
```

Em Bash: `bash plugins/grupo_donato_gestao/Tests/concurrency.sh`.

## Cobertura automatizada atual

Schema 001–029, marker 029, 29 tabelas, seeds, idempotência, fundação, cadastro central, catálogo, preços, disponibilidade, reservas e séries. A suíte cobre escopo/IDOR, tempo/DST, recorrência, conflitos, ciclo de vida, mass assignment, XSS, mascaramento, soft delete, auditoria append-only, sequências, permissões, verbos, CSRF e idiomas sem chaves duplicadas.

A resolução cobre lista explícita/padrão, quatro níveis de precedência, quantidade mínima, data de referência, preço/lista inativos ou expirados, produto/variação/recurso inativos, ausência explícita e comparação decimal sem `float`. Os índices normalizados são exercitados por inserts diretos para os padrões únicos e escopo de preço.

Resultado atual: **328 PASS / 0 FAIL**, concorrência **100 total / 100 distintos**, série **1 efetiva / 1 idempotente / 0 duplicidades** e uninstall `before=29 after=29 preserved=yes`.

## Smoke manual/HTTP

- Abrir dashboard, configurações, unidades, áreas, centros e auditoria.
- Abrir produtos, categorias, detalhe/variações, recursos, listas/grade de preços e resolver.
- Confirmar menu/abas conforme papel.
- Abrir, criar, editar e excluir logicamente os três cadastros.
- Confirmar mensagens, atualização da DataTable e detalhe da auditoria.
- Enviar POST sem CSRF (rejeitado) e com CSRF (aceito).
- Testar URL direta com papel sem permissão (redirect `forbidden`).
- Desativar e reativar; conferir dados/versões.
- Revisar console JavaScript e logs PHP/Apache após o teste.
- No resolver, conferir base, variação, recurso, variação+recurso, tier e ausência.

Na homologação de 18/06/2026, as seis páginas e os modais responderam 200; os CRUDs funcionaram; unidade inválida retornou `success=false`; POST sem CSRF retornou 303 e com token 200; papel sem permissão foi redirecionado para `forbidden`; permissões foram renderizadas e persistidas.

Na Fase 2A, contas/pessoas/listas/detalhes/auditoria responderam 200. O smoke criou e
editou conta/pessoa, relação, contato e dois endereços; confirmou principal no banco,
alerta/override de duplicidade, escopo ignorando `unit_id=999999`, CSRF 303 sem token e
soft delete final com zero registros técnicos HTTP ativos. O bloqueio de papel sem permissão
foi coberto no self-test e herdado do smoke da Fase 1; não havia segunda sessão staff ativa
para repeti-lo sem alterar usuário.

Na homologação final da Fase 2B, os cinco GETs mínimos autenticados (produtos,
categorias, recursos, listas e resolver) responderam 200; `products/list_data`
retornou JSON, o modal de recurso carregou e o resolver retornou ausência explícita.
Anônimo recebeu 302, rota inexistente 404 e POST sem CSRF 303. Não havia sessão
staff sem permissão; esse caso permaneceu coberto pelo `AccessService` no self-test.

## Limitações e interpretação

Não houve banco isolado para induzir falha de DDL/recuperação nem automação do console JavaScript. O log de startup do MariaDB registra alertas InnoDB de LSN anteriores à fase; após o startup não houve erro novo, e `CHECK TABLE` retornou OK para todo `rise_crm`, para as tabelas do plugin e para `mysql.innodb_*_stats`. Ainda assim, o host requer backup e saneamento preventivo antes de uso produtivo/restart. Uma falha no self-test deve ser tratada como bloqueio; não altere manualmente `gd_schema_versions` para mascará-la. Em concorrência, qualquer total diferente de 100 ou `distinct` diferente de 100 é falha.

## Cobertura da Fase 3A

O self-test cobre V019–V021, marker/21 tabelas, idempotência, horário local↔UTC, DST gap/fold, semiaberto, múltiplas janelas, overnight, precedência, status, duplicidade, override auditado, escopo, lote, calendário, permissões e rotas. A concorrência exige `A=50 B=50 total=100 distinct=100` e `temporal saved=1 duplicate=1`. Uninstall deve preservar 21 tabelas.

## Cobertura da Fase 3B1

O self-test cobre V022–V024, 24 tabelas, número, cliente/contato, múltiplos recursos, buffers, disponibilidade, conflitos, adjacência, holds, ciclo de vida, lock version, eventos, calendário privado, permissões, rotas e CSRF. Resultado da implementação: **288 PASS / 0 FAIL**.

```powershell
powershell -ExecutionPolicy Bypass -File plugins/grupo_donato_gestao/Tests/booking_concurrency.ps1
php plugins/grupo_donato_gestao/Tests/cli.php expire-holds 100
```

A concorrência dedicada exige `saved=1 conflict=1` para um recurso e para dois recursos em ordem inversa. Uninstall deve preservar 24 tabelas.

## Cobertura da Fase 3B2

O self-test cobre V025–V029, 29 tabelas, diária/semanal/mensal, mês sem dia, términos, horizonte, preview, idempotência, DST, overnight, recursos/buffers, políticas, destaque, split, regeneração, cancelamentos, histórico, imutabilidade, lock version, privacidade, unidade, IDOR, rotas e CSRF. Resultado: **328 PASS / 0 FAIL**.

`series_concurrency.ps1/.sh` exige 3 reservas, 2 runs concluídos, exatamente 1 efetivo, 1 idempotente e 0 chaves duplicadas. Uninstall deve preservar 29 tabelas.
