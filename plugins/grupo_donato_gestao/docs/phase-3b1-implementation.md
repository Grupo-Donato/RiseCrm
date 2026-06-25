# Fase 3B1 — implementação e homologação

## Status

**CONCLUÍDA COM RESTRIÇÕES**, versão **0.5.0**, schema/marker **024**, 24 tabelas.

## Escopo

Reservas únicas, múltiplos recursos, buffers, holds, pending confirmation, máquina de estados, conflito, locks concorrentes, lock version, histórico append-only, lista/detalhe/modal e calendário com privacidade. Não foram implementados recorrência, série, contratos, preços, cobranças, pagamentos ou check-in comercial.

## Schema

| Versão | Tabela | Finalidade |
|---|---|---|
| 022 | `gd_bookings` | reserva única, número, cliente opcional, horários e ciclo de vida |
| 023 | `gd_booking_resources` | recursos, buffers e ocupação efetiva |
| 024 | `gd_booking_events` | histórico operacional append-only |

O alvo é 024, totalizando 24 tabelas. As versões 001–021 não foram alteradas e o uninstall permanece não destrutivo.

## Componentes

- Models: bookings, booking resources e booking events.
- Services: booking, conflito, ciclo de vida, holds, eventos e locks ordenados.
- Controllers: `Bookings` e `Booking_lifecycle`.
- Permissões: `gd_bookings_view`, `gd_bookings_manage`, `gd_booking_status_manage`.
- UI: lista server-side, modal, detalhe/histórico e camada Reservas no calendário.
- CLI: `expire-holds`; concorrência dedicada Windows/Bash.

## Backup inicial

Diretório: `writable/backups/fase3b1_20260619_111549`. Contém dump do banco, ZIP do plugin, hashes e manifests de sistema legado, core e migrations.

## Homologação

- Backup: dump 398440 bytes, SHA-256 `dea423a08020ea9ed53ab50f902564726ea54242c3ddb7e370067fb8e8bf66ca`; plugin ZIP 319388 bytes, SHA-256 `70e6309ecdc68bd53782d2451edad76e197c24fa62185a8897f68969dcabe638`.
- `CHECK TABLE`: 24/24 OK.
- PHP lint: 170/170.
- Self-test: **288 PASS / 0 FAIL**.
- Concorrência anterior: sequência 100/100; temporal `saved=1 duplicate=1`.
- Concorrência de reservas: simples e multi-recurso `saved=1 conflict=1`.
- Install repetido: nenhuma versão nova; uninstall `before=24 after=24 preserved=yes`.
- Estado final limpo: zero reservas ativas; Q2–Q6 preservadas.
- Integridade: sistema legado 41/41, core `app`/`system` e migrations 001–021 sem mudança.
- Logs: nenhum erro relevante da fase.
- HTTP anônimo: GET protegido 302; POST sem CSRF 303.

Restrição não bloqueadora: não havia sessão staff reutilizável nem navegador automatizado para repetir o smoke autenticado de páginas e mutações. Rotas, serviços, autorização, CSRF e respostas foram cobertos pelo self-test e inspeção HTTP anônima.

## Confirmações

Nenhum arquivo do sistema legado ou fonte do core foi alterado. Não foram implementados série, recorrência, contrato, cobrança, preço aplicado, pagamento ou check-in comercial. Não houve exclusão física em fluxo de domínio. O uninstall preserva 24 tabelas e a Fase 3B2 não foi iniciada.

## Estado inicial e integridade

| Item | Resultado |
|---|---|
| Plugin | 0.4.0 |
| Schema/marker/tabelas | 021 / 021 / 21 |
| Self-test | 247 PASS / 0 FAIL |
| Sequência | 100/100 distintas |
| Concorrência temporal | 1 saved / 1 duplicate |
| Q2–Q6 | presentes, sem capacidade/preço/horário inventado |
| Reservas | tabelas ausentes; zero reserva |
| CHECK TABLE inicial | 21/21 OK |
| sistema legado | 41/41 hashes |

O MariaDB estava inicialmente parado por falha Aria real; a execução foi interrompida conforme a regra de segurança. Após recuperação externa do banco, o startup ficou limpo, o baseline 247/0 e `CHECK TABLE` foram repetidos antes de qualquer migration.

## Banco final

| Tabela lógica | Tabela física | Finalidade | Ativos | Soft-deleted | Índices principais | Uniques |
|---|---|---|---:|---:|---|---|
| `gd_bookings` | `rise_gd_bookings` | reserva única e ciclo de vida | 0 | 0 | unidade/status, período, cliente, contato, hold, atualização | unidade+número |
| `gd_booking_resources` | `rise_gd_booking_resources` | recursos, buffers e ocupação | 0 | 0 | booking, recurso+ocupação, recurso+fim | relação ativa booking+resource |
| `gd_booking_events` | `rise_gd_booking_events` | histórico append-only | 0 | n/a | booking+data, tipo+data, request | — |

`gd_bookings` mantém cliente/contato opcionais, tipo, UTC, timezone, status, validade de hold e `lock_version`. `gd_booking_resources` persiste os buffers e a ocupação calculada. Número e ocupação não são aceitos do navegador.

## Demonstração de conflitos

| Caso | Resultado automatizado |
|---|---|
| sobreposição total / duplicidade exata | rejeitada |
| sobreposição parcial | rejeitada |
| intervalo contido | rejeitado |
| adjacência `[fim=início]` | permitida |
| buffer alcança intervalo adjacente | conflito rejeitado |
| recurso diferente | permitido |
| hold ativo | bloqueia |
| hold vencido | não bloqueia antes da limpeza |
| multi-recurso | todos os recursos validados em lote |
| concorrência simples | 1 saved / 1 conflict |
| ordem inversa de dois recursos | 1 saved / 1 conflict, sem deadlock |

## Ciclo de vida demonstrado

| Origem | Destino | Resultado |
|---|---|---|
| hold | confirmed / cancelled / expired | permitido conforme validade |
| pending confirmation | confirmed / cancelled | permitido |
| confirmed | in progress / cancelled / no-show | permitido; no-show somente após início |
| in progress | completed / cancelled | permitido |
| terminal | qualquer transição | rejeitado |

As transições preenchem autoria/instante aplicáveis, incrementam lock version, geram evento e auditoria. Nenhuma gera efeito financeiro.

## Holds e calendário

Holds exigem validade futura limitada, bloqueiam apenas enquanto válidos e são expirados em lote por CLI/cron. Limpeza repetida é idempotente. O calendário retorna uma entrada por reserva/recurso, mostra o horário nominal e inclui ocupação/buffers nos dados técnicos. Sem booking view, o título é apenas “Ocupado”; não há PII nem financeiro.

## Matriz final de testes

| Teste | Procedimento | Resultado | Evidência |
|---|---|---|---|
| Backup | mysqldump + ZIP antes de V022 | PASS | diretório `fase3b1_20260619_111549` |
| CHECK TABLE | 24 tabelas `rise_gd_*` | PASS | 24/24 OK |
| Lint | `php -l` recursivo | PASS | 170/170 |
| Self-test | `cli.php selftest` | PASS | 288/0 |
| Schema/idempotência | `cli.php install` repetido | PASS | ran vazio; marker 024 |
| Sequência/temporal | `concurrency.ps1` | PASS | 100/100; 1/1 |
| Reservas concorrentes | `booking_concurrency.ps1` | PASS | simples e multi 1/1 |
| Uninstall | `cli.php uninstallcheck` | PASS | 24/24 preservadas |
| HTTP anônimo/CSRF | curl local | PASS | GET 302; POST 303 |
| HTTP autenticado | sessão/browser indisponível | restrição | coberto por services/rotas; não repetido em navegador |
| Calendário/privacidade | self-test | PASS | identificado vs. “Ocupado” |
| sistema legado/core/migrations antigas | manifests SHA-256 | PASS | zero divergência |
| Logs | varredura após homologação | PASS | zero erro relevante |
| Versão/settings | metadata, constante e banco | PASS | 0.5.0 / 024 |

## Restrições

### Bloqueadoras

Nenhuma.

### Não bloqueadoras

- Smoke autenticado em navegador não repetido por ausência de sessão staff e automação disponível.
- Falha DDL induzida continua não executada em banco isolado.

### Infraestrutura

- O host exigiu recuperação Aria externa antes do baseline; depois da recuperação, backup e CHECK TABLE foram aprovados e não houve novo erro.
- A raiz não é um worktree Git; integridade foi demonstrada por manifests SHA-256.

### Fase 3B2

Séries, RRULE, ocorrências, exceções e alterações parciais permanecem futuras.

### Fase 3C

Mensalistas, contratos, preços, sinal, cobrança, pagamento, políticas comerciais e check-in/out permanecem futuros.

### Validações do cliente

Horários reais, buffers padrão por recurso, duração/validade operacional e perfis reais de papel devem ser configurados/validados antes de produção.

### Dívidas técnicas

Automatizar browser/console e executar instalação limpa/falha DDL em clone isolado.
