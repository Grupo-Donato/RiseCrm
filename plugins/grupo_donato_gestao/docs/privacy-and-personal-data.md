# Privacidade e dados pessoais

Contas, pessoas, documentos, contatos e endereços são dados pessoais ou podem contê-los. A Fase 2A aplica minimização, escopo de unidade, saída escapada e auditoria mascarada.

## Armazenamento e exibição

- Grafia original é preservada; valores normalizados servem apenas a busca/comparação.
- Documento não é obrigatório nem identidade única.
- Listas mascaram documento, telefone e e-mail.
- Detalhes exigem autenticação staff e permissão; documentos/contatos permanecem mascarados na visão geral.
- Nenhum dado pessoal é colocado em URL, exceção ou log de aplicação pelo plugin.

## Auditoria

Documento e telefone mantêm apenas os quatro últimos dígitos; e-mail mantém a primeira letra e domínio; endereço e notas viram `***`. Payload bruto do request não é gravado. Auditoria é append-only e não possui fluxo de alteração/exclusão.

## Ciclo de vida

Exclusão é lógica e exige motivo. Conta não apaga pessoas; pessoa encerra relações e inativa contatos; relação é encerrada; contato e endereço usam soft delete. Uninstall preserva todas as tabelas.

## Fora da fase

Não há dados médicos, consentimentos, exportação LGPD, anonimização, purge ou política de retenção automatizada. Essas capacidades dependem de decisão jurídica e de fase própria.
