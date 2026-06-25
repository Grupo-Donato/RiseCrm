# Tempo e timezone

Instantes concretos são persistidos em `DATETIME` UTC. Horários semanais são horários civis (`TIME`) no timezone IANA de `gd_units.timezone`; o browser nunca escolhe o fuso de persistência.

Todos os intervalos são semiabertos `[start, end)`. A sobreposição canônica é:

```text
new_start < existing_end AND new_end > existing_start
```

Assim, intervalos adjacentes não conflitam. Duração e comparação usam timestamps inteiros e minutos inteiros, nunca float ou timestamp de 32 bits.

`TemporalService` valida formatos estritos, converte local↔UTC e rejeita:

- horário local inexistente durante avanço de DST;
- horário local ambíguo durante recuo de DST;
- fim menor ou igual ao início;
- intervalo acima do limite administrativo, padrão 366 dias e configurável por `gd_settings.temporal_admin_max_days`.

Regras overnight convertem cada ocorrência usando o timezone vigente naquela data. Uma ocorrência afetada por horário civil inválido/ambíguo é ignorada com segurança e não se torna disponibilidade silenciosa.

## Reservas únicas

Entradas `starts_at_local`, `ends_at_local` e validade local do hold são convertidas pelo timezone da unidade. Banco e ocupações usam UTC. Buffers são minutos inteiros aplicados antes/depois. Conflitos mantêm `[start,end)`, portanto adjacência sem buffer é permitida. O navegador não envia `occupancy_*` nem escolhe timezone.
