<?php
$active = (string) ($active ?? "");
$can_calendar = !empty($can_calendar);
$can_court_rentals = !empty($can_court_rentals);
$can_bookings = !empty($can_bookings);
$can_series = !empty($can_series);
$can_finance = !empty($can_finance);

$reservations_uri = $can_court_rentals
    ? "grupo_donato/court-rentals"
    : ($can_bookings ? "grupo_donato/bookings" : "grupo_donato/booking-series");

$items = [];
if ($can_calendar) {
    $items[] = ["key" => "agenda", "url" => "grupo_donato/calendar", "label" => app_lang("gd_menu_rental_agenda"), "icon" => "calendar"];
}
if ($can_court_rentals || $can_bookings || $can_series) {
    $items[] = ["key" => "reservations", "url" => $reservations_uri, "label" => app_lang("gd_menu_rental_bookings"), "icon" => "clipboard"];
}
if ($can_court_rentals) {
    $items[] = ["key" => "monthly", "url" => "grupo_donato/court-rentals/monthly", "label" => app_lang("gd_menu_rental_monthly"), "icon" => "repeat"];
}
if ($can_finance) {
    $items[] = ["key" => "finance", "url" => "grupo_donato/finance/rental-payments", "label" => app_lang("gd_menu_rental_finance"), "icon" => "dollar-sign"];
}

echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["items" => $items, "active" => $active]);
?>
