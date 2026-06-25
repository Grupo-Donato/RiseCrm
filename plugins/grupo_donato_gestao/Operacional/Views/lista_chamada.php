<?php if (empty($alunos)): ?>
    <div class="alert alert-warning mb0">Nenhum aluno ativo encontrado para esta turma.</div>
<?php else: ?>
    <?php echo form_open(get_uri("grupo_donato/operacional/salvar_presenca"), ["id" => "bombeiros-presenca-form", "class" => "general-form", "role" => "form"]); ?>
    <input type="hidden" name="data_aula" value="<?php echo esc($data_aula, "attr"); ?>" />
    <input type="hidden" name="turma" value="<?php echo esc($turma, "attr"); ?>" />

    <div class="table-responsive">
        <table class="table table-hover mb0">
            <thead>
                <tr>
                    <th>Aluno</th>
                    <th class="text-center w160">Presença</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($alunos as $aluno): ?>
                    <?php $status_presenca = $historico[$aluno->id] ?? "sem_registro"; ?>
                    <tr>
                        <td>
                            <strong><?php echo esc($aluno->nome_aluno); ?></strong>
                            <div class="text-off"><?php echo esc($turma); ?></div>
                        </td>
                        <td class="text-center">
                            <label class="mr15">
                                <input type="radio" name="presencas[<?php echo (int) $aluno->id; ?>]" value="presente" <?php echo $status_presenca === "presente" ? "checked" : ""; ?> />
                                Presente
                            </label>
                            <label class="mr15">
                                <input type="radio" name="presencas[<?php echo (int) $aluno->id; ?>]" value="falta" <?php echo $status_presenca === "falta" ? "checked" : ""; ?> />
                                Falta
                            </label>
                            <label class="mr15">
                                <input type="radio" name="presencas[<?php echo (int) $aluno->id; ?>]" value="feriado" <?php echo $status_presenca === "feriado" ? "checked" : ""; ?> />
                                Feriado
                            </label>
                            <label>
                                <input type="radio" name="presencas[<?php echo (int) $aluno->id; ?>]" value="aula_cancelada" <?php echo $status_presenca === "aula_cancelada" ? "checked" : ""; ?> />
                                Aula cancelada
                            </label>
                            <label class="ml15">
                                <input type="radio" name="presencas[<?php echo (int) $aluno->id; ?>]" value="sem_registro" <?php echo $status_presenca === "sem_registro" ? "checked" : ""; ?> />
                                Sem registro
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="pt15 text-end">
        <button type="submit" class="btn btn-primary">
            <span data-feather="check-circle" class="icon-16"></span> Salvar chamada
        </button>
    </div>
    <?php echo form_close(); ?>

    <script type="text/javascript">
        $(document).ready(function () {
            $("#bombeiros-presenca-form").appForm({
                isModal: false,
                onSuccess: function (result) {
                    if (result.success) {
                        appAlert.success(result.message);
                    }
                }
            });
        });
    </script>
<?php endif; ?>
