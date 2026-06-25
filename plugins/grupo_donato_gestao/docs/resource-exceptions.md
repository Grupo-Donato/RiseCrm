# Exceções de disponibilidade

`gd_resource_availability_exceptions` altera pontualmente a abertura regular de um recurso.

- `open`: abre uma janela que não seria coberta pela regra semanal.
- `closed`: fecha qualquer janela intersectante e prevalece sobre `open` e regra semanal.

Os instantes são persistidos em UTC. A UI recebe horário civil no timezone IANA da unidade e o backend faz a conversão. Título é obrigatório; motivo e metadata JSON são opcionais. Status: `active`, `inactive`, `cancelled` e `archived`; apenas `active` participa do motor.

Duplicata ativa exata de recurso, tipo e intervalo é bloqueada. Sobreposição ativa do mesmo tipo exige confirmação; o override é salvo sob lock e auditado como `overlap_override`. Tipos diferentes podem se sobrepor e são resolvidos pela precedência documentada.

Exceção não é bloqueio operacional: ela corrige o calendário de abertura. Manutenção, interdição e uso interno pertencem a `gd_resource_blocks`.
