<?php
$event = $event ?? (object) [];
$category = $category ?? (object) [];
$section = $section ?? "resumo";
$metrics = is_array($metrics ?? null) ? $metrics : [];
$eventUrl = get_uri("grupo_donato/operacional/evento/" . (int) $event->id);
$categoryUrl = $eventUrl . "/categoria/" . (int) $category->id;
$lineupLabels = ["called" => "Convocado", "starter" => "Titular", "substitute" => "Reserva", "absent" => "Ausente", "cut" => "Cortado"];
$confirmationLabels = ["waiting" => "Aguardando", "confirmed" => "Confirmado", "refused" => "Recusado", "no_response" => "Sem resposta", "pending" => "Aguardando"];
    $participantPhoto = static function ($row): string {
    if (($row->athlete_type ?? "") === "internal" && !empty($row->student_id) && !empty($row->photo_path)) return get_uri("grupo_donato/operacional/foto_aluno/" . (int) $row->student_id);
    return get_avatar();
};
$breadcrumbs = [["label" => "GD Academy", "url" => get_uri("grupo_donato/operacional?gd_tab=alunos")], ["label" => "Eventos", "url" => get_uri("grupo_donato/operacional?gd_tab=eventos")], ["label" => (string) ($event->name ?? "Evento"), "url" => $eventUrl], ["label" => (string) ($category->name ?? "Categoria")]];
?>
<?php echo view('grupo_donato_gestao\Operacional\Views\_academy_styles'); ?>
<div id="page-content" class="page-wrapper clearfix gd-academy-page">
    <?php echo view('grupo_donato_gestao\Operacional\Views\_academy_breadcrumbs', ["breadcrumbs" => $breadcrumbs]); ?>
    <?php echo view('grupo_donato_gestao\Operacional\Views\_academy_category_nav', ["event" => $event, "category" => $category, "section" => $section, "can_evaluate" => $can_evaluate ?? false]); ?>

    <?php if ($section === "resumo"): ?>
        <div class="row"><?php foreach ([["Atletas convocados", $metrics["athlete_count"] ?? 0], ["Confirmados", $metrics["confirmed_count"] ?? 0], ["Partidas", $metrics["match_count"] ?? 0], ["Avaliações realizadas", $metrics["evaluation_count"] ?? 0], ["Avaliações pendentes", $metrics["pending_evaluation_count"] ?? 0]] as $kpi): ?><div class="col-6 col-md-3 mb10"><div class="gd-academy-kpi"><span><?php echo esc($kpi[0]); ?></span><strong><?php echo esc((string) $kpi[1]); ?></strong></div></div><?php endforeach; ?></div>
        <div class="row mt15"><div class="col-md-7 mb15"><div class="gd-academy-card"><h3>Resumo da categoria</h3><div class="row"><div class="col-sm-6 mb10"><span class="gd-academy-muted">Evento</span><br><a href="<?php echo esc($eventUrl); ?>"><?php echo esc($event->name); ?></a></div><div class="col-sm-6 mb10"><span class="gd-academy-muted">Treinador</span><br><strong><?php echo !empty($category->instructor_user_id) ? "Usuário #" . (int) $category->instructor_user_id : "Não definido"; ?></strong></div><div class="col-sm-6 mb10"><span class="gd-academy-muted">Auxiliar</span><br><strong><?php echo esc($category->assistant ?: "Não definido"); ?></strong></div><div class="col-sm-6 mb10"><span class="gd-academy-muted">Faixa</span><br><strong><?php echo $category->min_age !== null ? (int) $category->min_age . "–" . (int) $category->max_age . " anos" : "Livre"; ?></strong></div></div></div></div><div class="col-md-5 mb15"><div class="gd-academy-card"><h3>Próximas ações</h3><a class="gd-academy-list-item mb10" href="<?php echo esc($categoryUrl . "/convocacao"); ?>"><span><strong>Gerenciar convocação</strong><small>Adicionar atletas e atualizar confirmação.</small></span><span>›</span></a><a class="gd-academy-list-item mb10" href="<?php echo esc($categoryUrl . "/partidas"); ?>"><span><strong>Ver partidas</strong><small>Agenda, adversários e placares.</small></span><span>›</span></a><?php if ($can_evaluate ?? false): ?><a class="gd-academy-list-item" href="<?php echo esc($categoryUrl . "/avaliacoes"); ?>"><span><strong>Avaliar atletas</strong><small><?php echo (int) ($metrics["pending_evaluation_count"] ?? 0); ?> pendente(s).</small></span><span>›</span></a><?php endif; ?></div></div></div>

    <?php elseif ($section === "convocacao"): ?>
        <div class="gd-academy-section-title"><div><h2>Convocação</h2><p class="gd-academy-muted mb0">Confirmação e situação esportiva são acompanhadas separadamente.</p></div></div>
        <?php if ($can_lineup ?? false): ?>
            <div class="row"><div class="col-md-7"><div class="gd-academy-form-card"><h3>Alunos do GD Academy</h3><p class="gd-academy-muted">Digite o nome ou a matrícula para filtrar. Os alunos ativos aparecem automaticamente.</p><form id="gd-academy-student-search" action="<?php echo esc(get_uri("grupo_donato/operacional/academy_student_search")); ?>" method="post"><?php echo csrf_field(); ?><input type="hidden" name="category_id" value="<?php echo (int) $category->id; ?>"><input class="form-control" name="query" placeholder="Digite nome ou matrícula" autocomplete="off" aria-label="Filtrar alunos do GD Academy"></form><div id="gd-academy-search-results" class="gd-academy-student-results mt10" aria-live="polite"><div class="gd-academy-muted">Carregando alunos...</div></div></div></div><div class="col-md-5"><div class="gd-academy-form-card"><h3>Atleta convidado</h3><?php echo form_open(get_uri("grupo_donato/operacional/add_event_participant"), ["class" => "gd-academy-ajax-form"]); ?><input type="hidden" name="category_id" value="<?php echo (int) $category->id; ?>"><input type="hidden" name="athlete_type" value="external"><div class="form-group"><label>Nome</label><input class="form-control" name="external_name" required></div><div class="row"><div class="col-6 form-group"><label>Nascimento</label><input class="form-control" type="date" name="birth_date"></div><div class="col-6 form-group"><label>Clube de origem</label><input class="form-control" name="origin_club"></div></div><div class="form-group"><label>Responsável</label><input class="form-control" name="responsible_name"></div><div class="form-group"><label>Telefone</label><input class="form-control" name="phone"></div><button class="btn btn-default" type="submit">Adicionar convidado</button><?php echo form_close(); ?></div></div></div>
        <?php endif; ?>
        <?php if (empty($participants)): ?><div class="gd-academy-empty"><i data-feather="users" class="icon-28"></i><h3>Nenhum atleta convocado</h3><p>Ainda não existem atletas convocados nesta categoria.</p></div><?php else: ?><div class="gd-academy-list"><?php foreach ($participants as $participant): ?><article class="gd-academy-list-item"><div class="d-flex align-items-center gap-2"><img class="gd-academy-avatar" src="<?php echo esc($participantPhoto($participant)); ?>" alt=""><div class="gd-academy-list-item-main"><strong><?php echo esc($participant->athlete_name); ?></strong><small><?php echo $participant->age !== null ? (int) $participant->age . " anos" : "Idade não informada"; ?> · <?php echo esc($participant->athlete_type === "internal" ? ($participant->turma ?? "Aluno GD Academy") : ($participant->origin_club ?? "Atleta convidado")); ?><?php if (!empty($participant->position)): ?> · <?php echo esc($participant->position); ?><?php endif; ?></small></div></div><div class="d-flex flex-wrap gap-1"><span class="gd-academy-status <?php echo ($participant->confirmation_status ?? "") === "confirmed" ? "gd-academy-status-success" : "gd-academy-status-warning"; ?>"><?php echo esc($confirmationLabels[$participant->confirmation_status] ?? $participant->confirmation_status); ?></span><span class="gd-academy-status gd-academy-status-muted"><?php echo esc($lineupLabels[$participant->lineup_status] ?? $participant->lineup_status); ?></span></div><?php if ($can_lineup ?? false): ?><div class="w-100"><div class="row mt10"><div class="col-md-4 mb8"><?php echo form_open(get_uri("grupo_donato/operacional/update_event_participant"), ["class" => "gd-academy-ajax-form"]); ?><input type="hidden" name="participant_id" value="<?php echo (int) $participant->id; ?>"><label class="gd-academy-muted">Situação esportiva</label><select class="form-control" name="lineup_status"><?php foreach ($lineupLabels as $key => $label): ?><option value="<?php echo esc($key); ?>" <?php echo $participant->lineup_status === $key ? "selected" : ""; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></div><div class="col-md-4 mb8"><label class="gd-academy-muted">Confirmação</label><select class="form-control" name="confirmation_status"><?php foreach ($confirmationLabels as $key => $label): ?><option value="<?php echo esc($key); ?>" <?php echo $participant->confirmation_status === $key ? "selected" : ""; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></div><div class="col-md-2 mb8"><label class="gd-academy-muted">Posição</label><input class="form-control" name="position" value="<?php echo esc($participant->position ?? ""); ?>"></div><div class="col-md-2 mb8 d-flex align-items-end"><button class="btn btn-default w-100" type="submit">Salvar</button><?php echo form_close(); ?></div></div></div><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>

    <?php elseif ($section === "partidas"): ?>
        <div class="gd-academy-section-title"><div><h2>Partidas</h2><p class="gd-academy-muted mb0">Jogos somente da categoria <?php echo esc($category->name); ?>.</p></div></div>
        <?php if ($can_manage ?? false): ?><?php echo form_open(get_uri("grupo_donato/operacional/save_event_match"), ["class" => "gd-academy-form-card gd-academy-ajax-form"]); ?><input type="hidden" name="category_id" value="<?php echo (int) $category->id; ?>"><h3>Nova partida</h3><div class="row"><div class="col-md-4 form-group"><label>Identificação</label><input class="form-control" name="name" placeholder="Ex.: Semifinal" required></div><div class="col-md-4 form-group"><label>Adversário</label><input class="form-control" name="opponent"></div><div class="col-md-4 form-group"><label>Fase</label><input class="form-control" name="phase"></div><div class="col-md-3 form-group"><label>Data</label><input class="form-control" type="date" name="match_date"></div><div class="col-md-3 form-group"><label>Horário</label><input class="form-control" type="time" name="match_time"></div><div class="col-md-3 form-group"><label>Campo</label><input class="form-control" name="field_name"></div><div class="col-md-3 form-group"><label>Local</label><input class="form-control" name="location"></div></div><button class="btn btn-primary" type="submit">+ Criar partida</button><?php echo form_close(); ?><?php endif; ?>
        <?php if (empty($matches)): ?><div class="gd-academy-empty"><i data-feather="crosshair" class="icon-28"></i><h3>Nenhuma partida cadastrada</h3><p>Nenhuma partida cadastrada para <?php echo esc($category->name); ?>.</p></div><?php else: ?><div class="gd-academy-list"><?php foreach ($matches as $match): $matchUrl = $categoryUrl . "/partida/" . (int) $match->id; ?><article class="gd-academy-list-item"><div class="gd-academy-list-item-main"><strong><?php echo esc($match->name); ?></strong><small><?php echo esc($match->opponent ?: "Adversário não informado"); ?><?php if (!empty($match->phase)): ?> · <?php echo esc($match->phase); ?><?php endif; ?><?php if (!empty($match->match_date)): ?> · <?php echo esc(date("d/m/Y", strtotime((string) $match->match_date))); ?><?php endif; ?><?php if (!empty($match->match_time)): ?> às <?php echo esc(substr((string) $match->match_time, 0, 5)); ?><?php endif; ?></small></div><div class="d-flex align-items-center gap-2"><strong><?php echo $match->gd_score !== null && $match->opponent_score !== null ? (int) $match->gd_score . " x " . (int) $match->opponent_score : "—"; ?></strong><a class="btn btn-primary btn-sm" href="<?php echo esc($matchUrl); ?>">Abrir partida</a><a class="btn btn-link btn-sm" href="<?php echo esc($matchUrl); ?>" target="_blank" rel="noopener" title="Abrir em nova aba"><i data-feather="external-link" class="icon-14"></i><span class="sr-only">Nova aba</span></a></div></article><?php endforeach; ?></div><?php endif; ?>

    <?php elseif ($section === "avaliacoes"): ?>
        <div class="gd-academy-section-title"><div><h2>Avaliações</h2><p class="gd-academy-muted mb0">Uma ficha individual por atleta, sem formulário em massa.</p></div></div>
        <?php if (empty($evaluations)): ?><div class="gd-academy-empty"><i data-feather="star" class="icon-28"></i><h3>Nenhum atleta para avaliar</h3><p>Convocados ativos aparecerão aqui.</p></div><?php else: ?><div class="gd-academy-list"><?php foreach ($evaluations as $evaluation): $evaluationUrl = $categoryUrl . "/avaliacao/" . (int) $evaluation->participant_id; $done = !empty($evaluation->evaluation_id); ?><article class="gd-academy-list-item"><div class="gd-academy-list-item-main"><strong><?php echo esc($evaluation->athlete_name); ?></strong><small><?php echo $done ? "Avaliado em " . esc(date("d/m/Y", strtotime((string) $evaluation->evaluated_at))) : "Ainda não avaliado"; ?><?php if ($done && $evaluation->average_score !== null): ?> · média <?php echo esc(number_format((float) $evaluation->average_score, 1, ",", ".")); ?><?php endif; ?></small></div><span class="gd-academy-status <?php echo $done ? "gd-academy-status-success" : "gd-academy-status-warning"; ?>"><?php echo $done ? "Concluída" : "Pendente"; ?></span><div><a class="btn btn-primary btn-sm" href="<?php echo esc($evaluationUrl); ?>"><?php echo $done ? "Abrir avaliação" : "Avaliar"; ?></a><a class="btn btn-link btn-sm" href="<?php echo esc($evaluationUrl); ?>" target="_blank" rel="noopener" title="Abrir em nova aba"><i data-feather="external-link" class="icon-14"></i><span class="sr-only">Nova aba</span></a></div></article><?php endforeach; ?></div><?php endif; ?>

    <?php elseif ($section === "estatisticas"): ?>
        <div class="gd-academy-section-title"><div><h2>Estatísticas</h2><p class="gd-academy-muted mb0">Resumo acumulado dos atletas desta categoria.</p></div></div>
        <?php if (empty($stats)): ?><div class="gd-academy-empty"><i data-feather="bar-chart-2" class="icon-28"></i><h3>Nenhuma estatística registrada</h3><p>As estatísticas aparecerão depois dos registros nas partidas.</p></div><?php else: ?><div class="gd-academy-table-wrap"><table class="table gd-academy-table"><thead><tr><th>Atleta</th><th>Partidas</th><th>Gols</th><th>Assistências</th><th>Defesas</th><th>Minutos</th></tr></thead><tbody><?php foreach ($stats as $stat): ?><tr><td data-label="Atleta"><strong><?php echo esc($stat->athlete_name); ?></strong></td><td data-label="Partidas"><?php echo (int) $stat->matches_played; ?></td><td data-label="Gols"><?php echo (int) $stat->goals; ?></td><td data-label="Assistências"><?php echo (int) $stat->assists; ?></td><td data-label="Defesas"><?php echo (int) $stat->saves; ?></td><td data-label="Minutos"><?php echo (int) $stat->minutes_played; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
    <?php endif; ?>
</div>
<?php echo view('grupo_donato_gestao\Operacional\Views\_academy_actions'); ?>
<?php if ($section === "convocacao" && ($can_lineup ?? false)): ?>
<script>
$(function(){
    var form = $("#gd-academy-student-search"), input = form.find("[name='query']"), results = $("#gd-academy-search-results"), timer = null, xhr = null, requestId = 0;

    function renderStudents(rows) {
        results.empty();
        if (!rows.length) {
            results.html('<div class="gd-academy-muted">Nenhum aluno ativo encontrado.</div>');
            return;
        }

        var summary = $("<div class='gd-academy-muted mb8'></div>");
        summary.text(rows.length + " aluno(s) encontrado(s)");
        results.append(summary);

        rows.forEach(function(row) {
            var item = $("<div class='gd-academy-student-result mb8'><div class='gd-academy-student-result-info'><strong></strong><small></small></div><span class='gd-academy-student-result-status'></span><button class='btn btn-primary btn-sm' type='button'>Adicionar</button></div>");
            var details = [];
            if (row.matricula) details.push("Matrícula " + row.matricula);
            if (row.turma) details.push(row.turma);
            if (row.age !== null && row.age !== undefined) details.push(row.age + " anos");
            item.find("strong").text(row.nome_aluno || row.name || "Aluno");
            item.find("small").text(details.join(" · ") || "Aluno ativo");

            if (row.age_compatible === false) {
                item.find(".gd-academy-student-result-status").text("Fora da faixa").addClass("gd-academy-status gd-academy-status-warning");
            }

            var button = item.find("button");
            if (row.already_added) {
                button.prop("disabled", true).removeClass("btn-primary").addClass("btn-default").text("Já convocado");
            } else {
                button.on("click", function() {
                    button.prop("disabled", true).text("Adicionando...");
                    appAjaxRequest({
                        url: "<?php echo get_uri("grupo_donato/operacional/add_event_participant"); ?>",
                        type: "POST",
                        data: {category_id: <?php echo (int) $category->id; ?>, athlete_type: "internal", student_id: row.id},
                        dataType: "json",
                        success: function(add) {
                            if (add && add.success) {
                                window.location.reload();
                            } else {
                                appAlert.error((add && add.message) || "Não foi possível convocar o aluno.");
                                button.prop("disabled", false).text("Adicionar");
                            }
                        },
                        error: function() {
                            appAlert.error("Não foi possível convocar o aluno.");
                            button.prop("disabled", false).text("Adicionar");
                        }
                    });
                });
            }
            results.append(item);
        });
    }

    function searchStudents() {
        var current = ++requestId;
        if (xhr && xhr.abort) xhr.abort();
        results.html('<div class="gd-academy-muted">Buscando alunos...</div>');
        xhr = appAjaxRequest({
            url: form.attr("action"),
            type: "POST",
            data: form.serialize(),
            dataType: "json",
            success: function(response) {
                if (current !== requestId) return;
                renderStudents((response && response.data && response.data.data) || []);
            },
            error: function() {
                if (current === requestId) results.html('<div class="text-danger">Não foi possível carregar os alunos.</div>');
            }
        });
    }

    form.on("submit", function(e) {
        e.preventDefault();
        clearTimeout(timer);
        searchStudents();
    });
    input.on("input", function() {
        clearTimeout(timer);
        timer = setTimeout(searchStudents, 250);
    });
    searchStudents();
});
</script>
<?php endif; ?>
<?php if ($section === "convocacao" && ($can_lineup ?? false)): ?>
<script>
$(function(){
    $.ajaxPrefilter(function(options){
        if(options.url && options.url.indexOf("/add_event_participant") !== -1 && options.data && typeof options.data === "object"){options.data["<?php echo csrf_token(); ?>"]="<?php echo csrf_hash(); ?>";}
    });
});
</script>
<?php endif; ?>
<?php if ($section === "convocacao" && ($can_lineup ?? false)): ?>
<script>
$(function(){
    var selector="form[action*='/update_event_participant']";
    $(document).off("submit.gdAcademy",selector).off("submit.gdAcademyCategory",selector).on("submit.gdAcademyCategory",selector,function(e){
        e.preventDefault();
        var form=this,scope=$(form).closest(".w-100"),button=scope.find("button[type='submit']"),original=button.html();
        button.prop("disabled",true).text("Salvando...");
        appAjaxRequest({url:form.action,type:"POST",data:scope.find("input,select").serialize(),dataType:"json",success:function(result){if(result&&result.success){window.location.reload();return;}appAlert.error((result&&result.message)||"Não foi possível atualizar o atleta.");button.prop("disabled",false).html(original);},error:function(){appAlert.error("Não foi possível atualizar o atleta.");button.prop("disabled",false).html(original);}});
    });
});
</script>
<?php endif; ?>
