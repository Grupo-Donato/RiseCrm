<?php
$balance = $client_wallet_summary->balance ? $client_wallet_summary->balance : 0;
?>
<div class="card dashboard-icon-widget">
    <div class="card-body ">
        <div class="widget-icon bg-primary">
            <i data-feather="credit-card" class="icon"></i>
        </div>
        <div class="widget-details">
            <h1><?php echo to_currency($balance, $currency_symbol); ?></h1>
            <span class="bg-transparent-white"><?php echo app_lang("wallet_balance"); ?></span>
        </div>
    </div>
</div>