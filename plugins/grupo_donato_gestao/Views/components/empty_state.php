<?php
/**
 * Estado vazio padronizado para tabelas/listas.
 *
 * Espera (todos opcionais):
 *  - $message: texto principal (default: gd_no_records)
 *  - $icon: feather icon (default: "inbox")
 *  - $action: HTML de um botão/atalho opcional
 */
$message = isset($message) && $message !== "" ? $message : app_lang("gd_no_records");
$icon = isset($icon) && $icon !== "" ? $icon : "inbox";
$action = isset($action) ? $action : "";
?>
<div class="text-center text-muted p-4">
    <i data-feather="<?php echo htmlspecialchars((string) $icon, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" class="icon-24 mb-2"></i>
    <div><?php echo htmlspecialchars((string) $message, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></div>
    <?php if ($action) { ?><div class="mt-3"><?php echo $action; ?></div><?php } ?>
</div>
