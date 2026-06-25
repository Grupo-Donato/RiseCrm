# Pessoas e relações

`gd_people` armazena indivíduos com grafia original e nome normalizado para busca. Nome completo é obrigatório; nascimento, nome preferido e vínculos Rise são opcionais. Não há CPF, senha, login, dados médicos ou campos escolares.

## Contatos

`gd_contact_methods` é a fonte oficial para telefone, WhatsApp, e-mail e outros contatos de pessoas. O valor é normalizado por tipo, mas o original é preservado. Telefones/e-mails não são únicos globalmente: familiares podem compartilhar contato. A troca de principal é transacional e só existe um principal ativo por pessoa e tipo.

## Relação conta–pessoa

`gd_account_people` permite vários papéis para a mesma pessoa na mesma conta e várias contas por pessoa dentro da mesma unidade. Papéis: titular, familiar, pai, mãe, responsável, responsável financeiro, participante, contatos principal/alternativo/emergência, representante, capitão, membro, organizador e outro.

Uma combinação ativa conta+pessoa+papel não se repete. Há uma relação principal ativa por conta. `role=financial_responsible` descreve o vínculo e força `is_financial_responsible=1`; a flag também pode indicar responsabilidade efetiva em outro papel. Relações encerradas mantêm `end_date` e histórico.

Conta e pessoa precisam existir, não estar soft-deleted e pertencer à unidade ativa. Pessoa excluída é soft-deleted; relações ativas são encerradas e contatos são inativados/soft-deleted, sem apagar contas.

## Multiunidade

Nesta fase a pessoa pertence a uma única unidade e não é compartilhada. Toda consulta e join aplica `unit_id` no backend. Identidade compartilhada entre unidades permanece tema futuro, sem ACL usuário×unidade adicional nesta fase.
