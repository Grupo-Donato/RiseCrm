# Séries de reservas

Uma série define cliente opcional, tipo, título, recursos, buffers, horário civil, timezone da unidade, recorrência, término, status padrão das reservas e política de conflito.

## Estados

- `active`: pode gerar ocorrências.
- `paused`: preserva tudo e bloqueia geração.
- `completed`: encerrada operacionalmente.
- `cancelled`: cancelada com motivo; futuras modificáveis são canceladas.
- `archived`: estado histórico final reservado ao ciclo administrativo.

Retomar uma série ativa o estado e tenta materializar o horizonte aplicável. `lock_version` impede overwrite concorrente.

## Recursos e conflitos

Recursos e buffers padrão ficam em `gd_booking_series_resources`. Cada ocorrência recalcula ocupação e passa pelos mesmos serviços físicos e conflitos das reservas avulsas.

- `reject_series`: nenhuma ocorrência daquela chamada persiste se uma candidata falhar.
- `skip_conflicts`: candidatas válidas persistem; cada falha gera exceção `conflict_skipped`.

Não existe override automático de conflito.

## Interface

O menu `Séries de reservas` oferece lista, filtros, criação/edição, preview, detalhe, recursos, ocorrências, exceções, histórico e ações de ciclo de vida. A lista filtra status, recurso, cliente e período.

O calendário continua exibindo reservas. Um símbolo de recorrência aparece apenas no título detalhado para quem pode ler reservas; a projeção privada mantém o título `Ocupado` e não retorna `series_id`.

## Operação

Use `generate` para completar o horizonte ou repetir uma geração com segurança. A repetição reconhece chaves existentes e retorna contagem idempotente. Eventos da série e ledger de execuções permitem auditoria operacional sem substituir `gd_audit_logs`.
