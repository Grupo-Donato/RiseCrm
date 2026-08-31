<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Seeds;

use grupo_donato_gestao\Services\FinanceService;
use grupo_donato_gestao\Services\PricingService;
use grupo_donato_gestao\Services\ProductCategoryService;
use grupo_donato_gestao\Services\ProductService;
use grupo_donato_gestao\Services\ProductVariantService;
use grupo_donato_gestao\Services\SchoolAttendanceService;
use grupo_donato_gestao\Services\SchoolClassService;
use grupo_donato_gestao\Services\SchoolEnrollmentService;
use grupo_donato_gestao\Services\SchoolStudentService;
use grupo_donato_gestao\Services\CourtRentalService;
use grupo_donato_gestao\Services\CourtRentalLifecycleService;
use grupo_donato_gestao\Services\ReceivableGenerationService;

/**
 * Dados comerciais de demonstração, sempre explícitos e idempotentes.
 *
 * A carga usa o mesmo caminho dos controllers: services, validações, auditoria
 * e relacionamentos da unidade ativa. Todos os códigos começam com DEMO para
 * que possam ser localizados e removidos manualmente depois da homologação.
 */
class DemoSeeder
{
    private $db;
    private int $actor_id;
    private int $unit_id;

    public function __construct(int $actor_id = 0, ?int $unit_id = null)
    {
        $this->db = db_connect();
        $this->actor_id = $actor_id;
        $unit = model("grupo_donato_gestao\\Models\\Gd_units_model")->get_default();
        $this->unit_id = $unit_id ?: (int) ($unit->id ?? 0);
        if ($this->unit_id <= 0) {
            throw new \RuntimeException("Nenhuma unidade ativa encontrada.");
        }
    }

    /** @return array<string,mixed> */
    public function run(): array
    {
        // O demo pode ser executado em uma instalação recém-criada.
        (new CatalogSeeder($this->actor_id))->run();
        (new FinanceSeeder($this->actor_id))->run();

        $today = new \DateTimeImmutable("now", new \DateTimeZone("America/Sao_Paulo"));
        $todayString = $today->format("Y-m-d");
        $categoryId = $this->ensureCategory();
        $products = $this->ensureProducts($categoryId);
        $variants = $this->ensureVariants((int) $products["uniform"]);
        $priceList = model("grupo_donato_gestao\\Models\\Gd_price_lists_model")->get_default($this->unit_id);
        if (!$priceList) {
            throw new \RuntimeException("Tabela de preço padrão não encontrada.");
        }

        $prices = 0;
        $prices += $this->ensurePrice((int) $priceList->id, (int) $products["academy"], "350.00");
        $prices += $this->ensurePrice((int) $priceList->id, (int) $products["court"], "120.00");
        $prices += $this->ensurePrice((int) $priceList->id, (int) $products["barbecue"], "160.00");
        $prices += $this->ensurePrice((int) $priceList->id, (int) $products["uniform"], "89.90", (int) $variants["P"]);
        $prices += $this->ensurePrice((int) $priceList->id, (int) $products["uniform"], "99.90", (int) $variants["M"]);

        $courtResource = $this->resourceId("Q2");
        $barbecueResource = $this->resourceId("CH1");
        if ($courtResource) {
            $prices += $this->ensurePrice((int) $priceList->id, (int) $products["court"], "140.00", 0, $courtResource);
        }
        if ($barbecueResource) {
            $prices += $this->ensurePrice((int) $priceList->id, (int) $products["barbecue"], "190.00", 0, $barbecueResource);
        }

        $studentId = $this->ensureStudent();
        $classId = $this->ensureClass($todayString);
        $enrollmentId = $this->ensureEnrollment($classId, $studentId, (int) $products["academy"], $todayString);
        (new SchoolAttendanceService($this->unit_id, $this->actor_id))->saveBatch($classId, $todayString, [$studentId => "present"]);

        $finance = $this->ensureFinance($enrollmentId, (int) $products["academy"], $todayString);
        $rentalFinance = $this->ensureRentalFinanceDemo($products, $priceList, $today);

        return [
            "unit_id" => $this->unit_id,
            "category_id" => $categoryId,
            "products" => $products,
            "variants" => $variants,
            "prices_created" => $prices,
            "student_id" => $studentId,
            "class_id" => $classId,
            "enrollment_id" => $enrollmentId,
            "finance" => $finance,
            "rental_finance" => $rentalFinance,
            "message" => "Dados DEMO criados ou reaproveitados com sucesso.",
        ];
    }

    /**
     * Fixtures financeiros de locacao, sempre identificados por [DEMO] e
     * reaproveitados por titulo para que o comando possa ser reenviado.
     * Nenhum registro fora desse conjunto e alterado.
     *
     * @param array<string,int> $products
     * @return array<string,mixed>
     */
    private function ensureRentalFinanceDemo(array $products, object $priceList, \DateTimeImmutable $today): array
    {
        $account = $this->db->table($this->db->prefixTable("gd_customer_accounts"))
            ->where("unit_id", $this->unit_id)->where("display_name", "[DEMO] Família Oliveira")
            ->where("deleted", 0)->get(1)->getRow();
        if (!$account) { throw new \RuntimeException("Conta DEMO da familia nao encontrada."); }

        $finance = new FinanceService($this->unit_id, $this->actor_id);
        $rentalService = new CourtRentalService($this->unit_id, $this->actor_id);
        $lifecycle = new CourtRentalLifecycleService($this->unit_id, $this->actor_id);
        $generator = new ReceivableGenerationService($this->unit_id, $this->actor_id);
        $accountId = (int) $account->id;
        $financialAccount = $finance->accounts()[0]["id"] ?? 0;
        $firstDate = $today->modify("+7 days");
        $secondDate = $today->modify("+8 days");
        $thirdDate = $today->modify("+9 days");

        $ensureRental = function (string $title, string $type, array $input) use ($rentalService, $lifecycle): object {
            $table = $this->db->prefixTable("gd_court_rentals");
            $row = $this->db->table($table)->where("unit_id", $this->unit_id)->where("title", $title)->where("deleted", 0)->get(1)->getRow();
            if (!$row) {
                $row = (object) ["id" => (int) (($type === "recurring" ? $rentalService->createWithSeries($input) : $rentalService->createWithBooking($input))["id"] ?? 0)];
            }
            $fresh = $rentalService->get((int) $row->id);
            if (!$fresh) { throw new \RuntimeException("Locacao DEMO nao encontrada: " . $title); }
            if ((string) $fresh->status === "draft") {
                $fresh = $lifecycle->activate((int) $fresh->id, (int) $fresh->lock_version);
            }
            return $fresh;
        };

        $monthly = $ensureRental("[DEMO] Mensalista quadra — parcial", "recurring", [
            "rental_type" => "recurring", "title" => "[DEMO] Mensalista quadra — parcial", "customer_account_id" => $accountId,
            "product_id" => (int) $products["court"], "price_list_id" => (int) $priceList->id,
            "list_amount" => "900.00", "negotiated_amount" => "900.00", "preferred_due_day" => 10,
            "effective_from" => $today->format("Y-m-d"), "starts_on" => $firstDate->format("Y-m-d"),
            "frequency" => "weekly", "interval_value" => 1, "weekdays" => [(int) $firstDate->format("N")],
            "local_start_time" => "18:00", "local_end_time" => "19:00", "ends_mode" => "count", "max_occurrences" => 8,
            "default_booking_status" => "pending_confirmation", "conflict_policy" => "reject_series",
            "generation_horizon_days" => 90, "resources" => [["resource_id" => $this->resourceId("Q4"), "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
        ]);
        $open = $ensureRental("[DEMO] Avulso quadra — em aberto", "single", [
            "rental_type" => "single", "title" => "[DEMO] Avulso quadra — em aberto", "customer_account_id" => $accountId,
            "product_id" => (int) $products["court"], "price_list_id" => (int) $priceList->id,
            "list_amount" => "1800.00", "negotiated_amount" => "1800.00", "effective_from" => $secondDate->format("Y-m-d"),
            "booking_status" => "pending_confirmation", "starts_at_local" => $secondDate->format("Y-m-d") . "T10:00",
            "ends_at_local" => $secondDate->format("Y-m-d") . "T11:00", "resources" => [["resource_id" => $this->resourceId("Q5"), "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
        ]);
        $paid = $ensureRental("[DEMO] Avulso quadra — pago", "single", [
            "rental_type" => "single", "title" => "[DEMO] Avulso quadra — pago", "customer_account_id" => $accountId,
            "product_id" => (int) $products["court"], "price_list_id" => (int) $priceList->id,
            "list_amount" => "650.00", "negotiated_amount" => "650.00", "effective_from" => $thirdDate->format("Y-m-d"),
            "booking_status" => "pending_confirmation", "starts_at_local" => $thirdDate->format("Y-m-d") . "T10:00",
            "ends_at_local" => $thirdDate->format("Y-m-d") . "T11:00", "resources" => [["resource_id" => $this->resourceId("Q6"), "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
        ]);

        $monthlyCharge = $this->demoRentalReceivable("court_rental", (int) $monthly->id, $today->format("Y-m"));
        if (!$monthlyCharge) {
            $generator->ensureMonth($today->format("Y-m"), "court_rental");
            $monthlyCharge = $this->demoRentalReceivable("court_rental", (int) $monthly->id, $today->format("Y-m"));
        }
        if ($monthlyCharge && (float) $monthlyCharge->paid_amount < 250.00 && (float) $monthlyCharge->balance_amount >= 250.00) {
            $finance->registerPayment([
                "allocations" => [(int) $monthlyCharge->id => "250.00"], "amount" => "250.00", "payment_date" => $today->format("Y-m-d"),
                "payment_method" => "pix", "financial_account_id" => (int) $financialAccount, "notes" => "[DEMO] Parcial de mensalista.",
            ]);
            $monthlyCharge = $finance->getReceivable((int) $monthlyCharge->id);
        }

        $openCharge = $this->demoRentalReceivable("court_rental", (int) $open->id, "");
        if (!$openCharge) {
            $generator->generateCourtRental((int) $open->id);
            $openCharge = $this->demoRentalReceivable("court_rental", (int) $open->id, "");
        }
        $paidCharge = $this->demoRentalReceivable("court_rental", (int) $paid->id, "");
        if (!$paidCharge) {
            $generator->generateCourtRental((int) $paid->id);
            $paidCharge = $this->demoRentalReceivable("court_rental", (int) $paid->id, "");
        }
        if ($paidCharge && (float) $paidCharge->balance_amount > 0.00) {
            $finance->registerPayment([
                "allocations" => [(int) $paidCharge->id => (string) $paidCharge->balance_amount], "amount" => (string) $paidCharge->balance_amount,
                "payment_date" => $today->format("Y-m-d"), "payment_method" => "pix", "financial_account_id" => (int) $financialAccount,
                "notes" => "[DEMO] Pagamento integral de avulso.",
            ]);
            $paidCharge = $finance->getReceivable((int) $paidCharge->id);
        }

        return [
            "monthly_partial" => ["rental_id" => (int) $monthly->id, "receivable_id" => (int) ($monthlyCharge->id ?? 0), "status" => (string) ($monthlyCharge->status ?? "") , "balance" => (string) ($monthlyCharge->balance_amount ?? "")],
            "single_open" => ["rental_id" => (int) $open->id, "receivable_id" => (int) ($openCharge->id ?? 0), "status" => (string) ($openCharge->status ?? ""), "balance" => (string) ($openCharge->balance_amount ?? "")],
            "single_paid" => ["rental_id" => (int) $paid->id, "receivable_id" => (int) ($paidCharge->id ?? 0), "status" => (string) ($paidCharge->status ?? ""), "balance" => (string) ($paidCharge->balance_amount ?? "")],
        ];
    }

    private function demoRentalReceivable(string $source, int $sourceId, string $reference): ?object
    {
        return $this->db->table($this->db->prefixTable("gd_receivables"))
            ->where("unit_id", $this->unit_id)->where("source_type", $source)->where("source_id", $sourceId)
            ->where("reference_month", $reference)->where("deleted", 0)->where("status <>", "cancelled")
            ->orderBy("id", "DESC")->get(1)->getRow();
    }

    private function ensureCategory(): int
    {
        $table = $this->db->prefixTable("gd_product_categories");
        $row = $this->db->table($table)->where("unit_id", $this->unit_id)->where("code", "DEMO-CATALOG")->where("deleted", 0)->get(1)->getRow();
        if ($row) {
            return (int) $row->id;
        }

        $result = (new ProductCategoryService($this->unit_id, $this->actor_id))->save([
            "code" => "DEMO-CATALOG",
            "name" => "Demonstração - catálogo",
            "description" => "Categoria criada para validar o catálogo integrado.",
            "status" => "active",
        ], 0, true);
        return (int) ($result["id"] ?? 0);
    }

    /** @return array<string,int> */
    private function ensureProducts(int $categoryId): array
    {
        $specs = [
            "academy" => ["code" => "DEMO-ACADEMY", "name" => "Mensalidade GD Academy", "product_type" => "service", "billing_mode" => "recurring", "unit_of_measure" => "unit", "description" => "Mensalidade de demonstração da academia."],
            "court" => ["code" => "DEMO-COURT", "name" => "Locação de quadra - hora", "product_type" => "rental", "billing_mode" => "per_hour", "unit_of_measure" => "hour", "requires_resource" => 1, "description" => "Produto de locação por hora para quadras."],
            "barbecue" => ["code" => "DEMO-BBQ", "name" => "Locação de churrasqueira - hora", "product_type" => "rental", "billing_mode" => "per_hour", "unit_of_measure" => "hour", "requires_resource" => 1, "description" => "Produto de locação por hora para churrasqueiras."],
            "uniform" => ["code" => "DEMO-UNIFORM", "name" => "Uniforme GD Academy", "product_type" => "physical", "billing_mode" => "one_time", "unit_of_measure" => "unit", "allows_variants" => 1, "track_stock" => 1, "description" => "Produto físico com variações de tamanho."],
        ];
        $service = new ProductService($this->unit_id, $this->actor_id);
        $ids = [];
        foreach ($specs as $key => $spec) {
            $row = $this->db->table($this->db->prefixTable("gd_products"))->where("unit_id", $this->unit_id)->where("code", $spec["code"])->where("deleted", 0)->get(1)->getRow();
            if ($row) {
                $ids[$key] = (int) $row->id;
                continue;
            }
            $result = $service->save($spec + ["category_id" => $categoryId, "status" => "active"], 0, true);
            $ids[$key] = (int) ($result["id"] ?? 0);
        }
        return $ids;
    }

    /** @return array<string,int> */
    private function ensureVariants(int $productId): array
    {
        $variants = [];
        foreach (["P" => "Tamanho P", "M" => "Tamanho M"] as $code => $name) {
            $row = $this->db->table($this->db->prefixTable("gd_product_variants"))->where("unit_id", $this->unit_id)->where("product_id", $productId)->where("code", "DEMO-UNIFORM-" . $code)->where("deleted", 0)->get(1)->getRow();
            if (!$row) {
                $result = (new ProductVariantService($this->unit_id, $this->actor_id))->save([
                    "product_id" => $productId,
                    "code" => "DEMO-UNIFORM-" . $code,
                    "name" => $name,
                    "attributes" => ["size" => $code],
                    "is_default" => $code === "P" ? 1 : 0,
                    "status" => "active",
                ], 0, true);
                $variants[$code] = (int) ($result["id"] ?? 0);
            } else {
                $variants[$code] = (int) $row->id;
            }
        }
        return $variants;
    }

    private function ensurePrice(int $listId, int $productId, string $amount, int $variantId = 0, int $resourceId = 0): int
    {
        $table = $this->db->prefixTable("gd_prices");
        $query = $this->db->table($table)->where("unit_id", $this->unit_id)->where("price_list_id", $listId)->where("product_id", $productId)->where("minimum_quantity", "1.000")->where("status", "active")->where("deleted", 0);
        $variantId ? $query->where("variant_id", $variantId) : $query->where("variant_id IS NULL", null, false);
        $resourceId ? $query->where("resource_id", $resourceId) : $query->where("resource_id IS NULL", null, false);
        if ($query->get(1)->getRow()) {
            return 0;
        }

        (new PricingService($this->unit_id, $this->actor_id))->save([
            "price_list_id" => $listId,
            "product_id" => $productId,
            "variant_id" => $variantId,
            "resource_id" => $resourceId,
            "amount" => $amount,
            "minimum_quantity" => "1",
            "status" => "active",
        ]);
        return 1;
    }

    private function resourceId(string $code): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_resources"))->where("unit_id", $this->unit_id)->where("code", $code)->where("deleted", 0)->get(1)->getRow();
        return (int) ($row->id ?? 0);
    }

    private function ensureStudent(): int
    {
        $row = $this->db->query(
            "SELECT sp.id FROM `{$this->db->prefixTable("gd_school_profiles")}` sp
             INNER JOIN `{$this->db->prefixTable("gd_people")}` p ON p.id = sp.person_id AND p.unit_id = sp.unit_id
             WHERE sp.unit_id = ? AND sp.deleted = 0 AND sp.status = 'active' AND p.deleted = 0 AND p.full_name = ? LIMIT 1",
            [$this->unit_id, "[DEMO] Lucas Oliveira"]
        )->getRow();
        if ($row) {
            return (int) $row->id;
        }

        $result = (new SchoolStudentService($this->unit_id, $this->actor_id))->save([
            "full_name" => "[DEMO] Lucas Oliveira",
            "birth_date" => "2012-05-12",
            "new_family_name" => "[DEMO] Família Oliveira",
            "status" => "active",
            "notes" => "Registro de demonstração do painel integrado.",
            "duplicate_override" => true,
        ]);
        return (int) ($result["id"] ?? 0);
    }

    private function ensureClass(string $today): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_classes"))->where("unit_id", $this->unit_id)->where("name", "[DEMO] Futebol Sub-14")->where("deleted", 0)->get(1)->getRow();
        if ($row) {
            return (int) $row->id;
        }

        $result = (new SchoolClassService($this->unit_id, $this->actor_id))->save([
            "name" => "[DEMO] Futebol Sub-14",
            "modality" => "Futebol",
            "class_type" => "group",
            "weekdays" => [(int) (new \DateTimeImmutable($today))->format("N")],
            "local_start_time" => "18:00",
            "local_end_time" => "19:00",
            "capacity" => 20,
            "starts_on" => (new \DateTimeImmutable($today))->modify("-30 days")->format("Y-m-d"),
            "status" => "active",
            "notes" => "Turma de demonstração do dashboard.",
        ]);
        return (int) ($result["id"] ?? 0);
    }

    private function ensureEnrollment(int $classId, int $studentId, int $productId, string $today): int
    {
        $row = $this->db->table($this->db->prefixTable("gd_enrollments"))->where("unit_id", $this->unit_id)->where("class_id", $classId)->where("school_profile_id", $studentId)->where("deleted", 0)->get(1)->getRow();
        if ($row) {
            return (int) $row->id;
        }

        $result = (new SchoolEnrollmentService($this->unit_id, $this->actor_id))->save([
            "class_id" => $classId,
            "school_profile_id" => $studentId,
            "product_id" => $productId,
            "starts_on" => (new \DateTimeImmutable($today))->modify("-30 days")->format("Y-m-d"),
            "preferred_due_day" => 10,
            "status" => "active",
        ]);
        return (int) ($result["id"] ?? 0);
    }

    /** @return array<string,mixed> */
    private function ensureFinance(int $enrollmentId, int $productId, string $today): array
    {
        $month = substr($today, 0, 7);
        $table = $this->db->prefixTable("gd_receivables");
        $row = $this->db->table($table)->where("unit_id", $this->unit_id)->where("source_type", "enrollment")->where("source_id", $enrollmentId)->where("reference_month", $month)->where("deleted", 0)->where("status <>", "cancelled")->get(1)->getRow();
        $finance = new FinanceService($this->unit_id, $this->actor_id);
        if (!$row) {
            $result = $finance->createReceivable([
                "source_type" => "enrollment",
                "source_id" => $enrollmentId,
                "reference_month" => $month,
                "description" => "[DEMO] Mensalidade GD Academy",
                "item_description" => "[DEMO] Plano mensal GD Academy",
                "issue_date" => (new \DateTimeImmutable($today))->modify("-10 days")->format("Y-m-d"),
                "due_date" => (new \DateTimeImmutable($today))->modify("-5 days")->format("Y-m-d"),
                "original_amount" => "350.00",
                "unit_amount" => "350.00",
                "quantity" => "1",
                "product_id" => $productId,
            ]);
            $row = $this->db->table($table)->where("id", (int) ($result["id"] ?? 0))->where("unit_id", $this->unit_id)->get(1)->getRow();
        }

        $payment = null;
        $fresh = $row ? $finance->getReceivable((int) $row->id) : null;
        if ($fresh && (float) $fresh->paid_amount < 150.00 && (float) $fresh->balance_amount >= 150.00) {
            $account = $finance->accounts()[0]["id"] ?? 0;
            $payment = $finance->registerPayment([
                "allocations" => [(int) $fresh->id => "150.00"],
                "amount" => "150.00",
                "payment_date" => $today,
                "payment_method" => "pix",
                "financial_account_id" => (int) $account,
                "notes" => "[DEMO] Pagamento parcial para validação.",
                "payment_type" => "regular",
            ]);
        }

        $expenseTable = $this->db->prefixTable("gd_expenses");
        $expense = $this->db->table($expenseTable)->where("unit_id", $this->unit_id)->where("description", "[DEMO] Material esportivo")->where("deleted", 0)->get(1)->getRow();
        if (!$expense) {
            $account = $finance->accounts()[0]["id"] ?? 0;
            $expense = $finance->saveExpense([
                "description" => "[DEMO] Material esportivo",
                "payee" => "[DEMO] Fornecedor local",
                "expense_date" => (new \DateTimeImmutable($today))->modify("-2 days")->format("Y-m-d"),
                "due_date" => (new \DateTimeImmutable($today))->modify("-2 days")->format("Y-m-d"),
                "paid_date" => (new \DateTimeImmutable($today))->modify("-1 day")->format("Y-m-d"),
                "amount" => "180.00",
                "status" => "paid",
                "financial_account_id" => (int) $account,
                "payment_method" => "pix",
                "notes" => "Despesa de demonstração do dashboard.",
            ]);
        }

        return [
            "receivable_id" => (int) ($fresh->id ?? $row->id ?? 0),
            "payment" => $payment,
            "expense" => is_array($expense) ? $expense : ["id" => (int) ($expense->id ?? 0)],
        ];
    }
}
