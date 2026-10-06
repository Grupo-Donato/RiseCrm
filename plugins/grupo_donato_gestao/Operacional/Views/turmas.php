<?php
$breadcrumbs = [
    ["label" => "GD Academy", "url" => get_uri("grupo_donato/operacional?gd_tab=alunos")],
    ["label" => "Turmas"]
];
$can_manage = !empty($can_manage);
$day_options = ["seg" => "Segunda", "ter" => "Terça", "qua" => "Quarta", "qui" => "Quinta", "sex" => "Sexta", "sab" => "Sábado", "dom" => "Domingo"];
$status_labels = ["presente" => "Presente", "falta" => "Falta", "feriado" => "Feriado", "aula_cancelada" => "Aula cancelada", "sem_registro" => "Sem registro"];
$day_values = static function ($value): array {
    $value = (string) ($value ?? "");
    return $value === "" ? [] : array_filter(array_map("trim", explode(",", $value)));
};
?>
<?php echo view('grupo_donato_gestao\Operacional\Views\_academy_styles'); ?>
<div id="page-content" class="page-wrapper clearfix gd-academy-page gd-turmas-page">
    <?php echo view('grupo_donato_gestao\Operacional\Views\_academy_breadcrumbs', ["breadcrumbs" => $breadcrumbs]); ?>
    <div class="gd-academy-header">
        <div>
            <div class="gd-academy-kicker">GD Academy · Organização</div>
            <h1>Turmas e horários</h1>
            <div class="gd-academy-subtitle">Organize os horários, os alunos de cada turma e consulte chamadas anteriores. Um aluno pode participar de vários horários.</div>
        </div>
        <div class="gd-academy-header-actions">
            <span class="gd-turmas-unit"><?php echo esc($unidade_atual->nome_unidade ?? "Unidade atual"); ?></span>
            <a class="btn btn-default" href="<?php echo esc(get_uri("grupo_donato/operacional?gd_tab=presenca")); ?>">Abrir presença</a>
        </div>
    </div>

    <?php if (!empty($access_denied)): ?>
        <div class="alert alert-danger mt20">Você não tem permissão para visualizar as turmas desta unidade.</div>
    <?php else: ?>

        <?php if ($can_manage): ?>
            <div class="gd-turmas-tools">
                <details class="gd-academy-form-card" open>
                    <summary class="gd-turmas-summary"><strong>Criar novo horário</strong><span>Defina os dias e a faixa de horário.</span></summary>
                    <?php echo form_open(get_uri("grupo_donato/operacional/save_turma"), ["class" => "gd-turma-save-form mt15"]); ?>
                        <div class="row">
                            <div class="col-md-4 form-group"><label>Nome do horário</label><input class="form-control" name="nome" maxlength="50" placeholder="Ex.: Sexta-feira conjunta" required></div>
                            <div class="col-md-2 form-group"><label>Início</label><input class="form-control" type="time" name="horario_inicio"></div>
                            <div class="col-md-2 form-group"><label>Término</label><input class="form-control" type="time" name="horario_fim"></div>
                            <div class="col-md-4 form-group"><label>Dias da semana</label><div class="gd-turmas-days">
                                <?php foreach ($day_options as $day => $label): ?><label><input type="checkbox" name="dias_semana[]" value="<?php echo esc($day); ?>"> <?php echo esc($label); ?></label><?php endforeach; ?>
                            </div></div>
                        </div>
                        <label class="gd-turma-active"><input type="checkbox" name="active" value="1" checked> Horário ativo</label>
                        <button class="btn btn-primary" type="submit"><i data-feather="plus" class="icon-14"></i> Criar horário</button>
                    <?php echo form_close(); ?>
                </details>

                <?php $active_turmas = array_values(array_filter($turmas, static fn($turma) => (int) $turma->active === 1)); ?>
                <?php if (count($active_turmas) > 1): ?>
                    <details class="gd-academy-form-card">
                        <summary class="gd-turmas-summary"><strong>Juntar turmas</strong><span>Leve os alunos das turmas de origem para um horário e arquive as origens. O histórico de chamadas fica preservado.</span></summary>
                        <?php echo form_open(get_uri("grupo_donato/operacional/mesclar_turmas"), ["class" => "gd-turma-merge-form mt15"]); ?>
                            <div class="row">
                                <div class="col-md-5 form-group"><label>Turma de destino</label><select class="form-control" name="turma_destino_id" required><option value="">Selecione</option><?php foreach ($active_turmas as $turma): ?><option value="<?php echo (int) $turma->id; ?>"><?php echo esc($turma->nome); ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-7 form-group"><label>Turmas que serão juntadas e arquivadas</label><select class="form-control" name="turmas_origem[]" multiple size="4" required><?php foreach ($active_turmas as $turma): ?><option value="<?php echo (int) $turma->id; ?>"><?php echo esc($turma->nome); ?></option><?php endforeach; ?></select><small class="gd-academy-muted">Use Ctrl ou Command para selecionar mais de uma turma.</small></div>
                            </div>
                            <button class="btn btn-default" type="submit"><i data-feather="git-merge" class="icon-14"></i> Juntar turmas selecionadas</button>
                        <?php echo form_close(); ?>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($turmas)): ?>
            <div class="gd-academy-empty mt20"><i data-feather="calendar" class="icon-28"></i><h3>Nenhum horário cadastrado</h3><p>Crie o primeiro horário para começar a organizar as chamadas.</p></div>
        <?php else: ?>
            <div class="gd-turmas-list mt20">
                <?php foreach ($turmas as $turma): ?>
                    <?php
                    $turma_id = (int) $turma->id;
                    $members = $membros_por_turma[$turma_id] ?? [];
                    $sessions = $chamadas_por_turma[$turma_id] ?? [];
                    $days_selected = $day_values($turma->dias_semana ?? "");
                    $session_is_open = (int) ($historico_turma_id ?? 0) === $turma_id;
                    ?>
                    <section class="gd-turma-card" id="gd-turma-<?php echo $turma_id; ?>">
                        <div class="gd-turma-card-heading">
                            <div><span class="gd-academy-kicker"><?php echo (int) $turma->active === 1 ? "Horário ativo" : "Horário arquivado"; ?></span><h2><?php echo esc($turma->nome); ?></h2>
                                <p><?php echo esc(($turma->descricao ?? "") ?: "Dias e horário não definidos"); ?> <span>·</span> <span class="gd-turma-member-count"><?php echo (int) count($members); ?> aluno(s)</span></p></div>
                            <?php if ((int) $turma->active === 1): ?><a class="btn btn-primary btn-sm" href="<?php echo esc(get_uri("grupo_donato/operacional?gd_tab=presenca&turma_id=" . $turma_id)); ?>"><i data-feather="check-square" class="icon-14"></i> Fazer chamada</a><?php endif; ?>
                        </div>

                        <div class="gd-turma-card-body">
                            <div class="gd-turma-main-column">
                                <?php if ($can_manage): ?>
                                    <details class="gd-turma-edit">
                                        <summary>Editar horário e dias</summary>
                                        <?php echo form_open(get_uri("grupo_donato/operacional/save_turma"), ["class" => "gd-turma-save-form mt10"]); ?>
                                            <input type="hidden" name="turma_id" value="<?php echo $turma_id; ?>">
                                            <div class="row">
                                                <div class="col-md-5 form-group"><label>Nome</label><input class="form-control" name="nome" maxlength="50" value="<?php echo esc($turma->nome, "attr"); ?>" required></div>
                                                <div class="col-md-2 form-group"><label>Início</label><input class="form-control" type="time" name="horario_inicio" value="<?php echo esc(!empty($turma->horario_inicio) ? substr((string) $turma->horario_inicio, 0, 5) : "", "attr"); ?>"></div>
                                                <div class="col-md-2 form-group"><label>Término</label><input class="form-control" type="time" name="horario_fim" value="<?php echo esc(!empty($turma->horario_fim) ? substr((string) $turma->horario_fim, 0, 5) : "", "attr"); ?>"></div>
                                                <div class="col-md-3 form-group"><label>Dias da semana</label><div class="gd-turmas-days">
                                                    <?php foreach ($day_options as $day => $label): ?><label><input type="checkbox" name="dias_semana[]" value="<?php echo esc($day); ?>" <?php echo in_array($day, $days_selected, true) ? "checked" : ""; ?>> <?php echo esc($label); ?></label><?php endforeach; ?>
                                                </div></div>
                                            </div>
                                            <label class="gd-turma-active"><input type="checkbox" name="active" value="1" <?php echo (int) $turma->active === 1 ? "checked" : ""; ?>> Horário ativo</label>
                                            <button class="btn btn-default btn-sm" type="submit">Salvar alterações</button>
                                        <?php echo form_close(); ?>
                                    </details>
                                <?php endif; ?>

                                <div class="gd-turma-roster-heading"><h3>Alunos neste horário</h3><span class="gd-turma-roster-count"><?php echo count($members); ?></span></div>
                                <?php if (empty($members)): ?>
                                    <p class="gd-academy-muted gd-turma-empty-roster">Nenhum aluno vinculado a este horário.</p>
                                <?php else: ?>
                                    <ul class="gd-turma-roster">
                                        <?php foreach ($members as $member): ?>
                                            <?php echo view("grupo_donato_gestao\\Operacional\\Views\\turma_roster_member", [
                                                "member" => $member,
                                                "turma_id" => $turma_id,
                                                "can_manage" => $can_manage && (int) $turma->active === 1,
                                            ]); ?>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ($can_manage && (int) $turma->active === 1): ?>
                                    <?php echo form_open(get_uri("grupo_donato/operacional/atualizar_turma_aluno"), ["class" => "gd-turma-membership-form gd-turma-add-form"]); ?>
                                        <input type="hidden" name="turma_id" value="<?php echo $turma_id; ?>"><input type="hidden" name="action" value="add">
                                        <label for="gd-turma-aluno-<?php echo $turma_id; ?>">Adicionar aluno</label>
                                        <div class="input-group"><select class="form-control" id="gd-turma-aluno-<?php echo $turma_id; ?>" name="aluno_id" required><option value="">Selecione um aluno</option><?php foreach ($alunos as $aluno): ?><option value="<?php echo (int) $aluno->id; ?>"><?php echo esc($aluno->nome_aluno); ?><?php echo !empty($aluno->matricula) ? " · " . esc($aluno->matricula) : ""; ?></option><?php endforeach; ?></select><span class="input-group-btn"><button class="btn btn-default" type="submit"><i data-feather="user-plus" class="icon-14"></i> Adicionar</button></span></div>
                                    <?php echo form_close(); ?>
                                <?php endif; ?>
                            </div>

                            <div class="gd-turma-history-column">
                                <details class="gd-turma-history" <?php echo $session_is_open ? "open" : ""; ?>>
                                    <summary><span><strong>Chamadas anteriores</strong><small><?php echo count($sessions); ?> chamada(s) registrada(s)</small></span><i data-feather="chevron-down" class="icon-16"></i></summary>
                                    <?php if (empty($sessions)): ?>
                                        <p class="gd-academy-muted mt10">Ainda não há chamadas registradas para este horário.</p>
                                    <?php else: ?>
                                        <ul class="gd-turma-session-list">
                                            <?php foreach ($sessions as $session): ?>
                                                <?php $session_url = get_uri("grupo_donato/operacional/turmas") . "?historico_turma_id=" . $turma_id . "&historico_data=" . rawurlencode((string) $session->data_aula) . "#gd-turma-" . $turma_id; ?>
                                                <li><a href="<?php echo esc($session_url, "attr"); ?>"><strong><?php echo esc(date("d/m/Y", strtotime((string) $session->data_aula))); ?></strong><span><?php echo (int) $session->presentes; ?> presentes · <?php echo (int) $session->faltas; ?> faltas</span></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </details>

                                <?php if ($session_is_open && !empty($historico_detalhe)): ?>
                                    <div class="gd-turma-history-detail"><h3>Chamada de <?php echo esc(date("d/m/Y", strtotime((string) $historico_data))); ?></h3>
                                        <ul><?php foreach ($historico_detalhe as $registro): ?><?php $status = $registro->status_tipo ?: ((int) $registro->status === 1 ? "presente" : "falta"); ?><li><span><?php echo esc($registro->nome_aluno); ?></span><strong class="is-<?php echo esc($status, "attr"); ?>"><?php echo esc($status_labels[$status] ?? $status); ?></strong></li><?php endforeach; ?></ul>
                                    </div>
                                <?php elseif ($session_is_open): ?>
                                    <div class="alert alert-info mt10">Não há registros detalhados para esta chamada.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
    .gd-turmas-page .gd-turmas-tools { display: grid; gap: 12px; margin-top: 20px; }
    .gd-turmas-page .gd-academy-form-card { margin-bottom: 0; }
    .gd-turmas-page .gd-turmas-summary { align-items: baseline; cursor: pointer; display: flex; flex-wrap: wrap; gap: 10px; list-style: none; }
    .gd-turmas-page .gd-turmas-summary::-webkit-details-marker { display: none; }
    .gd-turmas-page .gd-turmas-summary::after { content: "＋"; margin-left: auto; }
    .gd-turmas-page details[open] > .gd-turmas-summary::after { content: "－"; }
    .gd-turmas-page .gd-turmas-summary span { color: var(--academy-muted); font-size: 12px; }
    .gd-turmas-page .gd-turmas-days { display: flex; flex-wrap: wrap; gap: 5px 10px; padding-top: 7px; }
    .gd-turmas-page .gd-turmas-days label { color: var(--academy-muted); font-size: 11px; font-weight: 500; white-space: nowrap; }
    .gd-turmas-page .gd-turma-active { color: var(--academy-muted) !important; display: block; font-size: 12px; margin-bottom: 12px; }
    .gd-turmas-page .gd-turmas-unit { color: var(--academy-muted); font-size: 12px; }
    .gd-turmas-page .gd-turmas-list { display: grid; gap: 15px; }
    .gd-turmas-page .gd-turma-card { background: var(--academy-surface); border: 1px solid var(--academy-line); border-radius: 12px; overflow: hidden; }
    .gd-turmas-page .gd-turma-card-heading { align-items: center; background: var(--academy-surface-2); display: flex; gap: 15px; justify-content: space-between; padding: 17px 20px; }
    .gd-turmas-page .gd-turma-card-heading h2 { font-size: 19px; margin: 4px 0; }
    .gd-turmas-page .gd-turma-card-heading p { color: var(--academy-muted); font-size: 12px; margin: 0; }
    .gd-turmas-page .gd-turma-card-body { display: grid; gap: 20px; grid-template-columns: minmax(0, 1.2fr) minmax(260px, .8fr); padding: 18px 20px; }
    .gd-turmas-page .gd-turma-edit { border-bottom: 1px solid var(--academy-line); margin-bottom: 18px; padding-bottom: 14px; }
    .gd-turmas-page .gd-turma-edit summary { color: var(--academy-accent-hover); cursor: pointer; font-size: 12px; font-weight: 700; }
    .gd-turmas-page .gd-turma-roster-heading { align-items: center; display: flex; gap: 8px; margin-bottom: 9px; }
    .gd-turmas-page .gd-turma-roster-heading h3, .gd-turmas-page .gd-turma-history-detail h3 { font-size: 14px; font-weight: 700; margin: 0; }
    .gd-turmas-page .gd-turma-roster-heading > span { background: var(--academy-surface-2); border-radius: 99px; color: var(--academy-muted); font-size: 11px; padding: 3px 8px; }
    .gd-turmas-page .gd-turma-roster, .gd-turmas-page .gd-turma-session-list, .gd-turmas-page .gd-turma-history-detail ul { list-style: none; margin: 0; padding: 0; }
    .gd-turmas-page .gd-turma-roster { display: grid; gap: 5px; max-height: 320px; overflow: auto; }
    .gd-turmas-page .gd-turma-roster li, .gd-turmas-page .gd-turma-history-detail li { align-items: center; border-bottom: 1px solid var(--academy-line); display: flex; gap: 10px; justify-content: space-between; padding: 8px 2px; }
    .gd-turmas-page .gd-turma-roster li strong { display: block; font-size: 12px; }
    .gd-turmas-page .gd-turma-roster li small { color: var(--academy-muted); display: block; font-size: 10px; margin-top: 2px; }
    .gd-turmas-page .gd-turma-roster li form { margin: 0; }
    .gd-turmas-page .gd-turma-add-form { margin-top: 12px; }
    .gd-turmas-page .gd-turma-add-form > label { color: var(--academy-muted); font-size: 11px; }
    .gd-turmas-page .gd-turma-history { background: var(--academy-bg); border: 1px solid var(--academy-line); border-radius: 9px; padding: 12px; }
    .gd-turmas-page .gd-turma-history > summary { align-items: center; cursor: pointer; display: flex; justify-content: space-between; list-style: none; }
    .gd-turmas-page .gd-turma-history > summary::-webkit-details-marker { display: none; }
    .gd-turmas-page .gd-turma-history > summary strong, .gd-turmas-page .gd-turma-history > summary small { display: block; }
    .gd-turmas-page .gd-turma-history > summary strong { font-size: 13px; }
    .gd-turmas-page .gd-turma-history > summary small { color: var(--academy-muted); font-size: 11px; margin-top: 3px; }
    .gd-turmas-page .gd-turma-session-list { margin-top: 8px; max-height: 360px; overflow: auto; }
    .gd-turmas-page .gd-turma-session-list li { border-top: 1px solid var(--academy-line); }
    .gd-turmas-page .gd-turma-session-list a { align-items: center; color: var(--academy-text) !important; display: flex; font-size: 11px; justify-content: space-between; padding: 8px 2px; text-decoration: none; }
    .gd-turmas-page .gd-turma-session-list a:hover { color: var(--academy-accent-hover) !important; }
    .gd-turmas-page .gd-turma-session-list a span { color: var(--academy-muted); }
    .gd-turmas-page .gd-turma-history-detail { border: 1px solid var(--academy-line); border-radius: 9px; margin-top: 10px; padding: 12px; }
    .gd-turmas-page .gd-turma-history-detail h3 { margin-bottom: 8px; }
    .gd-turmas-page .gd-turma-history-detail li { font-size: 11px; }
    .gd-turmas-page .gd-turma-history-detail li strong { font-size: 10px; }
    .gd-turmas-page .gd-turma-history-detail .is-presente { color: #42cb8f; }
    .gd-turmas-page .gd-turma-history-detail .is-falta { color: #ef7882; }
    @media (max-width: 767px) {
        .gd-turmas-page .gd-turma-card-body { grid-template-columns: 1fr; }
        .gd-turmas-page .gd-turma-card-heading { align-items: flex-start; flex-direction: column; }
    }
</style>

<?php echo view('grupo_donato_gestao\Operacional\Views\_academy_actions'); ?>
<?php if ($can_manage && empty($access_denied)): ?>
<script>
$(function () {
    $("body").off("submit.gdTurmas", ".gd-turma-save-form, .gd-turma-merge-form, .gd-turma-membership-form")
        .on("submit.gdTurmas", ".gd-turma-save-form, .gd-turma-merge-form, .gd-turma-membership-form", function (event) {
            event.preventDefault();
            var form = this;
            var button = $(form).find("button[type='submit']").first();
            var label = button.text();
            button.prop("disabled", true);
            appAjaxRequest({
                // Hidden input name="action" masks the native form.action property.
                url: form.getAttribute("action"),
                type: "POST",
                data: $(form).serialize(),
                dataType: "json",
                success: function (result) {
                    if (result && result.success) {
                        if ($(form).hasClass("gd-turma-membership-form")) {
                            var $form = $(form);
                            var $card = $form.closest(".gd-turma-card");
                            var action = $form.find("input[name='action']").val();
                            var $roster = $card.find(".gd-turma-roster");

                            if (action === "add") {
                                if (!$roster.length) {
                                    $roster = $("<ul>").addClass("gd-turma-roster");
                                    $card.find(".gd-turma-empty-roster").replaceWith($roster);
                                }
                                if (result.member_html) {
                                    $roster.append(result.member_html);
                                }
                                var $select = $form.find("select[name='aluno_id']");
                                $select.val("");
                                $select.find("option").filter(function () {
                                    return String(this.value) === String(result.member_id);
                                }).remove();
                                button.prop("disabled", false);
                            } else {
                                var removedId = $form.find("input[name='aluno_id']").val();
                                $form.closest("li").remove();
                                var $addSelect = $card.find(".gd-turma-add-form select[name='aluno_id']");
                                var hasOption = $addSelect.find("option").filter(function () {
                                    return String(this.value) === String(removedId);
                                }).length > 0;
                                if (!hasOption && $addSelect.length) {
                                    $addSelect.append($("<option>", { value: removedId }).text(result.member_option_text || "Aluno"));
                                }
                                if (!$roster.find("li").length && $roster.length) {
                                    $roster.replaceWith($("<p>").addClass("gd-academy-muted gd-turma-empty-roster").text("Nenhum aluno vinculado a este horário."));
                                }
                            }

                            var memberCount = parseInt(result.member_count, 10) || 0;
                            $card.find(".gd-turma-member-count").text(memberCount + " aluno(s)");
                            $card.find(".gd-turma-roster-count").text(memberCount);
                            if (window.feather && typeof window.feather.replace === "function") {
                                window.feather.replace();
                            }
                            appAlert.success(result.message || "Lista de alunos atualizada.");
                            return;
                        }

                        appAlert.success(result.message || "Alterações salvas.");
                        window.location.reload();
                        return;
                    }
                    appAlert.error((result && result.message) || "Não foi possível salvar as alterações.");
                    button.prop("disabled", false).text(label);
                },
                error: function () {
                    appAlert.error("Não foi possível salvar as alterações.");
                    button.prop("disabled", false).text(label);
                }
            });
        });
});
</script>
<?php endif; ?>
