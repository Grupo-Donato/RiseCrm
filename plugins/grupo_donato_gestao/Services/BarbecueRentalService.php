<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

use grupo_donato_gestao\Config\Constants;

/**
 * Operação comercial de aluguel de churrasqueiras (Fase 3C).
 *
 * Camada de negócio SOBRE reservas/séries: vincula um acordo comercial a uma
 * reserva única (avulso) ou a uma série (mensalista), registra valor contratado
 * como snapshot imutável, dia preferencial de vencimento, vigência e estados.
 *
 * Para locações avulsas, integra o título a receber e o sinal opcional ao
 * FinanceService na mesma operação transacional. Mensalistas continuam usando
 * o fluxo financeiro recorrente existente; o dia de vencimento é condição
 * comercial do contrato. Reaproveita BookingService, BookingSeriesService e
 * PricingService; não duplica recorrência nem o motor de conflitos.
 */
class BarbecueRentalService extends CatalogDataService
{
    private $rentals;
    private $links;
    private $items;
    private ?object $login_user;
    private ?TemporalService $time = null;

    /** Conversor de fuso da unidade (lazy — reusa o serviço canônico). */
    private function time(): TemporalService
    {
        return $this->time ??= new TemporalService($this->unit_id);
    }

    public function __construct(int $unit_id, int $actor_id = 0, ?object $login_user = null)
    {
        parent::__construct($unit_id, $actor_id, $login_user);
        $this->rentals = model("grupo_donato_gestao\\Models\\Gd_barbecue_rentals_model");
        $this->links = model("grupo_donato_gestao\\Models\\Gd_barbecue_rental_schedule_links_model");
        $this->items = model("grupo_donato_gestao\\Models\\Gd_barbecue_rental_price_items_model");
        $this->login_user = $login_user;
    }

    /* ============================ Leitura ============================ */

    public function get(int $id): ?object
    {
        $row = $this->rentals->get_scoped($id, $this->unit_id);
        if (!$row) { return null; }
        $row->links = $this->resolvedLinks($id);
        $row->price_items = $this->items->for_rental($id, $this->unit_id);
        $row->events = (model("grupo_donato_gestao\\Models\\Gd_barbecue_rental_events_model"))->for_rental($id, $this->unit_id);
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

    public function singleRentalsList(array $options): array
    {
        return $this->queryList($options, "single");
    }

    public function monthlyRentersList(array $options): array
    {
        return $this->queryList($options, "recurring");
    }

    private function queryList(array $options, ?string $force_type): array
    {
        $table = $this->db->prefixTable("gd_barbecue_rentals");
        $accounts = $this->db->prefixTable("gd_customer_accounts");
        $links = $this->db->prefixTable("gd_barbecue_rental_schedule_links");
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
                // O vínculo comercial pode apontar para uma série (mensalista) ou
                // para um booking avulso; o filtro por churrasqueira precisa cobrir os dois
                // via recursos de série (gd_booking_series_resources) e recursos de
                // booking (gd_booking_resources), considerando apenas links ativos.
                $bresources = $this->db->prefixTable("gd_booking_resources");
                $resources = $this->db->prefixTable("gd_resources");
                $q->where(
                    "EXISTS (SELECT 1 FROM `$resources` rr WHERE rr.id=$rid AND rr.unit_id=$table.unit_id AND rr.resource_type='" . Constants::BARBECUE_RESOURCE_TYPE . "' AND rr.deleted=0 AND rr.is_active=1 AND rr.is_bookable=1)",
                    null,
                    false
                );
                $q->where(
                    "EXISTS (SELECT 1 FROM `$links` lr WHERE lr.rental_id=$table.id AND lr.unit_id=$table.unit_id AND lr.deleted=0 AND lr.link_kind <> 'historical' AND ("
                    . "EXISTS (SELECT 1 FROM `$sresources` sr WHERE sr.series_id=lr.booking_series_id AND sr.unit_id=lr.unit_id AND sr.deleted=0 AND sr.resource_id=$rid)"
                    . " OR EXISTS (SELECT 1 FROM `$bresources` br WHERE br.booking_id=lr.booking_id AND br.unit_id=lr.unit_id AND br.deleted=0 AND br.resource_id=$rid)"
                    . "))",
                    null,
                    false
                );
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
        $resource_id = (int) ($input["resource_id"] ?? 0);
        if ($resource_id > 0) {
            $resource = $this->db->table($this->db->prefixTable("gd_resources"))
                ->select("id")
                ->where("id", $resource_id)
                ->where("unit_id", $this->unit_id)
                ->where("resource_type", Constants::BARBECUE_RESOURCE_TYPE)
                ->where("deleted", 0)
                ->where("is_active", 1)
                ->where("is_bookable", 1)
                ->get(1)
                ->getRow();
            if (!$resource) { throw new \DomainException("gd_invalid_booking_resources"); }
        }
        $params = [
            "product_id" => $product_id,
            "variant_id" => (int) ($input["variant_id"] ?? 0),
            "resource_id" => $resource_id,
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
        $lock = new BarbecueRentalLockService();
        $in_tx = false; $id = 0; $number = "";
        try {
            $lock->acquire($this->unit_id, "new:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental draft transaction"); }
            $in_tx = true;
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $this->writeSnapshotIfPriced($id, $commercial);
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "draft"]);
            $this->audit_change("barbecue_rental_created", "barbecue_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "draft"]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental draft commit"); }
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
        $this->assertCommercialValue($commercial);
        $deposit = $this->normalizeDeposit($input, $commercial);
        $booking_input = $this->bookingInputFrom($input, $commercial);
        $resource_ids = array_map(static fn($r) => (int) $r["resource_id"], $booking_input["resources"]);
        $lock = new BarbecueRentalLockService();
        $rlock = new BookingResourceLockService();
        $in_tx = false; $id = 0; $number = ""; $booking = [];
        try {
            $lock->acquire($this->unit_id, "new:single:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            $rlock->acquire($this->unit_id, $resource_ids);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental single transaction"); }
            $in_tx = true;
            // A criação integrada de locação é uma operação gerencial completa.
            // Quando o formulário estiver marcado para confirmar/ativar, permite
            // que a reserva vinculada já seja criada como confirmada.
            $booking = (new BookingService($this->unit_id, $this->actor_id, $this->login_user))->save($booking_input, 0, true, true, true);
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $primary_resource = $resource_ids ? min($resource_ids) : 0;
            $this->insertLink($id, (int) $booking["id"], null, "primary");
            $this->writeSnapshotIfPriced($id, $commercial, $primary_resource);
            $evt = new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "single", "booking_id" => (int) $booking["id"]]);
            $evt->append($id, "schedule_linked", null, "draft", null, ["booking_id" => (int) $booking["id"], "link_kind" => "primary"]);
            $this->audit_change("barbecue_rental_created", "barbecue_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "single", "booking_id" => (int) $booking["id"]]);
            $finance = $this->createSingleRentalFinance($id, $commercial, $deposit);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental single commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $rlock->release();
            $lock->release();
        }
        return ["id" => $id, "rental_number" => $number, "lock_version" => 1, "booking_id" => (int) ($booking["id"] ?? 0), "booking_number" => (string) ($booking["booking_number"] ?? ""), "finance" => $finance ?? null];
    }

    /** Mensalista integrado: série (serviço existente) + locação + vínculo + snapshot. */
    public function createWithSeries(array $input): array
    {
        $commercial = $this->normalizeCommercial($input, "recurring");
        $this->assertCommercialValue($commercial);
        $series_input = $this->seriesInputFrom($input, $commercial);
        $lock = new BarbecueRentalLockService();
        $in_tx = false; $id = 0; $number = ""; $series = [];
        try {
            $lock->acquire($this->unit_id, "new:recurring:" . substr(hash("sha256", json_encode($commercial, JSON_UNESCAPED_SLASHES)), 0, 32));
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental recurring transaction"); }
            $in_tx = true;
            // Reutiliza o serviço de séries (transação aninhada + locks próprios);
            // NÃO duplica o gerador de recorrência.
            $series = (new BookingSeriesService($this->unit_id, $this->actor_id, $this->login_user))->create($series_input, true);
            $number = $this->nextNumber();
            $id = $this->insertRental($commercial, $number, "draft");
            $this->insertLink($id, null, (int) $series["id"], "primary");
            $primary_resource = (int) ($series_input["resources"][0]["resource_id"] ?? 0);
            $this->writeSnapshotIfPriced($id, $commercial, $primary_resource);
            $evt = new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($id, "created", null, "draft", null, ["rental_number" => $number, "mode" => "recurring", "series_id" => (int) $series["id"]]);
            $evt->append($id, "schedule_linked", null, "draft", null, ["booking_series_id" => (int) $series["id"], "link_kind" => "primary"]);
            $this->audit_change("barbecue_rental_created", "barbecue_rental", $id, null, ["rental_number" => $number] + $commercial, ["mode" => "recurring", "series_id" => (int) $series["id"]]);
            // A série e a locação só ficam concluídas quando a primeira
            // competência também está no ledger. A geração posterior continua
            // idempotente, mas a lista não pode depender da abertura do
            // financeiro para criar a cobrança inicial.
            $finance = $this->createRecurringRentalFinance($id, $commercial);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental recurring commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }
        return ["id" => $id, "rental_number" => $number, "lock_version" => 1, "series_id" => (int) ($series["id"] ?? 0), "series_number" => (string) ($series["series_number"] ?? ""), "generation" => $series["generation"] ?? null, "finance" => $finance ?? null];
    }

    /**
     * Edita a reserva avulsa e seus termos comerciais como uma única operação.
     * O BookingService continua sendo a fonte de verdade para conflitos,
     * disponibilidade, locks e histórico da reserva.
     */
    public function updateSingle(int $rental_id, array $input): array
    {
        $commercial = $this->normalizeCommercial($input, "single");
        $this->assertCommercialValue($commercial);
        $booking_input = $this->bookingInputFrom($input, $commercial);
        $lock = new BarbecueRentalLockService();
        $rlock = new BookingResourceLockService();
        $in_tx = false;
        $booking_id = 0;
        $booking_result = [];

        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $before = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if ((string) $before->rental_type !== "single" || in_array((string) $before->status, ["cancelled", "completed", "archived"], true)) {
                throw new \DomainException("gd_court_rental_not_editable");
            }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }

            foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
                if ((string) ($link->link_kind ?? "") !== "historical" && (int) ($link->booking_id ?? 0) > 0) {
                    $booking_id = (int) $link->booking_id;
                    break;
                }
            }
            if ($booking_id <= 0) { throw new \DomainException("gd_court_rental_booking_not_found"); }

            $booking_service = new BookingService($this->unit_id, $this->actor_id, $this->login_user);
            $booking_before = $booking_service->get($booking_id);
            if (!$booking_before) { throw new \DomainException("gd_court_rental_booking_not_found"); }
            $booking_input["status"] = (string) $booking_before->status;
            $booking_input["notes"] = $booking_before->notes;
            $booking_input["metadata"] = $booking_before->metadata;
            if ((string) $booking_before->status === "hold" && $booking_before->hold_expires_at_utc) {
                $booking_input["hold_expires_at_local"] = $this->time()->utcToLocalInput((string) $booking_before->hold_expires_at_utc);
            }
            $booking_input["lock_version"] = (int) ($input["booking_lock_version"] ?? 0);

            $old_resource_ids = array_map(static fn($r): int => (int) $r->resource_id, $booking_before->resources ?? []);
            $new_resource_ids = array_map(static fn($r): int => (int) ($r["resource_id"] ?? 0), $booking_input["resources"] ?? []);
            sort($old_resource_ids); sort($new_resource_ids);
            $price_fields = ["list_amount", "negotiated_amount", "discount_amount", "discount_reason", "product_id", "price_list_id", "price_id", "currency"];
            $price_changed = $old_resource_ids !== $new_resource_ids;
            foreach ($price_fields as $field) {
                if ((string) ($before->{$field} ?? "") !== (string) ($commercial[$field] ?? "")) { $price_changed = true; break; }
            }
            $old_total = $this->totalWithExtra($this->commercialTotal($this->commercialArray($before)), $before->extra_time_amount ?? "0.00") ?? "0.00";
            $new_total = $this->totalWithExtra($this->commercialTotal($commercial), $before->extra_time_amount ?? "0.00") ?? "0.00";
            $rlock->acquire($this->unit_id, array_values(array_unique(array_merge($old_resource_ids, $new_resource_ids))));

            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental single update transaction"); }
            $in_tx = true;
            $booking_result = $booking_service->save($booking_input, $booking_id, true, true, true);
            if (!$this->rentals->optimistic_update($rental_id, $this->unit_id, $expected, $this->stamp($commercial, false))) {
                throw new \DomainException("gd_court_rental_edit_conflict");
            }
            if ($price_changed) {
                $this->db->table($this->db->prefixTable("gd_barbecue_rental_price_items"))
                    ->where("rental_id", $rental_id)->where("unit_id", $this->unit_id)->where("deleted", 0)
                    ->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
                $this->writeSnapshotIfPriced($rental_id, $commercial, (int) ($new_resource_ids[0] ?? 0));
            }
            if (DataNormalizationService::decimalCompare($old_total, $new_total) !== 0) {
                (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->syncBarbecueRentalReceivableAmount(
                    $rental_id,
                    $new_total,
                    "Churrasqueira avulsa — " . $commercial["title"]
                );
            }
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append(
                $rental_id,
                "commercial_terms_changed",
                (string) $before->status,
                (string) $before->status,
                null,
                ["scope" => "single_full_edit", "booking_id" => $booking_id, "old_total" => $old_total, "new_total" => $new_total]
            );
            $this->audit_change("barbecue_rental_updated", "barbecue_rental", $rental_id, $this->commercialArray($before), $commercial, ["scope" => "single_full_edit", "booking_id" => $booking_id]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental single update commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $rlock->release();
            $lock->release();
        }

        $fresh = $this->rentals->get_scoped($rental_id, $this->unit_id);
        return [
            "id" => $rental_id,
            "lock_version" => (int) ($fresh->lock_version ?? 0),
            "booking_id" => $booking_id,
            "booking_lock_version" => (int) ($booking_result["lock_version"] ?? 0),
        ];
    }

    /**
     * Registra o tempo adicional de uma locação sem criar outro booking.
     * Para avulsas sincroniza a cobrança única; para mensalistas aplica a
     * diferença nas competências em aberto e nas próximas gerações.
     */
    public function registerExtraTime(int $rental_id, array $input): array
    {
        $lock = new BarbecueRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, "extra-time:" . $rental_id);
            $before = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            $rental_type = (string) $before->rental_type;
            if (!in_array($rental_type, ["single", "recurring"], true)) { throw new \DomainException("gd_extra_time_unsupported_type"); }
            if (in_array((string) $before->status, ["cancelled", "completed", "archived"], true)) {
                throw new \DomainException("gd_extra_time_not_editable");
            }
            $has_booking = false;
            foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
                if ((string) ($link->link_kind ?? "") !== "historical"
                    && ((int) ($link->booking_id ?? 0) > 0 || (int) ($link->booking_series_id ?? 0) > 0)
                ) {
                    $has_booking = true;
                    break;
                }
            }
            if (!$has_booking) { throw new \DomainException("gd_court_rental_booking_not_found"); }

            $raw_minutes = trim((string) ($input["extra_time_minutes"] ?? "0"));
            if ($raw_minutes === "") { $raw_minutes = "0"; }
            if (!preg_match('/^\d+$/', $raw_minutes)) { throw new \DomainException("gd_extra_time_invalid_minutes"); }
            $minutes = (int) $raw_minutes;
            if ($minutes < 0 || $minutes > 1440) { throw new \DomainException("gd_extra_time_invalid_minutes"); }

            $raw_amount = trim((string) ($input["extra_time_amount"] ?? ""));
            $amount = $raw_amount === "" ? "0.00" : DataNormalizationService::decimal($raw_amount, 2);
            if ($minutes === 0 && DataNormalizationService::decimalCompare($amount, "0.00") === 0) {
                $amount = "0.00";
            } elseif ($minutes <= 0 || DataNormalizationService::decimalCompare($amount, "0.00") <= 0) {
                throw new \DomainException("gd_extra_time_amount_required");
            }

            $notes = trim(strip_tags((string) ($input["extra_time_notes"] ?? "")));
            if (mb_strlen($notes) > 2000) { throw new \DomainException("gd_extra_time_notes_too_large"); }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }

            $base_total = $this->commercialTotal($this->commercialArray($before)) ?? "0.00";
            $old_extra = DataNormalizationService::decimal((string) ($before->extra_time_amount ?? "0.00"), 2);
            $old_total = $this->totalWithExtra($base_total, $old_extra) ?? "0.00";
            $new_total = $this->totalWithExtra($base_total, $amount) ?? "0.00";

            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental extra time transaction"); }
            $in_tx = true;
            if (!$this->rentals->optimistic_update($rental_id, $this->unit_id, $expected, [
                "extra_time_minutes" => $minutes,
                "extra_time_amount" => $amount,
                "extra_time_notes" => $notes !== "" ? $notes : null,
            ])) {
                throw new \DomainException("gd_court_rental_edit_conflict");
            }
            if (DataNormalizationService::decimalCompare($old_total, $new_total) !== 0) {
                $finance = new FinanceService($this->unit_id, $this->actor_id, $this->login_user);
                if ($rental_type === "single") {
                    $finance->syncBarbecueRentalReceivableAmount(
                        $rental_id,
                        $new_total,
                        "Churrasqueira avulsa — " . $before->title
                    );
                } else {
                    $finance->syncBarbecueRentalRecurringReceivableAmount(
                        $rental_id,
                        $old_extra,
                        $amount,
                        "Mensalista — " . $before->title
                    );
                }
            }
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append(
                $rental_id,
                "extra_time_added",
                (string) $before->status,
                (string) $before->status,
                $notes !== "" ? $notes : null,
                ["minutes" => $minutes, "old_amount" => $old_extra, "amount" => $amount, "old_total" => $old_total, "new_total" => $new_total]
            );
            $this->audit_change("barbecue_rental_extra_time", "barbecue_rental", $rental_id, [
                "extra_time_minutes" => (int) ($before->extra_time_minutes ?? 0),
                "extra_time_amount" => $old_extra,
                "extra_time_notes" => $before->extra_time_notes ?? null,
            ], [
                "extra_time_minutes" => $minutes,
                "extra_time_amount" => $amount,
                "extra_time_notes" => $notes !== "" ? $notes : null,
            ], ["old_total" => $old_total, "new_total" => $new_total]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental extra time commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }

        $fresh = $this->rentals->get_scoped($rental_id, $this->unit_id);
        return [
            "id" => $rental_id,
            "lock_version" => (int) ($fresh->lock_version ?? 0),
            "extra_time_minutes" => (int) ($fresh->extra_time_minutes ?? $minutes),
            "extra_time_amount" => (string) ($fresh->extra_time_amount ?? $amount),
            "total" => $new_total,
        ];
    }

    /**
     * Edita um mensalista e a série semanal vinculada como uma única operação.
     * Reservas passadas permanecem no histórico; o serviço canônico de séries
     * substitui apenas as ocorrências futuras.
     */
    public function updateMonthly(int $rental_id, array $input): array
    {
        $commercial = $this->normalizeCommercial($input, "recurring");
        $this->assertCommercialValue($commercial);
        $series_input = $this->seriesInputFrom($input, $commercial);
        $lock = new BarbecueRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $before = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if ((string) $before->rental_type !== "recurring" || in_array((string) $before->status, ["cancelled", "completed", "archived"], true)) {
                throw new \DomainException("gd_court_rental_not_editable");
            }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }

            $series_id = 0;
            foreach ($this->links->for_rental($rental_id, $this->unit_id) as $link) {
                if ((string) ($link->link_kind ?? "") !== "historical" && (int) ($link->booking_series_id ?? 0) > 0) {
                    $series_id = (int) $link->booking_series_id;
                    break;
                }
            }
            if ($series_id <= 0) { throw new \DomainException("gd_court_rental_series_not_found"); }

            $series_service = new BookingSeriesService($this->unit_id, $this->actor_id, $this->login_user);
            $series_before = $series_service->get($series_id);
            if (!$series_before) { throw new \DomainException("gd_court_rental_series_not_found"); }
            $series_input["lock_version"] = (int) ($input["series_lock_version"] ?? 0);

            $old_resource_ids = array_map(static fn($r): int => (int) $r->resource_id, $series_before->resources ?? []);
            sort($old_resource_ids);
            $new_resource_ids = array_map(static fn($r): int => (int) ($r["resource_id"] ?? 0), $series_input["resources"] ?? []);
            sort($new_resource_ids);
            $price_fields = ["list_amount", "negotiated_amount", "discount_amount", "discount_reason", "product_id", "price_list_id", "price_id", "currency"];
            $price_changed = $old_resource_ids !== $new_resource_ids;
            foreach ($price_fields as $field) {
                if ((string) ($before->{$field} ?? "") !== (string) ($commercial[$field] ?? "")) { $price_changed = true; break; }
            }

            $old_base_total = $this->commercialTotal($this->commercialArray($before));
            $new_base_total = $this->commercialTotal($commercial);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental monthly update transaction"); }
            $in_tx = true;
            $series_result = $series_service->updateEntire($series_id, $series_input);
            if (!$this->rentals->optimistic_update($rental_id, $this->unit_id, $expected, $this->stamp($commercial, false))) {
                throw new \DomainException("gd_court_rental_edit_conflict");
            }
            if ($price_changed) {
                $this->db->table($this->db->prefixTable("gd_barbecue_rental_price_items"))
                    ->where("rental_id", $rental_id)->where("unit_id", $this->unit_id)->where("deleted", 0)
                    ->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
                $this->writeSnapshotIfPriced($rental_id, $commercial, (int) ($new_resource_ids[0] ?? 0));
                if ($old_base_total !== null && $new_base_total !== null && DataNormalizationService::decimalCompare($old_base_total, $new_base_total) !== 0) {
                    (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->syncBarbecueRentalRecurringReceivableAmount(
                        $rental_id, $old_base_total, $new_base_total, "Mensalista churrasqueira — " . $commercial["title"]
                    );
                }
            }
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append(
                $rental_id,
                "commercial_terms_changed",
                (string) $before->status,
                (string) $before->status,
                null,
                ["scope" => "monthly_full_edit", "series_id" => $series_id]
            );
            $this->audit_change("barbecue_rental_updated", "barbecue_rental", $rental_id, $this->commercialArray($before), $commercial, ["scope" => "monthly_full_edit", "series_id" => $series_id]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental monthly update commit"); }
            $in_tx = false;
        } catch (\Throwable $e) {
            if ($in_tx) { $this->db->transRollback(); }
            throw $e;
        } finally {
            $lock->release();
        }

        $fresh = $this->rentals->get_scoped($rental_id, $this->unit_id);
        return [
            "id" => $rental_id,
            "lock_version" => (int) $fresh->lock_version,
            "series_id" => $series_id,
            "series_lock_version" => (int) ($series_result["lock_version"] ?? 0),
            "replaced_booking_ids" => $series_result["replaced_booking_ids"] ?? [],
            "generation" => $series_result["generation"] ?? null,
        ];
    }

    /** Vincula uma reserva ou série existente a uma locação. */
    public function linkExisting(int $rental_id, array $input): array
    {
        $booking_id = (int) ($input["booking_id"] ?? 0);
        $series_id = (int) ($input["booking_series_id"] ?? 0);
        $kind = (string) ($input["link_kind"] ?? "primary");
        if (!Constants::isCourtRentalLinkKind($kind) || $kind === "historical") { throw new \DomainException("gd_court_rental_invalid_link_kind"); }
        if (($booking_id > 0) === ($series_id > 0)) { throw new \DomainException("gd_court_rental_link_target_required"); }
        $lock = new BarbecueRentalLockService();
        $in_tx = false; $link_id = 0;
        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $rental = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$rental) { throw new \DomainException("gd_court_rental_not_found"); }
            if (in_array((string) $rental->status, ["cancelled", "completed", "archived"], true)) { throw new \DomainException("gd_court_rental_not_editable"); }
            $this->assertLinkTargetValid($rental, $booking_id, $series_id);
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental link transaction"); }
            $in_tx = true;
            $link_id = $this->insertLink($rental_id, $booking_id ?: null, $series_id ?: null, $kind);
            (new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user))->append($rental_id, "schedule_linked", (string) $rental->status, (string) $rental->status, null, ["booking_id" => $booking_id ?: null, "booking_series_id" => $series_id ?: null, "link_kind" => $kind]);
            $this->audit_change("barbecue_rental_schedule_linked", "barbecue_rental", $rental_id, null, ["booking_id" => $booking_id ?: null, "booking_series_id" => $series_id ?: null, "link_kind" => $kind]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental link commit"); }
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
        $lock = new BarbecueRentalLockService();
        $in_tx = false;
        try {
            $lock->acquire($this->unit_id, (string) $rental_id);
            $before = $this->rentals->get_scoped($rental_id, $this->unit_id);
            if (!$before) { throw new \DomainException("gd_court_rental_not_found"); }
            if (in_array((string) $before->status, ["cancelled", "completed", "archived"], true)) { throw new \DomainException("gd_court_rental_not_editable"); }
            $expected = (int) ($input["lock_version"] ?? 0);
            if ($expected !== (int) $before->lock_version) { throw new \DomainException("gd_court_rental_edit_conflict"); }
            $commercial = $this->normalizeCommercial(array_merge($this->commercialArray($before), $input), (string) $before->rental_type);
            $this->assertCommercialValue($commercial);
            $old_total = $this->totalWithExtra($this->commercialTotal($this->commercialArray($before)), $before->extra_time_amount ?? "0.00") ?? "0.00";
            $new_total = $this->totalWithExtra($this->commercialTotal($commercial), $before->extra_time_amount ?? "0.00") ?? "0.00";
            // Override sobre preço sugerido exige motivo + permissão.
            $is_override = $this->isPriceOverride($before, $commercial);
            if ($is_override) {
                if (!$can_override) { throw new \DomainException("gd_court_rental_price_override_denied"); }
                if (trim((string) ($commercial["discount_reason"] ?? "")) === "") { throw new \DomainException("gd_court_rental_override_reason_required"); }
            }
            if ($this->db->transBegin() === false) { throw new \RuntimeException("barbecue rental reprice transaction"); }
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
            $this->db->table($this->db->prefixTable("gd_barbecue_rental_price_items"))->where("rental_id", $rental_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->update(["deleted" => 1, "updated_at" => gmdate("Y-m-d H:i:s"), "updated_by" => $this->actor_id ?: null]);
            $this->writeSnapshotIfPriced($rental_id, $commercial, $primary_resource);
            $has_booking = (string) $before->rental_type === "single" && (bool) array_filter(
                $this->links->for_rental($rental_id, $this->unit_id),
                static fn($link): bool => (string) ($link->link_kind ?? "") !== "historical" && (int) ($link->booking_id ?? 0) > 0
            );
            if ($has_booking && DataNormalizationService::decimalCompare($old_total, $new_total) !== 0) {
                (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->syncBarbecueRentalReceivableAmount(
                    $rental_id,
                    $new_total,
                    "Churrasqueira avulsa — " . $commercial["title"]
                );
            }
            $evt = new BarbecueRentalEventService($this->unit_id, $this->actor_id, $this->login_user);
            $evt->append($rental_id, $is_override ? "price_overridden" : "commercial_terms_changed", (string) $before->status, (string) $before->status, $commercial["discount_reason"] ?? null, ["list_amount" => $commercial["list_amount"], "negotiated_amount" => $commercial["negotiated_amount"], "discount_amount" => $commercial["discount_amount"]]);
            $this->audit_change("barbecue_rental_repriced", "barbecue_rental", $rental_id, $this->commercialArray($before), $commercial, ["override" => $is_override]);
            if ($this->db->transCommit() === false) { throw new \RuntimeException("barbecue rental reprice commit"); }
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
        $financial_status = strtolower(trim((string) ($input["financial_status"] ?? "")));
        if ($financial_status !== "" && !in_array($financial_status, ["chargeable", "exempt"], true)) {
            throw new \DomainException("gd_finance_invalid_rental_status");
        }
        if ($financial_status === "exempt") {
            $metadata_data = $metadata ? json_decode($metadata, true) : [];
            if (!is_array($metadata_data)) { $metadata_data = []; }
            $metadata_data["financial_status"] = "exempt";
            $metadata = $this->metadata(json_encode($metadata_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

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

    /** Locação operacional completa precisa ter um valor livremente informado. */
    private function assertCommercialValue(array $commercial): void
    {
        if ($this->isExemptCommercial($commercial)) { return; }
        $total = $this->commercialTotal($commercial);
        if ($total === null || DataNormalizationService::decimalCompare($total, "0.00") <= 0) {
            throw new \DomainException("gd_barbecue_rental_value_required");
        }
    }

    private function isExemptCommercial(array $commercial): bool
    {
        $metadata = json_decode((string) ($commercial["metadata"] ?? ""), true);
        return is_array($metadata) && (string) ($metadata["financial_status"] ?? "") === "exempt";
    }

    /** Normaliza o sinal sem float e sem permitir valor acima do total. */
    private function normalizeDeposit(array $input, array $commercial): array
    {
        $raw = array_key_exists("deposit_amount", $input) ? $input["deposit_amount"] : "0.00";
        $deposit = DataNormalizationService::decimal($raw === "" || $raw === null ? "0.00" : $raw, 2);
        $base = $commercial["negotiated_amount"] ?? $commercial["list_amount"] ?? "0.00";
        $total = $this->moneyTotal("1.000", (string) $base, (string) ($commercial["discount_amount"] ?? "0.00"));
        if (DataNormalizationService::decimalCompare($deposit, $total) > 0) {
            throw new \DomainException("gd_deposit_exceeds_total");
        }
        $method = trim((string) ($input["deposit_payment_method"] ?? $input["payment_method"] ?? ""));
        $account = (int) ($input["financial_account_id"] ?? 0);
        if (DataNormalizationService::decimalCompare($deposit, "0.00") > 0 && !in_array($method, Constants::PAYMENT_METHODS, true)) {
            throw new \DomainException("gd_deposit_payment_method_required");
        }
        return ["amount" => $deposit, "payment_method" => $method, "financial_account_id" => $account];
    }

    /** Liga a cobrança avulsa ao ledger existente, com sinal opcional. */
    private function createSingleRentalFinance(int $rental_id, array $commercial, array $deposit): ?array
    {
        $total = $this->commercialTotal($commercial);
        if ($total === null) { return null; }
        if (DataNormalizationService::decimalCompare($total, "0.00") <= 0) { return null; }
        $issue = gmdate("Y-m-d");
        $due = (string) ($commercial["effective_from"] ?? $issue);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) || $due < $issue) { $due = $issue; }
        return (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->createCourtRentalReceivableWithDeposit([
            "source_type" => "barbecue_rental", "source_id" => $rental_id, "reference_month" => "",
            "description" => "Churrasqueira avulsa — " . $commercial["title"], "issue_date" => $issue, "due_date" => $due,
            "original_amount" => $total, "unit_amount" => $total, "quantity" => "1", "product_id" => (int) ($commercial["product_id"] ?? 0),
            "deposit_amount" => $deposit["amount"], "payment_method" => $deposit["payment_method"],
            "financial_account_id" => $deposit["financial_account_id"], "payment_date" => $issue,
        ]);
    }

    /** Cria a primeira competência do contrato mensalista de forma idempotente. */
    private function createRecurringRentalFinance(int $rental_id, array $commercial): ?array
    {
        $total = $this->commercialTotal($commercial);
        if ($total === null || DataNormalizationService::decimalCompare($total, "0.00") <= 0) { return null; }

        $effective = (string) ($commercial["effective_from"] ?? "");
        $reference = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective) ? substr($effective, 0, 7) : gmdate("Y-m");
        $day = (int) ($commercial["preferred_due_day"] ?? 10);
        $max = (int) (new \DateTimeImmutable($reference . "-01"))->format("t");
        $due = sprintf("%s-%02d", $reference, min(max($day, 1), $max));
        $issue = min(gmdate("Y-m-d"), $due);

        return (new FinanceService($this->unit_id, $this->actor_id, $this->login_user))->createReceivable([
            "source_type" => "barbecue_rental", "source_id" => $rental_id,
            "reference_month" => $reference, "description" => "Mensalista churrasqueira — " . $commercial["title"],
            "issue_date" => $issue, "due_date" => $due, "original_amount" => $total,
            "unit_amount" => $total, "quantity" => "1", "product_id" => (int) ($commercial["product_id"] ?? 0),
        ]);
    }

    private function commercialTotal(array $commercial): ?string
    {
        $base = $commercial["negotiated_amount"] ?? $commercial["list_amount"] ?? null;
        if ($base === null) { return null; }
        return $this->moneyTotal("1.000", (string) $base, (string) ($commercial["discount_amount"] ?? "0.00"));
    }

    private function totalWithExtra(?string $base_total, $extra_amount): ?string
    {
        if ($base_total === null) { return null; }
        $extra = DataNormalizationService::decimal($extra_amount ?? "0.00", 2);
        return $this->centsToDecimal($this->scaledInt($base_total, 2) + $this->scaledInt($extra, 2));
    }

    private function assertCustomerAndContact(int $customer, int $contact): void
    {
        if ($customer <= 0) { throw new \DomainException("gd_barbecue_customer_required"); }
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
        if ((string) $row->status !== "active" || !in_array((string) $row->product_type, Constants::COURT_RENTAL_PRODUCT_TYPES, true)) { throw new \DomainException("gd_barbecue_product_incompatible"); }
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
        $sequence->ensure($this->unit_id, "barbecue_rental", "CHU-" . gmdate("Y") . "-", 6, true);
        return $sequence->next($this->unit_id, "barbecue_rental");
    }

    private function insertRental(array $commercial, string $number, string $status): int
    {
        $data = $this->stamp($commercial + ["unit_id" => $this->unit_id, "rental_number" => $number, "status" => $status, "lock_version" => 1, "deleted" => 0], true);
        $id = (int) $this->rentals->ci_save($data);
        if ($id <= 0) { throw new \RuntimeException("barbecue rental insert"); }
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
        if ($id <= 0) { throw new \RuntimeException("barbecue rental link insert"); }
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
            $this->assertScheduleResourceType($booking_id, 0, Constants::BARBECUE_RESOURCE_TYPE);
            $b = $this->db->table($this->db->prefixTable("gd_bookings"))->select("id,customer_account_id,status")->where("id", $booking_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$b) { throw new \DomainException("gd_court_rental_booking_not_found"); }
            if (in_array((string) $b->status, ["cancelled", "expired"], true)) { throw new \DomainException("gd_court_rental_link_status_invalid"); }
            if ($b->customer_account_id !== null && (int) $b->customer_account_id !== (int) $rental->customer_account_id) { throw new \DomainException("gd_court_rental_link_customer_mismatch"); }
            if ($this->links->active_for_booking($booking_id, $this->unit_id)) { throw new \DomainException("gd_court_rental_already_linked"); }
        } else {
            $this->assertScheduleResourceType(0, $series_id, Constants::BARBECUE_RESOURCE_TYPE);
            $s = $this->db->table($this->db->prefixTable("gd_booking_series"))->select("id,customer_account_id,status")->where("id", $series_id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get(1)->getRow();
            if (!$s) { throw new \DomainException("gd_court_rental_series_not_found"); }
            if (in_array((string) $s->status, ["cancelled", "archived"], true)) { throw new \DomainException("gd_court_rental_link_status_invalid"); }
            if ($s->customer_account_id !== null && (int) $s->customer_account_id !== (int) $rental->customer_account_id) { throw new \DomainException("gd_court_rental_link_customer_mismatch"); }
            if ($this->links->active_for_series($series_id, $this->unit_id)) { throw new \DomainException("gd_court_rental_already_linked"); }
        }
    }

    private function assertScheduleResourceType(int $booking_id, int $series_id, string $resource_type): void
    {
        $bridge = $booking_id > 0 ? "gd_booking_resources" : "gd_booking_series_resources";
        $foreign = $booking_id > 0 ? "booking_id" : "series_id";
        $target = $booking_id > 0 ? $booking_id : $series_id;
        $table = $this->db->prefixTable($bridge);
        $resources = $this->db->prefixTable("gd_resources");
        $total = $this->db->table($table)->where("unit_id", $this->unit_id)->where($foreign, $target)->where("deleted", 0)->countAllResults();
        $matching = $this->db->table($table . " br")
            ->join($resources . " r", "r.id=br.resource_id AND r.unit_id=br.unit_id", "inner", false)
            ->where("br.unit_id", $this->unit_id)->where("br." . $foreign, $target)->where("br.deleted", 0)
            ->where("r.deleted", 0)->where("r.resource_type", $resource_type)->countAllResults();
        if ($total < 1 || $matching !== $total) { throw new \DomainException("gd_invalid_booking_resources"); }
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
        $ids = array_values(array_unique(array_map(static fn($r): int => (int) $r["resource_id"], $out)));
        $valid = $this->db->table($this->db->prefixTable("gd_resources"))
            ->select("id")->where("unit_id", $this->unit_id)->where("resource_type", Constants::BARBECUE_RESOURCE_TYPE)
            ->where("deleted", 0)->where("is_active", 1)->where("is_bookable", 1)->whereIn("id", $ids)
            ->countAllResults();
        if ($valid !== count($ids)) { throw new \DomainException("gd_invalid_booking_resources"); }
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

    /**
     * Resumo canônico da agenda no fuso da unidade.
     *
     * Para avulsa, converte o horário do booking de UTC para o fuso local usando
     * o TemporalService (sem substring de UTC). Para recorrente, usa os horários
     * locais já persistidos na série. Retorna também um `display` pronto para a
     * view, mantendo `local_time`/`weekdays`/`next_occurrence_utc` por compat.
     */
    private function scheduleSummary(object $rental, array $links): array
    {
        $kind = ((string) ($rental->rental_type ?? "")) === "recurring" ? "recurring" : "single";
        $resource_ids = []; $weekdays = []; $local_time = ""; $next_utc = null;
        $starts_at_local = null; $ends_at_local = null; $local_start_time = null; $local_end_time = null;
        foreach ($links as $link) {
            if ((int) ($link->deleted ?? 0) === 1 || (string) ($link->link_kind ?? "") === "historical") { continue; }
            if (!empty($link->series)) {
                $s = $link->series;
                foreach ($this->db->table($this->db->prefixTable("gd_booking_series_resources"))->select("resource_id")->where("series_id", $s->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get()->getResult() as $sr) { $resource_ids[] = (int) $sr->resource_id; }
                foreach (json_decode((string) $s->weekdays, true) ?: [] as $wd) { $weekdays[] = (int) $wd; }
                if ($local_start_time === null && $s->local_start_time) {
                    $local_start_time = substr((string) $s->local_start_time, 0, 5);
                    $local_end_time = substr((string) $s->local_end_time, 0, 5);
                    if ($local_time === "") { $local_time = $local_start_time . "–" . $local_end_time; }
                }
                $occ = $this->db->table($this->db->prefixTable("gd_bookings"))->select("MIN(starts_at_utc) AS n", false)->where("unit_id", $this->unit_id)->where("series_id", $s->id)->where("deleted", 0)->whereIn("status", Constants::BOOKING_BLOCKING_STATUSES)->where("starts_at_utc >=", gmdate("Y-m-d H:i:s"))->get(1)->getRow();
                if ($occ && $occ->n && ($next_utc === null || $occ->n < $next_utc)) { $next_utc = $occ->n; }
            } elseif (!empty($link->booking)) {
                $b = $link->booking;
                foreach ($this->db->table($this->db->prefixTable("gd_booking_resources"))->select("resource_id")->where("booking_id", $b->id)->where("unit_id", $this->unit_id)->where("deleted", 0)->get()->getResult() as $br) { $resource_ids[] = (int) $br->resource_id; }
                if ($starts_at_local === null) {
                    try {
                        $ls = $this->time()->utcToLocal((string) $b->starts_at_utc);
                        $le = $this->time()->utcToLocal((string) $b->ends_at_utc);
                        $starts_at_local = $ls->format("Y-m-d H:i:s");
                        $ends_at_local = $le->format("Y-m-d H:i:s");
                        if ($local_time === "") { $local_time = $ls->format("H:i") . "–" . $le->format("H:i"); }
                    } catch (\Throwable $e) { /* horário malformado: ignora, não vaza UTC como local */ }
                }
                if ($b->starts_at_utc >= gmdate("Y-m-d H:i:s") && ($next_utc === null || $b->starts_at_utc < $next_utc)) { $next_utc = $b->starts_at_utc; }
            }
        }
        $resource_ids = array_values(array_unique($resource_ids));
        $names = [];
        if ($resource_ids) {
            foreach ($this->db->table($this->db->prefixTable("gd_resources"))->select("code,name")->whereIn("id", $resource_ids)->where("unit_id", $this->unit_id)->where("resource_type", Constants::BARBECUE_RESOURCE_TYPE)->where("deleted", 0)->orderBy("code")->get()->getResult() as $r) { $names[] = $r->code . " — " . $r->name; }
        }
        sort($weekdays);
        $weekdays = array_values(array_unique($weekdays));
        $next_local = null;
        if ($next_utc !== null) { try { $next_local = $this->time()->utcToLocal((string) $next_utc)->format("Y-m-d H:i:s"); } catch (\Throwable $e) { $next_local = null; } }
        return [
            "kind" => $kind,
            "resource_names" => implode(", ", $names),
            "weekdays" => $weekdays,
            "local_time" => $local_time,
            "local_start_time" => $local_start_time,
            "local_end_time" => $local_end_time,
            "starts_at_local" => $starts_at_local,
            "ends_at_local" => $ends_at_local,
            "next_occurrence_utc" => $next_utc,
            "next_occurrence_local" => $next_local,
            "display" => $this->buildScheduleDisplay($kind, $weekdays, $local_time, $starts_at_local, $ends_at_local),
        ];
    }

    /** Texto pronto do horário (recorrente: dias · hora; avulsa: data/hora local). */
    private function buildScheduleDisplay(string $kind, array $weekdays, string $local_time, ?string $starts_at_local, ?string $ends_at_local): string
    {
        if ($kind === "recurring" && $weekdays) {
            $labels = array_map(static fn($d) => app_lang("gd_weekday_short_" . (int) $d), $weekdays);
            return implode(", ", $labels) . ($local_time !== "" ? " · " . $local_time : "");
        }
        if ($kind === "single" && $starts_at_local) {
            try {
                $s = new \DateTimeImmutable($starts_at_local);
                $e = $ends_at_local ? new \DateTimeImmutable($ends_at_local) : null;
                $same_day = $e && $s->format("Y-m-d") === $e->format("Y-m-d");
                return $s->format("d/m/Y H:i") . ($e ? "–" . ($same_day ? $e->format("H:i") : $e->format("d/m/Y H:i")) : "");
            } catch (\Throwable $e) { return $local_time; }
        }
        return $local_time;
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
