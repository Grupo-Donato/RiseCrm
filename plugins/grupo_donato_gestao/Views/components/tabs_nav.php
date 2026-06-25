<?php
/**
 * Barra de abas horizontal padronizada (idioma do Rise: nav-tabs).
 *
 * Espera:
 *  - $items: lista de ["key" => string, "url" => uri-relativa, "label" => texto, "icon" => feather (opcional)]
 *  - $active: chave do item ativo
 *
 * As telas continuam existindo individualmente; esta barra apenas conecta as
 * telas relacionadas sem depender do menu lateral (protótipo).
 */
$items = isset($items) && is_array($items) ? $items : [];
$active = isset($active) ? $active : "";
?>
<ul class="nav nav-tabs bg-white title scrollable-tabs mb-3" role="tablist">
    <?php foreach ($items as $item) {
        $is_active = ($active === ($item["key"] ?? "")) ? " active" : "";
        $icon = $item["icon"] ?? "";
        ?>
        <li class="nav-item">
            <a class="nav-link<?php echo $is_active; ?>" href="<?php echo get_uri($item["url"] ?? ""); ?>" role="tab">
                <?php if ($icon) { ?><i data-feather="<?php echo htmlspecialchars((string) $icon, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>" class="icon-16"></i> <?php } ?>
                <?php echo htmlspecialchars((string) ($item["label"] ?? ""), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?>
            </a>
        </li>
    <?php } ?>
</ul>
