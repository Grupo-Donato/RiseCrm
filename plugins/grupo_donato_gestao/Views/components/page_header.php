<?php
/**
 * Cabeçalho simples de página/seção. Espera: $title e (opcional) $buttons (HTML).
 */
$title = isset($title) ? $title : "";
$buttons = isset($buttons) ? $buttons : "";
?>
<div class="page-title clearfix">
    <h4><?php echo htmlspecialchars((string) $title, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></h4>
    <?php if ($buttons) { ?>
        <div class="title-button-group"><?php echo $buttons; ?></div>
    <?php } ?>
</div>
