<?php
/**
 * Navegação lateral das telas de configuração do plugin.
 * Espera: $active_tab. Usa $login_user (injetado pelo Template) para filtrar
 * por permissão. Admin vê tudo.
 */
$active_tab = isset($active_tab) ? $active_tab : "";
$is_admin = !empty($login_user->is_admin);
$perms = isset($login_user->permissions) && is_array($login_user->permissions) ? $login_user->permissions : array();

$can = function ($key) use ($is_admin, $perms) {
    if ($is_admin || get_array_value($perms, $key)) {
        return true;
    }
    $implied = array(
        "gd_settings_view" => "gd_settings_manage",
        "gd_units_view" => "gd_units_manage",
        "gd_business_areas_view" => "gd_business_areas_manage",
        "gd_cost_centers_view" => "gd_cost_centers_manage",
    );
    return isset($implied[$key]) && get_array_value($perms, $implied[$key]);
};

$items = array();
if ($can("gd_settings_view")) {
    $items[] = array("key" => "general", "url" => "grupo_donato/settings/general", "label" => app_lang("gd_settings_general"), "icon" => "sliders");
}
if ($can("gd_units_view")) {
    $items[] = array("key" => "units", "url" => "grupo_donato/settings/units", "label" => app_lang("gd_units"), "icon" => "map-pin");
}
if ($can("gd_business_areas_view")) {
    $items[] = array("key" => "business_areas", "url" => "grupo_donato/settings/business-areas", "label" => app_lang("gd_business_areas"), "icon" => "grid");
}
if ($can("gd_cost_centers_view")) {
    $items[] = array("key" => "cost_centers", "url" => "grupo_donato/settings/cost-centers", "label" => app_lang("gd_cost_centers"), "icon" => "pie-chart");
}
if ($can("gd_audit_view")) {
    $items[] = array("key" => "audit", "url" => "grupo_donato/audit", "label" => app_lang("gd_audit"), "icon" => "shield");
}
?>
<div class="card">
    <div class="page-title"><h4><?php echo app_lang("gd_app_title"); ?></h4></div>
    <ul class="list-group list-group-flush">
        <?php foreach ($items as $item) { ?>
            <a href="<?php echo get_uri($item["url"]); ?>"
               class="list-group-item list-group-item-action <?php echo $active_tab === $item["key"] ? "active" : ""; ?>">
                <i data-feather="<?php echo $item["icon"]; ?>" class="icon-16"></i>
                <?php echo $item["label"]; ?>
            </a>
        <?php } ?>
    </ul>
</div>
