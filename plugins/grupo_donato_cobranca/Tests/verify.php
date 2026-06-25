<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$required = [
    'index.php',
    'Config/Routes.php',
    'Database/Installer.php',
    'Services/Contracts/BillingConnectorInterface.php',
    'Services/ChargeService.php',
    'Services/SubscriptionService.php',
    'Controllers/Webhook.php',
    'Views/dashboard/index.php',
    'Views/charges/index.php',
    'Views/subscriptions/index.php',
    'Views/payment_methods/index.php',
    'Views/settings/index.php',
    'Language/portuguese/default_lang.php',
    'docs/integration-contract.md',
];
foreach ($required as $file) {
    if (!is_file($root . '/' . $file)) {
        $errors[] = 'Arquivo obrigatório ausente: ' . $file;
    }
}

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $phpFiles[] = $file->getPathname();
    }
}

$source = '';
foreach ($phpFiles as $file) {
    $source .= "\n" . file_get_contents($file);
}

$mustContain = [
    "'name' => 'Cobrança'",
    "cobranca/webhook/(:segment)",
    'FinanceService::class', // fallback checked below because implementation instantiates by FQCN
    'BillingConnectorInterface',
    'gdc_filter_billing_connector_',
    'registerPayment',
    'reversePayment',
];
foreach ($mustContain as $needle) {
    if ($needle === 'FinanceService::class') {
        if (!str_contains($source, 'grupo_donato_gestao\\Services\\FinanceService')) {
            $errors[] = 'Integração com FinanceService não encontrada.';
        }
        continue;
    }
    if (!str_contains($source, $needle)) {
        $errors[] = 'Trecho obrigatório não encontrado: ' . $needle;
    }
}

$installer = file_get_contents($root . '/Database/Installer.php');
foreach (['card_number', 'card_pan', '`pan`', '`cvv`', 'security_code', 'api_secret', 'access_token'] as $forbidden) {
    if (stripos($installer, $forbidden) !== false) {
        $errors[] = 'Campo sensível proibido no schema: ' . $forbidden;
    }
}

$lang = require $root . '/Language/portuguese/default_lang.php';
preg_match_all("/app_lang\\('([^']+)'/", $source, $matches);
preg_match_all("/DomainException\\('([^']+)'/", $source, $domainMatches);
$literalKeys = array_unique(array_merge($matches[1] ?? [], $domainMatches[1] ?? []));
foreach ($literalKeys as $key) {
    if (str_starts_with($key, 'gdc_') && !str_ends_with($key, '_') && !array_key_exists($key, $lang)) {
        $errors[] = 'Tradução ausente: ' . $key;
    }
}

if (!str_contains(file_get_contents($root . '/Controllers/Webhook.php'), "hash('sha256', \$body)")) {
    $errors[] = 'Hash do payload do webhook não encontrado.';
}
if (str_contains(file_get_contents($root . '/Controllers/Webhook.php'), 'file_put_contents')) {
    $errors[] = 'Webhook não deve persistir corpo bruto em arquivo.';
}

if ($errors) {
    fwrite(STDERR, "VERIFY FAIL\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "VERIFY PASS\n";
echo 'PHP files: ' . count($phpFiles) . "\n";
echo 'Language keys: ' . count($lang) . "\n";
echo "Security schema check: PASS\n";
