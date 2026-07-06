<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Config;

/**
 * Constantes da fundação do plugin Grupo Donato.
 *
 * Não há valores de cliente hard-coded aqui (decisão da Fase 0). Apenas
 * identificadores técnicos estáveis usados por toda a base do plugin.
 */
final class Constants
{
    /** Nome da pasta do plugin = namespace PSR-4 registrado pelo Rise (Autoload). */
    public const PLUGIN_FOLDER = "grupo_donato_gestao";

    /** Prefixo de URI das rotas (não confundir com o namespace). */
    public const ROUTE_PREFIX = "grupo_donato";

    /** Versão do plugin (deve bater com o cabeçalho de metadados do index.php). */
    public const PLUGIN_VERSION = "0.9.6";

    /**
     * Versão-alvo do schema. O SchemaRunner aplica até esta versão.
     * Corresponde ao maior arquivo em Database/Schema/Versions.
     */
    public const SCHEMA_TARGET = "049";

    /** Prefixo lógico das tabelas (o Rise antepõe o DBPrefix 'rise_'). */
    public const TABLE_PREFIX = "gd_";

    /** Chave de configuração que guarda a versão de schema aplicada. */
    public const SETTING_SCHEMA_VERSION = "schema_version";

    /** Marcador em disco para evitar consulta ao banco a cada request. */
    public const SCHEMA_MARKER_FILE = "gd_schema_version.txt";

    /** Tipos de documento para o gerador de sequências (apenas técnicos nesta fase). */
    public const DOCUMENT_TYPES = [
        "audit_ref",
    ];

    /** Status genéricos de registros ativáveis. */
    public const STATUS_ACTIVE = "active";
    public const STATUS_INACTIVE = "inactive";

    public const ACTIVATABLE_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    /** Status de execução de uma versão de schema. */
    public const SCHEMA_STATUS_RUNNING = "running";
    public const SCHEMA_STATUS_COMPLETED = "completed";
    public const SCHEMA_STATUS_FAILED = "failed";

    /** Tipos de centro de resultado. */
    public const COST_CENTER_TYPES = ["revenue", "cost", "mixed"];

    public const ACCOUNT_TYPES = ["individual", "family", "company", "team", "group", "organization", "event_customer", "other"];
    public const ACCOUNT_STATUSES = ["active", "inactive", "blocked", "archived"];
    public const DOCUMENT_TYPES_CUSTOMER = ["cpf", "cnpj", "other", "none"];
    public const PERSON_STATUSES = ["active", "inactive", "deceased", "archived"];
    public const ACCOUNT_PERSON_ROLES = ["owner", "family_member", "father", "mother", "guardian", "financial_responsible", "participant", "primary_contact", "secondary_contact", "emergency_contact", "representative", "captain", "member", "organizer", "other"];
    public const RELATION_STATUSES = ["active", "inactive", "ended"];
    public const CONTACT_TYPES = ["phone", "whatsapp", "email", "other"];
    public const CONTACT_STATUSES = ["active", "inactive"];
    public const ADDRESS_TYPES = ["main", "billing", "residential", "commercial", "event", "other"];
    public const ADDRESS_STATUSES = ["active", "inactive"];

    /* ---- Fase 2B: catálogo, recursos e preços ---- */

    /** Moeda padrão (ISO 4217). */
    public const DEFAULT_CURRENCY = "BRL";

    /** Preços oficiais de locação de quadras do Grupo Donato. */
    public const COURT_RENTAL_PRICE_PRESETS = [
        "single" => [90 => "380.00", 120 => "460.00"],
        "recurring" => [90 => "900.00", 120 => "1050.00"],
    ];

    /** Status de categorias de catálogo. */
    public const PRODUCT_CATEGORY_STATUSES = ["active", "inactive", "archived"];

    /** Tipos de recurso físico (persistidos em VARCHAR, validados em PHP). */
    public const RESOURCE_TYPES = ["court", "event_space", "bar_area", "locker_room", "parking", "equipment", "room", "other"];

    /** Tipos de produto. credit/discount são reservados (não expostos na UI nesta fase). */
    public const PRODUCT_TYPES = ["service", "physical", "rental", "fee", "credit", "discount", "other"];
    public const PRODUCT_TYPES_SELECTABLE = ["service", "physical", "rental", "fee", "other"];

    /** Modos de cobrança (classificação; não gera cobrança nesta fase). */
    public const BILLING_MODES = ["one_time", "recurring", "per_use", "per_hour", "per_day", "per_person", "per_event"];

    /** Unidades de medida. */
    public const UNITS_OF_MEASURE = ["unit", "hour", "day", "month", "session", "person", "event", "package", "other"];

    public const PRODUCT_STATUSES = ["draft", "active", "inactive", "archived"];
    public const VARIANT_STATUSES = ["active", "inactive", "archived"];
    public const PRICE_LIST_STATUSES = ["draft", "active", "inactive", "archived"];
    public const PRICE_STATUSES = ["active", "inactive", "archived"];

    /* ---- Fase 3A: disponibilidade e calendário-base ---- */

    public const AVAILABILITY_RULE_STATUSES = ["active", "inactive", "archived"];
    public const AVAILABILITY_EXCEPTION_TYPES = ["open", "closed"];
    public const AVAILABILITY_EXCEPTION_STATUSES = ["active", "inactive", "cancelled", "archived"];
    public const RESOURCE_BLOCK_TYPES = ["maintenance", "internal_use", "administrative", "closure", "cleaning", "inspection", "other"];
    public const RESOURCE_BLOCK_STATUSES = ["active", "completed", "cancelled", "archived"];
    public const RESOURCE_BLOCK_REASON_REQUIRED = ["maintenance", "closure", "administrative"];
    public const CALENDAR_MAX_DAYS = 93;
    public const TEMPORAL_ADMIN_MAX_DAYS = 366;

    /* ---- Fase 3B1: reservas únicas ---- */

    public const BOOKING_TYPES = ["customer_rental", "school", "personal", "event", "internal", "other"];
    public const BOOKING_COMMERCIAL_TYPES = ["customer_rental", "personal", "event"];
    public const BOOKING_STATUSES = ["hold", "pending_confirmation", "confirmed", "in_progress", "completed", "cancelled", "expired", "no_show"];
    public const BOOKING_INITIAL_STATUSES = ["hold", "pending_confirmation", "confirmed"];
    public const BOOKING_BLOCKING_STATUSES = ["hold", "pending_confirmation", "confirmed", "in_progress"];
    public const BOOKING_EDITABLE_STATUSES = ["hold", "pending_confirmation", "confirmed"];
    public const BOOKING_EVENT_TYPES = ["created", "updated", "hold_created", "confirmed", "started", "completed", "cancelled", "expired", "no_show", "resources_changed", "schedule_changed", "customer_changed", "contact_changed"];
    public const BOOKING_DEFAULT_HOLD_MINUTES = 30;
    public const BOOKING_MAX_HOLD_MINUTES = 10080;
    public const BOOKING_MAX_BUFFER_MINUTES = 1440;
    public const BOOKING_MAX_DURATION_MINUTES = 10080;

    /* ---- Fase 3B2: séries e recorrência ---- */

    public const BOOKING_SERIES_FREQUENCIES = ["daily", "weekly", "monthly"];
    public const BOOKING_SERIES_ENDS_MODES = ["until_date", "count", "open_ended"];
    public const BOOKING_SERIES_STATUSES = ["active", "paused", "completed", "cancelled", "archived"];
    public const BOOKING_SERIES_DEFAULT_STATUSES = ["pending_confirmation", "confirmed"];
    public const BOOKING_SERIES_CONFLICT_POLICIES = ["reject_series", "skip_conflicts"];
    public const BOOKING_SERIES_EXCEPTION_TYPES = ["skip", "cancel", "detach", "override", "split", "conflict_skipped"];
    public const BOOKING_SERIES_EVENT_TYPES = ["created", "updated", "generated", "paused", "resumed", "completed", "cancelled", "archived", "split", "occurrence_updated", "occurrence_cancelled", "conflict_skipped"];
    public const BOOKING_SERIES_MAX_OCCURRENCES_PER_OPERATION = 366;
    public const BOOKING_SERIES_DEFAULT_HORIZON_DAYS = 90;
    public const BOOKING_SERIES_MAX_HORIZON_DAYS = 730;

    /* ---- Fase 3C: operação comercial de locação de quadras ---- */

    /** Tipo da locação e ciclo comercial associado (não gera cobrança nesta fase). */
    public const COURT_RENTAL_TYPES = ["single", "recurring"];
    public const COURT_RENTAL_BILLING_CYCLES = ["one_time", "monthly"];

    /** Mapeamento canônico tipo -> ciclo. */
    public const COURT_RENTAL_TYPE_CYCLE = ["single" => "one_time", "recurring" => "monthly"];

    public const COURT_RENTAL_STATUSES = ["draft", "active", "suspended", "cancelled", "completed", "archived"];
    public const COURT_RENTAL_LINK_KINDS = ["primary", "replacement", "historical"];
    public const COURT_RENTAL_EVENT_TYPES = ["created", "updated", "activated", "suspended", "resumed", "cancelled", "completed", "schedule_linked", "schedule_replaced", "price_resolved", "price_overridden", "commercial_terms_changed"];

    /** Tratamento das ocorrências futuras ao suspender/cancelar (decisão explícita). */
    public const COURT_RENTAL_FUTURE_POLICIES = ["keep", "cancel", "pause_series"];

    /** Tipos de produto comercialmente compatíveis com locação de quadra. */
    public const COURT_RENTAL_PRODUCT_TYPES = ["rental", "service", "fee"];

    /* ---- Fase 4: escola e personal ---- */

    public const SCHOOL_PROFILE_STATUSES = ["active", "inactive", "ended"];
    public const SCHOOL_CLASS_TYPES = ["group", "personal"];
    public const SCHOOL_CLASS_STATUSES = ["active", "inactive", "ended"];
    public const SCHOOL_ENROLLMENT_STATUSES = ["active", "paused", "ended", "cancelled"];
    public const SCHOOL_ENROLLMENT_OPEN_STATUSES = ["active", "paused"];
    public const SCHOOL_ATTENDANCE_STATUSES = ["present", "absent", "justified", "unmarked"];
    public const SCHOOL_ATTENDANCE_SESSION_STATUSES = ["open", "completed"];
    public const FINANCIAL_ACCOUNT_TYPES = ["cash", "bank", "digital_wallet", "other"];
    public const FINANCIAL_ACCOUNT_STATUSES = ["active", "inactive"];
    public const RECEIVABLE_SOURCE_TYPES = ["enrollment", "court_rental", "manual", "other"];
    public const RECEIVABLE_STATUSES = ["open", "partial", "paid", "overdue", "cancelled"];
    public const PAYMENT_METHODS = ["cash", "pix", "debit_card", "credit_card", "bank_transfer", "other"];
    public const PAYMENT_STATUSES = ["confirmed", "reversed"];
    public const EXPENSE_STATUSES = ["pending", "paid", "cancelled"];

    /* ---- Fase 6: importação assistida ---- */

    public const IMPORT_TYPES = ["school_payments", "cash", "court_renters"];
    public const IMPORT_BATCH_STATUSES = ["draft", "previewed", "validated", "imported", "partially_imported", "failed", "archived"];
    public const IMPORT_ROW_STATUSES = ["pending", "valid", "invalid", "duplicate", "needs_review", "imported", "skipped"];
    public const IMPORT_ISSUE_SEVERITIES = ["error", "warning", "review"];
    public const IMPORT_ISSUE_TYPES = [
        "invalid_date", "invalid_amount", "inconsistent_month", "unknown_payment_method",
        "probable_duplicate", "payment_without_receivable", "missing_resource", "schedule_conflict",
        "unidentified_customer", "ambiguous_category", "multiple_people_in_cell", "missing_required",
        "missing_class", "incomplete", "import_error",
    ];
    public const IMPORT_TARGET_TYPES = [
        "customer_account", "person", "school_profile", "enrollment", "receivable",
        "payment", "expense", "cash_movement", "court_rental", "booking_series",
    ];

    /** Normalização assistida de método de pagamento (texto livre → chave canônica). */
    public const PAYMENT_METHOD_ALIASES = [
        "dinheiro" => "cash", "especie" => "cash", "espécie" => "cash", "cash" => "cash",
        "pix" => "pix",
        "debito" => "debit_card", "débito" => "debit_card", "cartao debito" => "debit_card", "cartão débito" => "debit_card", "debit" => "debit_card",
        "credito" => "credit_card", "crédito" => "credit_card", "cartao credito" => "credit_card", "cartão crédito" => "credit_card", "cartao" => "credit_card", "cartão" => "credit_card", "credit" => "credit_card",
        "transferencia" => "bank_transfer", "transferência" => "bank_transfer", "ted" => "bank_transfer", "doc" => "bank_transfer", "deposito" => "bank_transfer", "depósito" => "bank_transfer", "transfer" => "bank_transfer",
    ];

    /** Chaves sensíveis mascaradas pela auditoria (nunca persistidas em claro). */
    public const SENSITIVE_KEYS = [
        "password",
        "pass",
        "token",
        "secret",
        "authorization",
        "cookie",
        "api_key",
        "apikey",
        "access_token",
        "refresh_token",
        "card",
        "cvv",
    ];

    private function __construct()
    {
        // classe estática
    }

    public static function isActivatableStatus(string $status): bool
    {
        return in_array($status, self::ACTIVATABLE_STATUSES, true);
    }

    public static function isCostCenterType(string $type): bool
    {
        return in_array($type, self::COST_CENTER_TYPES, true);
    }

    public static function isResourceType(string $type): bool
    {
        return in_array($type, self::RESOURCE_TYPES, true);
    }

    public static function isProductType(string $type): bool
    {
        return in_array($type, self::PRODUCT_TYPES, true);
    }

    public static function isBillingMode(string $mode): bool
    {
        return in_array($mode, self::BILLING_MODES, true);
    }

    public static function isUnitOfMeasure(string $uom): bool
    {
        return in_array($uom, self::UNITS_OF_MEASURE, true);
    }

    public static function isProductStatus(string $status): bool
    {
        return in_array($status, self::PRODUCT_STATUSES, true);
    }

    public static function isPriceListStatus(string $status): bool
    {
        return in_array($status, self::PRICE_LIST_STATUSES, true);
    }

    public static function isPriceStatus(string $status): bool
    {
        return in_array($status, self::PRICE_STATUSES, true);
    }

    public static function isVariantStatus(string $status): bool
    {
        return in_array($status, self::VARIANT_STATUSES, true);
    }

    public static function isCategoryStatus(string $status): bool
    {
        return in_array($status, self::PRODUCT_CATEGORY_STATUSES, true);
    }

    /** Moeda ISO 4217 (3 letras maiúsculas). */
    public static function isCurrency(string $currency): bool
    {
        return (bool) preg_match('/^[A-Z]{3}$/', $currency);
    }

    public static function isCourtRentalType(string $type): bool
    {
        return in_array($type, self::COURT_RENTAL_TYPES, true);
    }

    public static function isCourtRentalStatus(string $status): bool
    {
        return in_array($status, self::COURT_RENTAL_STATUSES, true);
    }

    public static function isCourtRentalBillingCycle(string $cycle): bool
    {
        return in_array($cycle, self::COURT_RENTAL_BILLING_CYCLES, true);
    }

    public static function isCourtRentalLinkKind(string $kind): bool
    {
        return in_array($kind, self::COURT_RENTAL_LINK_KINDS, true);
    }

    public static function isCourtRentalFuturePolicy(string $policy): bool
    {
        return in_array($policy, self::COURT_RENTAL_FUTURE_POLICIES, true);
    }

    /** Ciclo de cobrança canônico para um tipo de locação. */
    public static function courtRentalCycleForType(string $type): ?string
    {
        return self::COURT_RENTAL_TYPE_CYCLE[$type] ?? null;
    }

    public static function isImportType(string $type): bool
    {
        return in_array($type, self::IMPORT_TYPES, true);
    }

    public static function isImportBatchStatus(string $status): bool
    {
        return in_array($status, self::IMPORT_BATCH_STATUSES, true);
    }

    public static function isImportRowStatus(string $status): bool
    {
        return in_array($status, self::IMPORT_ROW_STATUSES, true);
    }

    public static function isImportIssueType(string $type): bool
    {
        return in_array($type, self::IMPORT_ISSUE_TYPES, true);
    }

    public static function isImportTargetType(string $type): bool
    {
        return in_array($type, self::IMPORT_TARGET_TYPES, true);
    }

    /** Normaliza um método de pagamento em texto livre para a chave canônica, ou null. */
    public static function normalizePaymentMethod(string $value): ?string
    {
        $key = trim(mb_strtolower($value));
        if ($key === "") { return null; }
        if (in_array($key, self::PAYMENT_METHODS, true)) { return $key; }
        return self::PAYMENT_METHOD_ALIASES[$key] ?? null;
    }
}
