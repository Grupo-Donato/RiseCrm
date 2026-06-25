# Bloqueios de recursos

`gd_resource_blocks` representa indisponibilidade operacional explícita. Tipos: `maintenance`, `internal_use`, `administrative`, `closure`, `cleaning`, `inspection` e `other`. Motivo é obrigatório em manutenção, interdição e bloqueio administrativo.

Somente status `active` bloqueia. `completed`, `cancelled` e `archived` preservam histórico sem afetar disponibilidade. Instantes são UTC e a UI converte pelo timezone da unidade.

Duplicata ativa exata é rejeitada. Qualquer sobreposição ativa gera alerta e exige `overlap_override`; o override é auditado. Saves são serializados por recurso com `GET_LOCK`, revalidação dentro da transação e chave gerada de duplicidade exata.

Um bloqueio ativo tem precedência máxima após a própria validade do recurso. Intervalos são semiabertos; um bloqueio que termina exatamente quando a consulta começa não conflita.
