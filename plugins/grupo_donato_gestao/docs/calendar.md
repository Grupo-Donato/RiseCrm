# Calendário-base

A rota `GET /grupo_donato/calendar` usa o FullCalendar 5.5.1 já fornecido pelo Rise. Nenhuma biblioteca de calendário foi adicionada.

`CalendarService` projeta somente:

- regras semanais ativas como fundo disponível;
- exceções `open` e `closed`;
- bloqueios ativos.

Filtros de recurso e camada são aplicados no backend e sempre limitados à unidade ativa. O endpoint `GET /grupo_donato/calendar/events` aceita `start`, `end`, `resources` e `types`. A janela máxima padrão é 93 dias, configurável por `gd_settings.calendar_max_days` no escopo global ou da unidade, limitada internamente a 366 dias.

Eventos retornam título simples, instantes com offset da unidade, cor e `extendedProps` mínimos. Não expõem clientes, dados pessoais, preços ou valores financeiros. Não existem eventos de reserva nesta fase.

## Reservas na Fase 3B1

A camada `booking` projeta uma entrada por reserva/recurso. O evento principal usa o horário de utilização; `extendedProps` informa ocupação e buffers. Usuário com `gd_bookings_view` recebe número e título; usuário apenas com calendário recebe “Ocupado”. Cliente, contato, documento, notas, metadata e dados financeiros nunca entram no feed. Hold vencido é omitido como ocupação ativa; cores distinguem os oito status.

## Séries na Fase 3B2

Ocorrências continuam na camada `booking`. Leitores de reserva recebem o prefixo visual `↻` e `series_id`; a projeção privada mantém `Ocupado`, omite `series_id` e não expõe PII.
