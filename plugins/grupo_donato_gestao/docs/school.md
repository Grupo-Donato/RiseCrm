# Escola e personal

## Alunos

Em **Grupo Donato → Alunos**, selecione uma pessoa existente ou crie uma nova. Vincule uma conta do tipo família ou crie a família durante o cadastro. Responsáveis são pessoas existentes relacionadas à conta; um deles pode ser o principal.

Estados do perfil: ativo, inativo e encerrado. Nenhum desses fluxos exclui fisicamente pessoa, família ou histórico.

O detalhe mostra família, responsáveis, matrículas, turmas e frequência. A frequência é `presenças / marcações`, desconsiderando `unmarked`.

## Turmas e personal

Em **Turmas e personal**, informe nome, modalidade, tipo, instrutor, recurso, dias, horários, vigência, capacidade e estado. Personal usa capacidade padrão 1, mas ela é editável.

Uma turma pode:

- vincular uma série existente;
- vincular uma reserva única via backend;
- criar uma série semanal usando o motor de recorrência já existente.

Não existe recorrência paralela. As ocorrências são reservas normais e aparecem uma única vez no calendário.

## Matrículas

A matrícula exige aluno e turma ativos quando aberta. Estados: ativa, pausada, encerrada e cancelada. O backend bloqueia duas matrículas abertas do mesmo aluno na mesma turma e lotação acima da capacidade.

Produto e dia preferencial de vencimento são opcionais e informativos. Não há cobrança, pagamento ou título financeiro.

## Presenças

Em **Presenças**, escolha turma e data, carregue a chamada e marque presente, ausente, justificado ou não marcado. O lote só aceita alunos elegíveis para aquela turma/data. Uma nova gravação corrige o registro existente, sem duplicá-lo.

## Segurança

Todas as consultas e mutações recebem a unidade do contexto autenticado; IDs de outra unidade são rejeitados. Escritas são POST sob CSRF, usam permissões específicas e gravam auditoria. Não há exclusão física nos fluxos escolares.
