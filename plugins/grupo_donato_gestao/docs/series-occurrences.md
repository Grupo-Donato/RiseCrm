# Ocorrências de séries

Cada ocorrência é uma reserva completa em `gd_bookings`. Ela possui número próprio, recursos, buffers, UTC, timezone, status, lock version, booking events e auditoria.

Os campos `series_id`, `series_occurrence_key` e `series_local_date` preservam a origem. `is_series_exception` indica tratamento individual; `detached_from_series` impede que alterações futuras da definição reescrevam a ocorrência.

## Escopos de alteração

### Somente esta

A reserva passa pelo `BookingService`, revalida conflitos e recebe exceção `detach`. As demais ocorrências não mudam.

### Esta e as próximas

O split encerra a definição anterior no dia precedente, cria uma série sucessora, cancela logicamente e destaca somente futuras modificáveis, registra a ligação histórica e gera a sucessora. A operação estrutural é transacional e idempotente sob o lock da série.

### Série inteira

A definição é atualizada por compare-and-swap. Reservas futuras em estado editável são canceladas e destacadas antes da regeneração. Histórico terminal não é reescrito.

## Cancelamento

- `single`: cancela uma reserva e registra exceção.
- `this_and_future`: limita a série no dia anterior e cancela futuras modificáveis.
- `entire_series`: muda a série para `cancelled` e cancela futuras modificáveis.

Reservas e eventos não são apagados fisicamente. Ocorrências canceladas continuam disponíveis no histórico.

## Exceções

Tipos: `skip`, `cancel`, `detach`, `override`, `split` e `conflict_skipped`. A tabela é append-only; regeneração consulta exceções bloqueadoras e nunca apaga histórico para recriar uma ocorrência.
