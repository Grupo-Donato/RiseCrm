<?php

echo "# Fase 3D - operacao comercial de churrasqueiras\n";

$barbecueTables = [
    "gd_barbecue_rentals",
    "gd_barbecue_rental_schedule_links",
    "gd_barbecue_rental_price_items",
    "gd_barbecue_rental_events",
];
gd_assert("schema 053 de churrasqueiras aplicado", array_reduce($barbecueTables, fn($ok, $table) => $ok && $db->tableExists($prefix . $table), true));
$barbecueResourceType = \grupo_donato_gestao\Config\Constants::BARBECUE_RESOURCE_TYPE;
$barbecueResources = $db->table($prefix . "gd_resources")
    ->select("id,code,name,resource_type")
    ->where("unit_id", $unit_id)
    ->where("resource_type", $barbecueResourceType)
    ->where("deleted", 0)
    ->where("is_active", 1)
    ->where("is_bookable", 1)
    ->orderBy("code")
    ->get()
    ->getResult();
$barbecueIds = array_map(static fn($row): int => (int) $row->id, $barbecueResources);
$barbecueCodes = array_map(static fn($row): string => (string) $row->code, $barbecueResources);
gd_assert("recursos CH1-CH6 pertencem ao tipo barbecue_area", $barbecueCodes === ["CH1", "CH2", "CH3", "CH4", "CH5", "CH6"] && count($barbecueResources) === 6);
gd_assert("seis churrasqueiras sao reservaveis", count($barbecueIds) === 6 && !array_filter($barbecueResources, static fn($row): bool => $row->resource_type !== $barbecueResourceType));

$bookingService = new \grupo_donato_gestao\Services\BookingService($unit_id);
$courtResources = $bookingService->bookableResources(\grupo_donato_gestao\Config\Constants::COURT_RESOURCE_TYPE);
$areaResources = $bookingService->bookableResources($barbecueResourceType);
gd_assert("selecao de quadras nao expoe churrasqueiras", !array_filter($courtResources, static fn($row): bool => ($row["resource_type"] ?? "") === $barbecueResourceType));
gd_assert("selecao de churrasqueiras nao expoe quadras", !array_filter($areaResources, static fn($row): bool => ($row["resource_type"] ?? "") === \grupo_donato_gestao\Config\Constants::COURT_RESOURCE_TYPE));

$barbecueService = new \grupo_donato_gestao\Services\BarbecueRentalService($unit_id);
$barbecueCatalogPrice = $priceSvc()->save([
    "price_list_id" => $list["id"], "product_id" => $rental["id"], "resource_id" => $barbecueIds[0],
    "amount" => "180.00", "minimum_quantity" => "1",
]);
$barbecuePrice = $barbecueService->resolvePrice([
    "product_id" => $rental["id"],
    "price_list_id" => $list["id"],
    "resource_id" => $barbecueIds[0] ?? 0,
    "quantity" => "1",
]);
gd_assert("preco de churrasqueira resolve pela mesma tabela", !empty($barbecueCatalogPrice["saved"]) && !empty($barbecuePrice["found"]) && ($barbecuePrice["amount"] ?? "") === "180.00");
gd_assert("preco de churrasqueira rejeita ID de quadra", gd_throws(fn() => $barbecueService->resolvePrice([
    "product_id" => $rental["id"], "price_list_id" => $list["id"], "resource_id" => $bookA,
]), "gd_invalid_booking_resources"));

$singleBarbecueInput = [
    "rental_type" => "single",
    "title" => "Churrasqueira avulsa teste",
    "customer_account_id" => $family["id"],
    "contact_person_id" => $person_two["id"],
    "product_id" => $rental["id"],
    "price_list_id" => $list["id"],
    "list_amount" => "150.00",
    "negotiated_amount" => "150.00",
    "effective_from" => "2099-12-18",
    "booking_status" => "pending_confirmation",
    "starts_at_local" => "2099-12-18T10:00",
    "ends_at_local" => "2099-12-18T11:00",
    "resources" => [["resource_id" => $barbecueIds[0], "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
];
$singleBarbecue = $barbecueService->createWithBooking($singleBarbecueInput);
$singleBarbecueRow = $barbecueService->get($singleBarbecue["id"]);
gd_assert("avulso cria reserva, locacao e vinculo de churrasqueira", $singleBarbecue["id"] > 0 && $singleBarbecue["booking_id"] > 0 && count($singleBarbecueRow->links) === 1);
gd_assert("avulso de churrasqueira usa somente o recurso CH", ($singleBarbecueRow->schedule["resource_names"] ?? "") !== "" && strpos((string) $singleBarbecueRow->schedule["resource_names"], "CH1") === 0);
$singleSourceCount = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "barbecue_rental")->where("source_id", (int) $singleBarbecue["id"])->where("deleted", 0)->countAllResults();
gd_assert("financeiro da churrasqueira usa source_type proprio", $singleSourceCount === 1);

$singleBeforeEdit = $barbecueService->get($singleBarbecue["id"]);
$singleBookingBeforeEdit = $bookingService->get($singleBarbecue["booking_id"]);
$singleEditedResult = $barbecueService->updateSingle($singleBarbecue["id"], array_replace($singleBarbecueInput, [
    "title" => "Churrasqueira avulsa editada",
    "list_amount" => "160.00",
    "negotiated_amount" => "160.00",
    "starts_at_local" => "2099-12-18T12:00",
    "ends_at_local" => "2099-12-18T13:00",
    "resources" => [["resource_id" => $barbecueIds[1], "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
    "lock_version" => (int) $singleBeforeEdit->lock_version,
    "booking_lock_version" => (int) $singleBookingBeforeEdit->lock_version,
]));
$singleAfterEdit = $barbecueService->get($singleBarbecue["id"]);
$singleBookingAfterEdit = $bookingService->get($singleBarbecue["booking_id"]);
$singleEditFinance = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->summary([
    "source_type" => "barbecue_rental", "source_id" => (int) $singleBarbecue["id"],
]);
gd_assert("edicao de churrasqueira altera horario, recurso e valor", $singleEditedResult["lock_version"] === 2 && $singleAfterEdit->schedule["starts_at_local"] === "2099-12-18 12:00:00" && (int) $singleBookingAfterEdit->resources[0]->resource_id === $barbecueIds[1] && $singleEditFinance["total"] === "160.00");

$singleExtra = $barbecueService->registerExtraTime($singleBarbecue["id"], [
    "extra_time_minutes" => "30",
    "extra_time_amount" => "15,00",
    "extra_time_notes" => "Cliente permaneceu mais 30 minutos na churrasqueira.",
    "lock_version" => (int) $singleEditedResult["lock_version"],
]);
$singleExtraRow = $barbecueService->get($singleBarbecue["id"]);
$singleExtraFinance = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->summary([
    "source_type" => "barbecue_rental", "source_id" => (int) $singleBarbecue["id"],
]);
gd_assert("acrescimo de churrasqueira entra na cobranca", $singleExtra["lock_version"] === 3 && (int) $singleExtraRow->extra_time_minutes === 30 && $singleExtraRow->extra_time_amount === "15.00" && $singleExtraFinance["total"] === "175.00");
if (!isset($depositAccount) || !$depositAccount) {
    $depositAccount = $db->table($prefix . "gd_financial_accounts")
        ->where("unit_id", $unit_id)->where("status", "active")->where("deleted", 0)->orderBy("id")->get(1)->getRow();
}
$singlePayment = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->registerPayment([
    "amount" => "175.00",
    "payment_date" => gmdate("Y-m-d"),
    "payment_method" => "pix",
    "financial_account_id" => (int) ($depositAccount->id ?? 0),
    "allocations" => [$singleExtraFinance["receivables"][0]->id => "175.00"],
]);
$singlePaid = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->summary([
    "source_type" => "barbecue_rental", "source_id" => (int) $singleBarbecue["id"],
]);
$singleCashIn = $db->table($prefix . "gd_cash_movements")
    ->where("unit_id", $unit_id)->where("source_type", "payment")->where("source_id", (int) $singlePayment["id"])
    ->where("movement_type", "in")->where("amount", "175.00")->countAllResults();
gd_assert("baixa da churrasqueira muda status para pago e vira entrada", $singlePaid["status"] === "paid" && $singlePaid["balance"] === "0.00" && $singleCashIn === 1);

$monthlyBarbecue = $barbecueService->createWithSeries([
    "rental_type" => "recurring",
    "title" => "Mensalista churrasqueira teste",
    "customer_account_id" => $family["id"],
    "contact_person_id" => $person_two["id"],
    "product_id" => $rental["id"],
    "price_list_id" => $list["id"],
    "list_amount" => "300.00",
    "negotiated_amount" => "300.00",
    "preferred_due_day" => "10",
    "effective_from" => "2099-12-01",
    "starts_on" => "2099-12-01",
    "frequency" => "weekly",
    "interval_value" => 1,
    "weekdays" => [2],
    "local_start_time" => "18:00",
    "local_end_time" => "20:00",
    "ends_mode" => "count",
    "max_occurrences" => 3,
    "default_booking_status" => "pending_confirmation",
    "conflict_policy" => "reject_series",
    "resources" => [["resource_id" => $barbecueIds[2], "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
]);
$monthlyBarbecueRow = $barbecueService->get($monthlyBarbecue["id"]);
gd_assert("mensalista cria serie e vinculo de churrasqueira", $monthlyBarbecue["id"] > 0 && $monthlyBarbecue["series_id"] > 0 && count($monthlyBarbecueRow->links) === 1);
$monthlyActivated = (new \grupo_donato_gestao\Services\BarbecueRentalLifecycleService($unit_id))->activate($monthlyBarbecue["id"], (int) $monthlyBarbecueRow->lock_version);
$monthlyExtra = $barbecueService->registerExtraTime($monthlyBarbecue["id"], [
    "extra_time_minutes" => "60",
    "extra_time_amount" => "25.00",
    "extra_time_notes" => "Mensalista ficou mais 1 hora.",
    "lock_version" => (int) $monthlyActivated->lock_version,
]);
$generator = new \grupo_donato_gestao\Services\ReceivableGenerationService($unit_id);
$monthlyPreview = array_values(array_filter($generator->preview("2099-12"), static fn($row) => ($row["key"] ?? "") === "barbecue_rental:" . $monthlyBarbecue["id"]));
gd_assert("acrescimo tambem funciona para mensalista de churrasqueira", $monthlyExtra["lock_version"] === (int) $monthlyActivated->lock_version + 1 && count($monthlyPreview) === 1 && $monthlyPreview[0]["amount"] === "325.00" && $monthlyPreview[0]["notes"] === "Mensalista ficou mais 1 hora.");
$generator->generateMonth("2099-12");
$monthlyReceivable = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "barbecue_rental")->where("source_id", (int) $monthlyBarbecue["id"])
    ->where("reference_month", "2099-12")->where("deleted", 0)->get(1)->getRow();
$monthlyFinance = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->summary([
    "source_type" => "barbecue_rental", "source_id" => (int) $monthlyBarbecue["id"],
]);
gd_assert("mensalista gera cobranca propria com valor adicional", $monthlyReceivable && $monthlyReceivable->original_amount === "325.00" && $monthlyReceivable->status === "open" && $monthlyFinance["total"] === "325.00");
$monthlyPayment = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->registerPayment([
    "amount" => "325.00",
    "payment_date" => gmdate("Y-m-d"),
    "payment_method" => "pix",
    "financial_account_id" => (int) ($depositAccount->id ?? 0),
    "allocations" => [(int) $monthlyReceivable->id => "325.00"],
]);
$monthlyPaid = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->summary([
    "source_type" => "barbecue_rental", "source_id" => (int) $monthlyBarbecue["id"],
]);
gd_assert("baixa do mensalista muda status para pago", $monthlyPayment["id"] > 0 && $monthlyPaid["status"] === "paid" && $monthlyPaid["balance"] === "0.00");

$barbecueCalendar = new \grupo_donato_gestao\Services\CalendarService($unit_id, true, $barbecueResourceType);
$calendarStart = $tzSvc->utcToIsoLocal($tzSvc->localToUtc("2099-12-18", "00:00"));
$calendarEnd = $tzSvc->utcToIsoLocal($tzSvc->localToUtc("2099-12-19", "00:00"));
$barbecueEvents = $barbecueCalendar->events($calendarStart, $calendarEnd, [$barbecueIds[1]], ["booking"], []);
$barbecueMatch = array_values(array_filter($barbecueEvents, static fn($event): bool => (int) ($event["extendedProps"]["booking_id"] ?? 0) === (int) $singleBarbecue["booking_id"]));
gd_assert("agenda compartilhada identifica o aluguel de churrasqueira", $barbecueMatch && (int) ($barbecueMatch[0]["extendedProps"]["barbecue_rental_id"] ?? 0) === (int) $singleBarbecue["id"]);
gd_assert("agenda de churrasqueira nao aceita evento de quadra", $barbecueCalendar->events($calendarStart, $calendarEnd, [$bookA], ["booking"], []) === []);

$barbecueDraft = $barbecueService->createDraft([
    "rental_type" => "single", "title" => "Draft de isolamento", "customer_account_id" => $family["id"],
], "single");
gd_assert("vinculo de churrasqueira rejeita reserva de quadra", gd_throws(fn() => $barbecueService->linkExisting($barbecueDraft["id"], ["booking_id" => $replacementBooking["id"], "link_kind" => "primary"]), "gd_invalid_booking_resources"));
$barbecueEventModel = model("grupo_donato_gestao\Models\Gd_barbecue_rental_events_model");
gd_assert("eventos de churrasqueira sao append-only", gd_throws(fn() => $barbecueEventModel->delete(1), "Barbecue rental events cannot be deleted.") && gd_throws(fn() => $barbecueEventModel->update_where([], []), "Barbecue rental events cannot be updated."));

$bbManage = new \grupo_donato_gestao\Services\AccessService($pm("gd_barbecue_rentals_manage"));
$bbStatus = new \grupo_donato_gestao\Services\AccessService($pm("gd_barbecue_rentals_status_manage"));
$bbOverride = new \grupo_donato_gestao\Services\AccessService($pm("gd_barbecue_rentals_price_override"));
gd_assert("manage de churrasqueira implica as leituras operacionais", $bbManage->can("gd_barbecue_rentals_view") && $bbManage->can("gd_bookings_view") && $bbManage->can("gd_booking_series_view") && $bbManage->can("gd_calendar_view") && $bbManage->can("gd_resources_view") && $bbManage->can("gd_catalog_view"));
gd_assert("permissoes de churrasqueira nao concedem manage de quadras", !$bbManage->can("gd_court_rentals_manage") && !$bbStatus->can("gd_court_rentals_manage") && !$bbOverride->can("gd_court_rentals_manage"));
gd_assert("status e override de churrasqueira implicam a propria leitura", $bbStatus->can("gd_barbecue_rentals_view") && $bbOverride->can("gd_barbecue_rentals_view"));
gd_assert("rotas de churrasqueira separam GET e POST", isset($get_routes["grupo_donato/barbecue-rentals"], $get_routes["grupo_donato/barbecue-calendar"], $get_routes["grupo_donato/finance/barbecue-payments"]) && isset($post_routes["grupo_donato/barbecue-rentals/save-single"], $post_routes["grupo_donato/barbecue-rentals/save-monthly"], $post_routes["grupo_donato/barbecue-rentals/extra-time"], $post_routes["grupo_donato/finance/barbecue-payments/data"]));
gd_assert("CSRF protege escrita de churrasqueira", in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/barbecue-rentals/save-single", "POST"), "filter"), true) && in_array("csrf", (array) get_array_value($routes->getRoutesOptions("grupo_donato/finance/barbecue-payments/create-rental-charge", "POST"), "filter"), true));
gd_assert("idioma e menu de churrasqueiras resolvem", app_lang("gd_menu_barbecues") === "Churrasqueiras" && app_lang("gd_resource_type_barbecue_area") !== "gd_resource_type_barbecue_area");
