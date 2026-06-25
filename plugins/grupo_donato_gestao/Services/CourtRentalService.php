<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Operação comercial de locação de quadras (Fase 3C).
 *
 * Camada de negócio SOBRE reservas/séries: vincula um acordo comercial a uma
 * reserva única (avulso) ou a uma série (mensalista), registra valor contratado
 * como snapshot imutável, dia preferencial de vencimento, vigência e estados.
 *
 * NÃO gera título a receber, cobrança, pagamento, baixa, recibo, caixa, crédito,
 * multa, juros, caução ou nota fiscal. O dia de vencimento é apenas condição
 * comercial. Reaproveita BookingService, BookingSeriesService e PricingService;
 * não duplica o gerador de recorrência nem o motor de conflitos.
 */
class CourtRentalService extends CatalogDataService
{
    private $rentals;
    private $links;
    private $items;
    private ?object $login_user;

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->rentals = model("grupo_donato_gestao\\Models\\Gd_court_rentals_model");
        $this->links = model("grupo_donato_gestao\\Models\\Gd_court_rental_schedule_links_model");
        $this->items = model("grupo_donato_gestao\\Models\\Gd_court_rental_price_items_model");
        $this->login_user = $login_user;
    }

    /* ============================ Leitura ============================ */

    public function get(int $id): ?object
    {
        $row = $this->rentals->get_scoped($id, $this->unit_id);
        if (!$row) { return null; }
        $row->links = $this->resolvedLinks($id);
        $row->price_items = $this->items->for_rental($id, $this->unit_id);
        $row->events = (model("grupo_donato_gestao\\Models\\Gd_court_rental_events_model"))->for_rental($id, $this->unit_id);
        $row->customer_name = null; $row->contact_name = null;
        if ($row->customer_account_id) {
            $a = $this->db->table($this->db->prefixTable("gd_customer_accounts"))->select("display_name")->where("id", $row->customer_account_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            $row->customer_name = $a->display_name ?? null;
        }
        if ($row->contact_person_id) {
            $p = $this->db->table($this->db->prefixTable("gd_people"))->select("full_name")->where("id", $row->contact_person_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            $row->contact_name = $p->full_name ?? null;
        }
        $row->schedule = $this->scheduleSummary($row, $row->links);
        $row->price_difference = $this->priceDifference($row);
        return $row;
    }

    public function listPage(array $options): array
    {
        return $this->queryList($options, null);
    }

    public function monthlyRentersList(array $options): array
    {
        return $this->queryList($options, "recurring");
    }

    private function queryList(array $options, ?string $force_type): array
    {
        $table = $this->db->prefixTable("gd_court_rentals");
        $accounts = $this->db->prefixTable("gd_customer_accounts");
        $links = $this->db->prefixTable("gd_court_rental_schedule_links");
        $sresources = $this->db->prefixTable("gd_booking_series_resources");
        $base = function () use ($options, $force_type, $table, $accounts, $links, $sresources) {
            $q = $this->db->table($table)
                ->join($accounts, "$accounts.id=$table.customer_account_id AND $accounts.unit_id=$table.unit_id AND $accounts.deleted=0", "left", false)
                ->where("$table.unit_id", $this->unit_id)->where("$table.deleted", 0);
            if ($force_type) { $q->where("$table.rental_type", $force_type); }
            elseif ($value = trim((string) ($options["rental_type"] ?? ""))) { $q->where("$table.rental_type", $value); }
            if ($value = trim((string) ($options["status"] ?? ""))) { $q->where("$table.status", $value); }
            if ($value = (int) ($options["customer_account_id"] ?? 0)) { $q->where("$table.customer_account_id", $value); }
            if ($value = trim((string) ($options["date_from"] ?? ""))) { $q->where("COALESCE($table.effective_until,'9999-12-31') >=", $value); }
            if ($value = trim((string) ($options["date_to"] ?? ""))) { $q->where("COALESCE($table.effective_from,'0001-01-01') <=", $value); }
            if (($rid = (int) ($options["resource_id"] ?? 0)) > 0) {
                $q->where("EXISTS (SELECT 1 FROM `$links` lr JOIN `$sresources` sr ON sr.series_id=lr.booking_series_id AND sr.deleted=0 WHERE lr.rental_id=$table.id AND lr.unit_id=$table.unit_id AND lr.deleted=0 AND sr.resource_id=" . $rid . ")", null, false);
            }
            if (($weekday = (int) ($options["weekday"] ?? 0)) >= 1 && $weekday <= 7) {
                $series = $this->db->prefixTable("gd_booking_series");
                $q->where("EXISTS (SELECT 1 FROM `$links` lw JOIN `$series` sw ON sw.id=lw.booking_series_id AND sw.deleted=0 WHERE lw.rental_id=$table.id AND lw.unit_id=$table.unit_id AND lw.deleted=0 AND JSON_CONTAINS(COALESCE(sw.weekdays,'[]'), '" . $weekday . "'))", null, false);
            }
            if ($value = trim((string) ($options["search_by"] ?? ""))) { $q->groupStart()->like("$table.rental_number", $value)->orLike("$table.title", $value)->orLike("$accounts.display_name", $value)->groupEnd(); }
            return $q;
        };
        $total = $this->db->table($table)->where("unit_id", $this->unit_id)->where("deleted", 0);
        if ($force_type) { $total->where("rental_type", $force_type); }
        $total = $total->countAllResults();
        $filtered = (int) $base()->countAllResults(false);
        $q = $base()->select("$table.*, $accounts.display_name AS customer_name", false);
        $map = ["rental_number" => "$table.rental_number", "title" => "$table.title", "status" => "$table.status", "effective_from" => "$table.effective_from", "updated_at" => "$table.updated_at"];
        $order = $map[(string) ($options["order_by"] ?? "")] ?? "$table.updated_at";
        $dir = ($options["order_dir"] ?? "") === "ASC" ? "ASC" : "DESC";
        $q->orderBy($order, $dir)->limit(max(1, min(100, (int) ($options["limit"] ?? 25))), max(0, (int) ($options["skip"] ?? 0)));
        $rows = $q->get()->getResult();
        foreach ($rows as $row) {
            $row->links = $this->resolvedLinks((int) $row->id);
            $row->schedule = $this->scheduleSummary($row, $row->links);
        }
        return ["data" => $rows, "recordsTotal" => $total, "recordsFiltered" => $filtered];
    }

    /* ============================ Preço (sugestão) ============================ */

    /** Sugestão de preço via PricingService; ausência NÃO retorna zero. */
    public function resolvePrice(array $input): array
    {
        $product_id = (int) ($input["product_id"] ?? 0);
        if ($product_id <= 0) { return ["found" => false, "reason" => "no_product"]; }
        $params = [
            "product_id" => $product_id,
            "variant_id" => (int) ($input["variant_id"] ?? 0),
            "resource_id" => (int) ($input["resource_id"] ?? 0),
            "price_list_id" => (int) ($input["price_list_id"] ?? 0),
            "quantity" => (string) ($input["quantity"] ?? "1"),
            "reference_date" => (string) ($input["reference_date"] ?? ""),
        ];
        $resolved = (new PricingService($this->unit_id, $this->actor_id, $this->login_user))->resolve($params);
        return $resolved;
    }

    /* ============================ Criação ============================ */

    /** Rascunho comercial sem vínculo nem valor obrigatório. */
    public function createDraft(array $input, string $forced_type): array
    {
        $commercial = $this->normalizeCommercial($input, $forced_type);
        $lock = new CourtRentalLockService();
        $in_tx = false; $id = 0; $number = "";
        try {
            $lock->acquire($this->unit_id, "new:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental draft transaction"); }
            $in_tx = true;
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $this->writeSnapshotIfPriced($id, $commercial);
            (new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "draft"]);
            $this->audit_change("court_rental_created", "court_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "draft"]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental draft commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        return ["id" => $id, "rental_number" => $number, "lock_version" => 1];
    }

    /** Avulso integrado: reserva única + locação + vínculo + snapshot, em UMA transação. */
    public function createWithBooking(array $input): array
    {
        $commercial = $this->normalizeCommercial($input, "single");
        $booking_input = $this->bookingInputFrom($input, $commercial);
        $resource_ids = array_map(static fn($r) => (int) $r["resource_id"], $booking_input["resources"]);
        $lock = new CourtRentalLockService();
        $rlock = new BookingResourceLockService();
        $in_tx = false; $id = 0; $number = ""; $booking = [];
        try {
            $lock->acquire($this->unit_id, "new:single:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            $rlock->acquire($this->unit_id, $resource_ids);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental single transaction"); }
            $in_tx = true;
            $booking = (new BookingService($this->unit_id, $this->actor_id, $this->login_user))->save($booking_input, 0, false, true, true);
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $primary_resource = $resource_ids ? min($resource_ids) : 0;
            $this->insertLink($id, (int) $booking["id"], null, "primary");
            $this->writeSnapshotIfPriced($id, $commercial, $primary_resource);
            $evt = new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "single", "booking_id" => (int) $booking["id"]]);
            $evt->append($id, "schedule_linked", null, "draft", null, ["booking_id" => (int) $booking["id"], "link_kind" => "primary"]);
            $this->audit_change("court_rental_created", "court_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "single", "booking_id" => (int) $booking["id"]]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental single commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $rlock->release();
            $lock->release();
        }
        return ["id" => $id, "rental_number" => $number, "lock_version" => 1, "booking_id" => (int) ($booking["id"] ?? 0), "booking_number" => (string) ($booking["booking_number"] ?? "")];
    }

    /** Mensalista integrado: série (serviço existente) + locação + vínculo + snapshot. */
    public function createWithSeries(array $input): array
    {
        $commercial = $this->normalizeCommercial($input, "recurring");
        $series_input = $this->seriesInputFrom($input, $commercial);
        $lock = new CourtRentalLockService();
        $in_tx = false; $id = 0; $number = ""; $series = [];
        try {
            $lock->acquire($this->unit_id, "new:recurring:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental recurring transaction"); }
            $in_tx = true;
            // Reutiliza o serviço de séries (transação aninhada + locks próprios);
            // NÃO duplica o gerador de recorrência.
            $series = (new BookingSeriesService($this->unit_id, $this->actor_id, $this->login_user))->create($series_input, true);
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $this->insertLink($id, null, (int) $series["id"], "primary");
            $primary_resource = (int) ($series_input["resources"][0]["resource_id"] ?? 0);
            $this->writeSnapshotIfPriced($id, $commercial, $primary_resource);
            $evt = new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "recurring", "series_id" => (int) $series["id"]]);
            $evt->append($id, "schedule_linked", null, "draft", null, ["booking_series_id" => (int) $series["id"], "link_kind" => "primary"]);
            $this->audit_change("court_rental_created", "court_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "recurring", "series_id" => (int) $series["id"]]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental recurring commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        return ["id" => $id, "rental_number" => $number, "lock_version" => 1, "series_id" => (int) ($series["id"] ?? 0), "series_number" => (string) ($series["series_number"] ?? ""), "generation" => $series["generation"] ?? null];
    }

    /** Vincula uma reserva ou série existente a uma locação. */
    public function linkExisting(int $rental_id, array $input): array
    {
        $booking_id = (int) ($input["booking_id"] ?? 0);
        $series_id = (int) ($input["booking_series_id"] ?? 0);
        $kind = (string) ($input["link_kind"] ?? "primary");
        if (!Constants::isCourtRentalLinkKind($kind) || $kind === "historical") { throw new \DomainException("gd_court_rental_invalid_link_kind"); }
        if (($booking_id > 0) === ($series_id > 0)) { throw new \DomainException("gd_court_rental_link_target_required"); }
        $lock = new CourtRentalLockService();
        $in_tx = false; $link_id = 0;
        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $rental = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$rental) { throw new \DomainException("gd_court_rental_not_found"); }
            if (in_array((string) $rental->status, ["cancelled", "completed", "archived"], true)) { throw new \DomainException("gd_court_rental_not_editable"); }
            $this->assertLinkTargetValid($rental, $booking_id, $series_id);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental link transaction"); }
            $in_tx = true;
            $link_id = $this->insertLink($rental_id, $booking_id ?: null, $series_id ?: null, $kind);
            (new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($rental_id, "schedule_linked", (string) $rental->status, (string) $rental->status, null, ["booking_id" => $booking_id ?: null, "booking_series_id" => $series_id ?: null, "link_kind" => $kind]);
            $this->audit_change("court_rental_schedule_linked", "court_rental", $rental_id, null, ["booking_id" => $booking_id ?: null, "booking_series_id" => $series_id ?: null, "link_kind" => $kind]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental link commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            if (stripos($e->getMessage(), "Duplicate") !== false) { throw new \DomainException("gd_court_rental_already_linked"); }
            throw $e;
        } finally {
            $lock->release();
        }
        return ["id" => $rental_id, "link_id" => $link_id];
    }

    /* ============================ Reprecificação explícita ============================ */

    /** Reprecificação explícita e auditada; não altera snapshots históricos. */
    public function reprice(int $rental_id, array $input, bool $can_override): array
    {
        $lock = new CourtRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $before = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if (in_array((string) $before->status, ["cancelled", "completed", "archived"], true)) { throw new \DomainException("gd_court_rental_not_editable"); }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            $commercial = $this->normalizeCommercial(array_merge($this->commercialArray($before), $input), (string) $before->rental_type);
            // Override sobre preço sugerido exige motivo + permissão.
            $is_override = $this->isPriceOverride($before, $commercial);
            if ($is_override) {
                if (!$can_override) { throw new \DomainException("gd_court_rental_price_override_denied"); }
                if (trim((string) ($commercial["discount_reason"] ?? "")) === "") { throw new \DomainException("gd_court_rental_override_reason_required"); }
            }
            if ($this->db->transBegin() === false) { throw new \RuntimeException("court rental reprice transaction"); }
            $in_tx = true;
            $update = $this->stamp([
                "list_amount" => $commercial["list_amount"], "negotiated_amount" => $commercial["negotiated_amount"],
                "discount_amount" => $commercial["discount_amount"], "discount_reason" => $commercial["discount_reason"],
                "product_id" => $commercial["product_id"], "price_list_id" => $commercial["price_list_id"], "price_id" => $commercial["price_id"],
                "currency" => $commercial["currency"],
            ], false);
            if (!$this->rentals->optimistic_update($rental_id, $this->unit_id, $expected, $update)) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            // Snapshots históricos preservados: marca os atuais como deleted e cria novo.
            $primary_resource = $this->primaryResource($rental_id);
            $this->db->table($this->db->prefixTable("gd_court_rental_price_items"))->where("rental_id", $rental_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
            $this->writeSnapshotIfPriced($rental_id, $commercial, $primary_resource);
            $evt = new CourtRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($rental_id, $is_override ? "price_overridden" : "commercial_terms_changed", (string) $before->status, (string) $before->status, $commercial["discount_reason"] ?? null, ["list_amount" => $commercial["list_amount"], "negotiated_amount" => $commercial["negotiated_amount"], "discount_amount" => $commercial["discount_amount"]]);
            $this->audit_change("court_rental_repriced", "court_rental", $rental_id, $this->commercialArray($before), $commercial, ["override" => $is_override]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("court rental reprice commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        $fresh = $this->rentals->get_scoped($rental_id, $this->unit_id);
        return ["id" => $rental_id, "lock_version" => (int) $fresh->lock_version];
    }

    /* ============================ Helpers de normalização ============================ */

    public function normalizeCommercial(array $input, string $forced_type): array
    {
        if (!Constants::isCourtRentalType($forced_type)) { throw new \DomainException("gd_court_rental_invalid_type"); }
        $cycle = Constants::courtRentalCycleForType($forced_type);

        $title = DataNormalizationService::text(strip_tags((string) ($input["title"] ?? "")));
        if ($title === "" || mb_strlen($title) > 180) { throw new \DomainException("gd_court_rental_title_required"); }

        $customer = (int) ($input["customer_account_id"] ?? 0);
        $contact = (int) ($input["contact_person_id"] ?? 0);
        $this->assertCustomerAndContact($customer, $contact);

        $due_day = null;
        if ($forced_type === "recurring") {
            $raw = trim((string) ($input["preferred_due_day"] ?? ""));
            if ($raw !== "") {
                if (!preg_match('/^\d+$/', $raw) || (int) $raw < 1 || (int) $raw > 31) { throw new \DomainException("gd_court_rental_invalid_due_day"); }
                $due_day = (int) $raw;
            }
        }

        $effective_from = $this->valid_date($input["effective_from"] ?? "", true);
        $effective_until = $this->valid_date($input["effective_until"] ?? "", true);
        if ($effective_from && $effective_until && $effective_until < $effective_from) { throw new \DomainException("gd_court_rental_invalid_validity"); }

        $product_id = $this->assertCompatibleProduct((int) ($input["product_id"] ?? 0));
        $price_list_id = $this->assertUnitRef("gd_price_lists", (int) ($input["price_list_id"] ?? 0), "gd_invalid_price_list");
        $price_id = $this->assertUnitRef("gd_prices", (int) ($input["price_id"] ?? 0), "gd_court_rental_invalid_price");

        $list_amount = DataNormalizationService::decimal($input["list_amount"] ?? "", 2, true);
        $negotiated_amount = DataNormalizationService::decimal($input["negotiated_amount"] ?? "", 2, true);
        $discount_amount = DataNormalizationService::decimal($input["discount_amount"] ?? "", 2, true);
        $discount_reason = DataNormalizationService::text(strip_tags((string) ($input["discount_reason"] ?? "")));
        if ($discount_reason !== "" && mb_strlen($discount_reason) > 255) { $discount_reason = mb_substr($discount_reason, 0, 255); }

        $base = $list_amount ?? $negotiated_amount;
        if ($discount_amount !== null && DataNormalizationService::decimalCompare($discount_amount, "0.00") > 0) {
            if ($discount_reason === "") { throw new \DomainException("gd_court_rental_discount_reason_required"); }
            if ($base === null || DataNormalizationService::decimalCompare($discount_amount, $base) > 0) { throw new \DomainException("gd_court_rental_discount_exceeds_base"); }
        }

        $currency = strtoupper(trim((string) ($input["currency"] ?? Constants::DEFAULT_CURRENCY)));
        if (!Constants::isCurrency($currency)) { throw new \DomainException("gd_invalid_currency"); }

        $notes = trim(strip_tags((string) ($input["commercial_notes"] ?? "")));
        if (mb_strlen($notes) > 5000) { throw new \DomainException("gd_court_rental_notes_too_large"); }
        $metadata = $this->metadata($input["metadata"] ?? null);

        return [
            "customer_account_id" => $customer, "contact_person_id" => $contact ?: null,
            "rental_type" => $forced_type, "title" => $title, "billing_cycle" => $cycle,
            "preferred_due_day" => $due_day, "effective_from" => $effective_from, "effective_until" => $effective_until,
            "currency" => $currency, "list_amount" => $list_amount, "negotiated_amount" => $negotiated_amount,
            "discount_amount" => $discount_amount, "discount_reason" => $discount_reason ?: null,
            "product_id" => $product_id, "price_list_id" => $price_list_id, "price_id" => $price_id,
            "commercial_notes" => $notes ?: null, "metadata" => $metadata,
        ];
    }

    private function assertCustomerAndContact(int $customer, int $contact): void
    {
        if ($customer <= 0) { throw new \DomainException("gd_court_rental_customer_required"); }
        if ($this->db->table($this->db->prefixTable("gd_customer_accounts"))->where("id", $customer)->where("unit_id", $this->unit_id)->where("deleted", 0)->where("status", "active")->countAllResults() !== 1) {
            throw new \DomainException("gd_court_rental_invalid_customer");
        }
        if ($contact > 0) {
            $person = $this->db->table($this->db->prefixTable("gd_people"))->where("id", $contact)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults();
            $link = $this->db->table($this->db->prefixTable("gd_account_people"))->where("unit_id", $this->unit_id)->where("account_id", $customer)->where("person_id", $contact)->where("status", "active")->where("deleted", 0)->countAllResults();
            if ($person !== 1 || $link < 1) { throw new \DomainException("gd_court_rental_invalid_contact"); }
        }
    }

    private function assertCompatibleProduct(int $product_id): ?int
    {
        if ($product_id <= 0) { return null; }
        $row = $this->db->table($this->db->prefixTable("gd_products"))->select("id,product_type,status")->where("id", $product_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
        if (!$row) { throw new \DomainException("gd_invalid_product"); }
        if ((string) $row->status !== "active" || !in_array((string) $row->product_type, Constants::COURT_RENTAL_PRODUCT_TYPES, true)) { throw new \DomainException("gd_court_rental_product_incompatible"); }
        return $product_id;
    }

    private function assertUnitRef(string $table, int $id, string $error): ?int
    {
        if ($id <= 0) { return null; }
        if ($this->db->table($this->db->prefixTable($table))->where("id", $id)->where("unit_id", $this->unit_id)->where("deleted", 0)->countAllResults() !== 1) { throw new \DomainException($error); }
        return $id;
    }

    private function metadata($value): ?string
    {
        $json = DataNormalizationService::json($value, 16000);
        if ($json === null) { return null; }
        $data = json_decode($json, true);
        $walk = function ($value, string $key = "") use (&$walk): void {
            foreach (["password", "token", "secret", "authorization", "cookie", "payment", "charge"] as $bad) { if (str_contains(mb_strtolower($key), $bad)) { throw new \DomainException("gd_court_rental_metadata_forbidden"); } }
            if (is_array($value)) { foreach ($value as $k => $child) { $walk($child, (string) $k); } }
            elseif (is_string($value) && preg_match('/[<>]/', $value)) { throw new \DomainException("gd_court_rental_metadata_forbidden"); }
        };
        $walk($data);
        return $json;
    }

    /* ============================ Persistência interna ============================ */

    private function nextNumber(): string
    {
        $sequence = new SequenceService();
        $sequence->ensure($this->unit_id, "court_rental", "LOC-" . gmdate("Y") . "-", 6, true);
        return $sequence->next($this->unit_id, "court_rental");
    }

    private function insertRental(array $commercial, string $number, string $status): int
    {
        $data = $this->stamp($commercial + ["unit_id" => $this->unit_id, "rental_number" => $number, "status" => $status, "lock_version" => 1, "deleted" => 0], true);
        $id = (int) $this->rentals->ci_save($data);
        if ($id <= 0) { throw new \RuntimeException("court rental insert"); }
        return $id;
    }

    private function insertLink(int $rental_id, ?int $booking_id, ?int $series_id, string $kind): int
    {
        $active = $kind !== "historical";
        $data = $this->stamp([
            "unit_id" => $this->unit_id, "rental_id" => $rental_id,
            "booking_id" => $booking_id, "booking_series_id" => $series_id, "link_kind" => $kind,
            "active_booking_guard" => ($active && $booking_id) ? $booking_id : null,
            "active_series_guard" => ($active && $series_id) ? $series_id : null,
            "deleted" => 0,
        ], true);
        $id = (int) $this->links->ci_save($data);
        if ($id <= 0) { throw new \RuntimeException("court rental link insert"); }
        return $id;
    }

    /** Cria um item de snapshot da negociação quando há valor/preço definido. */
    private function writeSnapshotIfPriced(int $rental_id, array $commercial, int $resource_id = 0): void
    {
        $unit_amount = $commercial["negotiated_amount"] ?? $commercial["list_amount"];
        if ($unit_amount === null) { return; } // rascunho pode existir sem preço
        $quantity = "1.000";
        $discount = $commercial["discount_amount"] ?? "0.00";
        $total = $this->moneyTotal($quantity, $unit_amount, $discount);
        $snapshot = [
            "list_amount" => $commercial["list_amount"], "negotiated_amount" => $commercial["negotiated_amount"],
            "discount_amount" => $commercial["discount_amount"], "discount_reason" => $commercial["discount_reason"],
            "product_id" => $commercial["product_id"], "price_list_id" => $commercial["price_list_id"], "price_id" => $commercial["price_id"],
            "currency" => $commercial["currency"], "captured_at" => gmdate("Y-m-d H:i:s"),
        ];
        $data = $this->stamp([
            "unit_id" => $this->unit_id, "rental_id" => $rental_id,
            "product_id" => $commercial["product_id"], "variant_id" => null, "resource_id" => $resource_id ?: null,
            "price_id" => $commercial["price_id"], "description" => $commercial["title"] ?? null,
            "quantity" => $quantity, "unit_amount" => $unit_amount, "discount_amount" => $discount, "total_amount" => $total,
            "currency" => $commercial["currency"], "snapshot" => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            "deleted" => 0,
        ], true);
        $this->items->ci_save($data);
    }

    private function assertLinkTargetValid(object $rental, int $booking_id, int $series_id): void
    {
        if ($booking_id > 0) {
            $b = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,customer_account_id,status")->where("id", $booking_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$b) { throw new \DomainException("gd_court_rental_booking_not_found"); }
            if (in_array((string) $b->status, ["cancelled", "expired"], true)) { throw new \DomainException("gd_court_rental_link_status_invalid"); }
            if ($b->customer_account_id !== null && (int) $b->customer_account_id !== (int) $rental->customer_account_id) { throw new \DomainException("gd_court_rental_link_customer_mismatch"); }
            if ($this->links->active_for_booking($booking_id, $this->unit_id)) { throw new \DomainException("gd_court_rental_already_linked"); }
        } else {
            $s = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,customer_account_id,status")->where("id", $series_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$s) { throw new \DomainException("gd_court_rental_series_not_found"); }
            if (in_array((string) $s->status, ["cancelled", "archived"], true)) { throw new \DomainException("gd_court_rental_link_status_invalid"); }
            if ($s->customer_account_id !== null && (int) $s->customer_account_id !== (int) $rental->customer_account_id) { throw new \DomainException("gd_court_rental_link_customer_mismatch"); }
            if ($this->links->active_for_series($series_id, $this->unit_id)) { throw new \DomainException("gd_court_rental_already_linked"); }
        }
    }

    /* ============================ Construção de input reaproveitado ============================ */

    private function bookingInputFrom(array $input, array $commercial): array
    {
        $status = trim((string) ($input["booking_status"] ?? "pending_confirmation"));
        if (!in_array($status, ["pending_confirmation", "confirmed"], true)) { $status = "pending_confirmation"; }
        return [
            "booking_type" => "customer_rental", "title" => $commercial["title"],
            "customer_account_id" => $commercial["customer_account_id"], "contact_person_id" => $commercial["contact_person_id"],
            "starts_at_local" => $input["starts_at_local"] ?? "", "ends_at_local" => $input["ends_at_local"] ?? "",
            "status" => $status, "resources" => $this->cleanResources($input["resources"] ?? []),
            "notes" => null, "metadata" => null,
        ];
    }

    private function seriesInputFrom(array $input, array $commercial): array
    {
        return [
            "booking_type" => "customer_rental", "title" => $commercial["title"],
            "customer_account_id" => $commercial["customer_account_id"], "contact_person_id" => $commercial["contact_person_id"],
            "frequency" => $input["frequency"] ?? "", "interval_value" => $input["interval_value"] ?? 1,
            "weekdays" => $input["weekdays"] ?? [], "monthly_day" => $input["monthly_day"] ?? null,
            "local_start_time" => $input["local_start_time"] ?? "", "local_end_time" => $input["local_end_time"] ?? "",
            "starts_on" => $input["starts_on"] ?? "", "ends_mode" => $input["ends_mode"] ?? "", "ends_on" => $input["ends_on"] ?? null,
            "max_occurrences" => $input["max_occurrences"] ?? null,
            "default_booking_status" => $input["default_booking_status"] ?? "pending_confirmation",
            "conflict_policy" => $input["conflict_policy"] ?? "reject_series",
            "generation_horizon_days" => $input["generation_horizon_days"] ?? Constants::BOOKING_SERIES_DEFAULT_HORIZON_DAYS,
            "resources" => $this->cleanResources($input["resources"] ?? []), "notes" => null, "metadata" => null,
        ];
    }

    private function cleanResources($raw): array
    {
        if (!is_array($raw) || !$raw) { throw new \DomainException("gd_invalid_booking_resources"); }
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) { continue; }
            $out[] = ["resource_id" => (int) ($entry["resource_id"] ?? 0), "buffer_before_minutes" => $entry["buffer_before_minutes"] ?? 0, "buffer_after_minutes" => $entry["buffer_after_minutes"] ?? 0];
        }
        if (!$out) { throw new \DomainException("gd_invalid_booking_resources"); }
        return $out;
    }

    /* ============================ Apresentação / cálculo ============================ */

    private function resolvedLinks(int $rental_id): array
    {
        $links = $this->links->for_rental($rental_id, $this->unit_id);
        foreach ($links as $link) {
            $link->booking = null; $link->series = null;
            if ($link->booking_id) {
                $link->booking = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,booking_number,title,status,starts_at_utc,ends_at_utc")->where("id", $link->booking_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
            } elseif ($link->booking_series_id) {
                $link->series = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,series_number,title,status,frequency,weekdays,monthly_day,local_start_time,local_end_time,starts_on,ends_on")->where("id", $link->booking_series_id)->where("unit_id", $this->unit_id)->get(1)->getRow();
            }
        }
        return $links;
    }

    private function scheduleSummary(object $rental, array $links): array
    {
        $resource_ids = []; $weekdays = []; $local_time = ""; $next = null;
        foreach ($links as $link) {
            if ((int) ($link->deleted ?? 0) === 1 || (string) ($link->link_kind ?? "") === "historical") { continue; }
            if (!empty($link->series)) {
                $s = $link->series;
                foreach ($this->db->table($this->db->prefixTable("gd_booking_series_resources"))->select("resource_id")->where("series_id", $s->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get()->getResult() as $sr) { $resource_ids[] = (int) $sr->resource_id; }
                foreach (json_decode((string) $s->weekdays, true) ?: [] as $wd) { $weekdays[] = (int) $wd; }
                if ($local_time === "" && $s->local_start_time) { $local_time = substr((string) $s->local_start_time, 0, 5) . "–" . substr((string) $s->local_end_time, 0, 5); }
                $occ = $this->db->table($this->db->prefixTable("gd_bookings"))->select("MIN(starts_at_utc) AS n", false)->where("unit_id", $this->unit_id)->where("series_id", $s->id)->where("deleted", 0)->whereIn("status", Constants::BOOKING_BLOCKING_STATUSES)->where("starts_at_utc >=", gmdate("Y-m-d H:i:s"))->get(1)->getRow();
                if ($occ && $occ->n && ($next === null || $occ->n < $next)) { $next = $occ->n; }
            } elseif (!empty($link->booking)) {
                $b = $link->booking;
                foreach ($this->db->table($this->db->prefixTable("gd_booking_resources"))->select("resource_id")->where("booking_id", $b->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get()->getResult() as $br) { $resource_ids[] = (int) $br->resource_id; }
                if ($local_time === "") { $local_time = substr((string) $b->starts_at_utc, 11, 5) . "–" . substr((string) $b->ends_at_utc, 11, 5); }
                if ($b->starts_at_utc >= gmdate("Y-m-d H:i:s") && ($next === null || $b->starts_at_utc < $next)) { $next = $b->starts_at_utc; }
            }
        }
        $resource_ids = array_values(array_unique($resource_ids));
        $names = [];
        if ($resource_ids) {
            foreach ($this->db->table($this->db->prefixTable("gd_resources"))->select("code,name")->whereIn("id", $resource_ids)->where("unit_id", $this->unit_id)->orderBy("code")->get()->getResult() as $r) { $names[] = $r->code . " — " . $r->name; }
        }
        sort($weekdays);
        return ["resource_names" => implode(", ", $names), "weekdays" => array_values(array_unique($weekdays)), "local_time" => $local_time, "next_occurrence_utc" => $next];
    }

    private function priceDifference(object $rental): ?string
    {
        if ($rental->list_amount === null || $rental->negotiated_amount === null) { return null; }
        if (DataNormalizationService::decimalCompare((string) $rental->list_amount, (string) $rental->negotiated_amount) < 0) { return null; }
        return $this->subtract((string) $rental->list_amount, (string) $rental->negotiated_amount);
    }

    private function commercialArray(object $rental): array
    {
        return [
            "title" => $rental->title, "customer_account_id" => (int) $rental->customer_account_id, "contact_person_id" => $rental->contact_person_id ? (int) $rental->contact_person_id : 0,
            "preferred_due_day" => $rental->preferred_due_day, "effective_from" => $rental->effective_from, "effective_until" => $rental->effective_until,
            "currency" => $rental->currency, "list_amount" => $rental->list_amount, "negotiated_amount" => $rental->negotiated_amount,
            "discount_amount" => $rental->discount_amount, "discount_reason" => $rental->discount_reason,
            "product_id" => $rental->product_id ? (int) $rental->product_id : 0, "price_list_id" => $rental->price_list_id ? (int) $rental->price_list_id : 0, "price_id" => $rental->price_id ? (int) $rental->price_id : 0,
            "commercial_notes" => $rental->commercial_notes, "metadata" => $rental->metadata,
        ];
    }

    private function isPriceOverride(object $before, array $commercial): bool
    {
        return (string) ($before->negotiated_amount ?? "") !== (string) ($commercial["negotiated_amount"] ?? "")
            || (string) ($before->discount_amount ?? "") !== (string) ($commercial["discount_amount"] ?? "");
    }

    /** Recurso primário do snapshot anterior (preservado ao reprecificar). */
    private function primaryResource(int $rental_id): int
    {
        $ids = [];
        foreach ($this->items->for_rental($rental_id, $this->unit_id, true) as $it) {
            if ($it->resource_id) { $ids[] = (int) $it->resource_id; }
        }
        return $ids ? min($ids) : 0;
    }

    /** Total = quantidade × valor unitário − desconto, em centavos inteiros (sem float). */
    private function moneyTotal(string $quantity, string $unit_amount, string $discount): string
    {
        $q = $this->scaledInt($quantity, 3);      // milésimos
        $u = $this->scaledInt($unit_amount, 2);   // centavos
        $d = $this->scaledInt($discount, 2);      // centavos
        $gross_scaled = $q * $u;                   // escala 10^5
        $gross_cents = intdiv($gross_scaled + 500, 1000); // arredonda para centavos (half-up)
        $cents = $gross_cents - $d;
        if ($cents < 0) { $cents = 0; }
        return $this->centsToDecimal($cents);
    }

    private function subtract(string $a, string $b): string
    {
        $cents = $this->scaledInt($a, 2) - $this->scaledInt($b, 2);
        if ($cents < 0) { $cents = 0; }
        return $this->centsToDecimal($cents);
    }

    private function scaledInt(string $value, int $scale): int
    {
        [$int, $frac] = array_pad(explode(".", $value, 2), 2, "");
        $frac = substr(str_pad($frac, $scale, "0"), 0, $scale);
        return (int) ($int . $frac);
    }

    private function centsToDecimal(int $cents): string
    {
        return intdiv($cents, 100) . "." . str_pad((string) ($cents % 100), 2, "0", STR_PAD_LEFT);
    }
}
