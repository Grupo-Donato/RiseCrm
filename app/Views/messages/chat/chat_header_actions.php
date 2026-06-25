<?php
$isMobile = preg_match('/(android|iphone|ipad|windows phone)/i', get_array_value($_SERVER, 'HTTP_USER_AGENT'));
?>
<div class="chat-close chat-topbar-btn" id="chat-close-icon" data-type="open">
    <i data-feather="x" class="icon"></i>
</div>

<?php if (!$isMobile) { ?>
    <div class="more-options chat-topbar-btn" data-bs-toggle="dropdown" aria-expanded="true">
        <i data-feather="more-horizontal" class="icon-16"></i>
    </div>
    <ul class="dropdown-menu dropdown-menu-end" role="menu">
        <a role="presentation" href="javascript:;" class="dropdown-item reset-chat-dimension"><i data-feather="refresh-cw" class="icon-16 me-1"></i> <?php echo app_lang('reset_window'); ?></a>
        <a role="presentation" href="javascript:;" class="dropdown-item chat-full-screen"><i data-feather="maximize" class="icon-16 me-1"></i> <?php echo app_lang('full_screen'); ?></a>
        <a role="presentation" href="javascript:;" class="dropdown-item chat-exit-full-screen hide"><i data-feather="minimize" class="icon-16 me-1"></i> <?php echo app_lang('exit_full_screen'); ?></a>
    </ul>
<?php } ?>