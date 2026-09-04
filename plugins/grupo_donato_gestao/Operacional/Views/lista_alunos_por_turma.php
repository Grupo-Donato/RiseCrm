<?php
$turmas = is_array($turmas ?? null) ? $turmas : [];
$total_alunos = (int) ($total_alunos ?? 0);
?>

<div class="gd-alunos-class-list">
    <?php if (!$turmas): ?>
        <div class="text-off">Nenhum aluno encontrado nesta unidade.</div>
    <?php else: ?>
        <div class="gd-alunos-class-summary">
            <p class="text-off">Alunos da unidade organizados por turma.</p>
            <span class="badge bg-secondary"><?php echo $total_alunos; ?> <?php echo $total_alunos === 1 ? "aluno" : "alunos"; ?></span>
        </div>

        <?php $turma_counter = 0; ?>
        <?php foreach ($turmas as $turma): ?>
            <?php $turma_counter++; ?>
            <?php $quantidade = count($turma["alunos"] ?? []); ?>
            <section class="gd-alunos-class-card" aria-labelledby="gd-turma-heading-<?php echo $turma_counter; ?>">
                <div class="gd-alunos-class-heading">
                    <h3 id="gd-turma-heading-<?php echo $turma_counter; ?>"><?php echo esc($turma["label"] ?? "Sem turma"); ?></h3>
                    <span class="badge bg-primary"><?php echo $quantidade; ?> <?php echo $quantidade === 1 ? "criança" : "crianças"; ?></span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover gd-alunos-class-table">
                        <thead>
                            <tr>
                                <th scope="col" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="aluno" aria-label="Ordenar por aluno">Aluno <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th scope="col" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="matricula" aria-label="Ordenar por matrícula">Matrícula <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th scope="col" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="responsavel" aria-label="Ordenar por responsável">Responsável <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th scope="col" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="whatsapp" aria-label="Ordenar por WhatsApp">WhatsApp <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th scope="col" class="text-center" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="faltas" aria-label="Ordenar por faltas deste mês">Faltas este mês <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th scope="col" class="text-right" aria-sort="none"><button type="button" class="gd-alunos-sort-button" data-gd-alunos-sort-key="mensalidade" aria-label="Ordenar por mensalidade">Mensalidade <span class="gd-alunos-sort-icon" aria-hidden="true">↕</span></button></th>
                                <th class="text-center gd-alunos-class-actions"><span class="sr-only">Ações</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($turma["alunos"] ?? []) as $aluno): ?>
                                <?php
                                $options = modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='edit' class='icon-16'></i>", [
                                    "class" => "edit",
                                    "title" => "Editar aluno",
                                    "data-post-id" => $aluno->id
                                ]);
                                if (!empty($aluno->exame_medico)) {
                                    $options .= "<a href='" . get_uri("grupo_donato/operacional/baixar_exame_medico/" . (int) $aluno->id) . "' target='_blank' rel='noopener' title='Exame médico'><i data-feather='file-text' class='icon-16'></i></a>";
                                }
                                $options .= js_anchor("<i data-feather='x' class='icon-16'></i>", [
                                    "class" => "delete gd-aluno-por-turma-delete",
                                    "title" => app_lang("delete"),
                                    "data-id" => $aluno->id,
                                    "data-action-url" => get_uri("grupo_donato/operacional/delete_aluno"),
                                    "data-action" => "delete-confirmation"
                                ]);
                                ?>
                                <tr>
                                    <td><?php echo esc($aluno->nome_aluno); ?></td>
                                    <td><?php echo esc($aluno->matricula ?: (string) $aluno->id); ?></td>
                                    <td><?php echo esc($aluno->responsavel_nome ?: "-"); ?></td>
                                    <td><?php echo esc($aluno->responsavel_whats ?: "-"); ?></td>
                                    <td class="text-center"><?php echo bombeiros_faltas_indicator($aluno->faltas_count ?? 0); ?></td>
                                    <td class="text-right">R$ <?php echo number_format((float) $aluno->valor_mensalidade, 2, ",", "."); ?></td>
                                    <td class="text-center option gd-alunos-class-actions"><?php echo $options; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
