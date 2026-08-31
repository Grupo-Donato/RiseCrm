<?php

echo "# Fluxo financeiro de locacoes — cenarios obrigatorios\n";

$rentalFinance = new \grupo_donato_gestao\Services\FinanceService($unit_id);
$rentalGenerator = new \grupo_donato_gestao\Services\ReceivableGenerationService($unit_id);

// 1. Mensalista criado com a primeira competencia ligada ao contrato.
$monthlyChargeCount = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "barbecue_rental")
    ->where("source_id", (int) $monthlyBarbecue["id"])->where("reference_month", "2099-12")
    ->where("deleted", 0)->countAllResults();
gd_assert("mensalista novo possui cobranca da competencia", $monthlyChargeCount === 1);

// 2. Avulso criado com exatamente uma cobranca unica (competencia vazia).
$singleChargeCount = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "barbecue_rental")
    ->where("source_id", (int) $singleBarbecue["id"])->where("reference_month", "")
    ->where("deleted", 0)->countAllResults();
gd_assert("avulso novo possui uma cobranca unica", $singleChargeCount === 1);

// 3. Competencia futura nao herda vencidos de outros meses.
$futureBalance = $rentalFinance->balancesBySource("court_rental", [(int) $monthly31["id"]], "2099-12");
gd_assert(
    "competencia futura nao assume divida antiga",
    isset($futureBalance[(int) $monthly31["id"]])
        && $futureBalance[(int) $monthly31["id"]]["overdue"] === "0.00"
        && count($futureBalance[(int) $monthly31["id"]]["open_ids"]) === 1
    , json_encode($futureBalance, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
);

// 4–6. O estado vem da cobranca real: vencida, paga e parcial.
gd_assert("cobranca vencida usa vencimento da propria cobranca", $rentalFinance->getReceivable((int) $pastDueCr["id"])->status === "overdue");
gd_assert("cobranca paga nao permanece vencida", $rentalFinance->getReceivable((int) $singleCharge["id"])->status === "paid");
gd_assert("cobranca parcialmente paga preserva o saldo", $r2partial->status === "partial" && $r2partial->balance_amount === "100.00", json_encode((array) $r2partial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// 7. Isencao e um estado explicito e nao cria recebivel.
$exemptRental = $rentalService->createDraft([
    "rental_type" => "single", "title" => "Locacao isenta de teste", "customer_account_id" => $family["id"],
    "financial_status" => "exempt",
], "single");
$exemptRow = $rentalService->get((int) $exemptRental["id"]);
$exemptMetadata = json_decode((string) ($exemptRow->metadata ?? ""), true);
$exemptCharges = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->whereIn("source_type", ["court_rental", "barbecue_rental"])
    ->where("source_id", (int) $exemptRental["id"])->where("deleted", 0)->countAllResults();
gd_assert("locacao isenta usa estado explicito sem cobranca", ($exemptMetadata["financial_status"] ?? "") === "exempt" && $exemptCharges === 0);

// 8. Avulso pago fica liquidado no recebivel especifico.
gd_assert("avulso pago fica liquidado", $singlePaid["status"] === "paid" && $singlePaid["balance"] === "0.00");

// 9. Novo avulso em aberto recebe uma cobranca recebivel.
$flowSingle = $rentalService->createWithBooking([
    "rental_type" => "single", "title" => "Avulso fluxo financeiro", "customer_account_id" => $family["id"],
    "contact_person_id" => $person_two["id"], "product_id" => $rental["id"], "price_list_id" => $list["id"],
    "list_amount" => "210.00", "negotiated_amount" => "210.00", "effective_from" => "2099-12-22",
    "booking_status" => "pending_confirmation", "starts_at_local" => "2099-12-22T10:00",
    "ends_at_local" => "2099-12-22T11:00", "resources" => [["resource_id" => $bookB, "buffer_before_minutes" => 0, "buffer_after_minutes" => 0]],
]);
$flowCharge = $rentalFinance->getReceivable((int) ($flowSingle["finance"]["id"] ?? 0));
gd_assert("avulso novo fica em aberto com cobranca vinculada", $flowCharge && $flowCharge->status === "open" && $flowCharge->reference_month === "");

// 10. O contrato usado pelo clique do $ e inequívoco; ID sem contexto falha
// com erro de dominio conhecido, sem cair no formulario financeiro generico.
$flowContext = $rentalFinance->courtRentalPaymentContext((int) $flowCharge->id);
$routeSource = (string) @file_get_contents(__DIR__ . "/../Config/Routes.php");
gd_assert(
    "botao de pagamento usa contexto valido da locacao",
    $flowContext && $flowContext["source_type"] === "court_rental"
        && str_contains($routeSource, "rental-payment-modal")
        && gd_throws(fn() => $rentalFinance->registerCourtRentalPayment(["receivable_id" => 999999]), "gd_record_not_found")
);

// 11–12. Baixa contextualizada e leitura nova confirmam persistencia apos refresh.
$flowPayment = $rentalFinance->registerCourtRentalPayment([
    "receivable_id" => (int) $flowCharge->id,
    "customer_name" => $flowContext["renter_name"],
    "competence" => "12/2099",
    "amount" => (string) $flowCharge->balance_amount,
    "payment_date" => gmdate("Y-m-d"), "payment_method" => "pix",
    "financial_account_id" => (int) ($depositAccount->id ?? 0),
]);
gd_assert("registrar pagamento baixa a cobranca correta", $flowPayment["status"] === "paid" && $flowPayment["balance"] === "0.00");
$flowFresh = (new \grupo_donato_gestao\Services\FinanceService($unit_id))->getReceivable((int) $flowCharge->id);
gd_assert("atualizacao da pagina preserva baixa e impede recebimento duplicado", $flowFresh->status === "paid" && gd_throws(fn() => $rentalFinance->registerCourtRentalPayment([
    "receivable_id" => (int) $flowCharge->id, "customer_name" => $flowContext["renter_name"], "competence" => "12/2099",
    "amount" => "1.00", "payment_date" => gmdate("Y-m-d"), "payment_method" => "pix",
]), "gd_finance_receivable_unavailable"));

// 13. Reenvio da geracao avulsa retorna a mesma cobranca.
$flowDuplicate = $rentalGenerator->generateCourtRental((int) $flowSingle["id"], ["amount" => "999.00", "due_date" => "2099-12-22"]);
$flowCountAfterReplay = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "court_rental")
    ->where("source_id", (int) $flowSingle["id"])->where("reference_month", "")
    ->where("deleted", 0)->countAllResults();
gd_assert("reenviar avulso nao duplica recebivel", empty($flowDuplicate["created"]) && !empty($flowDuplicate["duplicate"]) && $flowCountAfterReplay === 1);

// 14. Reenvio mensal da mesma competencia e idempotente.
$monthReplayOne = $rentalGenerator->ensureMonth("2099-12", "barbecue_rental");
$monthReplayTwo = $rentalGenerator->ensureMonth("2099-12", "barbecue_rental");
$monthlyCountAfterReplay = $db->table($prefix . "gd_receivables")
    ->where("unit_id", $unit_id)->where("source_type", "barbecue_rental")
    ->where("source_id", (int) $monthlyBarbecue["id"])->where("reference_month", "2099-12")
    ->where("deleted", 0)->countAllResults();
gd_assert(
    "reenviar competencia mensal nao duplica cobranca",
    $monthlyCountAfterReplay === 1 && ($monthReplayOne["created"] + $monthReplayOne["duplicates"]) >= 1
        && ($monthReplayTwo["created"] + $monthReplayTwo["duplicates"]) >= 1
);
