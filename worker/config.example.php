<?php

return [
    'gratulomat_url' => 'https://gratulomat.cz',
    'gratulomat_token' => '',                // stejná hodnota jako WORKER_TOKEN v shared/.env na serveru
    'myucto_url' => 'http://127.0.0.1:8080', // lokální instance MyÚčto
    'myucto_token' => '',                    // mi_pat_…
    'myucto_supplier_id' => null,            // jen u tokenu nevázaného na firmu
    'consumer_client_address' => ['street' => '[ulice provozovatele]', 'city' => '[obec]', 'zip' => '[PSČ]'],
    'branding_profile_id' => null,
    'poll_seconds' => 30,
];
