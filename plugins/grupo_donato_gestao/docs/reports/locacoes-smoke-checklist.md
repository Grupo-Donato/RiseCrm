# Smoke autenticado — locações (manual)

Requer instalação real do Rise com sessão de staff logada e permissões `gd_court_rentals_*`, `gd_calendar_view`, `gd_bookings_view`, `gd_booking_series_view`, `gd_finance_view`, `gd_receivables_manage`, `gd_payments_manage`. Testar em **desktop**, **tablet (~768px)** e **celular (~390px)** ou viewport equivalente (DevTools → device toolbar).

Para cada endpoint AJAX, abrir DevTools → Network e confirmar `Content-Type: application/json` e, em erro de conflito/versão nas ações de ciclo de vida, status **409** com `message`/`error_code`.

## Menu
- [ ] Sidemenu de Locações mostra apenas **Agenda · Reservas · Mensalistas · Financeiro** (+ Cobranças se o plugin estiver ativo). "Séries recorrentes" e "Locações avulsas" **não** aparecem como itens próprios.
- [ ] Abrir `grupo_donato/booking-series` e `grupo_donato/bookings` redireciona para a aba correspondente de Reservas.

## Agenda (`grupo_donato/calendar`)
- [ ] Carrega a grade (semana no desktop, lista no celular); filtro por quadra e por status funcionam.
- [ ] "Disponibilidade padrão" vem **desligada**; ao ligá-la aparecem faixas verdes; ao desligar, somem.
- [ ] Clicar numa reserva abre o detalhe operacional; horários exibidos no fuso da unidade.
- [ ] (Network) `calendar/events` retorna eventos com `extendedProps.booking_id`; para reservas com locação comercial ativa há `court_rental_id`.

## Reservas — 3 abas (`grupo_donato/court-rentals`)
- [ ] Aba **Locações comerciais**: lista carrega; filtrar por **quadra** mostra tanto avulsas quanto mensalistas ligadas àquela quadra.
- [ ] Aba **Ocupações**: 11 colunas; filtros por quadra/tipo/status/cliente/data.
- [ ] Aba **Recorrências**: 9 colunas; filtros por quadra/status/data.

## Modal avulsa (`single-modal`)
- [ ] Seções Cliente / Horário / Condições / Quadras / Avançadas.
- [ ] **Produto** e **Tabela de preço** (área avançada) são **Select2 com busca remota** (digitar filtra; paginação); só aparecem produtos `rental/service/fee` da unidade.
- [ ] "Verificar disponibilidade" retorna disponível/indisponível; sem quadra selecionada bloqueia envio.
- [ ] Salvar cria a locação; ela aparece na Agenda e na aba de Locações. Conflito de horário mostra **mensagem funcional** (modal não congela).

## Modal mensalista (`monthly-modal`)
- [ ] Cliente/contato/título, valor mensal, dia de vencimento, dias da semana, hora início/fim, início, término (aberto/até data/por quantidade), quadras.
- [ ] Produto/tabela de preço = Select2 remoto (idem avulsa).
- [ ] Prévia lista ocorrências no fuso local **sem** persistir.
- [ ] Salvar cria série + locação; aparece na Agenda, na aba de Locações e na tela de Mensalistas.

## Mensalistas (`grupo_donato/court-rentals/monthly`)
- [ ] Tabela com 9 colunas; **Situação financeira** como badge (Em dia / Em aberto / Parcial / Vencido).
- [ ] Em página com muitos contratos, a lista carrega sem lentidão (situação financeira em lote, sem N+1).
- [ ] Ação **Registrar pagamento**: com 1 cobrança aberta abre o modal já com a cobrança selecionada; com várias abre Contas a receber filtradas por aquele contrato (`source_type=court_rental&source_id=ID`).
- [ ] Ações Suspender/Retomar: conflito de `lock_version` mostra mensagem e recarrega (409).

## Detalhe da locação (`court-rentals/view/{id}`)
- [ ] Avulsa: data/hora no fuso da unidade. Recorrente: dias da semana + horário local + próxima ocorrência.
- [ ] Resumo financeiro, vínculos de agenda, condições comerciais, histórico, ações de ciclo de vida.
- [ ] Ações de ciclo de vida (ativar/suspender/retomar/cancelar/concluir) funcionam; cancelar exige motivo; conflito de versão → 409 + recarrega.

## Geração e pagamento
- [ ] "Gerar cobrança" (avulsa) é idempotente: repetir não cria segunda cobrança (mensagem "Já existe uma cobrança para esta locação").
- [ ] Registrar pagamento baixa a cobrança; caixa e contas a receber refletem.

## Preservação (não pode quebrar)
- [ ] Alunos, turmas, presença, mensalidades escolares, pagamentos, despesas, caixa e Financeiro geral continuam funcionando normalmente.
- [ ] Responsividade: filtros/toolbars quebram linha; modais reorganizam colunas; tabelas com `table-responsive`; nenhum scroll horizontal no corpo.
