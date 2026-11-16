<?php

/* Společné pro worker.php a check.php. */

const WORKER_VERSION = '1.1';

spl_autoload_register(function (string $class) {
    if (str_starts_with($class, 'Worker\\')) {
        require __DIR__.'/'.str_replace('\\', '/', substr($class, 7)).'.php';
    }
});

function worker_config(): array
{
    $file = __DIR__.'/../config.php';
    if (! is_file($file)) {
        fwrite(STDERR, "Chybí worker/config.php – zkopírujte config.example.php a doplňte tokeny.\n");
        exit(1);
    }

    return require $file;
}

/** Volání API Gratulomatu (Bearer token workeru, heartbeat v hlavičkách). */
function worker_api(array $config): Closure
{
    return function (string $method, string $path, array $fields = []) use ($config) {
        $ch = curl_init(rtrim($config['gratulomat_url'], '/').$path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer '.$config['gratulomat_token'], 'Accept: application/json',
                'X-Worker-Host: '.gethostname(), 'X-Worker-Version: '.WORKER_VERSION,
            ],
        ]);
        if ($fields) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields); // multipart kvůli PDF / souborům
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status >= 400) {
            throw new RuntimeException("Gratulomat {$method} {$path} → HTTP {$status}".($err ? " ({$err})" : ''));
        }

        return json_decode($body ?: '[]', true) ?? [];
    };
}
