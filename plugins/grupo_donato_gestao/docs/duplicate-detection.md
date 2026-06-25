# Detecção assistida de duplicidade

`DuplicateDetectionService` retorna `record_id`, `entity_type`, `confidence`, `matched_fields` e `display_summary`. Não existe merge automático nem machine learning.

## Contas

- `exact`: documento normalizado igual.
- `high`: e-mail, telefone ou WhatsApp igual.
- `medium`: nome normalizado igual.
- `low`: nome semelhante (85% ou distância de Levenshtein até 2).

Documento exato interrompe o save até confirmação. Outros sinais são assistivos. Confirmações geram evento `duplicate_override`.

## Pessoas

Nome+nascimento ou contato igual geram confiança alta; nome igual, média; nome semelhante, baixa. Contatos são comparados pela tabela oficial. Correspondências fortes exigem confirmação, mas semelhança de nome isolada não bloqueia.

Sinais exatos são consultados diretamente e limitados a 100 resultados. A varredura aproximada considera os 100 registros mais recentes da unidade para manter custo previsível. Toda consulta exclui soft-deleted e respeita unidade.

## Endereços

Dentro da mesma conta, CEP normalizado+logradouro+número ativos iguais geram alerta. O usuário pode confirmar, e o override é auditado.
