# Contas de clientes

Uma conta representa o titular comercial futuro de contratos, cobranças, reservas e vendas. Uma pessoa representa um ser humano. Família, empresa, equipe ou grupo são contas; seus integrantes são pessoas ligadas por papéis.

## Tipos e status

Tipos persistidos: `individual`, `family`, `company`, `team`, `group`, `organization`, `event_customer` e `other`. Status: `active`, `inactive`, `blocked` e `archived`. Os valores são `VARCHAR`, validados no serviço e traduzidos na interface.

## Documento e contatos diretos

Documento é opcional (`cpf`, `cnpj`, `other`, `none`). O valor original é preservado e uma versão normalizada é indexada. Não há unique rígido; documento exato produz alerta forte e exige confirmação auditada. Listagens e auditoria exibem documento mascarado.

E-mail, telefone e WhatsApp da conta representam contato geral da entidade. Contatos de indivíduos ficam exclusivamente em `gd_contact_methods`.

## Unidade, Rise e exclusão

`unit_id` sempre vem do `UnitContextService`; valores enviados pelo navegador são ignorados. `rise_client_id` é opcional, validado contra `clients`, e não cria cliente Rise. Uma conta com relações ativas não pode ser excluída. A exclusão exige motivo, usa soft delete e não exclui pessoas.

## Endereços

Endereços pertencem à conta, permitem dados incompletos e CEP normalizado. Existe no máximo um endereço principal ativo por conta, garantido por transação e índice gerado. Duplicidade exata de CEP/logradouro/número exige confirmação. O país padrão é uma configuração global opcional e não é hard-coded.
