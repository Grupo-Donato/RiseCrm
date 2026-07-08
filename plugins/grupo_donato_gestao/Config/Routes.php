<?php

if (defined("GRUPO_DONATO_GESTAO_ROUTES_LOADED")) {
    return;
}
define("GRUPO_DONATO_GESTAO_ROUTES_LOADED", true);

if (!isset($routes)) {
    $routes = \Config\Services::routes(true);
}

// Landing de staff: o núcleo do Rise encaminha para "green_crm" (pós-login, menu
// "Dashboard" e app_redirect interno). Aqui o destino é redirecionado para o painel
// operacional do Grupo Donato, sem alterar arquivos do núcleo.
$routes->match(["get", "post"], "green_crm", static function () {
    helper("url");
    return redirect()->to(site_url("grupo_donato/operacional") . "?gd_tab=dashboard");
});

$routes->group("grupo_donato/finance/rental-payments", ["namespace" => "grupo_donato_gestao\\Controllers"], function ($routes) {
    $routes->get("", "Rental_finance::index");
    $routes->post("data", "Rental_finance::list_data");
    $routes->post("summary", "Rental_finance::summary");
    $routes->post("generate", "Rental_finance::generate_month");
    $routes->post("create-rental-charge", "Rental_finance::create_rental_charge");
});

$routes->group("grupo_donato", ["namespace" => "grupo_donato_gestao\\Controllers", "filter" => "csrf"], function ($routes) {

    // Painel
    $routes->get("/", "Dashboard::index");
    $routes->get("dashboard", "Dashboard::index");

    // Configurações — geral
    $routes->get("settings", "Settings::index");
    $routes->get("settings/general", "Settings::general");
    $routes->post("settings/general/save", "Settings::save_general");

    // Configurações — unidades
    $routes->get("settings/units", "Units::index");
    $routes->post("settings/units/list_data", "Units::list_data");
    $routes->post("settings/units/modal_form", "Units::modal_form");
    $routes->post("settings/units/save", "Units::save");
    $routes->post("settings/units/delete", "Units::delete");

    // Configurações — áreas de negócio
    $routes->get("settings/business-areas", "Business_areas::index");
    $routes->post("settings/business-areas/list_data", "Business_areas::list_data");
    $routes->post("settings/business-areas/modal_form", "Business_areas::modal_form");
    $routes->post("settings/business-areas/save", "Business_areas::save");
    $routes->post("settings/business-areas/delete", "Business_areas::delete");

    // Configurações — centros de resultado
    $routes->get("settings/cost-centers", "Cost_centers::index");
    $routes->post("settings/cost-centers/list_data", "Cost_centers::list_data");
    $routes->post("settings/cost-centers/modal_form", "Cost_centers::modal_form");
    $routes->post("settings/cost-centers/save", "Cost_centers::save");
    $routes->post("settings/cost-centers/delete", "Cost_centers::delete");

    // Auditoria
    $routes->get("audit", "Audit::index");
    $routes->post("audit/list_data", "Audit::list_data");
    $routes->post("audit/view", "Audit::view");

    $routes->get("customers", "Customer_accounts::index");
    $routes->get("customers/view/(:num)", "Customer_accounts::view/$1");
    $routes->post("customers/list_data", "Customer_accounts::list_data");
    $routes->post("customers/modal_form", "Customer_accounts::modal_form");
    $routes->post("customers/save", "Customer_accounts::save");
    $routes->post("customers/delete", "Customer_accounts::delete");
    $routes->post("customers/duplicates", "Customer_accounts::duplicates");

    $routes->get("people", "People::index");
    $routes->get("people/view/(:num)", "People::view/$1");
    $routes->post("people/list_data", "People::list_data");
    $routes->post("people/modal_form", "People::modal_form");
    $routes->post("people/save", "People::save");
    $routes->post("people/delete", "People::delete");
    $routes->post("people/duplicates", "People::duplicates");

    $routes->post("account-people/list_data", "Account_people::list_data");
    $routes->post("account-people/modal_form", "Account_people::modal_form");
    $routes->post("account-people/save", "Account_people::save");
    $routes->post("account-people/delete", "Account_people::delete");
    $routes->post("contacts/list_data", "Contact_methods::list_data");
    $routes->post("contacts/modal_form", "Contact_methods::modal_form");
    $routes->post("contacts/save", "Contact_methods::save");
    $routes->post("contacts/delete", "Contact_methods::delete");
    $routes->post("addresses/list_data", "Addresses::list_data");
    $routes->post("addresses/modal_form", "Addresses::modal_form");
    $routes->post("addresses/save", "Addresses::save");
    $routes->post("addresses/delete", "Addresses::delete");

    /* ---- Fase 2B: catálogo ---- */

    // Categorias do catálogo
    $routes->get("catalog/categories", "Product_categories::index");
    $routes->post("catalog/categories/list_data", "Product_categories::list_data");
    $routes->post("catalog/categories/modal_form", "Product_categories::modal_form");
    $routes->post("catalog/categories/save", "Product_categories::save");
    $routes->post("catalog/categories/delete", "Product_categories::delete");

    // Produtos e serviços
    $routes->get("catalog/products", "Products::index");
    $routes->get("catalog/products/view/(:num)", "Products::view/$1");
    $routes->post("catalog/products/list_data", "Products::list_data");
    $routes->post("catalog/products/modal_form", "Products::modal_form");
    $routes->post("catalog/products/save", "Products::save");
    $routes->post("catalog/products/delete", "Products::delete");

    // Variações (product_id no POST)
    $routes->post("catalog/variants/list_data", "Product_variants::list_data");
    $routes->post("catalog/variants/modal_form", "Product_variants::modal_form");
    $routes->post("catalog/variants/save", "Product_variants::save");
    $routes->post("catalog/variants/delete", "Product_variants::delete");

    // Recursos físicos
    $routes->get("resources", "Resources::index");
    $routes->get("resources/view/(:num)", "Resources::view/$1");
    $routes->post("resources/list_data", "Resources::list_data");
    $routes->post("resources/modal_form", "Resources::modal_form");
    $routes->post("resources/save", "Resources::save");
    $routes->post("resources/delete", "Resources::delete");

    /* ---- Fase 3A: disponibilidade e calendário-base ---- */
    $routes->get("calendar", "Calendar::index");
    $routes->get("calendar/events", "Calendar::events");
    $routes->get("bookings", "Bookings::index");
    $routes->post("bookings/list-data", "Bookings::list_data");
    $routes->get("bookings/modal", "Bookings::modal");
    $routes->post("bookings/modal", "Bookings::modal");
    $routes->post("bookings/save", "Bookings::save");
    $routes->get("bookings/view/(:num)", "Bookings::view/$1");
    $routes->post("bookings/delete", "Bookings::delete");
    $routes->post("bookings/check-availability", "Bookings::check_availability");
    $routes->post("bookings/customer-options", "Bookings::customer_options");
    $routes->post("bookings/contact-options", "Bookings::contact_options");
    $routes->post("bookings/(:num)/confirm", "Booking_lifecycle::confirm/$1");
    $routes->post("bookings/(:num)/start", "Booking_lifecycle::start/$1");
    $routes->post("bookings/(:num)/complete", "Booking_lifecycle::complete/$1");
    $routes->post("bookings/(:num)/cancel", "Booking_lifecycle::cancel/$1");
    $routes->post("bookings/(:num)/no-show", "Booking_lifecycle::no_show/$1");
    $routes->get("booking-series", "Booking_series::index");
    $routes->post("booking-series/list-data", "Booking_series::list_data");
    $routes->get("booking-series/view/(:num)", "Booking_series::view/$1");
    $routes->get("booking-series/modal", "Booking_series::modal");
    $routes->post("booking-series/modal", "Booking_series::modal");
    $routes->post("booking-series/occurrence-modal", "Booking_series::occurrence_modal");
    $routes->post("booking-series/customer-options", "Booking_series::customer_options");
    $routes->post("booking-series/contact-options", "Booking_series::contact_options");
    $routes->post("booking-series/check-availability", "Booking_series::check_availability");
    $routes->post("booking-series/preview", "Booking_series::preview");
    $routes->post("booking-series/save", "Booking_series::save");
    $routes->post("booking-series/(:num)/generate", "Booking_series::generate/$1");
    $routes->post("booking-series/(:num)/pause", "Booking_series::pause/$1");
    $routes->post("booking-series/(:num)/resume", "Booking_series::resume/$1");
    $routes->post("booking-series/(:num)/complete", "Booking_series::complete/$1");
    $routes->post("booking-series/(:num)/cancel", "Booking_series::cancel/$1");
    $routes->post("booking-series/update-occurrence", "Booking_series::update_occurrence");
    $routes->post("booking-series/update-this-and-future", "Booking_series::update_this_and_future");
    $routes->post("booking-series/update-entire", "Booking_series::update_entire");
    $routes->post("booking-series/cancel-occurrence", "Booking_series::cancel_occurrence");
    $routes->post("booking-series/cancel-this-and-future", "Booking_series::cancel_this_and_future");

    /* ---- Fase 3C: operação comercial de locação de quadras ---- */
    $routes->get("court-rentals", "Court_rentals::index");
    $routes->get("court-rentals/monthly", "Court_rentals::monthly");
    $routes->post("court-rentals/list-data", "Court_rentals::list_data");
    $routes->post("court-rentals/monthly-data", "Court_rentals::monthly_data");
    $routes->get("court-rentals/view/(:num)", "Court_rentals::view/$1");
    $routes->get("court-rentals/single-modal", "Court_rentals::single_modal");
    $routes->post("court-rentals/single-modal", "Court_rentals::single_modal");
    $routes->get("court-rentals/monthly-modal", "Court_rentals::monthly_modal");
    $routes->post("court-rentals/monthly-modal", "Court_rentals::monthly_modal");
    $routes->post("court-rentals/link-modal", "Court_rentals::link_modal");
    $routes->post("court-rentals/customer-options", "Court_rentals::customer_options");
    $routes->post("court-rentals/contact-options", "Court_rentals::contact_options");
    $routes->post("court-rentals/product-options", "Court_rentals::product_options");
    $routes->post("court-rentals/price-list-options", "Court_rentals::price_list_options");
    $routes->post("court-rentals/check-availability", "Court_rentals::check_availability");
    $routes->post("court-rentals/preview", "Court_rentals::preview");
    $routes->post("court-rentals/resolve-price", "Court_rentals::resolve_price");
    $routes->post("court-rentals/save-draft", "Court_rentals::save_draft");
    $routes->post("court-rentals/save-rental", "Court_rentals::save_rental");
    $routes->post("court-rentals/save-single", "Court_rentals::save_single");
    $routes->post("court-rentals/save-monthly", "Court_rentals::save_monthly");
    $routes->post("court-rentals/link-existing", "Court_rentals::link_existing");
    $routes->post("court-rentals/reprice", "Court_rentals::reprice");
    $routes->post("court-rentals/(:num)/activate", "Court_rentals::activate/$1");
    $routes->post("court-rentals/(:num)/suspend", "Court_rentals::suspend/$1");
    $routes->post("court-rentals/(:num)/resume", "Court_rentals::resume/$1");
    $routes->post("court-rentals/(:num)/cancel", "Court_rentals::cancel/$1");
    $routes->post("court-rentals/(:num)/complete", "Court_rentals::complete/$1");

    /* ---- Fase 4: escola e personal ---- */
    $routes->get("school/students", "School_students::index");
    $routes->post("school/students/list-data", "School_students::list_data");
    $routes->get("school/students/view/(:num)", "School_students::view/$1");
    $routes->match(["get", "post"], "school/students/modal", "School_students::modal");
    $routes->post("school/students/save", "School_students::save");
    $routes->get("school/classes", "School_classes::index");
    $routes->post("school/classes/list-data", "School_classes::list_data");
    $routes->get("school/classes/view/(:num)", "School_classes::view/$1");
    $routes->match(["get", "post"], "school/classes/modal", "School_classes::modal");
    $routes->post("school/classes/save", "School_classes::save");
    $routes->post("school/classes/enrollment-modal", "School_classes::enrollment_modal");
    $routes->post("school/classes/enroll", "School_classes::enroll");
    $routes->get("school/attendance", "School_attendance::index");
    $routes->get("school/attendance/roster", "School_attendance::roster");
    $routes->post("school/attendance/save", "School_attendance::save");

    /* ---- Fase 5: financeiro básico ---- */
    $routes->get("finance", "Finance::index");
    $routes->post("finance/account-modal", "Finance::account_modal");
    $routes->post("finance/accounts/save", "Finance::save_account");
    $routes->get("finance/receivables", "Finance::receivables");
    $routes->post("finance/receivables/data", "Finance::receivables_data");
    $routes->post("finance/receivable-modal", "Finance::receivable_modal");
    $routes->post("finance/receivables/save", "Finance::save_receivable");
    $routes->get("finance/receivables/view/(:num)", "Finance::view_receivable/$1");
    $routes->post("finance/receivables/cancel", "Finance::cancel_receivable");
    $routes->get("finance/generate", "Finance::generate");
    $routes->post("finance/generate/preview", "Finance::generation_preview");
    $routes->post("finance/generate/confirm", "Finance::generation_confirm");
    $routes->post("finance/generate-rental", "Finance::generate_rental");
    $routes->get("finance/payments", "Finance::payments");
    $routes->post("finance/payments/data", "Finance::payments_data");
    $routes->post("finance/payment-modal", "Finance::payment_modal");
    $routes->post("finance/payments/save", "Finance::save_payment");
    $routes->post("finance/payments/reverse", "Finance::reverse_payment");
    $routes->get("finance/payments/receipt/(:num)", "Finance::receipt/$1");
    $routes->get("finance/expenses", "Finance::expenses");
    $routes->post("finance/expenses/data", "Finance::expenses_data");
    $routes->post("finance/expense-modal", "Finance::expense_modal");
    $routes->post("finance/expenses/save", "Finance::save_expense");
    $routes->get("finance/cash", "Finance::cash");
    $routes->post("finance/cash/data", "Finance::cash_data");
    $routes->get("resources/availability/(:num)", "Resource_availability::index/$1");
    $routes->post("resources/availability/list_data", "Resource_availability::list_data");
    $routes->post("resources/availability/modal_form", "Resource_availability::modal_form");
    $routes->post("resources/availability/save", "Resource_availability::save");
    $routes->post("resources/availability/delete", "Resource_availability::delete");
    $routes->get("resources/exceptions/(:num)", "Resource_exceptions::index/$1");
    $routes->post("resources/exceptions/list_data", "Resource_exceptions::list_data");
    $routes->post("resources/exceptions/modal_form", "Resource_exceptions::modal_form");
    $routes->post("resources/exceptions/save", "Resource_exceptions::save");
    $routes->post("resources/exceptions/delete", "Resource_exceptions::delete");
    $routes->get("resources/blocks/(:num)", "Resource_blocks::index/$1");
    $routes->post("resources/blocks/list_data", "Resource_blocks::list_data");
    $routes->post("resources/blocks/modal_form", "Resource_blocks::modal_form");
    $routes->post("resources/blocks/save", "Resource_blocks::save");
    $routes->post("resources/blocks/delete", "Resource_blocks::delete");

    // Tabelas de preço
    $routes->get("pricing/lists", "Price_lists::index");
    $routes->get("pricing/lists/view/(:num)", "Price_lists::view/$1");
    $routes->post("pricing/lists/list_data", "Price_lists::list_data");
    $routes->post("pricing/lists/modal_form", "Price_lists::modal_form");
    $routes->post("pricing/lists/save", "Price_lists::save");
    $routes->post("pricing/lists/delete", "Price_lists::delete");

    // Preços (price_list_id no POST) + resolução
    $routes->get("pricing/resolver", "Prices::resolver");
    $routes->post("pricing/prices/list_data", "Prices::list_data");
    $routes->post("pricing/prices/modal_form", "Prices::modal_form");
    $routes->post("pricing/prices/save", "Prices::save");
    $routes->post("pricing/prices/delete", "Prices::delete");
    $routes->post("pricing/prices/variants", "Prices::variants");
    $routes->post("pricing/resolve", "Prices::resolve");

    /* ---- Fase 6: importação assistida ---- */
    $routes->get("imports", "Imports::index");
    $routes->post("imports/list-data", "Imports::list_data");
    $routes->get("imports/new", "Imports::new_batch");
    $routes->post("imports/upload", "Imports::upload");
    $routes->post("imports/preview", "Imports::preview");
    $routes->post("imports/mapping", "Imports::mapping");
    $routes->post("imports/validate", "Imports::validate");
    $routes->get("imports/view/(:num)", "Imports::view/$1");
    $routes->post("imports/issues", "Imports::issues");
    $routes->post("imports/confirm", "Imports::confirm");
    $routes->post("imports/reprocess", "Imports::reprocess");
});
