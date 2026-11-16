<?php

use Worker\InvoiceJobHandler;
use Worker\MyUctoClient;

/*
 * Kontrola instalace workeru na Mac Mini:  php worker/bin/check.php [--test-invoice]
 *  - PHP a rozšíření, vyplněná konfigurace
 *  - spojení s lokálním MyÚčtem (token, sazba DPH 21 %)
 *  - spojení s Gratulomatem (token workeru) – nic nezamyká ani nezpracovává
 *  --test-invoice: založí v MyÚčtu KONCEPT dokladu 990 Kč se slevou −198 Kč (nevystavuje ho)
 *                  a vypíše, jak MyÚčto spočítalo základ a DPH. Koncept pak v MyÚčtu smažte.
 */

require __DIR__.'/../src/bootstrap.php';

$ok = true;
$line = function (bool $pass, string $msg, string $fix = '') use (&$ok) {
    $ok = $ok && $pass;
    echo ($pass ? '  ✓ ' : '  ✗ ').$msg.PHP_EOL;
    if (! $pass && $fix) {
        echo "      → {$fix}".PHP_EOL;
    }
};

echo 'Gratulomat worker '.WORKER_VERSION." – kontrola\n\n1) Prostředí\n";
$line(version_compare(PHP_VERSION, '8.1', '>='), 'PHP '.PHP_VERSION, 'Potřeba PHP 8.1+: brew install php');
$line(extension_loaded('curl'), 'rozšíření curl', 'Doinstalujte PHP s curl (Homebrew PHP ho má).');
$line(extension_loaded('mbstring'), 'rozšíření mbstring');

$config = worker_config();
echo "\n2) Konfigurace (worker/config.php)\n";
$line(($config['gratulomat_token'] ?? '') !== '', 'token Gratulomatu vyplněný', 'Stejná hodnota jako WORKER_TOKEN v shared/.env na serveru.');
$line(str_starts_with((string) ($config['myucto_token'] ?? ''), 'mi_pat_'), 'token MyÚčta vyplněný (mi_pat_…)', 'MyÚčto → Firma → API tokeny → nový token, rozsah read_write.');
$addr = $config['consumer_client_address'] ?? [];
$line(! str_contains(json_encode($addr, JSON_UNESCAPED_UNICODE), '['), 'adresa souhrnného klienta vyplněná', 'Doplňte consumer_client_address (adresa provozovatele).');

echo "\n3) MyÚčto ({$config['myucto_url']})\n";
$myucto = new MyUctoClient($config['myucto_url'], (string) $config['myucto_token'], $config['myucto_supplier_id'] ?? null);
try {
    $vat = $myucto->vatRateId(21.0);
    $line(true, "spojení a token v pořádku, sazba DPH 21 % má id {$vat}");
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $line(false, 'MyÚčto: '.$msg, match (true) {
        str_contains($msg, '401'), str_contains($msg, '403') => 'Token je neplatný, vypršel nebo nemá rozsah read_write.',
        str_contains($msg, 'connect') => 'MyÚčto neběží nebo je jiná adresa/port v myucto_url.',
        str_contains($msg, 'Sazba DPH') => 'Spojení i token fungují, jen neznám tvar číselníku sazeb. Pošlete mi tento řádek (tokeny v něm nejsou).',
        default => 'Zkontrolujte myucto_url a token.',
    });
}

echo "\n4) Gratulomat ({$config['gratulomat_url']})\n";
try {
    $r = worker_api($config)('GET', '/api/worker/ping');
    $line(! empty($r['ok']), "spojení a token v pořádku · ve frontě {$r['queued']} úloh, z toho s chybou {$r['failed']}");
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $line(false, $msg, str_contains($msg, '401')
        ? 'Token workeru nesouhlasí s WORKER_TOKEN na serveru (po úpravě .env spusťte php artisan config:cache).'
        : 'Zkontrolujte gratulomat_url a připojení k internetu.');
}

if (in_array('--test-invoice', $argv, true) && $ok) {
    echo "\n5) Zkušební koncept dokladu v MyÚčtu\n";
    try {
        $handler = new InvoiceJobHandler($myucto, $addr, $config['branding_profile_id'] ?? null);
        $draft = $myucto->createDraft($handler->draftPayload([
            'order_number' => 'TEST-'.date('YmdHis'), 'paid_at' => date('Y-m-d'), 'stripe_payment' => 'test', 'vat_rate' => 21,
            'customer' => ['email' => 'test@gratulomat.cz', 'name' => 'Test'],
            'lines' => [
                ['name' => 'TEST Tarif Párty (smažte koncept)', 'qty' => 1, 'unit_price_halere' => 99000],
                ['name' => 'TEST Sleva JARO20 (20 %)', 'qty' => 1, 'unit_price_halere' => -19800],
            ],
        ]));
        $inv = $myucto->getInvoice((int) $draft['id']);
        $line(true, "koncept id {$draft['id']} založen (záporný řádek slevy MyÚčto přijalo)");
        echo "      Očekávané: celkem 792,00 Kč = základ 654,55 + DPH 137,45\n";
        foreach ($inv as $k => $v) {
            if (is_scalar($v) && preg_match('/total|vat|base|price|amount/i', $k)) {
                echo "      MyÚčto {$k}: {$v}\n";
            }
        }
        echo "      → Porovnejte čísla a koncept v MyÚčtu smažte (není vystavený).\n";
    } catch (Throwable $e) {
        $line(false, 'koncept se nepodařilo založit: '.$e->getMessage(), 'Pokud vadí záporný řádek, dejte vědět – slevu rozpočítáme do položek.');
    }
}

echo PHP_EOL.($ok ? 'Vše v pořádku. Worker můžete spustit: php worker/bin/worker.php (nebo přes launchd).' : 'Opravte položky označené ✗ a spusťte kontrolu znovu.').PHP_EOL;
exit($ok ? 0 : 1);
