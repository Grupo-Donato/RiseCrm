<?php
$active = (string) ($active ?? "");
$can_calendar = !empty($can_calendar);
$can_barbecue_rentals = !empty($can_barbecue_rentals);
$can_finance = !empty($can_finance);

$items = [];
if ($can_calendar) {
    $items[] = ["key" => "agenda", "url" => "grupo_donato/barbecue-calendar", "label" => app_lang("gd_menu_barbecue_agenda"), "icon" => "calendar"];
}
if ($can_barbecue_rentals) {
    $items[] = ["key" => "single", "url" => "grupo_donato/barbecue-rentals", "label" => app_lang("gd_menu_barbecue_reservas"), "icon" => "clipboard"];
}
if ($can_finance) {
    $items[] = ["key" => "finance", "url" => "grupo_donato/finance/barbecue-payments", "label" => app_lang("gd_menu_barbecue_finance"), "icon" => "dollar-sign"];
}

echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["items" => $items, "active" => $active]);
?>
