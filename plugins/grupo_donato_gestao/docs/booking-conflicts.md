# Conflitos de reservas

`BookingConflictService` consulta em lote `gd_booking_resources` e `gd_bookings`. O intervalo é semiaberto e usa a ocupação efetiva:

```text
existing_start < new_end AND existing_end > new_start
```

Adjacência é permitida. Bloqueiam: hold ainda válido, pending confirmation, confirmed e in progress. Completed, cancelled, expired, no-show, soft-deleted e hold vencido não bloqueiam.

Antes de salvar ou confirmar, `BookingService` agrupa janelas iguais e chama o `AvailabilityService`, preservando regras semanais, exceções e bloqueios da Fase 3A. Depois consulta conflitos de reservas. A própria reserva é excluída durante edição.

Escritas adquirem `GET_LOCK` por `gd:booking:{unit}:{resource}` em ordem numérica. Reservas multi-recurso usam sempre a mesma ordem, revalidam sob os locks e os liberam em `finally`. O harness dedicado exige uma gravação, um conflito e nenhum deadlock tanto para um recurso quanto para dois recursos em ordem inversa.

