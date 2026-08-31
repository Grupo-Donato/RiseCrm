<?php
/**
 * NavegaÃ§Ã£o interna do catÃ¡logo. As telas nÃ£o dependem do menu lateral:
 * produtos, categorias, recursos e preÃ§os permanecem no mesmo fluxo.
 */
$items = [
    ["key" => "products", "url" => "grupo_donato/catalog/products", "label" => app_lang("gd_menu_products"), "icon" => "package"],
];
if (!empty($can_categories)) {
    $items[] = ["key" => "categories", "url" => "grupo_donato/catalog/categories", "label" => app_lang("gd_product_categories"), "icon" => "folder"];
}
if (!empty($can_resources)) {
    $items[] = ["key" => "resources", "url" => "grupo_donato/resources", "label" => app_lang("gd_menu_resources"), "icon" => "map"];
}
if (!empty($can_pricing)) {
    $items[] = ["key" => "pricing", "url" => "grupo_donato/pricing/lists", "label" => app_lang("gd_menu_price_lists"), "icon" => "tag"];
    $items[] = ["key" => "resolver", "url" => "grupo_donato/pricing/resolver", "label" => app_lang("gd_price_resolver"), "icon" => "search"];
}
echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["items" => $items, "active" => $active_catalog_tab ?? ""]);
