<?php

declare(strict_types=1);

require dirname(__DIR__) . '/Services/Contracts/BillingConnectorInterface.php';
require dirname(__DIR__) . '/Services/NullBillingConnector.php';
require dirname(__DIR__) . '/Services/Money.php';

use grupo_donato_cobranca\Services\Money;
use grupo_donato_cobranca\Services\NullBillingConnector;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(Money::normalize('237') === '237.00', 'normalize integer');
$assert(Money::normalize('0237,5') === '237.50', 'normalize decimal comma');
$assert(Money::compare('10.01', '10.00') === 1, 'money compare greater');
$assert(Money::compare('10.00', '10.00') === 0, 'money compare equal');

$invalid = false;
try {
    Money::normalize('10.009');
} catch (DomainException $e) {
    $invalid = $e->getMessage() === 'gdc_invalid_amount';
}
$assert($invalid, 'reject fractional cent');

$null = new NullBillingConnector('test');
$assert($null->code() === 'test', 'null connector code');
$assert($null->capabilities()['pix'] === false, 'null connector capabilities');
$assert($null->createPixCharge([])['success'] === false, 'null connector blocks operations');

echo "PURE UNIT PASS\n";
