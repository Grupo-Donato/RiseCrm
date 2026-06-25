# Reservas únicas

A Fase 3B1 implementa uma ocupação individual em `gd_bookings`, ligada a um ou mais recursos por `gd_booking_resources`. Na Fase 3B2, a mesma entidade também materializa ocorrências; reservas avulsas mantêm os campos de série nulos. Não existem contrato, preço ou cobrança.

O número `RES-AAAA-NNNNNN` é emitido no backend pelo `SequenceService`, é único por unidade e nunca reutilizado. A unidade e o timezone vêm do contexto validado. Tipos: `customer_rental`, `school`, `personal`, `event`, `internal` e `other`. Os tipos comercialmente identificáveis exigem conta ativa; contato, quando informado, deve possuir relação ativa com essa conta na mesma unidade.

Início e fim recebidos como horário civil são convertidos pelo `TemporalService` e persistidos em UTC. Cada recurso possui buffers inteiros próprios. A ocupação é calculada no backend:

```text
occupancy_start = starts_at - buffer_before
occupancy_end   = ends_at + buffer_after
```

Alterações operacionais só são aceitas em `hold`, `pending_confirmation` e `confirmed`, antes do início. O `lock_version` impede overwrite por edição obsoleta. Exclusão é lógica e limitada a hold, pending, cancelled e expired; recursos e eventos permanecem rastreáveis.

Metadata é JSON limitada e não aceita HTML, segredos nem campos financeiros. Requests não podem definir unidade, número, autoria, ocupação, ciclo de vida ou soft delete.
