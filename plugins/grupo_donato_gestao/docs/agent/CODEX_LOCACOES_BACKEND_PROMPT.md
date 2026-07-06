# Prompt para o Codex — finalizar backend das locações do Grupo Donato

Trabalhe no plugin Rise CRM `grupo_donato_gestao`, partindo da versão **0.9.2** desta entrega.

## Objetivo

Concluir e validar o backend necessário para o novo front de locações de quadras, sem reconstruir o sistema e sem alterar os módulos de alunos, turmas, presença, mensalidades escolares, pagamentos, despesas ou caixa, exceto pelos vínculos já previstos com locações.

O front já foi reorganizado e deve ser preservado. A solução final precisa ser funcional, simples e coerente com o Rise CRM.

Leia antes de alterar:

- `docs/LOCACOES_FRONT_AUDIT.md`
- `docs/agent/LOCACOES_BACKEND_CONTRACT.md`
- `Config/Constants.php`
- `Config/Permissions.php`
- `Config/Routes.php`
- `Controllers/Court_rentals.php`
- `Controllers/Calendar.php`
- `Controllers/Bookings.php`
- `Controllers/Booking_series.php`
- `Services/CourtRentalService.php`
- `Services/BookingService.php`
- `Services/BookingSeriesService.php`
- `Services/CalendarService.php`
- `Services/ReceivableGenerationService.php`
- `Services/FinanceService.php`
- migrations `V022` a `V045`

## Restrições obrigatórias

1. Não faça reset, truncate, drop geral, reinstall destrutivo ou limpeza de dados.
2. Não modifique o core do Rise.
3. Não recrie o CSS do Rise e não introduza framework visual novo.
4. Não desfaça a consolidação do menu e das telas.
5. Não volte a expor “Reservas”, “Séries recorrentes” e “Locações avulsas” como três itens separados no sidemenu.
6. Não altere regras de alunos/escola que já estão funcionando.
7. Não crie tabelas novas antes de comprovar que as 49 tabelas atuais não atendem.
8. Caso uma migration aditiva seja realmente necessária, crie uma nova versão após `049`, idempotente, sem alterar dados antigos e atualize schema/version/marker conforme o padrão do plugin.
9. Preserve escopo por unidade, permissões, CSRF, auditoria, soft delete, locks e `lock_version`.
10. Não substitua services existentes por lógica duplicada em controllers.
11. Não faça alterações cosméticas fora de locações.

## Entendimento obrigatório do domínio

- `gd_bookings` representa uma ocupação concreta.
- `gd_booking_series` representa uma regra recorrente.
- `gd_court_rentals` representa o acordo comercial.
- `gd_court_rental_schedule_links` liga o acordo à reserva ou série.
- `gd_receivables` representa cobranças.
- `gd_payments` e `gd_payment_allocations` representam pagamentos.

A unificação feita no front é apenas de navegação. Não fundir essas entidades.

## Etapa 1 — auditoria técnica antes de codar

Faça uma inspeção completa e registre em `docs/reports/locacoes-backend-audit.md`:

- estado das migrations 001–049;
- tabelas e índices relevantes;
- rotas usadas pelo novo front;
- permissions usadas por cada endpoint;
- formato real das respostas JSON;
- consistência de timezone;
- filtros de listagem;
- fluxo de criação avulsa;
- fluxo de criação recorrente;
- geração financeira;
- concorrência e transações;
- gaps encontrados.

Não altere nada até terminar essa auditoria.

## Etapa 2 — corrigir os pontos obrigatórios

### 2.1 Filtro de quadra em locações

Corrija `CourtRentalService::queryList()`.

Hoje o filtro `resource_id` considera apenas `gd_booking_series_resources`. Ele deve funcionar para:

- recorrentes ligadas a séries;
- avulsas ligadas a bookings;
- somente links ativos, não históricos e `deleted=0`;
- somente recursos da unidade ativa.

A mesma regra deve ser usada na contagem filtrada e na consulta de dados.

Crie teste cobrindo avulsa e recorrente.

### 2.2 Resumo de agenda no timezone da unidade

Crie uma representação canônica do horário da locação no service/DTO:

- avulsa: data e hora inicial/final locais;
- recorrente: dias da semana, hora local e próxima ocorrência local;
- quadras vinculadas;
- sem usar substring de timestamp UTC.

O controller possui uma correção de apresentação temporária. Após o service retornar o valor correto, simplifique o controller sem quebrar a view.

Crie testes de timezone, inclusive virada de dia e horário que cruza meia-noite.

### 2.3 JSON consistente

Revise todos os endpoints AJAX usados pelo módulo:

- `calendar/events`
- `court-rentals/list-data`
- `court-rentals/monthly-data`
- `court-rentals/customer-options`
- `court-rentals/contact-options`
- `court-rentals/check-availability`
- `court-rentals/preview`
- `court-rentals/resolve-price`
- `court-rentals/save-single`
- `court-rentals/save-monthly`
- `court-rentals/reprice`
- lifecycle de locação
- listagens de bookings e séries

Use `response->setJSON()` ou helper equivalente e defina status HTTP coerente. Nunca retorne warning/HTML em endpoint JSON.

Formato mínimo:

```json
{"success":true,"message":"..."}
```

Erro:

```json
{"success":false,"message":"...","error_code":"gd_..."}
```

Para conflito de agenda ou versão, use HTTP 409.

### 2.4 Calendário e vínculo comercial

No retorno de `CalendarService::events()`:

- mantenha `booking_id`;
- inclua `court_rental_id` quando houver link comercial ativo;
- mantenha status, booking type e recursos em `extendedProps`;
- respeite filtros recebidos;
- não misture disponibilidade padrão quando ela não foi solicitada.

O front pode continuar abrindo o booking. Apenas disponibilize o vínculo comercial para evolução e diagnóstico.

### 2.5 Busca amigável de produto e tabela de preço

Os formulários mantêm `product_id` e `price_list_id` na área avançada. Crie endpoints Select2 para:

- produtos ativos da unidade ou globais permitidos;
- apenas tipos `rental`, `service` ou `fee`;
- tabelas de preço ativas;
- busca por nome/código;
- paginação e limite.

Depois ligue os selects do front a esses endpoints, preservando o layout e deixando os IDs apenas como valores internos.

Não permita produto incompatível ou de outra unidade.

### 2.6 Preço

Valide `resolve-price`:

- produto obrigatório para resolução;
- recurso opcional;
- tabela opcional;
- quantidade e data de referência;
- retorno `found=false` quando não houver preço;
- nunca assumir zero;
- retorno de `price_id`, `amount`, `currency` e `matched_scope`.

Ao criar ou reprecificar, mantenha snapshot comercial e histórico.

### 2.7 Situação financeira dos mensalistas

A lista atual calcula o financeiro por locação. Refatore para evitar N+1:

- agregue cobranças por `source_type='court_rental'` e `source_id`;
- limite à unidade;
- calcule saldo aberto e vencido em uma subquery/consulta em lote;
- retorne estado: em dia, em aberto, parcial ou vencido;
- mantenha a mesma estrutura de 9 colunas da tabela.

Não altere o módulo financeiro geral.

### 2.8 Ações financeiras contextualizadas

Na lista de mensalistas:

- “Gerar cobrança” deve abrir a geração já contextualizada ou uma rota específica segura;
- “Registrar pagamento” deve abrir a cobrança vencida/em aberto daquele contrato quando houver uma única;
- se houver várias, abrir contas a receber filtradas por `source_type=court_rental&source_id=ID`;
- não criar cobrança duplicada.

Na locação avulsa:

- `finance/generate-rental` deve ser idempotente;
- usar cliente, source e descrição do registro no banco, não do request;
- se já houver cobrança, retornar a existente ou uma mensagem clara;
- manter o índice único existente.

Na recorrente:

- geração mensal deve considerar somente locações ativas e vigentes no mês;
- respeitar `preferred_due_day` e tratar dias 29/30/31 em meses menores;
- manter idempotência por mês.

### 2.9 Concorrência e transações

Valide os fluxos:

- duas avulsas no mesmo horário;
- avulsa concorrendo com série;
- duas séries com ocorrência conflitante;
- dois updates com mesmo `lock_version`;
- suspensão/cancelamento enquanto há edição de ocorrência;
- geração duplicada de cobrança;
- pagamento concorrente.

Requisitos:

- uma operação vence;
- outra recebe erro funcional;
- nenhuma gravação parcial;
- locks liberados em `finally`;
- auditoria preservada.

### 2.10 Lifecycle

Revise activate, suspend, resume, cancel e complete.

- validar transições permitidas;
- exigir motivo quando necessário;
- aplicar `future_policy` corretamente;
- não modificar ocorrências passadas ou concluídas;
- registrar evento e auditoria;
- retornar novo `status` e `lock_version`.

## Etapa 3 — validar os formulários do front

### Avulsa

Confirme que `save-single` aceita e valida exatamente:

- `customer_account_id`
- `contact_person_id`
- `title`
- `starts_at_local`
- `ends_at_local`
- `booking_status`
- `resources[*][selected]`
- buffers
- `negotiated_amount`
- `list_amount`
- `commercial_notes`
- campos avançados de desconto, vigência e preço
- `activate`
- `justification`

A transação deve criar booking + locação + link + snapshot + eventos.

### Mensalista

Confirme que `save-monthly` aceita e valida:

- cliente, contato e título;
- valor e dia de vencimento;
- dias da semana;
- hora inicial/final;
- início;
- término aberto/até data/por quantidade;
- recursos e buffers;
- conflito e status padrão;
- vigência e preço;
- ativação.

A transação deve criar série + locação + link + snapshot + eventos.

### Prévia e disponibilidade

- Não persistir nada.
- Retornar detalhes de conflitos.
- Preservar timezone.
- Validar recursos.

## Etapa 4 — testes

Execute e registre os resultados em `docs/reports/locacoes-backend-validation.md`.

Obrigatórios:

1. `php -l` em todos os PHP.
2. `Tests/verify-fast.sh`.
3. `Tests/verify-full.sh` no ambiente Rise real.
4. self-test.
5. testes de concorrência existentes.
6. novos testes de filtro por recurso.
7. novos testes de timezone.
8. testes de JSON/status HTTP.
9. testes de geração financeira idempotente.
10. smoke autenticado das telas:
   - Agenda;
   - Reservas/Locações comerciais;
   - Reservas/Ocupações;
   - Reservas/Recorrências;
   - modal avulsa;
   - modal mensalista;
   - Mensalistas;
   - detalhe;
   - geração e pagamento.

No smoke, testar desktop, tablet e celular ou viewport equivalente.

## Critérios de aceite

- O sidemenu continua enxuto.
- Nenhuma tela de aluno ou financeiro existente é quebrada.
- A agenda carrega e filtra corretamente.
- Avulsa criada aparece na agenda e na aba de locações.
- Mensalista criado aparece na agenda, na aba de locações e na tela de mensalistas.
- Filtro por quadra funciona para avulsa e recorrente.
- Horários exibidos correspondem ao timezone da unidade.
- Nenhuma duplicidade é criada em concorrência.
- Situação financeira da lista não usa N+1.
- Geração de cobrança é idempotente.
- Todos os endpoints AJAX retornam JSON válido.
- Permissões e unidade são respeitadas.
- `verify-fast`, `verify-full`, self-test e concorrência passam no ambiente real.
- Nenhuma migration destrutiva.
- Nenhum CSS global novo.

## Entrega final do Codex

Ao concluir, entregue:

1. resumo objetivo do que foi alterado;
2. lista de arquivos modificados;
3. migrations criadas, se houver, com justificativa;
4. resultados completos dos testes;
5. gaps que permaneceram;
6. instruções de deploy sem reset de banco;
7. rollback seguro dos arquivos;
8. confirmação explícita de que alunos, pagamentos, caixa e demais módulos existentes continuam preservados.

Não implemente recursos fora do escopo, como reserva pública, fila de espera, campeonatos, app, rateio, bar/comanda, integração bancária ou nota fiscal.
