<?php

use Worker\InvoiceJobHandler;
use Worker\MyUctoClient;

/*
 * Pomocný worker Gratulomatu – smyčka na Mac Mini.
 * Úlohy si vyzvedává z API Gratulomatu (pull), takže Mac Mini nepotřebuje veřejnou adresu.
 * Endpointy /api/worker/* budou součástí aplikace (fáze Platby / Videoprezentace).
 */

spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'Worker\\')) {
        require __DIR__.'/../src/'.str_replace('\\', '/', substr($class, 7)).'.php';
    }
});

$config = require __DIR__.'/../config.php';

$myucto = new MyUctoClient($config['myucto_url'], $config['myucto_token'], $config['myucto_supplier_id']);
$invoices = new InvoiceJobHandler($myucto, $config['consumer_client_address'], $config['branding_profile_id']);

$api = function (string $method, string $path, array $fields = []) use ($config) {
    $ch = curl_init(rtrim($config['gratulomat_url'], '/').$path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer '.$config['gratulomat_token'], 'Accept: application/json'],
    ]);
    if ($fields) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); // multipart kvůli PDF / souborům
    }
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $status >= 400) {
        throw new RuntimeException("Gratulomat {$method} {$path} → HTTP {$status}");
    }

    return json_decode($body ?: '[]', true) ?? [];
};

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
            } catch (Throwable $e) {
                fwrite(STDERR, date('c')." úloha {$job['id']}: {$e->getMessage()}\n");
                $api('POST', "/api/worker/jobs/{$job['id']}/failed", ['error' => mb_substr($e->getMessage(), 0, 1000)]);
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, date('c')." {$e->getMessage()}\n");
    }
    sleep($config['poll_seconds']);
}
