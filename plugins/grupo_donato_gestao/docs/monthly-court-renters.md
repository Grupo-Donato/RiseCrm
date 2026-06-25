# Mensalistas de quadra

A lista de mensalistas é a visão operacional dos acordos comerciais recorrentes (`rental_type = recurring`). Ela sustenta a operação atualmente mantida no controle de mensalistas — **sem importar dados** nesta fase.

Acesse por **Grupo Donato → Locações de quadras → Mensalistas** (`grupo_donato/court-rentals/monthly`).

## Colunas

| Coluna | Origem |
|---|---|
| Cliente | conta da locação |
| Contato | pessoa de contato (opcional) |
| Recurso(s) | recursos da série vinculada |
| Dia(s) da semana | `weekdays` da série (rótulos Seg–Dom) |
| Horário local | `local_start_time`–`local_end_time` da série, no fuso da unidade |
| Vigência | `effective_from → effective_until` da locação |
| Dia de vencimento | `preferred_due_day` (condição comercial) |
| Valor contratado | `negotiated_amount` (ou `list_amount`) |
| Status | estado da locação |
| Próxima ocorrência | primeira reserva futura da série (convertida para o fuso da unidade) |

## Filtros

- Recurso
- Dia da semana
- Status
- Cliente (via busca)
- Vigência (intervalo de datas)

O filtro por recurso e por dia da semana usa `EXISTS` sobre os vínculos → série → recursos/`weekdays`, mantendo a paginação server-side correta.

## Observações

- A "próxima ocorrência" considera apenas reservas futuras em estados que ocupam a agenda; locações suspensas/canceladas mantêm o histórico, mas a série fica pausada (sem geração futura).
- O horário e os dias vêm da **série**; a locação acrescenta as condições comerciais (vigência, vencimento, valor). Ver [locação comercial](court-rentals.md) e [preço/snapshot](court-rental-pricing.md).
