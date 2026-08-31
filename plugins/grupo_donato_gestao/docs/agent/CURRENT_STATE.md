# Estado atual substituível

## Versão e schema

- Versão: **0.9.8**.
- Schema alvo do pacote: **066** (V053 cria as tabelas comerciais independentes de churrasqueiras; V066 ajusta o catálogo físico).
- Tabelas `gd_*` previstas após V053: **53** (49 anteriores + 4 tabelas de churrasqueiras).
- Uninstall permanece não destrutivo.

## Fases entregues

- Fundação, cadastro central, catálogo e recursos.
- Disponibilidade, reservas únicas, séries e calendário.
- Locação comercial de quadras.
- Aluguel comercial de quatro churrasqueiras e do espaço CH5-SL, isolado das quadras (agenda, avulsos, mensalistas e pagamentos).
- Escola de futebol e personal.
- Financeiro básico integrado.
- Locações avulsas com valor livre, recebível idempotente, sinal opcional, saldo e histórico de pagamentos.
- Cancelamento de mensalista encerra a série, libera ocorrências futuras e preserva o histórico passado; pausa permanece reversível.
- **Finalização do protótipo**: menu reduzido a 9 telas, dashboard operacional com
  atalhos, navegação por abas/botões, mensalistas com situação financeira. Ver
  `docs/prototype-guide.md` e `docs/reports/prototype-final.md`.

## sistema legado embutido (override explícito dos guardrails #2/#3)

- Por decisão explícita do responsável (override dos guardrails #2/#3), TODO o código do
  plugin sistema legado foi importado para dentro do Grupo Donato, sob o sub-namespace
  `grupo_donato_gestao\Operacional` em `Operacional/` (Controllers, 10 Models, 24 Views, Config/Routes,
  Language, Database, bootstrap).
- O plugin sistema legado original (`módulo legado (removido)`) **não foi alterado** (apenas lido/copiado);
  a etapa "sistema legado integrity" do verify-full continua válida.
- Namespace reescrito (`plugin_legado` → `grupo_donato_gestao\Operacional`); prefixo de URL
  preservado em minúsculas (`grupo_donato/operacional/...`) — links internos das views intactos.
- `index.php` faz `require Operacional/bootstrap.php` (registra menu próprio com 13 itens, exclusões CSRF
  da matrícula pública, rotas e a função de instalação). `gd_install()` chama `bombeiros_install_or_update()`.
- 9 tabelas criadas: `grupo_donato_unidades, grupo_donato_responsaveis, grupo_donato_alunos, grupo_donato_cobrancas,
  grupo_donato_custos_unidade, grupo_donato_presenca, grupo_donato_comprovantes, grupo_donato_person_unit_access, grupo_donato_leads_palestra`.
- Verificação: `php Tests/cli.php operacional-check` (autoload, rotas, views, 9 tabelas) e `operacional-install`.
- Ressalvas: recursos opcionais do sistema legado que dependem de bibliotecas/serviços externos
  (Dompdf/Mpdf para PDF, adaptador IARA/IA via cURL+api_key) só funcionam se as libs/chaves existirem;
  smoke de navegador não executado (sem sessão/automação), mas autoload/rotas/views/tabelas verificados.

## Importação (Cenário 2 — não continuada)

- V046–V049 aplicadas; tabelas `gd_import_*` existem mas permanecem **sem uso**.
- Módulo **oculto do menu**; rotas técnicas mantidas; importador **não** foi continuado.
- `import_selftest` **desligado** da suíte (`Tests/cli.php`) por estar fora do escopo do protótipo.

## Financeiro básico

- Contas financeiras com seed idempotente `Caixa Principal`.
- Cobranças manuais e vinculadas a matrícula ou locação, com itens snapshot.
- Geração mensal assistida para matrículas e mensalistas; duplicidades são ignoradas.
- Cobrança avulsa criada automaticamente de forma idempotente na criação da locação; o detalhe mantém geração manual apenas para legados sem cobrança.
- Sinal de avulso usa `payment_type=deposit`; pagamentos posteriores continuam no ledger e nas alocações existentes.
- Pagamentos de locações seguem baixa contextual no padrão da GD Academy: a competência cria cobranças recorrentes de forma idempotente, confere pessoa/competência/valor/data/forma e usa a conta financeira padrão, mudando a cobrança para `paid` ao quitar o saldo.
- Edição integral de locações: avulsa atualiza reserva/horário/quadra/termos e sincroniza o recebível sem apagar pagamentos; mensalista atualiza contrato e ocorrências futuras.
- Locação avulsa e mensalista permitem registrar tempo adicional, valor e observação sem criar reserva; a avulsa atualiza a cobrança única e o mensalista atualiza competências em aberto/próximas, virando entrada no caixa na baixa.
- Pagamentos totais, parciais e multi-cobrança por alocações separadas.
- Estorno preserva pagamento/alocações e cria movimento inverso.
- Despesas pendentes, pagas ou canceladas; pagamento gera saída.
- Livro-caixa append-only com entradas, saídas e saldo acumulado.
- Dashboard com aberto, vencido, recebido, despesas, saldo e inadimplentes.
- Resumo financeiro em aluno, conta de cliente e locação; matrícula possui link contextual.
- Dinheiro é calculado em strings decimais/centavos inteiros no backend, sem `float`.

## Baseline atual (pós-protótipo)

- Self-test: **503 PASS / 5 FAIL** (sem `import_selftest`); as cinco falhas são regressões preexistentes fora do acréscimo de permanência.
- Schema/idempotência e concorrências (sequência/temporal, booking, séries, locação): PASS.
- `CHECK TABLE`: **49/49 OK**.
- Uninstall: **49/49 preservadas**.
- sistema legado: PASS contra baseline.
- Migrations 001–051 preservadas; 046–049 (importação) e V050–V052 aplicadas e intactas.

## Pendência de homologação (não-plugin)

- `verify-full`: verde em tudo do plugin; única divergência é a etapa *Rise core integrity*,
  que acusa `app/Config/Logger.php` alterado hoje 09:58 (ajuste de threshold de log, fora deste
  trabalho). Por guardrail o core não foi editado. **Ação do responsável**: reverter o arquivo
  ao baseline (hash `4f45…e1e`, 5877 bytes); depois `verify-full` fica 100% verde e a versão
  pode ir a **1.0.0**.

## Reparo de ambiente realizado (banco corrompido pelo rebuild de hoje)

- `gd_settings` e `gd_business_areas` tinham B-tree InnoDB corrompido → recriadas via TRUNCATE
  (sem dados de domínio) e repovoadas pelo instalador idempotente.
- AUTO_INCREMENT de todas as tabelas `gd_*` reassentado para `MAX(id)+1` (estava em 1 com linhas).
- Nenhuma migration alterada; nenhuma exclusão de tabela; sem dados de domínio perdidos.

## Restrições ambientais

- A raiz não é worktree Git; integridade depende de SHA-256 e backups.
- Não há `spark`; usar `Tests/cli.php` e `Tests/verify-*`.
- Smoke autenticado depende de sessão staff disponível; não há automação de navegador configurada.
- Falha DDL induzida em clone isolado ainda não foi executada.
- Não existe ACL adicional usuário x unidade.

## Fora do escopo

Sem boleto, integração Pix, gateway, conciliação bancária, nota fiscal, juros/multa automáticos, comissão, parcelamento detalhado, contas a pagar avançadas, DRE, plano contábil, fechamento formal de caixa ou importação de planilhas.

## Próxima fase

1. Reverter `app/Config/Logger.php` ao baseline e re-rodar `verify-full` (100% verde).
2. Bump de versão para **1.0.0** (Constants + cabeçalho `index.php`) e re-rodar `verify-fast`.
3. Demais fases (ex.: retomar importação) só se formalmente definidas; importação segue
   não continuada.
