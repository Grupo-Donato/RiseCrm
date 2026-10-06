<?php
$member = $member ?? (object) [];
$turma_id = (int) ($turma_id ?? 0);
$can_manage = !empty($can_manage);
?>
<li>
    <span>
        <strong><?php echo esc($member->nome_aluno ?? "Aluno"); ?></strong>
        <?php if (!empty($member->matricula)): ?><small><?php echo esc($member->matricula); ?></small><?php endif; ?>
    </span>
    <?php if ($can_manage): ?>
        <?php echo form_open(get_uri("grupo_donato/operacional/atualizar_turma_aluno"), ["class" => "gd-turma-membership-form"]); ?>
            <input type="hidden" name="turma_id" value="<?php echo $turma_id; ?>">
            <input type="hidden" name="aluno_id" value="<?php echo (int) ($member->id ?? 0); ?>">
            <input type="hidden" name="action" value="remove">
            <button class="btn btn-default btn-xs" type="submit" aria-label="Remover <?php echo esc($member->nome_aluno ?? "aluno", "attr"); ?> da turma"><i data-feather="x" class="icon-14"></i> Remover</button>
        <?php echo form_close(); ?>
    <?php endif; ?>
</li>
