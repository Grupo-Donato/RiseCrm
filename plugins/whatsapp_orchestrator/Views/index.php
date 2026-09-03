<?php
$tabs = [
    'dashboard' => ['label' => 'Visão geral', 'icon' => 'activity'],
    'conversations' => ['label' => 'Conversas', 'icon' => 'message-circle'],
    'contacts' => ['label' => 'Contatos', 'icon' => 'users'],
    'instances' => ['label' => 'Instâncias', 'icon' => 'smartphone'],
    'campaigns' => ['label' => 'Campanhas', 'icon' => 'send'],
    'bots' => ['label' => 'Bots', 'icon' => 'git-branch'],
    'settings' => ['label' => 'Configurações', 'icon' => 'settings']
];

if (empty($can_manage_instances)) {
    unset($tabs['instances']);
}
if (empty($can_manage_settings)) {
    unset($tabs['settings']);
}
if (empty($can_manage_contacts)) { unset($tabs['contacts']); }
if (empty($can_manage_campaigns)) { unset($tabs['campaigns']); }
if (empty($can_manage_bots)) { unset($tabs['bots']); }

$active_tab = $active_tab ?? 'dashboard';
if (!isset($tabs[$active_tab])) {
    $active_tab = 'dashboard';
}

include __DIR__ . '/partials/styles.php';
?>

<div id="page-content" class="page-wrapper clearfix impulso-page-content<?php echo $active_tab === 'conversations' ? ' impulso-page-content--conversations' : ''; ?>">
    <div id="impulso-hub-app" class="impulso-hub" data-active-tab="<?php echo esc($active_tab); ?>">
        <div class="card impulso-shell-card">
            <?php if (!empty($integration_error)) { ?>
                <div class="alert alert-danger m-3" role="alert"><?php echo esc($integration_error); ?></div>
            <?php } ?>

            <?php if (!empty($can_send_messages)) { ?>
                <div class="page-title clearfix impulso-topbar impulso-topbar--compact">
                    <div class="title-button-group skip-dropdown-migration impulso-topbar-actions">
                        <button class="btn btn-primary" type="button" data-impulso-action="new-conversation" aria-label="Nova conversa" title="Nova conversa">
                            <i data-feather="plus"></i> Nova conversa
                        </button>
                    </div>
                </div>
            <?php } ?>

            <div class="impulso-workspace">
                <?php include __DIR__ . '/partials/' . $active_tab . '.php'; ?>
            </div>
        </div>

        <?php include __DIR__ . '/modals/common.php'; ?>
    </div>
</div>

<?php include __DIR__ . '/partials/scripts.php'; ?>
