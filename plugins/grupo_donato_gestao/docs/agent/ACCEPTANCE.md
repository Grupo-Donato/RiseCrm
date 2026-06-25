# Critérios globais reutilizáveis

Use esta lista em toda fase. Critérios específicos da tarefa são adicionais.

## Código e lint

- [ ] Todos os PHP do plugin passam em `php -l`.
- [ ] Controllers permanecem finos e Services concentram regras.
- [ ] SQL complexo está em Models/Services apropriados, com binds/query builder.
- [ ] Nenhuma chave de idioma nova aparece crua na UI.
- [ ] Nenhum arquivo fora do escopo foi alterado.

## Schema e dados

- [ ] Nova evolução usa versão posterior, aditiva e idempotente.
- [ ] Migrations concluídas permanecem byte a byte inalteradas.
- [ ] Alvo, marker, setting e maior versão completed coincidem.
- [ ] Quantidade e estrutura de tabelas correspondem à fase.
- [ ] `CHECK TABLE` está aprovado quando banco real é usado.
- [ ] Reinstalação/atualização repetida não duplica schema nem seeds.
- [ ] Não há seed comercial fictício nem registro técnico residual.

## Segurança

- [ ] Anônimo e cliente não acessam endpoints staff.
- [ ] Staff sem permissão é bloqueado também por URL direta.
- [ ] Leitura e gestão usam permissões distintas quando necessário.
- [ ] Toda escrita é POST com CSRF.
- [ ] GET não produz efeito colateral.
- [ ] Unidade é resolvida e validada no backend.
- [ ] IDs cruzados entre unidades são rejeitados (IDOR).
- [ ] Campos de sistema não entram por mass assignment.
- [ ] Ordenação, filtros, tipos, estados e transições usam whitelist.
- [ ] Saída é escapada e erros não vazam detalhes internos.

## Domínio e auditoria

- [ ] Invariantes do agregado são validadas no Service.
- [ ] Mutações relevantes geram auditoria mascarada.
- [ ] Histórico append-only não pode ser editado ou excluído.
- [ ] Request bruto, secrets, tokens, cookies e authorization não são persistidos.
- [ ] Fluxos de domínio não executam exclusão física.
- [ ] Estado terminal e dependências preservam histórico.

## Concorrência e precisão

- [ ] Operações críticas revalidam dentro de transação/lock.
- [ ] Multi-lock usa ordem determinística e libera em `finally`.
- [ ] Unique/índice protege invariantes possíveis no banco.
- [ ] Harness entre processos demonstra o resultado concorrente esperado.
- [ ] Dinheiro usa DECIMAL/string, sem `float` em regra de negócio.
- [ ] Datas concretas usam UTC e timezone da unidade.
- [ ] Intervalos mantêm semântica semiaberta.

## Regressão e operação

- [ ] `verify-fast` passa durante a implementação.
- [ ] `verify-full` passa antes da homologação.
- [ ] Self-test completo mantém todas as fases anteriores verdes.
- [ ] Install/upgrade é idempotente.
- [ ] Uninstall preserva todas as tabelas e dados.
- [ ] sistema legado permanece igual ao baseline.
- [ ] Core Rise permanece igual ao baseline disponível.
- [ ] Logs PHP/Rise/Apache não contêm erro novo relevante.
- [ ] Restrições de infraestrutura são registradas, não ocultadas.

## Documentação e versão

- [ ] Documentação arquitetural e temática reflete o código real.
- [ ] `CURRENT_STATE.md` substitui o estado anterior.
- [ ] `HANDOFF.md` registra testes, pendências e próxima ação.
- [ ] Relatório da tarefa é criado em `docs/reports/`.
- [ ] README aponta para a documentação operacional.
- [ ] Bump de versão só ocorre após todos os critérios obrigatórios.
- [ ] Metadata, constante, setting, dashboard e documentos exibem a mesma versão.

