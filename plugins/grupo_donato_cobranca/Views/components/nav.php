<?php
$active = $active ?? '';
$items = [
    ['key' => 'dashboard', 'url' => 'cobranca', 'label' => app_lang('gdc_dashboard'), 'icon' => 'home'],
    ['key' => 'charges', 'url' => 'cobranca/charges', 'label' => app_lang('gdc_charges'), 'icon' => 'file-text'],
    ['key' => 'subscriptions', 'url' => 'cobranca/subscriptions', 'label' => app_lang('gdc_subscriptions'), 'icon' => 'repeat'],
    ['key' => 'payment_methods', 'url' => 'cobranca/payment-methods', 'label' => app_lang('gdc_cards'), 'icon' => 'credit-card'],
];
if (function_exists('gdc_user_can') && gdc_user_can('gdc_billing_settings')) {
    $items[] = ['key' => 'settings', 'url' => 'cobranca/settings', 'label' => app_lang('gdc_integration'), 'icon' => 'settings'];
}
?>
<div class="card mb-3"><div class="card-body p-2"><div class="btn-group flex-wrap">
<?php foreach ($items as $item): ?>
    <a href="<?php echo get_uri($item['url']); ?>" class="btn <?php echo $active === $item['key'] ? 'btn-primary' : 'btn-default'; ?>">
        <i data-feather="<?php echo esc($item['icon']); ?>" class="icon-16"></i> <?php echo esc($item['label']); ?>
    </a>
<?php endforeach; ?>
</div></div></div>
