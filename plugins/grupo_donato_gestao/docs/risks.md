# Riscos Técnicos

Probabilidade (P) e Impacto (I): B=baixo, M=médio, A=alto.

## Estado da Fase 2B

- **Sobreposição/versionamento de preços:** mitigados por vigência, unique do
  escopo ativo, `GET_LOCK`, rejeição de intervalos sobrepostos e auditoria de
  valor/vigência. Alterar preço ainda é update auditado nesta fase, não um
  documento financeiro imutável.
- **Concorrência de padrões:** uma lista padrão por unidade e uma variação padrão
  por produto são protegidas por coluna gerada+unique e troca transacional.
- **Catálogo multiunidade:** services, models, joins e dropdowns restringem a
  unidade ativa; área/centro globais são exceções explícitas. ACL usuário×unidade
  continua futura.
- **JSON arbitrário:** metadata/attributes aceitam apenas JSON válido, têm limite
  de tamanho e são escapados na saída; não existe execução do conteúdo.
- **Precisão decimal:** banco usa `DECIMAL`; validação/seleção usam strings e
  `decimalCompare`, sem cálculo de preço por `float`.
- **Crescimento da resolução:** a consulta está indexada por unidade/lista/produto
  e filtra data/quantidade; volume futuro deve ser medido antes de ampliar para
  múltiplas listas ou promoções.
- **Seeds reais:** Q2–Q6 são cadastros conhecidos, mas área, centro, capacidade,
  dimensões e preços permanecem nulos até validação. `DEFAULT` não contém preço.
- **Dependência futura da agenda:** `is_bookable` não implementa disponibilidade,
  conflito nem reserva; a Fase 3A passou a consumir recursos sem misturar produto e
  ocupação.

## Estado da Fase 2A

- **Isolamento multiunidade:** mitigado no cadastro central por escopo backend em models,
  services e joins; `unit_id` do navegador é ignorado. ACL usuário×unidade permanece futura.
- **PII em documento/contato/endereço:** mitigado por saída mascarada, auditoria mascarada,
  escape HTML e ausência de payload bruto em logs.
- **Duplicidade:** detecção é assistiva e limitada; documento exato exige confirmação.
  Merge continua proibido, portanto revisão humana ainda é necessária.
- **Nomes aproximados:** a janela de 100 registros limita custo, mas pode não sugerir um
  homônimo antigo. Sinais exatos não dependem dessa janela.
- **Browser:** console JavaScript não foi automatizado; permanece restrição de homologação.

## R-01 — Plugin não usa migrations (P:A · I:M)
Schema por SQL idempotente no hook; erro de idempotência pode corromper instalação.
**Mitigação:** instalador modular + helpers `gd_ensure_*`; rodar 2x em CI; versionar
`schema_version`; nunca DDL destrutivo automático. Ver migration-strategy.md.

## R-02 — Acoplamento indevido com o núcleo do Rise (P:M · I:M)
Referenciar `rise_invoices`/`rise_orders`/`rise_clients` cria dependência de versão do
Rise (3.9.6). Mudanças no core podem quebrar integrações.
**Mitigação:** integração por adaptadores finos; colunas `rise_*_id` opcionais; domínio
funciona sem o espelhamento; testes de integração por versão.

## R-03 — Complexidade de agenda/recorrência (P:A · I:A)
Recorrência + conflitos + bloqueios de evento (montagem/limpeza) + múltiplos recursos é a
parte mais difícil. Risco de dupla marcação e de performance na materialização.
**Mitigação:** `reserva_series`+ocorrências, `ConflitoService` com lock, RRULE testada com
casos de borda (feriado, DST, virada de mês), janela deslizante por job.

## R-04 — Consistência financeira (P:M · I:A)
`valor_pago` derivado de alocações, estornos, créditos e caixa precisam fechar sempre.
**Mitigação:** imutabilidade (D-06), tudo em transação (D-12), tolerância (D-09),
reconciliador `gd:reconcile-financeiro`, auditoria append-only, testes de invariantes
(Σ alocações = valor_pago; saldo caixa = abertura+movimentos).

## R-05 — Concorrência (P:M · I:A)
Numeração, baixa de estoque, fechamento de caixa e reserva sob acesso simultâneo.
**Mitigação:** `FOR UPDATE`/locks, `UNIQUE` + idempotência (D-08, D-10).

## R-06 — Multiunidade mal isolada (P:M · I:A)
Repetir a falha do sistema legado (unidade ativa em sessão sem revalidação) vaza dados entre
unidades. **Mitigação:** revalidar escopo por request, filtro row-level em todas as
queries, testes de autorização cruzada.

## R-07 — Migração de dados do legado/sistema legado (P:A · I:M)
Importar base atual (alunos/cobranças/pagamentos do sistema legado ou planilhas) é propenso a
sujeira e regras divergentes (turma texto, pagamento na cobrança).
**Mitigação:** módulo de Importação com preview/correspondência/erros; de/para explícito;
nunca importar direto em produção; reconciliação pós-importação.

## R-08 — Escopo amплo / over-engineering (P:A · I:M)
21 módulos podem levar a esforço excessivo antes de valor entregue.
**Mitigação:** roadmap por fases com caminho crítico (0→1→2→3→4→6→7); entregar MVP
operacional cedo (escola + financeiro + caixa) e evoluir.

## R-09 — Performance sem paginação/índices (P:M · I:M)
Volume de presenças, movimentos, cobranças cresce rápido.
**Mitigação:** paginação server-side (D-22), índices do database-plan, snapshots de saldo
se necessário (D-22/D-23).

## R-10 — LGPD e dados médicos (P:M · I:A)
Atestados/saúde e PII de menores exigem cuidado legal.
**Mitigação:** `documentos.sensivel`, consentimentos, acesso restrito + log, minimização
(D-20/D-21). Validar política de retenção com o cliente.

## R-11 — Endpoints públicos abusáveis (P:M · I:M)
Formulário público (reserva/lead) sem proteção vira vetor de spam/fraude (falha do
sistema legado). **Mitigação:** honeypot + rate-limit + OTP quando aplicável; CSRF-exempt
apenas no grupo público.

## R-12 — Dependência do ambiente XAMPP/MySQL (P:B · I:M)
DDL não transacional no MySQL; charset/collation; timezone do servidor.
**Mitigação:** passos pequenos idempotentes; UTC no banco; definir collation
(`utf8mb4`) e engine (InnoDB) explícitos no schema.

## R-13 — Integrações externas instáveis (P:M · I:M)
Gateways de pagamento/mensageria/fiscal podem falhar/duplicar.
**Mitigação:** idempotência, fila/retry, log de integração, degradação graciosa,
isolamento no módulo Integrações (lição IARA do sistema legado).

## R-14 — Conflito com outros plugins/menus (P:B · I:B)
Colisão de chaves de menu/rotas/hook com plugins futuros.
**Mitigação:** prefixo `gd_`/`grupo_donato_gestao` em tudo; nome de hook de instalação =
nome da pasta (lição do sistema legado).

## R-15 — Manutenção/competência da equipe (P:M · I:M)
Arquitetura em camadas (Services/Repositories/Events) é mais exigente que o estilo
procedural do Rise. **Mitigação:** convenções documentadas, base `Gd_Controller`/Service,
exemplos por módulo, revisão de código contra a checklist de decisões.

## R-16 — Tempo civil, DST e meia-noite (P:M · I:A)

Converter horário semanal como se fosse UTC pode deslocar disponibilidade ou criar instantes inexistentes/ambíguos. **Mitigação:** timezone IANA da unidade, conversão centralizada, rejeição explícita de gaps/folds DST, testes overnight e persistência UTC de instantes.

## R-17 — Corrida em sobreposições (P:M · I:A)

Duas escritas simultâneas poderiam validar antes uma da outra. **Mitigação:** `GET_LOCK` por unidade/recurso, revalidação transacional, unique de duplicata exata e teste entre processos.

## R-18 — Consulta de calendário sem limite (P:M · I:M)

Janelas extensas multiplicam ocorrências semanais. **Mitigação:** limite padrão de 93 dias, configuração limitada, filtros backend e ausência de dados pessoais/financeiros no feed.

## R-19 — Dupla ocupação em reservas (P:M · I:A)

Duas reservas poderiam passar pela validação simultaneamente. **Mitigação realizada:** `GET_LOCK` por unidade/recurso em ordem numérica, revalidação de disponibilidade e conflito sob locks, consulta semiaberta sobre ocupação com buffers e harness concorrente entre processos.

## R-20 — Holds órfãos e edição concorrente (P:M · I:M)

Hold vencido poderia bloquear até o cron ou uma edição obsoleta sobrescrever outra. **Mitigação realizada:** conflito ignora hold vencido independentemente da limpeza; lote cron/CLI é idempotente; `lock_version` protege updates.
