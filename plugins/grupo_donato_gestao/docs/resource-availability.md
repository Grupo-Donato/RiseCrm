# Disponibilidade de recursos

A disponibilidade regular de um recurso é definida em `gd_resource_availability_rules`. Cada regra pertence à unidade e ao recurso, usa `weekday` no padrão `0=domingo ... 6=sábado`, `TIME` local da unidade e vigência opcional por `DATE`.

Múltiplas janelas não sobrepostas são aceitas. Janelas adjacentes são válidas porque todos os intervalos são semiabertos: `[início, fim)`. Uma regra com `spans_next_day=1` ancora no dia informado e termina no dia seguinte; por exemplo, sexta 22:00–02:00 cobre o início do sábado. Sem essa flag, o fim deve ser posterior ao início no mesmo dia.

Regras ativas do mesmo recurso não podem se sobrepor quando suas vigências se cruzam, inclusive entre uma janela overnight e o dia seguinte. O Service valida novamente sob `GET_LOCK` e transação; a chave gerada `active_exact_key` também rejeita duplicata operacional exata.

`AvailabilityService::check()` e `checkMany()` consideram o intervalo solicitado inteiro. Regras adjacentes podem compor cobertura contínua. Para a operação simplificada do Grupo Donato, uma quadra que ainda não possua nenhuma regra semanal fica disponível por padrão, respeitando bloqueios e exceções de fechamento. Assim que o recurso possuir ao menos uma regra semanal ativa, a grade passa a ser restritiva e os horários fora da cobertura ficam indisponíveis.

## Precedência

1. recurso ausente, excluído, inativo ou não reservável;
2. bloqueio ativo intersectante;
3. exceção `closed` intersectante;
4. exceção `open` cobrindo o intervalo;
5. regra semanal cobrindo o intervalo;
6. disponibilidade padrão quando o recurso não possui nenhuma regra semanal;
7. indisponível por ausência de cobertura quando já existe grade configurada.

O retorno inclui `available`, recurso, intervalo UTC, timezone, `source`, `reason_code` e IDs das regras, exceções e bloqueios correspondentes. `checkMany()` carrega recursos, regras, exceções e bloqueios em quatro consultas em lote, sem N+1.

Esta fase não cria reservas, holds, séries ou ocorrências.

## Consumo pela Fase 3B1

`BookingService` continua tratando `AvailabilityService` como fonte exclusiva da disponibilidade física. Para cada janela de ocupação com buffers, recursos de intervalo igual são consultados em lote. Somente depois ocorre a consulta de conflito entre reservas. A lógica de regra semanal, exceção e bloqueio não foi duplicada no controller.
