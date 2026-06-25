# Holds de reservas

Hold é uma reserva provisória com `status=hold` e `hold_expires_at_utc` futuro. A validade é obrigatória e limitada por `booking_hold_max_minutes` (padrão 30 minutos, teto interno de 10080).

Um hold bloqueia somente enquanto `hold_expires_at_utc > agora`. Portanto, a correção da consulta de conflito não depende do job de limpeza. Hold vencido não confirma e pode ser substituído por nova reserva antes da limpeza.

`BookingHoldService::expireBatch()` processa lotes limitados e idempotentes, muda hold para expired, incrementa `lock_version` e grava evento e auditoria. A execução está disponível em:

```powershell
php plugins/grupo_donato_gestao/Tests/cli.php expire-holds 100
```

O mesmo lote é ligado ao hook `app_hook_after_cron_run` do Rise. Nenhum hold é apagado fisicamente.

