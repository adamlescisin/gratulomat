<?php

use Worker\InvoiceJobHandler;
use Worker\MyUctoClient;

/*
 * Pomocný worker Gratulomatu – smyčka na Mac Mini.
 * Úlohy si vyzvedává z API Gratulomatu (pull), takže Mac Mini nepotřebuje veřejnou adresu.
 * Endpointy /api/worker/* budou součástí aplikace (fáze Platby / Videoprezentace).
 */

require __DIR__.'/../src/bootstrap.php';
$config = worker_config();
$once = in_array('--once', $argv, true); // jeden průchod (ruční test, ladění)

$myucto = new MyUctoClient($config['myucto_url'], $config['myucto_token'], $config['myucto_supplier_id']);
$invoices = new InvoiceJobHandler($myucto, $config['consumer_client_address'], $config['branding_profile_id']);

$api = worker_api($config);
fwrite(STDOUT, date('c').' worker '.WORKER_VERSION.' spuštěn, server '.$config['gratulomat_url'].PHP_EOL);

while (true) {
    try {
        foreach ($api('GET', '/api/worker/jobs')['data'] ?? [] as $job) {
            try {
                $result = match ($job['type']) {
                    'invoice' => $invoices->handle($job['payload']),
                    'credit_note' => $invoices->handleCreditNote($job['payload']),
                    default => null, // video / mp4: doplní fáze Videoprezentace
                };
                if ($result === null) {
                    continue;
                }
                $pdf = tempnam(sys_get_temp_dir(), 'gm').'.pdf';
                file_put_contents($pdf, $result['pdf']);
                $api('POST', "/api/worker/jobs/{$job['id']}/done", [
                    'myucto_id' => $result['myucto_id'],
                    'number' => (string) $result['number'],
                    'pdf' => new CURLFile($pdf, 'application/pdf', ($result['number'] ?: 'doklad').'.pdf'),
                ]);
                unlink($pdf);
                fwrite(STDOUT, date('c')." úloha {$job['id']} ({$job['type']}) hotová: doklad {$result['number']}\n");
            } catch (Throwable $e) {
                fwrite(STDERR, date('c')." úloha {$job['id']}: {$e->getMessage()}\n");
                $api('POST', "/api/worker/jobs/{$job['id']}/failed", ['error' => mb_substr($e->getMessage(), 0, 1000)]);
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, date('c')." {$e->getMessage()}\n");
    }
    if ($once) {
        break;
    }
    sleep($config['poll_seconds']);
}
