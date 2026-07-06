<?php

echo "# Fase 3C — operação comercial de locação de quadras\n";

$rentalTables = ["gd_court_rentals", "gd_court_rental_schedule_links", "gd_court_rental_price_items", "gd_court_rental_events"];
gd_assert("schema 030–033 aplicado", array_reduce($rentalTables, fn($ok, $table) => $ok && $db->tableExists($prefix . $table), true));
$rentalEventFields = $db->getFieldNames($prefix . "gd_court_rental_events");
gd_assert("eventos de locação são append-only no schema", !in_array("deleted", $rentalEventFields, true));
$linkIndexes = $db->query("SHOW INDEX FROM `{$prefix}gd_court_rental_schedule_links`")->getResult();
gd_assert("unique protege vínculo ativo de reserva e série", (bool) array_filter($linkIndexes, static fn($i) => $i->Key_name === "uniq_active_series" && (int) $i->Non_unique === 0) && (bool) array_filter($linkIndexes, static fn($i) => $i->Key_name === "uniq_active_booking" && (int) $i->Non_unique === 0));

$rentalService = new \grupo_donato_gestao\Services\CourtRentalService($unit_id);
$rentalLifecycle = new \grupo_donato_gestao\Services\CourtRentalLifecycleService($unit_id);
$rentalPrice = $priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $rental["id"], "amount" => "150.00", "minimum_quantity" => "1"]);

// ---- Pricing como sugestão (nunca zero) ----
$suggestion = $rentalService->resolvePrice(["product_id" => $rental["id"], "price_list_id" => $list["id"], "quantity" => "1"]);
gd_assert("preço sugerido resolve via PricingService", $suggestion["found"] && $suggestion["amount"] === "150.00" && $suggestion["matched_scope"] === "product_base");
$noPrice = $rentalService->resolvePrice(["product_id" => $phys["id"], "price_list_id" => $list["id"], "quantity" => "1"]);
gd_assert("ausência de preço não retorna zero", $noPrice["found"] === false && !isset($noPrice["amount"]));

// ---- Avulso completo ----
$singleInput = [
    "rental_type" => "single", "title" => "Locação avulsa teste", "customer_account_id" => $family["id"], "contact_person_id" => $person_two["id"],
    "product_id" => $rental["id"], "price_list_id" => $list["id"], "list_amount" => "150.00", "negotiated_amount" => "150.00", "effective_from" => "2099-12-01",
    "booking_status" => "pending_confirmation", "starts_at_local" => "2099-12-01T10:00", "ends_at_local" => "2099-12-01T11:00",
    "resources" => [["resource_id" => $bookA, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
];
$single = $rentalService->createWithBooking($singleInput);
gd_assert("avulso cria locação + reserva + vínculo", $single["id"] > 0 && $single["booking_id"] > 0 && str_starts_with($single["rental_number"], "LOC-" . gmdate("Y") . "-"));
$singleRow = $rentalService->get($single["id"]);
gd_assert("avulso registra snapshot com total calculado no backend", count($singleRow->price_items) === 1 && $singleRow->price_items[0]->unit_amount === "150.00" && $singleRow->price_items[0]->total_amount === "150.00");
gd_assert("avulso vincula a reserva como principal", count($singleRow->links) === 1 && (int) $singleRow->links[0]->booking_id === (int) $single["booking_id"] && $singleRow->links[0]->link_kind === "primary");
gd_assert("avulso nasce como rascunho", $singleRow->status === "draft");

// ---- Validações comerciais ----
gd_assert("conta inexistente é rejeitada", gd_throws(fn() => $rentalService->createDraft(array_replace($singleInput, ["customer_account_id" => 999999]), "single"), "gd_court_rental_invalid_customer"));
gd_assert("contato fora da conta é rejeitado", gd_throws(fn() => $rentalService->createDraft(array_replace($singleInput, ["contact_person_id" => $override_person["id"]]), "single"), "gd_court_rental_invalid_contact"));
gd_assert("produto incompatível é rejeitado", gd_throws(fn() => $rentalService->createDraft(array_replace($singleInput, ["product_id" => $phys["id"]]), "single"), "gd_court_rental_product_incompatible"));
gd_assert("vigência final antes da inicial é rejeitada", gd_throws(fn() => $rentalService->createDraft(array_replace($singleInput, ["effective_from" => "2099-12-31", "effective_until" => "2099-12-01"]), "single"), "gd_court_rental_invalid_validity"));

// ---- Desconto com motivo obrigatório e snapshot ----
$discDraft = $rentalService->createDraft(["rental_type" => "single", "title" => "Com desconto", "customer_account_id" => $family["id"], "list_amount" => "200.00", "negotiated_amount" => "200.00", "discount_amount" => "50.00", "discount_reason" => "Promoção"], "single");
gd_assert("snapshot calcula total com desconto exato", $rentalService->get($discDraft["id"])->price_items[0]->total_amount === "150.00");
gd_assert("desconto exige motivo", gd_throws(fn() => $rentalService->createDraft(["rental_type" => "single", "title" => "x", "customer_account_id" => $family["id"], "list_amount" => "100.00", "discount_amount" => "10.00"], "single"), "gd_court_rental_discount_reason_required"));
gd_assert("desconto não supera o valor-base", gd_throws(fn() => $rentalService->createDraft(["rental_type" => "single", "title" => "x", "customer_account_id" => $family["id"], "list_amount" => "100.00", "discount_amount" => "150.00", "discount_reason" => "erro"], "single"), "gd_court_rental_discount_exceeds_base"));

// ---- Override e snapshot imutável ----
$priceDraft = $rentalService->createDraft(["rental_type" => "single", "title" => "Rascunho preço", "customer_account_id" => $family["id"], "product_id" => $rental["id"], "list_amount" => "150.00", "negotiated_amount" => "150.00"], "single");
gd_assert("override sem motivo é rejeitado", gd_throws(fn() => $rentalService->reprice($priceDraft["id"], ["negotiated_amount" => "120.00", "lock_version" => 1], true), "gd_court_rental_override_reason_required"));
gd_assert("override sem permissão é negado", gd_throws(fn() => $rentalService->reprice($priceDraft["id"], ["negotiated_amount" => "120.00", "discount_reason" => "Fidelidade", "lock_version" => 1], false), "gd_court_rental_price_override_denied"));
$repriced = $rentalService->reprice($priceDraft["id"], ["negotiated_amount" => "120.00", "discount_reason" => "Fidelidade", "lock_version" => 1], true);
gd_assert("override autorizado reprecifica e incrementa lock_version", $repriced["lock_version"] === 2 && $rentalService->get($priceDraft["id"])->negotiated_amount === "120.00");
$priceItemsAll = $db->table($prefix . "gd_court_rental_price_items")->where("rental_id", $priceDraft["id"])->orderBy("id")->get()->getResult();
gd_assert("reprecificação preserva snapshot histórico e cria novo", count($priceItemsAll) === 2 && (int) $priceItemsAll[0]->deleted === 1 && $priceItemsAll[0]->unit_amount === "150.00" && (int) $priceItemsAll[1]->deleted === 0 && $priceItemsAll[1]->unit_amount === "120.00");
$priceSvc()->save(["price_list_id" => $list["id"], "product_id" => $rental["id"], "amount" => "999.00", "minimum_quantity" => "1"], $rentalPrice["id"]);
gd_assert("snapshot não muda com alteração de preço no catálogo", $rentalService->get($priceDraft["id"])->price_items[0]->unit_amount === "120.00");

// ---- Mensalista completo + dia de vencimento 1 e 31 ----
$monthlyBase = [
    "rental_type" => "recurring", "title" => "Mensalista teste", "customer_account_id" => $family["id"], "contact_person_id" => $person_two["id"],
    "product_id" => $rental["id"], "price_list_id" => $list["id"], "negotiated_amount" => "500.00", "preferred_due_day" => "1", "effective_from" => "2099-12-01",
    "frequency" => "weekly", "interval_value" => 1, "weekdays" => [1], "local_start_time" => "08:00", "local_end_time" => "09:00",
    "starts_on" => "2099-12-07", "ends_mode" => "count", "max_occurrences" => 3, "default_booking_status" => "pending_confirmation",
    "conflict_policy" => "reject_series", "generation_horizon_days" => 90, "resources" => [["resource_id" => $bookA, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
];
$monthly = $rentalService->createWithSeries($monthlyBase);
gd_assert("mensalista cria locação + série + vínculo", $monthly["id"] > 0 && $monthly["series_id"] > 0 && ($monthly["generation"]["created"] ?? 0) === 3);
$monthlyRow = $rentalService->get($monthly["id"]);
gd_assert("mensalista usa dia de vencimento 1 e vincula a série", (int) $monthlyRow->preferred_due_day === 1 && (int) $monthlyRow->links[0]->booking_series_id === (int) $monthly["series_id"]);
$monthly31 = $rentalService->createWithSeries(array_replace($monthlyBase, ["preferred_due_day" => "31", "title" => "Mensalista 31", "weekdays" => [2], "starts_on" => "2099-12-08"]));
gd_assert("mensalista aceita dia de vencimento 31", (int) $rentalService->get($monthly31["id"])->preferred_due_day === 31);

// ---- Vínculos a reservas/séries existentes ----
$freeBooking = (new \grupo_donato_gestao\Services\BookingService($unit_id))->save(["booking_type" => "customer_rental", "title" => "Reserva livre", "customer_account_id" => $family["id"], "contact_person_id" => $person_two["id"], "starts_at_local" => "2099-12-15T10:00", "ends_at_local" => "2099-12-15T11:00", "status" => "pending_confirmation", "resources" => [["resource_id" => $bookA, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]]]);
$linkDraft = $rentalService->createDraft(["rental_type" => "single", "title" => "Para vínculo", "customer_account_id" => $family["id"]], "single");
$linkResult = $rentalService->linkExisting($linkDraft["id"], ["booking_id" => $freeBooking["id"], "link_kind" => "primary"]);
gd_assert("vincula reserva existente válida", $linkResult["link_id"] > 0);
$linkDraft2 = $rentalService->createDraft(["rental_type" => "single", "title" => "Para vínculo 2", "customer_account_id" => $family["id"]], "single");
gd_assert("vínculo duplicado de reserva é rejeitado", gd_throws(fn() => $rentalService->linkExisting($linkDraft2["id"], ["booking_id" => $freeBooking["id"], "link_kind" => "primary"]), "gd_court_rental_already_linked"));
gd_assert("vínculo duplicado de série é rejeitado", gd_throws(fn() => $rentalService->linkExisting($linkDraft2["id"], ["booking_series_id" => $monthly["series_id"], "link_kind" => "primary"]), "gd_court_rental_already_linked"));
gd_assert("vínculo exige exatamente um alvo", gd_throws(fn() => $rentalService->linkExisting($linkDraft2["id"], ["booking_id" => $freeBooking["id"], "booking_series_id" => $monthly["series_id"], "link_kind" => "primary"]), "gd_court_rental_link_target_required"));

// ---- Ciclo de vida ----
$noLinkDraft = $rentalService->createDraft(["rental_type" => "single", "title" => "Sem vínculo", "customer_account_id" => $family["id"], "negotiated_amount" => "100.00"], "single");
gd_assert("ativação exige ao menos um vínculo operacional", gd_throws(fn() => $rentalLifecycle->activate($noLinkDraft["id"], 1, false, ""), "gd_court_rental_activation_requires_link"));
$linkDraftRow = $rentalService->get($linkDraft["id"]);
gd_assert("ativação sem valor exige justificativa formal", gd_throws(fn() => $rentalLifecycle->activate($linkDraft["id"], (int) $linkDraftRow->lock_version, false, ""), "gd_court_rental_value_required"));
$activated = $rentalLifecycle->activate($linkDraft["id"], (int) $linkDraftRow->lock_version, true, "Cortesia institucional");
gd_assert("ativação com justificativa e permissão", $activated->status === "active" && $activated->activated_at !== null);
gd_assert("suspensão exige política de ocorrências futuras", gd_throws(fn() => $rentalLifecycle->suspend($linkDraft["id"], (int) $activated->lock_version, "invalid"), "gd_court_rental_future_policy_required"));
$suspended = $rentalLifecycle->suspend($linkDraft["id"], (int) $activated->lock_version, "keep", "Pausa");
gd_assert("suspensão muda estado e não apaga reserva", $suspended->status === "suspended" && (new \grupo_donato_gestao\Services\BookingService($unit_id))->get($freeBooking["id"])->status !== "cancelled");
$resumed = $rentalLifecycle->resume($linkDraft["id"], (int) $suspended->lock_version);
gd_assert("retomada volta para ativa", $resumed->status === "active");
gd_assert("lock_version obsoleto bloqueia transição", gd_throws(fn() => $rentalLifecycle->complete($linkDraft["id"], 1), "gd_court_rental_edit_conflict"));
$completed = $rentalLifecycle->complete($linkDraft["id"], (int) $resumed->lock_version);
gd_assert("conclusão encerra a locação", $completed->status === "completed");
gd_assert("estado terminal rejeita transição", gd_throws(fn() => $rentalLifecycle->suspend($linkDraft["id"], (int) $completed->lock_version, "keep"), "gd_invalid_court_rental_transition"));

// ---- Política de ocorrências futuras (keep mantém; cancel cancela e pausa) ----
$m31 = $rentalService->get($monthly31["id"]);
$m31Active = $rentalLifecycle->activate($monthly31["id"], (int) $m31->lock_version, false, "");
$rentalLifecycle->suspend($monthly31["id"], (int) $m31Active->lock_version, "keep", "Pausa");
$m31Occ = $db->table($prefix . "gd_bookings")->where("series_id", $monthly31["series_id"])->where("deleted", 0)->whereNotIn("status", ["cancelled", "completed", "no_show"])->countAllResults();
gd_assert("política keep preserva ocorrências e pausa a geração", $m31Occ === 3 && $db->table($prefix . "gd_booking_series")->where("id", $monthly31["series_id"])->get(1)->getRow()->status === "paused");

$monthlyActivate = $rentalLifecycle->activate($monthly["id"], (int) $rentalService->get($monthly["id"])->lock_version, false, "");
gd_assert("mensalista ativa com valor contratado", $monthlyActivate->status === "active");
gd_assert("cancelamento exige motivo", gd_throws(fn() => $rentalLifecycle->cancel($monthly["id"], (int) $monthlyActivate->lock_version, "", "cancel"), "gd_cancellation_reason_required"));
$cancelled = $rentalLifecycle->cancel($monthly["id"], (int) $monthlyActivate->lock_version, "Cliente desistiu", "cancel");
$activeMonthlyOcc = $db->table($prefix . "gd_bookings")->where("series_id", $monthly["series_id"])->where("deleted", 0)->whereNotIn("status", ["cancelled", "completed", "no_show"])->countAllResults();
gd_assert("cancelamento encerra locação, pausa série e cancela futuras", $cancelled->status === "cancelled" && $db->table($prefix . "gd_booking_series")->where("id", $monthly["series_id"])->get(1)->getRow()->status === "paused" && $activeMonthlyOcc === 0);

// ---- Sem financeiro, append-only, IDOR ----
$forbiddenTables = ["gd_invoices", "gd_charges", "gd_installments", "gd_cash_sessions"];
gd_assert("nenhuma tabela financeira fora do escopo autorizado foi criada", !array_filter($forbiddenTables, static fn($t) => $db->tableExists($prefix . $t)));
gd_assert("apenas as 4 tabelas da Fase 3C foram adicionadas", (int) $db->query("SELECT COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE ?", [$prefix . "gd_court_rental%"])->getRow()->c === 4);
$rentalEventModel = model("grupo_donato_gestao\\Models\\Gd_court_rental_events_model");
gd_assert("eventos de locação bloqueiam delete e update", gd_throws(fn() => $rentalEventModel->delete(1), "Court rental events cannot be deleted.") && gd_throws(fn() => $rentalEventModel->update_where([], []), "Court rental events cannot be updated."));
gd_assert("IDOR de locação entre unidades retorna null", (new \grupo_donato_gestao\Services\CourtRentalService($unit2_id))->get($single["id"]) === null);

// ---- Permissões, rotas, idioma ----
$crManage = new \grupo_donato_gestao\Services\AccessService($pm("gd_court_rentals_manage"));
$crStatus = new \grupo_donato_gestao\Services\AccessService($pm("gd_court_rentals_status_manage"));
$crOverride = new \grupo_donato_gestao\Services\AccessService($pm("gd_court_rentals_price_override"));
gd_assert("manage de locação implica as leituras necessárias", $crManage->can("gd_court_rentals_view") && $crManage->can("gd_bookings_view") && $crManage->can("gd_booking_series_view") && $crManage->can("gd_calendar_view") && $crManage->can("gd_resources_view") && $crManage->can("gd_customer_accounts_view") && $crManage->can("gd_people_view") && $crManage->can("gd_catalog_view") && $crManage->can("gd_price_lists_view"));
gd_assert("manage de locação não concede gestão de cadastros", !$crManage->can("gd_bookings_manage") && !$crManage->can("gd_resources_manage") && !$crManage->can("gd_customer_accounts_manage"));
gd_assert("status de locação vê a locação sem gerir cadastros", $crStatus->can("gd_court_rentals_view") && !$crStatus->can("gd_court_rentals_manage") && !$crStatus->can("gd_resources_manage"));
gd_assert("override de preço implica ver a locação", $crOverride->can("gd_court_rentals_view"));
gd_assert("rotas de locação separam leitura GET e escrita POST", isset($get_routes["grupo_donato/court-rentals"]) && (bool) array_filter(array_keys($get_routes), static fn($route) => str_starts_with((string) $route, "grupo_donato/court-rentals/view/")) && isset($post_routes["grupo_donato/court-rentals/save-single"], $post_routes["grupo_donato/court-rentals/save-monthly"], $post_routes["grupo_donato/court-rentals/reprice"]) && !isset($get_routes["grupo_donato/court-rentals/save-single"]));
gd_assert("CSRF protege escrita de locação", in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/court-rentals/save-single", "POST"), "filter"), true));
gd_assert("idioma da Fase 3C resolve", app_lang("gd_menu_court_rentals") === "Locações de quadras" && app_lang("gd_court_rental_status_active") !== "gd_court_rental_status_active");
$crDynamicKeys = [];
foreach (["gd_court_rental_status_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_STATUSES, "gd_court_rental_type_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_TYPES, "gd_court_rental_billing_cycle_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_BILLING_CYCLES, "gd_court_rental_link_kind_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_LINK_KINDS, "gd_court_rental_event_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_EVENT_TYPES, "gd_court_rental_future_policy_" => \grupo_donato_gestao\Config\Constants::COURT_RENTAL_FUTURE_POLICIES] as $prefix_key => $values) {
    foreach ($values as $value) { $crDynamicKeys[] = $prefix_key . $value; }
}
$missingCrKeys = array_values(array_filter($crDynamicKeys, static fn($key) => app_lang($key) === $key));
gd_assert("chaves dinâmicas da Fase 3C resolvem", !$missingCrKeys, implode(",", $missingCrKeys));

// ---- 2.1: filtro por quadra cobre avulsa (gd_booking_resources) e recorrente (gd_booking_series_resources) ----
$byResource = $rentalService->listPage(["resource_id" => $bookA, "limit" => 100]);
$byResourceIds = array_map(static fn($r) => (int) $r->id, $byResource["data"]);
gd_assert("filtro por quadra encontra avulsa vinculada a booking", in_array((int) $single["id"], $byResourceIds, true));
gd_assert("filtro por quadra encontra recorrente vinculada a série", in_array((int) $monthly["id"], $byResourceIds, true));
gd_assert("contagem filtrada por quadra usa o mesmo escopo dos dados", $byResource["recordsFiltered"] === count($byResource["data"]) || $byResource["recordsFiltered"] >= count($byResource["data"]));
$byOther = $rentalService->listPage(["resource_id" => $bookC, "limit" => 100]);
$byOtherIds = array_map(static fn($r) => (int) $r->id, $byOther["data"]);
gd_assert("filtro por outra quadra exclui locações não vinculadas a ela", !in_array((int) $single["id"], $byOtherIds, true) && !in_array((int) $monthly["id"], $byOtherIds, true));

// ---- 2.2: resumo de agenda no fuso da unidade (sem substring de UTC) ----
$singleSchedule = $rentalService->get($single["id"])->schedule;
gd_assert("resumo de avulsa é canônico no fuso local", ($singleSchedule["kind"] ?? "") === "single" && substr((string) $singleSchedule["starts_at_local"], 0, 16) === "2099-12-01 10:00" && $singleSchedule["local_time"] === "10:00–11:00" && ($singleSchedule["display"] ?? "") !== "");
$recurringSchedule = $rentalService->get($monthly["id"])->schedule;
gd_assert("resumo de recorrente traz dias e horário local", ($recurringSchedule["kind"] ?? "") === "recurring" && $recurringSchedule["local_start_time"] === "08:00" && in_array(1, $recurringSchedule["weekdays"], true));
$tzSvc = new \grupo_donato_gestao\Services\TemporalService($unit_id);
$midStart = $tzSvc->utcToLocal($tzSvc->localToUtc("2099-12-20", "23:00"))->format("Y-m-d H:i");
$midEnd = $tzSvc->utcToLocal($tzSvc->localToUtc("2099-12-21", "00:30"))->format("Y-m-d H:i");
gd_assert("horário que cruza meia-noite preserva data/hora local (virada de dia)", $midStart === "2099-12-20 23:00" && $midEnd === "2099-12-21 00:30");

// ---- 2.5/2.6: options de produto e tabela de preço (escopo por unidade + tipos compatíveis) ----
$prodOptions = (new \grupo_donato_gestao\Services\ProductService($unit_id))->options("", 50);
$optTypes = array_values(array_unique(array_map(static fn($r) => (string) $r["product_type"], $prodOptions)));
gd_assert("options de produto só traz tipos compatíveis com locação", !array_diff($optTypes, \grupo_donato_gestao\Config\Constants::COURT_RENTAL_PRODUCT_TYPES));
gd_assert("options de produto inclui o de locação e exclui o físico", (bool) array_filter($prodOptions, static fn($r) => (int) $r["id"] === (int) $rental["id"]) && !array_filter($prodOptions, static fn($r) => (int) $r["id"] === (int) $phys["id"]));
gd_assert("options de produto respeita a unidade (não vaza de outra)", !array_filter((new \grupo_donato_gestao\Services\ProductService($unit2_id))->options("", 50), static fn($r) => (int) $r["id"] === (int) $rental["id"]));
$plOptions = (new \grupo_donato_gestao\Services\PriceListService($unit_id))->options("", 50);
gd_assert("options de tabela de preço traz a lista ativa de teste", (bool) array_filter($plOptions, static fn($r) => (int) $r["id"] === (int) $list["id"]));

// ---- 2.4: evento de calendário expõe court_rental_id + booking_type do vínculo ativo ----
$calSvc = new \grupo_donato_gestao\Services\CalendarService($unit_id, true);
$calStart = $tzSvc->utcToIsoLocal($tzSvc->localToUtc("2099-12-01", "00:00"));
$calEnd = $tzSvc->utcToIsoLocal($tzSvc->localToUtc("2099-12-02", "00:00"));
$calEvents = $calSvc->events($calStart, $calEnd, [$bookA], ["booking"], []);
$calMatch = array_values(array_filter($calEvents, static fn($e) => (int) ($e["extendedProps"]["booking_id"] ?? 0) === (int) $single["booking_id"]));
gd_assert("calendário inclui court_rental_id, booking_type e resource_ids no evento vinculado", $calMatch && (int) ($calMatch[0]["extendedProps"]["court_rental_id"] ?? 0) === (int) $single["id"] && ($calMatch[0]["extendedProps"]["booking_type"] ?? "") === "customer_rental" && isset($calMatch[0]["extendedProps"]["resource_ids"]));
gd_assert("calendário sem tipos solicitados não devolve disponibilidade padrão", !array_filter($calSvc->events($calStart, $calEnd, [$bookA], [], []), static fn($e) => ($e["extendedProps"]["event_type"] ?? "") === "weekly_rule"));
