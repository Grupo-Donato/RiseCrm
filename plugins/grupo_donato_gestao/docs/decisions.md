# Decisões Técnicas (ADRs)

Decisões transversais que governam todo o plugin. Cada uma tem ID estável.

## Decisões realizadas na Fase 2A

### D-35 — Conta não é pessoa

Conta é o titular comercial futuro; pessoa é o indivíduo. A relação N:N guarda papéis.
Nenhum contrato, cobrança ou reserva foi criado nesta fase.

### D-36 — Unidade fechada no backend

Conta, pessoa e relação pertencem à unidade ativa resolvida por `UnitContextService`.
Não há pessoa compartilhada nem ACL usuário×unidade. IDs de unidade do browser são ignorados.

### D-37 — Contato oficial da pessoa

`gd_contact_methods` é a única fonte para contatos de indivíduos. `gd_people` não possui
colunas concorrentes de e-mail/telefone/WhatsApp.

### D-38 — Duplicidade assistiva

Sem merge automático. Documento exato exige confirmação; demais sinais orientam o usuário.
Overrides são auditados. Comparação aproximada tem janela limitada.

### D-39 — Principais transacionais

Relação principal, contato principal por tipo e endereço principal são trocados em transação
e protegidos por unique sobre coluna gerada para o estado ativo.

### D-40 — Exclusão lógica com motivo

Conta com relação ativa é bloqueada. Pessoa encerra relações e inativa contatos. Relação é
encerrada; contato/endereço usam soft delete. Auditoria nunca é removida.

### D-41 — Rise opcional

`rise_client_id`, `rise_user_id` e `rise_contact_id` só são aceitos quando o alvo existe.
Não há criação automática nem dependência obrigatória.

### D-42 — País padrão sem dado comercial

Configuração global opcional sugere país em novo endereço. O seed não define valor.

## Decisões realizadas na Fase 2B

### D-43 — Catálogo unificado

Produto, serviço, locação e taxa compartilham `gd_products`; tipo, modo de cobrança
e unidade de medida classificam o item. `credit`/`discount` permanecem reservados.

### D-44 — Recurso não é produto

`gd_resources` representa o objeto físico potencialmente ocupável; `gd_products`
representa o que poderá ser vendido. Preço pode especializar produto por recurso,
sem embutir valor, disponibilidade ou agenda no recurso.

### D-45 — Área e centro opcionais

Produto e recurso podem não ter área/centro. Quando presentes, são ativos, globais
ou da unidade e compatíveis entre si. Q2–Q6 são seedadas sem área/centro.

### D-46 — JSON textual validado

Metadata e attributes usam `MEDIUMTEXT`, seguindo o host, e são validados/reencodados
antes de persistir. A UI usa textarea; não foi introduzido tipo JSON nativo.

### D-47 — Decimal exato no pricing

`amount`/`reference_cost` usam `DECIMAL(15,2)` e quantidade `DECIMAL(15,3)`.
Normalização e comparação usam strings; nenhum cálculo de resolução depende de float.

### D-48 — Padrões únicos

Há no máximo uma tabela padrão ativa por unidade e uma variação padrão ativa por
produto. Colunas geradas+unique protegem o banco; Services usam lock/transação.

### D-49 — Precedência de preço

Lista explícita válida ou a padrão ativa é escolhida primeiro. A ordem é
variação+recurso, produto+recurso, variação, produto-base; depois maior quantidade
mínima aplicável, vigência mais recente e ID. Variação/recurso inativo não causa
fallback silencioso.

### D-50 — Unicidade normalizada

Códigos, padrões e escopos ativos usam colunas geradas `PERSISTENT` que retornam
NULL fora do estado protegido, permitindo soft delete sem bloquear novo cadastro.

### D-51 — Seeds mínimos reais

Q2–Q6 e `DEFAULT` são idempotentes. Não são seedados capacidade, dimensão, área,
centro, produto ou preço comercial não confirmado.

### D-52 — Limites explícitos da Fase 2B

Variações não têm saldo; recursos não têm agenda; preços não geram venda, cobrança
ou pagamento. Estoque, PDV, agenda/reserva e financeiro não foram iniciados.

## D-01 — Valores monetários
`DECIMAL(15,2)` no banco (não `double` como o core do Rise, para evitar erro de ponto
flutuante em somatórios financeiros). Entrada/saída via helpers Rise (`to_currency`,
`unformat_currency`); comparações usam tolerância de arredondamento (D-09). Quantidades
`DECIMAL(15,3)`; percentuais `DECIMAL(7,4)`. Moeda única (BRL) inicialmente.

## D-02 — Integrar o Rise, não duplicar
O domínio do Donato é mais rico que o núcleo do Rise. Decisão: **tabelas próprias `gd_*`**
para o domínio, **referenciando** entidades Rise (`clients`, `users`, `items`, `invoices`,
`orders`, `expenses`) por colunas `rise_*_id` quando houver ganho de integração (ex.:
emitir fatura nativa, aparecer no faturamento do Rise). Identidade não é duplicada: a
fonte da verdade do domínio é o plugin; o Rise é integração opcional por entidade.

## D-03 — Datas e fuso horário
`DATE` para datas de negócio; `DATETIME` em **UTC** para instantes. Exibição convertida
para o fuso da unidade/usuário via helpers Rise (`format_to_date/datetime`,
`convert_date_*`). Agenda usa o timezone da **unidade** (`gd_unidades.timezone`) como
referência para reservas/aulas.

## D-04 — Status
`VARCHAR(30)` validado por **Enum PHP** (não `ENUM` de banco — evolução sem `ALTER`
arriscado, como o sistema legado precisou fazer). Transições de status passam por Service
(máquina de estados), nunca update direto.

## D-05 — Exclusão lógica
Padrão `deleted TINYINT(1)` (compatível com `Crud_model`). **Exceções append-only**
(nunca soft-delete, nunca update): `auditoria`, `*_movimentos`, `pagamento_alocacoes`,
`recibos`, `*_historico`, `*_versoes`, `consentimentos`, `caixa_movimentos`,
`estoque_movimentos`. Mestres com lançamentos são **inativados**, não excluídos.

## D-06 — Imutabilidade financeira
Cobrança, pagamento, alocação e recibo são **imutáveis após emissão**. Correções:
- cobrança errada → **cancelamento** (status) + nova cobrança;
- pagamento errado → **estorno** (pagamento negativo vinculado `estorno_de_id`);
- nunca editar valor/linha de documento financeiro emitido.

## D-07 — Estornos
Estorno é lançamento de sinal oposto que referencia o original (`estorno_de_id`,
alocação `tipo=estorno`). Recalcula `valor_pago` da(s) cobrança(s) afetada(s). Exige
permissão `pagamentos:admin` + motivo + auditoria. Caixa: estorno gera movimento próprio.

## D-08 — Idempotência
Operações sensíveis a retry (pagamento, importação de linha, geração de cobrança
recorrente, webhook de gateway) carregam `idempotency_key`/chave natural com índice
**UNIQUE**, evitando duplicidade. Ex.: cobrança recorrente única por
(origem, competência); pagamento por `idempotency_key`.

## D-09 — Tolerância de arredondamento
Comparações de "quitado" usam tolerância (ex.: `< 0,02`), espelhando o
`paid_status_tolarance` do Rise. Centralizada num helper `Money::isSettled()`.

## D-10 — Concorrência
- Numeração e baixa de estoque/caixa: `SELECT … FOR UPDATE` dentro de transação.
- Reserva: checagem de conflito + `UNIQUE`/lock no recurso+intervalo para evitar
  corrida de dupla marcação.
- Saldos derivados (estoque, créditos, caixa) calculados de movimentos sob transação.

## D-11 — Controle de versão (documentos)
Contratos e preços versionam de forma imutável (`contrato_versoes`, vigências de
`precos`). Editar = nova versão com vigência; histórico preservado.

## D-12 — Transações
Toda operação que toca >1 tabela roda em transação no Service (não no controller, como o
sistema legado fazia). Padrão: `try { transStart … transComplete } catch { rollback + DomainException }`.
Sucesso emite **evento de domínio** (após commit) para auditoria/estoque/caixa.

## D-13 — Auditoria
Na Fase 1, auditoria explícita via `AuditService` nos CRUDs da fundação, em
`gd_audit_logs`. Não há listener global dos hooks `app_hook_data_*` nem eventos de domínio.
O model é append-only e mascara dados sensíveis. A estratégia de eventos permanece futura.

## D-14 — Multiunidade
Unidade ativa em sessão é revalidada no backend; somente unidades ativas são aceitas.
Na Fase 2A, contas, pessoas e filhos são estritamente scoped à unidade ativa. ACL por
`gd_usuario_unidade`, compartilhamento de identidade e consolidação são futuros.

## D-15 — Numeração de documentos
Fase 1: `gd_sequences` única por (unidade, tipo), com `prefix`, `padding`, reset anual e
`last_reset_year`. Incremento usa transação e `FOR UPDATE`. Formatos comerciais são futuros.

## D-16 — Recorrência
Padrão **regra + ocorrências**: `reserva_series` (RRULE iCal) gera `reservas`
materializadas por job com janela deslizante (ex.: próximos 90 dias). Cobranças
recorrentes geradas por job a partir de matrícula/contrato/locação mensal. Alterar a
regra não muda ocorrências passadas.

## D-17 — Conflitos de agenda
`ConflitoService` valida, antes de confirmar reserva, sobreposição em `reserva_recursos`
(mesmo recurso/intervalo) e `bloqueios`. Eventos somam blocos de montagem/limpeza.
Resolução: rejeitar, sugerir alternativa, ou permitir overbooking explícito (config).

## D-18 — Segurança
Controllers estendem `Security_Controller`. CSRF nativo do Rise nos POSTs autenticados.
Endpoints públicos isolados, isentos de CSRF mas com honeypot + rate-limit + (quando
aplicável) OTP. Autorização centralizada no `AccessService`; row-level por unidade.
Sem SQL por concatenação (query builder/binds).

## D-19 — Uploads
Fase 1 não implementa uploads nem `gd_arquivos`. Fases futuras devem reutilizar
`app_files_helper`/`files/`; tabela própria só será criada se houver metadado de domínio real.

## D-20 — Dados médicos / sensíveis
Atestados/restrições de saúde como `gd_documentos.sensivel=1`: acesso restrito
(`clientes:admin`), leitura logada, retenção mínima. Não exibir em listas gerais.

## D-21 — LGPD
Base legal e finalidade em `gd_consentimentos`; direito de revogação (append-only);
exportação/eliminação de dados pessoais sob processo controlado; minimização (não copiar
PII desnecessária entre tabelas — snapshots só em recibos/versões por imutabilidade).

## D-22 — Performance
Auditoria, contas e pessoas usam paginação server-side e whitelist; seções filhas têm
limite e ordem fixa. Cadastros pequenos da fundação permanecem client-side.

## D-23 — Escalabilidade
Jobs de cron com lotes e janelas (recorrência, inadimplência, lembretes). Operações
em massa idempotentes e retomáveis. Estrutura modular permite ativar/evoluir módulos
isoladamente.

## D-24 — Nomenclatura
Prefixo `gd_`. **Proibido reaproveitar prefixos/nomes internos da marca anterior.** Classes PSR-ish
dentro do padrão do Rise (`Gd_xxx_model`, controllers `Ucfirst`). Sem nomes internos do sistema legado.

## D-25 — Respostas e erros
Formato único de resposta AJAX `{success, message, data}` (vs. o sistema legado inconsistente).
Na Fase 1, validações retornam JSON via `Gd_Controller`; acesso negado retorna JSON em
POST/AJAX ou redirect `forbidden` em GET. Exceções tipadas de domínio ficam para fases futuras.

## D-26 — DBPrefix

Código operacional usa `getPrefix()`/`prefixTable()` e nomes lógicos `gd_*`. `rise_` só
aparece em documentação/resultado de homologação. Nenhum patch no core.

## D-27 — Charset e collation

Herdar o ambiente Rise homologado: `utf8`/`utf8_general_ci`, InnoDB. Não forçar utf8mb4
em tabelas do plugin para evitar mistura de collations com o host. Limite conhecido: sem
caracteres Unicode de quatro bytes enquanto o Rise permanecer em utf8.

## D-28 — JSON como texto

`before_data`, `after_data`, `metadata` e values JSON usam `MEDIUMTEXT` + `json_encode`.
Não usar tipo JSON nativo nesta fundação.

## D-29 — UTC e autoria

`Gd_Model` define `created_at/updated_at` em UTC; controllers/services definem
`created_by/updated_by`. Campos de auditoria não são aceitos do request.

## D-30 — Schema runner próprio

Versões 001–012, lock por banco/prefixo, estados, retry, reconciliação idempotente e marker.
MariaDB DDL não recebe promessa de rollback transacional.

## D-31 — Permissões nativas do Rise

Chaves planas `gd_*` via hooks reais de papéis. `manage` implica `view`; autorização e menu
usam a mesma semântica. Não existe tabela de permissão própria.

## D-32 — Sem purge e sem segredos

Uninstall preserva dados. Não há purge. Settings secretos são recusados até existir
mecanismo seguro de criptografia/gestão de chave validado.

## D-33 — CLI sem spark

Esta distribuição não contém `spark`; `Tests/cli.php` inicializa o console do CI4 e é a
interface suportada para instalação/self-test. As classes `Commands/` permanecem
compatíveis, mas não são o caminho operacional neste host.

## D-34 — Outbox e módulos futuros

Outbox, tabelas operacionais e integrações externas não pertencem à Fase 1. Serão novas
versões somente quando uma fase futura exigir.

## Decisões realizadas na Fase 3A

### D-53 — Instantes UTC e horário semanal local

Exceções/bloqueios são instantes UTC; regras usam weekday e `TIME` no timezone IANA da unidade. Horários DST inexistentes ou ambíguos são rejeitados.

### D-54 — Intervalos semiabertos

Todo conflito usa `[start,end)`: `new_start < existing_end AND new_end > existing_start`. Adjacência é permitida.

### D-55 — Precedência fechada

Recurso inválido → bloqueio → closed → open → regra semanal → indisponível. Ausência de regra nunca abre o recurso.

### D-56 — Locks temporais

Regras, exceções e bloqueios serializam saves por recurso com `GET_LOCK` e transação. Duplicata exata também possui proteção de banco.

### D-57 — Exceção não é bloqueio

Exceção corrige abertura; bloqueio registra impedimento operacional. Sobreposição confirmada é auditada e nunca mesclada automaticamente.

### D-58 — Calendário read-only de fundação

FullCalendar 5.5.1 do Rise projeta somente disponibilidade, exceções e bloqueios em janela limitada. Nenhum cliente, preço ou reserva é retornado.

### D-59 — Permissões temporais mínimas

Gestão temporal implica calendário e leitura de recurso, mas não gestão do cadastro físico.

### D-60 — Limite da Fase 3A

Reservas, holds, séries, ocorrências, recorrência e conflitos de ocupação pertencem exclusivamente à Fase 3B.

## Decisões realizadas na Fase 3B1

### D-61 — Reserva única separada de recorrência

`gd_bookings` representa apenas uma ocupação concreta. Série/RRULE não foi antecipada.

### D-62 — Ocupação inclui buffers por recurso

Horário nominal fica na reserva; ocupação calculada fica na relação recurso e governa conflito e disponibilidade.

### D-63 — Estados bloqueantes explícitos

Hold válido, pending, confirmed e in progress bloqueiam. Estados terminais e hold vencido não bloqueiam.

### D-64 — Locks ordenados e lock version

Recursos são bloqueados em ordem numérica com `GET_LOCK`; edição usa compare-and-swap por `lock_version`.

### D-65 — Histórico duplo e privacidade

`gd_booking_events` mantém histórico operacional append-only; auditoria geral permanece mascarada. Calendário sem booking view mostra somente “Ocupado”.

### D-66 — Sem efeito comercial

Tipo de reserva é classificação operacional. Nenhum preço, contrato, cobrança, pagamento ou check-in comercial é gerado.
