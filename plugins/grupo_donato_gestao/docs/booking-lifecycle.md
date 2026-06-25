# Ciclo de vida de reservas

`BookingLifecycleService` é a única máquina de estados:

```text
hold -> confirmed | cancelled | expired
pending_confirmation -> confirmed | cancelled
confirmed -> in_progress | cancelled | no_show
in_progress -> completed | cancelled
completed | cancelled | expired | no_show -> terminal
```

Confirmar revalida hold, disponibilidade física e conflitos dentro dos locks dos recursos. Iniciar e concluir registram usuário e instante sem criar check-in comercial, consumo ou cobrança. Cancelar exige motivo. No-show exige observação e somente é permitido depois do início previsto. Expiração é sistêmica e não possui endpoint público.

Cada mudança incrementa `lock_version`, gera evento append-only em `gd_booking_events` e auditoria mascarada em `gd_audit_logs`.

