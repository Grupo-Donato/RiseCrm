<?php if (!empty($financial)) {
    $status = (string) ($financial["status"] ?? "none");
    $status_class = ["paid" => "bg-success", "overdue" => "bg-danger", "deposit_only" => "bg-info", "partial" => "bg-warning", "unpaid" => "bg-warning", "none" => "bg-secondary"][$status] ?? "bg-secondary";
    $history = (array) ($financial["payment_history"] ?? []);
    $open_receivables = array_values(array_filter((array) ($financial["receivables"] ?? []), static fn($receivable) => (float) ($receivable->balance_amount ?? 0) > 0 && !in_array((string) ($receivable->status ?? ""), ["paid", "cancelled"], true)));
?>
<div class="card mt-3">
    <div class="card-header"><h4><?php echo app_lang("gd_finance_financial_summary"); ?></h4></div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3"><strong><?php echo app_lang("gd_finance_amount"); ?></strong><br>R$ <?php echo esc($financial["total"] ?? "0.00"); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_finance_paid"); ?></strong><br>R$ <?php echo esc($financial["paid"] ?? "0.00"); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_finance_balance"); ?></strong><br>R$ <?php echo esc($financial["balance"] ?? "0.00"); ?></div>
            <div class="col-md-3"><strong><?php echo app_lang("gd_finance_situation"); ?></strong><br><span class="badge <?php echo $status_class; ?>"><?php echo app_lang("gd_finance_status_" . $status); ?></span></div>
        </div>
        <?php if ($history) { ?>
            <h5 class="mt-4"><?php echo app_lang("gd_finance_payment_history"); ?></h5>
            <div class="table-responsive"><table class="table table-sm mb0">
                <thead><tr><th><?php echo app_lang("gd_finance_number"); ?></th><th><?php echo app_lang("gd_date"); ?></th><th><?php echo app_lang("gd_finance_type"); ?></th><th><?php echo app_lang("gd_finance_method"); ?></th><th><?php echo app_lang("gd_finance_amount"); ?></th></tr></thead>
                <tbody><?php foreach ($history as $payment) { ?>
                    <tr><td><?php echo esc($payment->payment_number ?? "-"); ?></td><td><?php echo esc($payment->payment_date ?? "-"); ?></td><td><?php echo app_lang((string) ($payment->payment_type ?? "regular") === "deposit" ? "gd_finance_deposit" : "gd_finance_regular_payment"); ?></td><td><?php echo app_lang("gd_finance_method_" . ($payment->payment_method ?? "other")); ?></td><td>R$ <?php echo esc($payment->allocated_amount ?? $payment->amount ?? "0.00"); ?></td></tr>
                <?php } ?></tbody>
            </table></div>
        <?php } ?>
        <?php if (!empty($financial["last_payment"])) { ?><div class="text-muted mt-2"><small><?php echo app_lang("gd_finance_last_payment"); ?>: <?php echo esc($financial["last_payment"]->payment_number . " — " . $financial["last_payment"]->payment_date); ?></small></div><?php } ?>
        <?php if (!empty($can_payments) && count($open_receivables) === 1) { $receivable = $open_receivables[0]; ?>
            <?php echo modal_anchor(get_uri("grupo_donato/finance/rental-payment-modal"), '<i data-feather="check-circle" class="icon-16"></i> ' . app_lang("gd_rental_payments_settle"), ["class" => "btn btn-primary mt-3 me-2", "title" => app_lang("gd_rental_payments_settle"), "data-post-receivable_id" => (int) $receivable->id, "data-post-balance" => (string) $receivable->balance_amount, "data-modal-class" => "gd-payment-modal"]); ?>
        <?php } elseif (!empty($can_payments) && count($open_receivables) > 1) { ?>
            <div class="dropdown d-inline-block mt-3 me-2">
                <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i data-feather="check-circle" class="icon-16"></i> <?php echo app_lang("gd_rental_payments_settle"); ?></button>
                <ul class="dropdown-menu">
                    <?php foreach ($open_receivables as $receivable) {
                        $reference = (string) ($receivable->reference_month ?? "");
                        $reference_label = preg_match('/^\d{4}-\d{2}$/', $reference) ? substr($reference, 5, 2) . "/" . substr($reference, 0, 4) : format_to_date((string) $receivable->due_date, false);
                    ?>
                        <li><?php echo modal_anchor(get_uri("grupo_donato/finance/rental-payment-modal"), "Competência " . esc($reference_label) . " — R$ " . esc((string) $receivable->balance_amount), ["class" => "dropdown-item", "title" => app_lang("gd_rental_payments_settle"), "data-post-receivable_id" => (int) $receivable->id, "data-post-balance" => (string) $receivable->balance_amount, "data-modal-class" => "gd-payment-modal"]); ?></li>
                    <?php } ?>
                </ul>
            </div>
        <?php } ?>
    </div>
</div>
<?php } ?>
