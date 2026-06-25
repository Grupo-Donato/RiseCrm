# Fase 3A — implementação e homologação

## Escopo entregue

Regras semanais, exceções pontuais, bloqueios operacionais, motor de disponibilidade em lote e calendário-base por recurso/unidade. Não foram criadas reservas, holds, séries, ocorrências, clientes na agenda, preços ou lançamentos financeiros.

## Schema

| Versão | Tabela |
|---|---|
| 019 | `gd_resource_availability_rules` |
| 020 | `gd_resource_availability_exceptions` |
| 021 | `gd_resource_blocks` |

O estado final é versão 0.4.0, marker 021 e 21 tabelas `gd_*`. Migrations 001–018 não foram alteradas.

## Segurança e concorrência

Controllers separados, equipe interna, unidade ativa revalidada, permissões no backend, POST+CSRF para toda escrita, whitelists e paginação server-side. Locks por recurso protegem sobreposição/duplicidade; overrides são explícitos e auditados.

Permissões: `gd_calendar_view`, `gd_resource_availability_manage` e `gd_resource_blocks_manage`. Gestão temporal implica calendário e leitura de recursos, mas nunca `gd_resources_manage`.

## Backup prévio

Criado em `writable/backups/fase3a_20260618_232147` antes do schema:

- `rise_crm.sql`: 373079 bytes; SHA-256 `92e2de62e4211213676e510804d90b4f6bd0788f3335ea2a1e09ccc20d29c3b0`;
- `grupo_donato_gestao.zip`: 275565 bytes; SHA-256 `e2a8ad1512a251464350c6f58653ef43259efbae22bdc88715bf18344e9c4332`.

## Baseline confirmado

Versão 0.3.0, schema/marker 018, 18 tabelas, self-test 207/0, concorrência 100/100, Q2–Q6 sem capacidade/horário/preço inventado, `CHECK TABLE` OK nas 18 tabelas e sistema legado 41/41 inalterado.

## Testes da fase

O self-test cobre schema, idempotência, intervalos semiabertos, múltiplas janelas, overnight, DST inexistente/ambíguo, precedência, duplicidades, sobreposições, auditoria, IDOR, lote, calendário, permissões e rotas. `Tests/concurrency.ps1` também executa dois saves temporais simultâneos e exige um sucesso e uma rejeição.

## Homologação final

- PHP lint integral: PASS.
- Self-test: **247 PASS / 0 FAIL**.
- Instalação/schema reexecutados sem versão nova.
- Concorrência: sequência 100/100 distinta; temporal `saved=1 duplicate=1`.
- HTTP autenticado: agenda, três páginas, três writes CSRF, lista server-side, feed com quatro eventos e três deletes.
- Uninstall: `before=21 after=21 preserved=yes`.
- `CHECK TABLE`: OK nas 21 tabelas.
- Estado limpo: 0 regras, 0 exceções, 0 bloqueios residuais; 0 horários em Q2–Q6; 0 tabelas de reserva.
- sistema legado: 41/41 hashes; core `app` e `system` iguais ao baseline; migrations 001–018 iguais ao baseline.

Hashes de árvore usados nesta homologação: `app=ed2301b8d5c54daee3ac920c3d68c5cc54b23f48563694531e2e94a64da0051f`, `system=ccc2716bcc67d836c6641be3f06a0889874c08bc3c8ba54ac195f8dc40757eb2`, `schema001018=e8bf6d2abeb75ab43d3fd3b63f2b1b3cad0380f1abee282bb5aa7c5c0b9edda4`.

Restrição não bloqueadora: não houve automação de console JavaScript em navegador nem falha induzida de DDL em banco isolado. Renderização e CRUD HTTP reais foram homologados.
