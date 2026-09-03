<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$indicators = $dashboard["indicators"] ?? [];
$events = is_array($dashboard["events"] ?? null) ? $dashboard["events"] : [];
$pending = is_array($dashboard["pending"] ?? null) ? $dashboard["pending"] : [];
$can_manage = !empty($can_manage);
$money = static fn($value): string => "R$ " . number_format((float) ($value ?? 0), 2, ",", ".");
$eventTypes = [
    "championship" => "Campeonato", "cup" => "Copa", "tournament" => "Torneio", "friendly" => "Amistoso",
    "festival" => "Festival", "single_game" => "Jogo isolado", "official" => "Oficial", "unofficial" => "Não oficial", "other" => "Outro",
];
$statusLabels = [
    "draft" => "Rascunho", "registrations_open" => "Inscrições abertas", "confirmed" => "Confirmado",
    "in_progress" => "Em andamento", "completed" => "Concluído", "cancelled" => "Cancelado",
];
$statusClass = static function (string $status): string {
    return ["completed" => "bg-success", "in_progress" => "bg-info", "cancelled" => "bg-danger", "confirmed" => "bg-primary"][$status] ?? "bg-secondary";
};
$activeUnitSlug = (string) ($unidade_atual->slug ?? "sao_bernardo_do_campo");
?>
<style>
    .gd-events-list-page {
        --events-bg: var(--gd-bg, #03182f);
        --events-surface: var(--gd-surface, #082a52);
        --events-surface-2: var(--gd-surface-2, #0b315f);
        --events-line: var(--gd-border, #244d78);
        --events-text: var(--gd-text, #fff);
        --events-muted: var(--gd-muted, #b7c5d8);
        --events-accent: var(--gd-gold, #d2a63a);
        --events-accent-hover: var(--gd-gold-hover, #e4bc55);
        background: var(--events-bg);
        color: var(--events-text);
        font-size: 14px;
    }

    .gd-events-list-page h1,
    .gd-events-list-page h2,
    .gd-events-list-page h3,
    .gd-events-list-page label { color: var(--events-text); }
    .gd-events-list-page a:not(.btn) { color: var(--events-accent-hover); }
    .gd-events-list-page .gd-kicker { color: var(--events-accent-hover); font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .gd-events-list-page .gd-page-heading { align-items: flex-start; display: flex; gap: 18px; justify-content: space-between; }
    .gd-events-list-page .gd-page-heading h1 { font-size: 26px; font-weight: 600; margin: 4px 0 5px; }
    .gd-events-list-page .gd-page-heading p { color: var(--events-muted); margin: 0; }
    .gd-events-list-page .gd-filter-card,
    .gd-events-list-page .gd-kpi,
    .gd-events-list-page .gd-event-card,
    .gd-events-list-page .gd-empty { background: var(--events-surface) !important; border: 1px solid var(--events-line) !important; color: var(--events-text); }
    .gd-events-list-page .gd-filter-card { border-radius: 10px; margin: 22px 0; padding: 16px; }
    .gd-events-list-page .gd-filter-card label { color: var(--events-muted); font-size: 12px; font-weight: 600; }
    .gd-events-list-page .form-control,
    .gd-events-list-page select { background-color: var(--events-surface-2) !important; border-color: var(--events-surface-2) !important; color: var(--events-text) !important; }
    .gd-events-list-page .form-control:focus,
    .gd-events-list-page select:focus { background-color: var(--events-bg) !important; border-color: var(--gd-border-strong, #315d8b) !important; }
    .gd-events-list-page .form-control::placeholder { color: var(--events-muted); opacity: .8; }
    .gd-events-list-page option { background: var(--events-surface); color: var(--events-text); }
    .gd-events-list-page input[type="date"] { color-scheme: dark; }
    .gd-events-list-page .gd-kpi { border-radius: 10px; min-height: 90px; padding: 14px 16px; }
    .gd-events-list-page .gd-kpi span { color: var(--events-muted); display: block; font-size: 12px; }
    .gd-events-list-page .gd-kpi strong { color: var(--events-text); display: block; font-size: 23px; margin-top: 7px; }
    .gd-events-list-page .gd-event-card { border-radius: 10px; height: 100%; padding: 17px; transition: border-color .15s ease, transform .15s ease; }
    .gd-events-list-page .gd-event-card:hover { border-color: var(--gd-border-strong, #315d8b) !important; transform: translateY(-1px); }
    .gd-events-list-page .gd-event-card h3 { color: var(--events-text); font-size: 18px; font-weight: 600; margin: 0; }
    .gd-events-list-page .gd-event-meta { color: var(--events-muted); font-size: 13px; line-height: 1.55; margin: 11px 0; }
    .gd-events-list-page .gd-event-categories { color: var(--events-text); font-size: 12px; min-height: 19px; }
    .gd-events-list-page .gd-event-stats { border-top: 1px solid var(--events-line); display: grid; gap: 8px; grid-template-columns: repeat(3, 1fr); margin-top: 14px; padding-top: 13px; }
    .gd-events-list-page .gd-event-stats strong { color: var(--events-text); display: block; font-size: 17px; }
    .gd-events-list-page .gd-event-stats span { color: var(--events-muted); font-size: 11px; }
    .gd-events-list-page .gd-empty { border-style: dashed !important; border-radius: 10px; color: var(--events-muted); padding: 40px 20px; text-align: center; }
    .gd-events-list-page .gd-empty h3 { color: var(--events-text); }
    .gd-events-list-page .text-off { color: var(--events-muted) !important; }
    .gd-events-list-page .btn-primary { background-color: var(--events-accent) !important; border-color: var(--events-accent) !important; color: var(--events-bg) !important; }
    .gd-events-list-page .btn-primary:hover,
    .gd-events-list-page .btn-primary:focus { background-color: var(--events-accent-hover) !important; border-color: var(--events-accent-hover) !important; }
    .gd-events-list-page .btn-link { color: var(--events-accent-hover) !important; }

    @media (max-width: 767px) {
        .gd-events-list-page .gd-page-heading { align-items: stretch; flex-direction: column; }
        .gd-events-list-page .gd-page-heading .btn { width: 100%; }
        .gd-events-list-page .gd-event-stats { gap: 4px; }
        .gd-events-list-page .gd-event-stats strong { font-size: 15px; }
    }
</style>

<div id="page-content" class="page-wrapper clearfix gd-events-list-page">
    <div class="gd-page-heading">
        <div>
            <div class="gd-kicker">GD Academy</div>
            <h1>Eventos</h1>
            <p>Encontre um evento e abra seu espaço de operação.</p>
        </div>
        <?php if ($can_manage): ?>
            <a class="btn btn-primary" href="<?php echo esc(get_uri("grupo_donato/operacional/evento-novo")); ?>"><i data-feather="plus" class="icon-16"></i> Novo evento</a>
        <?php endif; ?>
    </div>

    <div class="row mt20">
        <?php foreach ([
            ["Próximos eventos", (int) ($indicators["upcoming_events"] ?? 0)],
            ["Em andamento", (int) ($indicators["in_progress_events"] ?? 0)],
            ["Pendências", count($pending)],
            ["Pagamentos pendentes", (int) ($indicators["pending_payments"] ?? 0)],
        ] as $kpi): ?>
            <div class="col-6 col-md-3 mb10"><div class="gd-kpi"><span><?php echo esc($kpi[0]); ?></span><strong><?php echo esc((string) $kpi[1]); ?></strong></div></div>
        <?php endforeach; ?>
    </div>

    <div class="gd-filter-card">
        <form method="get" action="<?php echo esc(get_uri("grupo_donato/operacional")); ?>">
            <input type="hidden" name="gd_tab" value="eventos">
            <div class="row align-items-end">
                <div class="col-md-4 mb10"><label for="gd-event-search">Buscar evento</label><input id="gd-event-search" class="form-control" type="search" name="event_search" value="<?php echo esc($_GET["event_search"] ?? ""); ?>" placeholder="Nome, local ou organização"></div>
                <div class="col-md-2 mb10"><label for="gd-event-date-from">De</label><input id="gd-event-date-from" class="form-control" type="date" name="event_date_from" value="<?php echo esc($_GET["event_date_from"] ?? ""); ?>"></div>
                <div class="col-md-2 mb10"><label for="gd-event-date-to">Até</label><input id="gd-event-date-to" class="form-control" type="date" name="event_date_to" value="<?php echo esc($_GET["event_date_to"] ?? ""); ?>"></div>
                <div class="col-md-2 mb10"><label for="gd-event-status">Status</label><select id="gd-event-status" class="form-control" name="event_status"><option value="">Todos</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?php echo esc($key); ?>" <?php echo (($_GET["event_status"] ?? "") === $key) ? "selected" : ""; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-2 mb10"><label for="gd-event-type">Tipo</label><select id="gd-event-type" class="form-control" name="event_type"><option value="">Todos</option><?php foreach ($eventTypes as $key => $label): ?><option value="<?php echo esc($key); ?>" <?php echo (($_GET["event_type"] ?? "") === $key) ? "selected" : ""; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3 mb0"><label for="gd-event-unit">Unidade</label><select id="gd-event-unit" class="form-control"><option value="<?php echo esc($activeUnitSlug); ?>"><?php echo esc($unidade_atual->nome_unidade ?? "Unidade atual"); ?></option><?php foreach (($unidades_contexto_dropdown ?? []) as $slug => $label): ?><option value="<?php echo esc($slug); ?>" <?php echo $slug === $activeUnitSlug ? "selected" : ""; ?>><?php echo esc($label); ?></option><?php endforeach; ?></select></div>
                <div class="col-md-9 text-md-end mt10 mt-md-0"><button class="btn btn-default" type="submit"><i data-feather="search" class="icon-14"></i> Filtrar</button> <a class="btn btn-link" href="<?php echo esc(get_uri("grupo_donato/operacional?gd_tab=eventos")); ?>">Limpar filtros</a></div>
            </div>
        </form>
    </div>

    <?php if (!$events): ?>
        <div class="gd-empty"><i data-feather="calendar" class="icon-32"></i><h3 class="mt15">Nenhum evento encontrado</h3><p>Crie o primeiro evento ou ajuste os filtros da busca.</p><?php if ($can_manage): ?><a class="btn btn-primary" href="<?php echo esc(get_uri("grupo_donato/operacional/evento-novo")); ?>">+ Criar primeiro evento</a><?php endif; ?></div>
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-between mb15"><h2 class="h4 mb0">Eventos encontrados</h2><span class="text-off small"><?php echo count($events); ?> exibido(s)</span></div>
        <div class="row">
            <?php foreach ($events as $event): $metrics = $event->metrics ?? []; $eventUrl = get_uri("grupo_donato/operacional/evento/" . (int) $event->id); ?>
                <div class="col-md-6 col-xl-4 mb15"><article class="gd-event-card">
                    <div class="d-flex align-items-start justify-content-between gap-2"><h3><?php echo esc($event->name); ?></h3><span class="badge <?php echo esc($statusClass((string) $event->status)); ?>"><?php echo esc($statusLabels[$event->status] ?? $event->status); ?></span></div>
                    <div class="gd-event-meta"><div><i data-feather="calendar" class="icon-14"></i> <?php echo esc(date("d/m/Y", strtotime((string) $event->starts_on))); ?><?php if (!empty($event->event_time)): ?> às <?php echo esc(substr((string) $event->event_time, 0, 5)); ?><?php endif; ?></div><div><i data-feather="map-pin" class="icon-14"></i> <?php echo esc($event->location ?: "Local não informado"); ?></div><div><i data-feather="award" class="icon-14"></i> <?php echo esc($eventTypes[$event->event_type] ?? $event->event_type); ?></div></div>
                    <div class="gd-event-categories"><?php echo esc($metrics["category_names"] ?? "Nenhuma categoria cadastrada"); ?></div>
                    <div class="gd-event-stats"><div><strong><?php echo (int) ($metrics["called"] ?? 0); ?></strong><span>convocados</span></div><div><strong><?php echo (int) ($metrics["confirmed"] ?? 0); ?></strong><span>confirmados</span></div><div><strong><?php echo $money($metrics["open_amount"] ?? 0); ?></strong><span>em aberto</span></div></div>
                    <div class="d-flex align-items-center justify-content-between mt15"><a class="btn btn-primary btn-sm" href="<?php echo esc($eventUrl); ?>">Abrir evento</a><a class="btn btn-link btn-sm" href="<?php echo esc($eventUrl); ?>" target="_blank" rel="noopener" title="Abrir evento em nova aba"><i data-feather="external-link" class="icon-14"></i><span class="sr-only">Nova aba</span></a></div>
                </article></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
$(function(){
    $("#gd-event-unit").on("change",function(){
        var slug=$(this).val();
        appAjaxRequest({url:"<?php echo get_uri("grupo_donato/operacional/trocar_unidade"); ?>",type:"POST",data:{unidade_slug:slug},dataType:"json",success:function(r){if(r&&r.success){window.location.reload();}else{appAlert.error((r&&r.message)||"Não foi possível trocar a unidade.");}}});
    });
});
</script>
