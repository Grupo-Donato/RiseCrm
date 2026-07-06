# Contrato funcional e técnico — backend das locações

Este documento transcreve o que o novo front espera do backend. Ele não propõe reescrever a base existente. A orientação é reaproveitar reservas, séries, locações comerciais e financeiro já implementados.

## 1. Princípio de domínio

### 1.1 Entidades que não devem ser fundidas

- **Booking:** uma ocupação concreta, em UTC no banco e no fuso da unidade no front.
- **Booking series:** uma definição recorrente que gera bookings.
- **Court rental:** acordo comercial, com cliente, preço, vencimento, vigência e estado.
- **Receivable:** cobrança financeira vinculada ao `court_rental`.
- **Payment/allocation:** pagamento e alocação em uma ou mais cobranças.

Uma locação avulsa deve possuir um vínculo ativo com um booking. Uma locação recorrente deve possuir um vínculo ativo com uma booking series.

### 1.2 Regra de tela

O front unifica a consulta, não os dados. As abas podem consumir endpoints separados.

## 2. Convenções obrigatórias

- Prefixo de rota: `grupo_donato`.
- Todas as rotas de escrita permanecem dentro do grupo protegido por CSRF.
- Todas as consultas e escritas devem respeitar a unidade ativa.
- IDs recebidos devem ser validados na unidade ativa.
- Registros com `deleted = 1` não aparecem.
- Permissões existentes devem ser respeitadas.
- Datas de bookings são persistidas em UTC.
- Datas/horas do formulário são interpretadas no timezone da unidade.
- Operações concorrentes usam locks e `lock_version` já existentes.
- Erros de domínio devem ser convertidos em mensagens `app_lang` adequadas.
- Nenhum reset, truncate ou recriação destrutiva de banco.

## 3. Resposta JSON padrão

### Sucesso simples

```json
{
  "success": true,
  "message": "Registro salvo.",
  "id": 123,
  "lock_version": 1
}
```

### Erro funcional

```json
{
  "success": false,
  "message": "Este horário já está ocupado.",
  "error_code": "gd_booking_conflict"
}
```

### Requisitos

- Definir `Content-Type: application/json; charset=UTF-8`.
- Não retornar HTML, warning ou stack trace em endpoints JSON.
- Status HTTP recomendado:
  - 200 para sucesso;
  - 400 para entrada inválida;
  - 403 para permissão;
  - 404 para registro fora da unidade/inexistente;
  - 409 para conflito de agenda ou `lock_version`;
  - 422 para regra de domínio não atendida;
  - 500 apenas para falha não tratada.

## 4. Permissões

| Ação | Permissão mínima |
|---|---|
| Ver agenda | `gd_calendar_view` |
| Ver bookings | `gd_bookings_view` |
| Criar/editar booking | `gd_bookings_manage` |
| Alterar status operacional | `gd_booking_status_manage` |
| Ver séries | `gd_booking_series_view` |
| Criar/editar séries | `gd_booking_series_manage` |
| Ver locações | `gd_court_rentals_view` |
| Criar/editar locações | `gd_court_rentals_manage` |
| Alterar estado comercial | `gd_court_rentals_status_manage` |
| Sobrescrever preço | `gd_court_rentals_price_override` |
| Ver financeiro | `gd_finance_view` |
| Gerar cobrança | `gd_receivables_manage` |
| Registrar pagamento | `gd_payments_manage` |

O backend nunca deve confiar apenas na ocultação do botão no front.

## 5. Agenda

### 5.1 Endpoint

`GET grupo_donato/calendar/events`

### Query

- `start`: ISO 8601, obrigatório.
- `end`: ISO 8601, obrigatório.
- `resources`: IDs separados por vírgula; vazio significa todos permitidos.
- `statuses`: status de bookings separados por vírgula.
- `types`: `booking`, `block`, `closed_exception`, `open_exception`, `weekly_rule`.

### Resposta esperada

Array compatível com FullCalendar:

```json
[
  {
    "id": "booking-812",
    "title": "Futebol de sexta — Empresa X",
    "start": "2026-07-10T19:30:00-03:00",
    "end": "2026-07-10T21:00:00-03:00",
    "backgroundColor": "...",
    "borderColor": "...",
    "extendedProps": {
      "event_type": "booking",
      "booking_id": 812,
      "court_rental_id": 144,
      "status": "confirmed",
      "booking_type": "customer_rental",
      "resource_ids": [2]
    }
  }
]
```

### Ajustes necessários

- Incluir `court_rental_id` quando houver vínculo ativo.
- Manter o `booking_id` para operação.
- Não devolver disponibilidade padrão quando o tipo não foi solicitado.
- Não vazar eventos de unidade sem acesso.
- Cores podem continuar definidas pelo service, mas status deve estar disponível em texto no front.

## 6. Área unificada de Reservas

### 6.1 Locações comerciais

`POST grupo_donato/court-rentals/list-data`

Filtros:

- `rental_type`
- `status`
- `resource_id`
- `customer_account_id`
- `date_from`
- `date_to`
- parâmetros padrão do `appTable`: busca, ordenação, limite e offset.

Correção obrigatória:

O filtro `resource_id` deve encontrar:

- locações recorrentes por `gd_booking_series_resources`;
- locações avulsas por `gd_booking_resources`;
- somente vínculos ativos, não históricos e não excluídos.

O total e o total filtrado devem usar exatamente as mesmas condições de escopo.

### 6.2 Ocupações

`POST grupo_donato/bookings/list-data`

Filtros:

- `resource_id`
- `booking_type`
- `status`
- `customer_account_id`
- `date_from`
- `date_to`

As linhas devem permanecer no formato de array esperado pelo `appTable` atual, com 11 colunas.

### 6.3 Recorrências

`POST grupo_donato/booking-series/list-data`

Filtros:

- `resource_id`
- `status`
- `date_from`
- `date_to`

As linhas devem permanecer com 9 colunas.

## 7. Cadastro de locação avulsa

### 7.1 Endpoint

`POST grupo_donato/court-rentals/save-single`

### Payload principal

```text
rental_type=single
customer_account_id
contact_person_id
 title
starts_at_local
ends_at_local
booking_status=pending_confirmation
resources[RESOURCE_ID][selected]=1
resources[RESOURCE_ID][buffer_before_minutes]
resources[RESOURCE_ID][buffer_after_minutes]
negotiated_amount
list_amount
commercial_notes
```

### Payload avançado opcional

```text
discount_amount
discount_reason
effective_from
effective_until
product_id
price_list_id
price_id
activate=1
justification
```

### Validações

- Cliente obrigatório, ativo e da unidade.
- Contato, quando informado, deve pertencer ao cliente.
- Título obrigatório, sanitizado e até 180 caracteres.
- Início e fim válidos no timezone da unidade.
- Fim maior que início.
- Duração dentro do limite configurado.
- Pelo menos uma quadra bookable, ativa e da unidade.
- Buffers não negativos e dentro do limite.
- Sem conflito com booking, bloqueio, fechamento ou indisponibilidade.
- Valor decimal brasileiro ou canônico deve ser normalizado.
- Desconto não pode ultrapassar a base.
- Desconto exige motivo quando aplicável.
- Produto deve ser ativo e compatível com locação.
- Tabela/preço devem pertencer à unidade ou ao escopo global permitido.
- Ativação exige preço válido ou permissão de override com justificativa.

### Transação

A criação deve continuar atômica:

1. adquirir lock comercial;
2. adquirir locks das quadras;
3. criar booking;
4. criar court rental;
5. criar vínculo;
6. criar snapshot de preço quando aplicável;
7. registrar eventos e auditoria;
8. commit;
9. ativar, se solicitado, com `lock_version` correto.

Se qualquer etapa falhar, nenhuma parte pode permanecer gravada.

### Resposta

```json
{
  "success": true,
  "message": "Registro salvo.",
  "id": 144,
  "rental_number": "LOC-2026-000144",
  "lock_version": 1,
  "booking_id": 812,
  "booking_number": "RES-2026-000812",
  "status": "draft"
}
```

## 8. Verificação de disponibilidade

### Endpoint

`POST grupo_donato/court-rentals/check-availability`

Usa os mesmos campos de horário e recursos do cadastro avulso.

### Resposta

```json
{
  "success": true,
  "data": {
    "available": false,
    "starts_at_utc": "2026-07-10 22:30:00",
    "ends_at_utc": "2026-07-11 00:00:00",
    "resources": [
      {
        "resource_id": 2,
        "available": false,
        "reasons": ["booking_conflict"]
      }
    ],
    "conflicts": [
      {
        "type": "booking",
        "id": 801,
        "title": "Mensalista",
        "starts_at_utc": "...",
        "ends_at_utc": "..."
      }
    ]
  }
}
```

O front hoje usa apenas `available`, mas o backend deve manter detalhes para evolução e diagnóstico.

## 9. Cadastro de mensalista

### Endpoint

`POST grupo_donato/court-rentals/save-monthly`

### Payload principal

```text
rental_type=recurring
customer_account_id
contact_person_id
title
negotiated_amount
preferred_due_day
commercial_notes
frequency=weekly
interval_value=1
weekdays[]=1..7
local_start_time
local_end_time
starts_on
ends_mode=open_ended|until_date|count
ends_on
max_occurrences
resources[RESOURCE_ID][selected]=1
resources[RESOURCE_ID][buffer_before_minutes]
resources[RESOURCE_ID][buffer_after_minutes]
generation_horizon_days=90
```

### Payload avançado

```text
conflict_policy=reject_series|skip_conflicts
default_booking_status=pending_confirmation|confirmed
list_amount
discount_amount
discount_reason
product_id
price_list_id
price_id
effective_from
effective_until
activate=1
justification
```

### Validações específicas

- Pelo menos um dia da semana.
- `preferred_due_day` entre 1 e 31.
- Horário inicial e final válidos; tratar corretamente ocorrência que cruza meia-noite, se permitida.
- `starts_on` obrigatório.
- `ends_on` obrigatório quando `ends_mode=until_date`.
- `max_occurrences` obrigatório quando `ends_mode=count`.
- Horizonte entre os limites existentes.
- Conflito deve respeitar a política escolhida.
- Para `reject_series`, qualquer conflito impede a criação inteira.
- Para `skip_conflicts`, ocorrências conflitantes devem ser registradas como exceção/evento.

### Transação

1. normalizar comercial;
2. criar série pelo service existente;
3. criar locação;
4. vincular série e locação;
5. snapshot de preço;
6. eventos e auditoria;
7. commit;
8. ativar opcionalmente.

### Resposta

```json
{
  "success": true,
  "id": 145,
  "rental_number": "LOC-2026-000145",
  "lock_version": 1,
  "series_id": 77,
  "series_number": "SER-2026-000077",
  "generation": {
    "created": 12,
    "skipped": 0
  },
  "status": "draft"
}
```

## 10. Prévia de recorrência

### Endpoint

`POST grupo_donato/court-rentals/preview`

Recebe a definição da série e recursos. Não grava dados.

### Resposta

Array de ocorrências:

```json
{
  "success": true,
  "data": [
    {
      "local_start": "2026-07-13 19:30:00",
      "local_end": "2026-07-13 21:00:00",
      "starts_at_utc": "...",
      "ends_at_utc": "...",
      "available": true,
      "conflicts": []
    }
  ]
}
```

Não deve gravar locks persistentes, bookings ou exceções.

## 11. Busca de cliente e contato

### Cliente

`POST grupo_donato/court-rentals/customer-options`

Entrada:

```json
{"q":"empresa"}
```

Saída Select2:

```json
{
  "results": [
    {"id": 10, "text": "Empresa X (Empresa)"}
  ]
}
```

### Contato

`POST grupo_donato/court-rentals/contact-options`

Entrada:

```json
{"customer_account_id":10,"q":"joão"}
```

Saída Select2:

```json
{
  "results": [
    {"id": 91, "text": "João da Silva"}
  ]
}
```

Requisitos:

- Limite e paginação segura.
- Busca case-insensitive.
- Somente ativos e da unidade.
- Não expor CPF, e-mail ou telefone no texto sem necessidade.

## 12. Produto e tabela de preço

O front mantém IDs em “Opções avançadas” apenas para preservar compatibilidade. O backend deve fornecer:

- endpoint Select2 de produtos ativos e compatíveis com `rental`, `service` ou `fee`;
- endpoint Select2 de tabelas de preço ativas;
- resolução por produto, recurso, tabela, quantidade e data;
- retorno de escopo encontrado, preço e moeda;
- ausência de preço como `found=false`, nunca como preço zero.

Endpoint atual:

`POST grupo_donato/court-rentals/resolve-price`

Resposta esperada:

```json
{
  "success": true,
  "data": {
    "found": true,
    "amount": "250.00",
    "currency": "BRL",
    "price_id": 22,
    "matched_scope": "resource"
  }
}
```

## 13. Tela de mensalistas

### Endpoint

`POST grupo_donato/court-rentals/monthly-data`

Filtros:

- `resource_id`
- `weekday`
- `status`
- `date_from`
- `date_to`
- busca e paginação do appTable.

### Dados necessários por linha

- cliente;
- contato principal;
- recursos;
- dias da semana;
- horário local;
- valor;
- vencimento;
- estado comercial;
- próxima ocorrência;
- saldo aberto;
- saldo vencido;
- IDs das cobranças abertas relevantes;
- ações permitidas.

### Desempenho

A situação financeira não deve executar uma query por linha. Calcular em lote com `LEFT JOIN`/subquery agregada por `source_id`, limitada à unidade e `source_type='court_rental'`.

## 14. Detalhe da locação

`GET grupo_donato/court-rentals/view/{id}`

O objeto fornecido à view deve conter:

- dados comerciais;
- cliente e contato;
- schedule summary no timezone da unidade;
- links ativos e históricos;
- booking/série vinculados;
- price items;
- diferença de preço;
- eventos;
- resumo financeiro;
- permissões aplicáveis.

### Schedule summary canônico

Para avulsa:

```json
{
  "kind": "single",
  "display": "10/07/2026 19:30–21:00",
  "starts_at_local": "2026-07-10 19:30:00",
  "ends_at_local": "2026-07-10 21:00:00",
  "resource_names": "Q2 — Society 2"
}
```

Para recorrente:

```json
{
  "kind": "recurring",
  "display": "Seg, Qua · 19:30–21:00",
  "weekdays": [1,3],
  "local_start_time": "19:30",
  "local_end_time": "21:00",
  "next_occurrence_local": "2026-07-08 19:30:00",
  "resource_names": "Q2 — Society 2"
}
```

Não montar horário local usando substring de UTC.

## 15. Ciclo de vida comercial

Endpoints existentes:

- `POST court-rentals/{id}/activate`
- `POST court-rentals/{id}/suspend`
- `POST court-rentals/{id}/resume`
- `POST court-rentals/{id}/cancel`
- `POST court-rentals/{id}/complete`

Payload comum:

- `lock_version`
- `reason`, quando exigido
- `future_policy`, quando aplicável
- `justification`, na ativação com override

Políticas futuras:

- `keep`: mantém bookings futuros.
- `cancel`: cancela bookings futuros ainda elegíveis.
- `pause_series`: pausa a série vinculada.

Requisitos:

- Atualização otimista.
- Evento append-only.
- Auditoria.
- Alteração consistente da série/bookings conforme política.
- Nunca alterar bookings concluídos ou passados indevidamente.
- Em conflito de versão, retornar 409 e novo estado atual para recarregar a tela.

## 16. Reprecificação

`POST grupo_donato/court-rentals/reprice`

Campos:

- `id`
- `lock_version`
- dados comerciais preservados pela whitelist
- `negotiated_amount`
- `discount_amount`
- `discount_reason`
- referências de preço, quando houver

Requisitos:

- Somente locação editável.
- Se há override, exigir permissão e justificativa/motivo.
- Não reescrever snapshots antigos sem histórico; marcar anterior como excluído lógico e criar novo snapshot.
- Registrar evento e auditoria.
- Não alterar automaticamente cobrança já emitida; eventual ajuste financeiro deve ser ação explícita.

## 17. Financeiro

### 17.1 Avulsa

`POST grupo_donato/finance/generate-rental`

Entrada:

- `rental_id`
- `amount`
- `due_date`

Requisitos:

- Apenas `rental_type=single`.
- Valor padrão = negociado; fallback = tabela.
- Cliente e source vêm da locação, não do request.
- `source_type='court_rental'`.
- `source_id=rental_id`.
- `reference_month=''`.
- Idempotência pelo índice único existente.
- Se já existe cobrança, retornar a cobrança existente ou erro funcional claro, sem duplicar.

### 17.2 Mensalista

A geração mensal existente deve incluir locações:

- recorrentes;
- ativas;
- vigentes no mês;
- com valor definido;
- da unidade ativa.

Chave única:

`unit_id + source_type + source_id + reference_month + deleted`

### 17.3 Pagamento

A lista de mensalistas deve, quando possível, abrir o modal já com a cobrança em aberto/vencida selecionada. Se houver mais de uma, abrir a lista filtrada daquele `source_id`.

### 17.4 Estado financeiro

- **Em dia:** sem saldo aberto/vencido.
- **Em aberto:** saldo futuro ou não vencido.
- **Vencido:** saldo positivo com `due_date < hoje`.
- **Parcial:** pode ser exibido quando a cobrança possui `paid_amount > 0` e saldo restante.

## 18. Concorrência e conflitos

### Cenários mínimos

1. Dois usuários tentam reservar a mesma quadra e horário.
2. Uma série é criada enquanto outra reserva ocupa uma ocorrência.
3. Dois usuários alteram a mesma locação com `lock_version` igual.
4. Um pagamento é registrado enquanto outro usuário baixa a mesma cobrança.
5. Um mensalista é suspenso enquanto uma ocorrência futura é editada.

### Resultado esperado

- Somente uma operação vencedora.
- Nenhuma duplicidade.
- Transação perdedora recebe erro funcional.
- Locks sempre liberados em `finally`.
- Dados parciais não permanecem.

## 19. Testes obrigatórios

### Estáticos

- PHP lint.
- Metadado de versão = constante.
- Chaves de idioma sem duplicidade.
- Rotas dentro do grupo CSRF.

### Unitários/serviço

- normalização monetária;
- timezone;
- conflito;
- vínculo locação/booking;
- vínculo locação/série;
- filtro por recurso para avulsa e recorrente;
- vigência;
- vencimento 29/30/31 em meses menores;
- geração idempotente de cobrança;
- estado financeiro agregado;
- lifecycle e políticas futuras.

### Integração

- criar avulsa pela rota;
- criar mensalista pela rota;
- prévia sem persistência;
- consulta no calendário;
- geração e pagamento;
- permissões negadas;
- unidade errada retorna 404/403;
- concorrência.

### Smoke autenticado no Rise

- menu e permissões;
- Agenda desktop e celular;
- três abas de Reservas;
- modal avulsa;
- modal mensalista;
- detalhe;
- mensalistas;
- financeiro.

## 20. Fora do escopo do backend desta rodada

Não implementar agora:

- reserva pública;
- fila de espera;
- app/PWA;
- campeonato;
- partida aberta;
- divisão de pagamento entre jogadores;
- integração com portão/luz;
- comanda do bar;
- integração bancária;
- nota fiscal;
- política de chuva;
- cupons ou preço dinâmico;
- dashboard novo;
- novas telas de aluno ou escola.
