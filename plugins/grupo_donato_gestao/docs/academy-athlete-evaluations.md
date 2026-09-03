# GD Academy — avaliação e histórico esportivo

As avaliações ficam em `gd_academy_athlete_evaluations`, com notas relacionais em `gd_academy_evaluation_scores`. O catálogo inicial é semeado pela V067 e pode receber critérios por unidade, inclusive critérios específicos de posição.

Cada avaliação mantém separadas:

- classificação de desempenho;
- notas de 1 a 5 por critério;
- pontos fortes;
- pontos a desenvolver;
- recomendação para o próximo treino;
- comentário geral;
- feedback para o responsável;
- observação interna.

`internal_note` é administrativo e não aparece no resumo do responsável. O histórico do atleta consulta o cadastro legado e mostra evento, categoria, presença na convocação, placar/estatísticas e avaliação; não copia o aluno para uma entidade paralela.

Avaliações e estatísticas exigem escopo da unidade e validam que partida, categoria, evento e participante pertencem ao mesmo agregado. A atualização usa `lock_version` na avaliação e grava auditoria.
