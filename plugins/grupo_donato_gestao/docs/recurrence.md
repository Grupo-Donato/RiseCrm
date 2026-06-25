# Recorrência

## Frequências

- `daily`: a cada N dias desde `starts_on`.
- `weekly`: a cada N semanas, nos dias ISO 1–7 selecionados.
- `monthly`: a cada N meses, no dia configurado.

Na recorrência mensal, um mês sem o dia solicitado é ignorado. O horário nunca é movido para o último dia do mês.

## Término

- `until_date`: data final inclusiva.
- `count`: quantidade máxima, limitada a 366 por operação.
- `open_ended`: sem materialização infinita; gera até `hoje + generation_horizon_days`.

O horizonte permitido é de 1 a 730 dias. Uma chamada nunca materializa mais de 366 ocorrências.

## Tempo

O gerador percorre datas civis no timezone persistido da unidade e converte cada início/fim para UTC com `TemporalService`. Se a hora final for igual ou anterior à inicial, a ocorrência termina no dia local seguinte.

Horários inexistentes ou ambíguos por DST são rejeitados. Isso evita deslocar silenciosamente o horário civil escolhido.

## Preview e idempotência

Preview chama o mesmo gerador e os mesmos validadores de disponibilidade/conflito, mas não persiste série, reserva, evento ou exceção.

`series_occurrence_key` é a data local `YYYY-MM-DD`. O unique por série/chave, combinado ao lock da série, garante que duas gerações concorrentes produzam uma execução efetiva e outra idempotente.
