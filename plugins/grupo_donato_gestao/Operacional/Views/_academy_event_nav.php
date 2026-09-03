<?php
$eventUrl = get_uri("grupo_donato/operacional/evento/" . (int) $event->id);
$eventTypeLabels = ["championship" => "Campeonato", "cup" => "Copa", "tournament" => "Torneio", "friendly" => "Amistoso", "festival" => "Festival", "single_game" => "Jogo isolado", "official" => "Oficial", "unofficial" => "Não oficial", "other" => "Outro"];
$eventStatusLabels = ["draft" => "Rascunho", "registrations_open" => "Inscrições abertas", "confirmed" => "Confirmado", "in_progress" => "Em andamento", "completed" => "Concluído", "cancelled" => "Cancelado"];
$eventStatusClass = ["completed" => "bg-success", "in_progress" => "bg-info", "cancelled" => "bg-danger", "confirmed" => "bg-primary"][(string) $event->status] ?? "bg-secondary";
$eventSection = $section ?? "resumo";
?>
<div class="gd-academy-header">
    <div><div class="gd-academy-kicker">Evento</div><h1><?php echo esc($event->name); ?></h1><div class="gd-academy-subtitle"><i data-feather="calendar" class="icon-14"></i> <?php echo esc(date("d/m/Y", strtotime((string) $event->starts_on))); ?><?php if (!empty($event->event_time)): ?> · <?php echo esc(substr((string) $event->event_time, 0, 5)); ?><?php endif; ?> · <i data-feather="map-pin" class="icon-14"></i> <?php echo esc($event->location ?: "Local não informado"); ?> · <?php echo esc($eventTypeLabels[$event->event_type] ?? $event->event_type); ?></div></div>
    <div class="gd-academy-header-actions"><span class="badge <?php echo esc($eventStatusClass); ?>"><?php echo esc($eventStatusLabels[$event->status] ?? $event->status); ?></span><a class="btn btn-default btn-sm" href="<?php echo esc(get_uri("grupo_donato/operacional?gd_tab=eventos")); ?>">← Eventos</a></div>
</div>
<nav class="gd-academy-nav" aria-label="Navegação do evento">
    <?php foreach (["resumo" => "Resumo", "categorias" => "Categorias", "financeiro" => "Financeiro", "checklist" => "Checklist", "configuracoes" => "Configurações"] as $key => $label): ?>
        <?php if ($key === "financeiro" && empty($can_finance)) continue; ?><?php if ($key === "configuracoes" && empty($can_manage)) continue; ?>
        <a class="<?php echo $eventSection === $key ? "active" : ""; ?>" href="<?php echo esc($key === "resumo" ? $eventUrl : $eventUrl . "/" . $key); ?>"><?php echo esc($label); ?></a>
    <?php endforeach; ?>
</nav>
