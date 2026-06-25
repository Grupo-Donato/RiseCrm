<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Config;

/**
 * Catálogo canônico das permissões da FUNDAÇÃO.
 *
 * As permissões do plugin são persistidas no mecanismo nativo do Rise
 * (array serializado em `roles.permissions`), injetadas na tela de papéis via
 * o hook `app_hook_role_permissions_extension` e salvas via o filtro
 * `app_filter_role_permissions_save_data`. A checagem é feita pelo AccessService
 * lendo `login_user->permissions` (admin tem acesso total).
 *
 * Apenas permissões de módulos JÁ implementados nesta fase são registradas.
 */
final class Permissions
{
    /** Management permissions imply the corresponding read permission. */
    public const MANAGE_IMPLIES_VIEW = [
        "gd_settings_view" => "gd_settings_manage",
        "gd_units_view" => "gd_units_manage",
        "gd_business_areas_view" => "gd_business_areas_manage",
        "gd_cost_centers_view" => "gd_cost_centers_manage",
        "gd_customer_accounts_view" => "gd_customer_accounts_manage",
        "gd_people_view" => "gd_people_manage",
        "gd_resources_view" => "gd_resources_manage",
        "gd_price_lists_view" => "gd_price_lists_manage",
        "gd_bookings_view" => "gd_bookings_manage",
        "gd_booking_series_view" => "gd_booking_series_manage",
        "gd_court_rentals_view" => "gd_court_rentals_manage",
        "gd_school_view" => "gd_students_manage",
        "gd_finance_view" => "gd_receivables_manage",
        "gd_imports_view" => "gd_imports_manage",
    ];

    /** Permissões filhas sem chave view própria liberam a leitura do cadastro pai. */
    /**
     * Operar locações comerciais (Fase 3C) implica LER reservas, séries, calendário,
     * recursos, contas, pessoas, catálogo e preços — sem conceder a gestão desses
     * cadastros. As 4 chaves de court_rentals são listadas em cada leitura porque a
     * checagem em AccessService::can NÃO é transitiva.
     */
    private const COURT_RENTAL_READERS = ["gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"];

    public const ADDITIONAL_VIEW_IMPLICATIONS = [
        "gd_customer_accounts_view" => ["gd_customer_relations_manage", "gd_addresses_manage", "gd_bookings_manage", "gd_booking_series_view", "gd_booking_series_manage", "gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_people_view" => ["gd_customer_relations_manage", "gd_contacts_manage", "gd_bookings_manage", "gd_booking_series_view", "gd_booking_series_manage", "gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        // Catálogo: produtos/categorias e (preços) implicam a leitura do catálogo.
        "gd_catalog_view" => ["gd_products_manage", "gd_product_categories_manage", "gd_prices_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        // Para resolver/gerir preços é preciso ler recursos e tabelas de preço.
        "gd_resources_view" => ["gd_prices_manage", "gd_resource_availability_manage", "gd_resource_blocks_manage", "gd_bookings_manage", "gd_booking_series_view", "gd_booking_series_manage", "gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_price_lists_view" => ["gd_prices_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_calendar_view" => ["gd_resource_availability_manage", "gd_resource_blocks_manage", "gd_bookings_manage", "gd_booking_status_manage", "gd_booking_series_view", "gd_booking_series_manage", "gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_bookings_view" => ["gd_booking_status_manage", "gd_booking_series_view", "gd_booking_series_manage", "gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_booking_series_view" => ["gd_booking_series_status_manage", "gd_court_rentals_view", "gd_court_rentals_manage", "gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        // Override de preço e gestão de status implicam ver a própria locação.
        "gd_court_rentals_view" => ["gd_court_rentals_status_manage", "gd_court_rentals_price_override"],
        "gd_school_view" => ["gd_classes_manage", "gd_enrollments_manage", "gd_attendance_manage"],
        "gd_finance_view" => ["gd_payments_manage", "gd_expenses_manage", "gd_cash_view"],
    ];

    /** Lista plana de todas as chaves de permissão da fundação. */
    public const KEYS = [
        "gd_dashboard_view",
        "gd_settings_view",
        "gd_settings_manage",
        "gd_units_view",
        "gd_units_manage",
        "gd_business_areas_view",
        "gd_business_areas_manage",
        "gd_cost_centers_view",
        "gd_cost_centers_manage",
        "gd_audit_view",
        "gd_customer_accounts_view",
        "gd_customer_accounts_manage",
        "gd_people_view",
        "gd_people_manage",
        "gd_customer_relations_manage",
        "gd_contacts_manage",
        "gd_addresses_manage",
        "gd_catalog_view",
        "gd_products_manage",
        "gd_product_categories_manage",
        "gd_resources_view",
        "gd_resources_manage",
        "gd_price_lists_view",
        "gd_price_lists_manage",
        "gd_prices_manage",
        "gd_calendar_view",
        "gd_resource_availability_manage",
        "gd_resource_blocks_manage",
        "gd_bookings_view",
        "gd_bookings_manage",
        "gd_booking_status_manage",
        "gd_booking_series_view",
        "gd_booking_series_manage",
        "gd_booking_series_status_manage",
        "gd_court_rentals_view",
        "gd_court_rentals_manage",
        "gd_court_rentals_status_manage",
        "gd_court_rentals_price_override",
        "gd_school_view",
        "gd_students_manage",
        "gd_classes_manage",
        "gd_enrollments_manage",
        "gd_attendance_manage",
        "gd_finance_view",
        "gd_receivables_manage",
        "gd_payments_manage",
        "gd_expenses_manage",
        "gd_cash_view",
        "gd_imports_view",
        "gd_imports_manage",
    ];

    /**
     * Agrupamento para renderização na tela de papéis.
     * Cada item: [key => chave, label_key => chave de tradução].
     */
    public static function groups(): array
    {
        return [
            "gd_permissions_general" => [
                ["key" => "gd_dashboard_view", "label_key" => "gd_perm_dashboard_view"],
                ["key" => "gd_audit_view", "label_key" => "gd_perm_audit_view"],
            ],
            "gd_permissions_settings" => [
                ["key" => "gd_settings_view", "label_key" => "gd_perm_settings_view"],
                ["key" => "gd_settings_manage", "label_key" => "gd_perm_settings_manage"],
                ["key" => "gd_units_view", "label_key" => "gd_perm_units_view"],
                ["key" => "gd_units_manage", "label_key" => "gd_perm_units_manage"],
                ["key" => "gd_business_areas_view", "label_key" => "gd_perm_business_areas_view"],
                ["key" => "gd_business_areas_manage", "label_key" => "gd_perm_business_areas_manage"],
                ["key" => "gd_cost_centers_view", "label_key" => "gd_perm_cost_centers_view"],
                ["key" => "gd_cost_centers_manage", "label_key" => "gd_perm_cost_centers_manage"],
            ],
            "gd_permissions_customers" => [
                ["key" => "gd_customer_accounts_view", "label_key" => "gd_perm_customer_accounts_view"],
                ["key" => "gd_customer_accounts_manage", "label_key" => "gd_perm_customer_accounts_manage"],
                ["key" => "gd_people_view", "label_key" => "gd_perm_people_view"],
                ["key" => "gd_people_manage", "label_key" => "gd_perm_people_manage"],
                ["key" => "gd_customer_relations_manage", "label_key" => "gd_perm_customer_relations_manage"],
                ["key" => "gd_contacts_manage", "label_key" => "gd_perm_contacts_manage"],
                ["key" => "gd_addresses_manage", "label_key" => "gd_perm_addresses_manage"],
            ],
            "gd_permissions_catalog" => [
                ["key" => "gd_catalog_view", "label_key" => "gd_perm_catalog_view"],
                ["key" => "gd_products_manage", "label_key" => "gd_perm_products_manage"],
                ["key" => "gd_product_categories_manage", "label_key" => "gd_perm_product_categories_manage"],
                ["key" => "gd_resources_view", "label_key" => "gd_perm_resources_view"],
                ["key" => "gd_resources_manage", "label_key" => "gd_perm_resources_manage"],
            ],
            "gd_permissions_pricing" => [
                ["key" => "gd_price_lists_view", "label_key" => "gd_perm_price_lists_view"],
                ["key" => "gd_price_lists_manage", "label_key" => "gd_perm_price_lists_manage"],
                ["key" => "gd_prices_manage", "label_key" => "gd_perm_prices_manage"],
            ],
            "gd_permissions_calendar" => [
                ["key" => "gd_calendar_view", "label_key" => "gd_perm_calendar_view"],
                ["key" => "gd_resource_availability_manage", "label_key" => "gd_perm_resource_availability_manage"],
                ["key" => "gd_resource_blocks_manage", "label_key" => "gd_perm_resource_blocks_manage"],
            ],
            "gd_permissions_bookings" => [
                ["key" => "gd_bookings_view", "label_key" => "gd_perm_bookings_view"],
                ["key" => "gd_bookings_manage", "label_key" => "gd_perm_bookings_manage"],
                ["key" => "gd_booking_status_manage", "label_key" => "gd_perm_booking_status_manage"],
                ["key" => "gd_booking_series_view", "label_key" => "gd_perm_booking_series_view"],
                ["key" => "gd_booking_series_manage", "label_key" => "gd_perm_booking_series_manage"],
                ["key" => "gd_booking_series_status_manage", "label_key" => "gd_perm_booking_series_status_manage"],
            ],
            "gd_permissions_court_rentals" => [
                ["key" => "gd_court_rentals_view", "label_key" => "gd_perm_court_rentals_view"],
                ["key" => "gd_court_rentals_manage", "label_key" => "gd_perm_court_rentals_manage"],
                ["key" => "gd_court_rentals_status_manage", "label_key" => "gd_perm_court_rentals_status_manage"],
                ["key" => "gd_court_rentals_price_override", "label_key" => "gd_perm_court_rentals_price_override"],
            ],
            "gd_permissions_school" => [
                ["key" => "gd_school_view", "label_key" => "gd_perm_school_view"],
                ["key" => "gd_students_manage", "label_key" => "gd_perm_students_manage"],
                ["key" => "gd_classes_manage", "label_key" => "gd_perm_classes_manage"],
                ["key" => "gd_enrollments_manage", "label_key" => "gd_perm_enrollments_manage"],
                ["key" => "gd_attendance_manage", "label_key" => "gd_perm_attendance_manage"],
            ],
            "gd_permissions_finance" => [
                ["key" => "gd_finance_view", "label_key" => "gd_perm_finance_view"],
                ["key" => "gd_receivables_manage", "label_key" => "gd_perm_receivables_manage"],
                ["key" => "gd_payments_manage", "label_key" => "gd_perm_payments_manage"],
                ["key" => "gd_expenses_manage", "label_key" => "gd_perm_expenses_manage"],
                ["key" => "gd_cash_view", "label_key" => "gd_perm_cash_view"],
            ],
            "gd_permissions_imports" => [
                ["key" => "gd_imports_view", "label_key" => "gd_perm_imports_view"],
                ["key" => "gd_imports_manage", "label_key" => "gd_perm_imports_manage"],
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function impliedBy(string $key): ?string
    {
        return self::MANAGE_IMPLIES_VIEW[$key] ?? null;
    }

    /** @return array<string> */
    public static function additionallyImpliedBy(string $key): array
    {
        return self::ADDITIONAL_VIEW_IMPLICATIONS[$key] ?? [];
    }

    private function __construct()
    {
        // classe estática
    }
}
