<?php

/*
 * Gratulomat – produktová konfigurace.
 *
 * Výchozí hodnoty ceníku. V produkci se ceník verzuje v databázi (tabulky
 * plans / addons, viz specifikace „Tarify, limity a cenotvorba“); tento
 * soubor slouží jako seed a jako zdroj pro testy.
 */

return [

    // Kam míří odkazy „Vytvořeno v Gratulomatu“ ze stránek událostí (growth loop).
    'marketing_url' => env('MARKETING_URL', 'https://gratulomat.cz'),
    // Google Analytics 4 (G-XXXXXXXXXX). Prázdné = měření vypnuté, nenačte se nic.
    'ga_id' => env('GA_MEASUREMENT_ID'),

    'site_mode' => env('SITE_MODE', 'coming_soon'), // coming_soon | maintenance | live

    // Provozovatelé (back-office /backoffice) – e-maily oddělené čárkou. Přihlašují se odkazem z e-mailu.
    'operators' => array_values(array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', (string) env('OPERATOR_EMAILS', ''))))),

    // Kam chodí upozornění (čekací listina, nové objednávky).
    'notify_email' => env('NOTIFY_EMAIL', 'ahoj@gratulomat.cz'),

    // Dočasně z .env (čárkou oddělené); později tabulka tester_codes v back-office.
    'tester_codes' => array_filter(explode(',', (string) env('TESTER_CODES', ''))),
    'tester_cookie' => 'gm_tester',
    'tester_cookie_days' => 30,

    'locales' => [
        // jazyky, ve kterých může být událost (primární jazyk)
        'event' => ['cs'],
        // jazyky rozhraní stránky hosta (přepínač v hlavičce)
        'guest' => ['cs', 'en', 'de'],
        'default' => 'cs',
        'cookie' => 'gm_locale',
    ],

    'vat_rate' => 21,

    // Pořadí tarifů je důležité: index = úroveň.
    'plans' => [
        'ochutnavka' => ['name' => 'Ochutnávka', 'price' => 0, 'guests' => 5, 'photos' => 10, 'collect_days' => 14, 'archive_months' => 1],
        'party' => ['name' => 'Párty', 'price' => 990, 'guests' => 25, 'photos' => null, 'collect_days' => 90, 'archive_months' => 3],
        'premiera' => ['name' => 'Premiéra', 'price' => 1990, 'guests' => 150, 'photos' => null, 'collect_days' => 180, 'archive_months' => 12],
        'vip' => ['name' => 'VIP', 'price' => 4990, 'guests' => 300, 'photos' => null, 'collect_days' => 365, 'archive_months' => 12, 'inquiry' => true],
    ],

    // Funkce → nejnižší tarif, který ji obsahuje.
    'features' => [
        'wishes' => 'party',
        'no_watermark' => 'party',
        'offline' => 'party',
        'all_themes' => 'party',
        'dark_mode' => 'party',
        'videos' => 'premiera',
        'import' => 'premiera',
        'anonymous_link' => 'premiera',
        'game' => 'premiera',
        'mp4' => 'premiera',
        'reminders' => 'premiera',
        'translations' => 'premiera',
        'vip_services' => 'vip',
    ],

    'addons' => [
        'guest_pack' => ['name' => '+20 hostů', 'price' => 290, 'size' => 20, 'min_plan' => 'party', 'max_per_event' => null],
        'archive_year' => ['name' => 'Archiv o rok déle', 'price' => 290, 'min_plan' => 'party'],
        'photobook' => ['name' => 'Fotokniha', 'price' => 1290, 'min_plan' => 'party'],
    ],

    // Výchozí štítky témat podle typu události (v primárním jazyce události; organizátor je může upravit).
    'default_tags' => [
        'default' => ['Rodina', 'Škola', 'Práce', 'Cesty', 'Sport', 'Mejdan', 'Trapas', 'Jen tak'],
        'svatba' => ['Seznámení', 'Rodina', 'Přátelé', 'Cesty', 'Mejdan', 'Trapas', 'Jen tak'],
        'firemni-jubileum' => ['Začátky', 'Projekty', 'Lidé', 'Akce', 'Úspěchy', 'Trapas'],
        'podekovani' => ['Začátky', 'Společné chvíle', 'Rady a lekce', 'Akce', 'Úspěchy', 'Trapas', 'Jen tak'],
        'smutecni' => ['Rodina', 'Přátelé', 'Práce', 'Cesty', 'Koníčky', 'Poslední setkání'],
    ],

    'memory_limits' => [
        'files_per_memory' => 12,
        'video_mb' => 200,   // jen tarify s funkcí „videos“
        'audio_mb' => 25,
        'audio_seconds' => 180,
    ],

    'event_types' => [
        'narozeniny', 'svatba', 'vyroci-svatby', 'duchod', 'firemni-jubileum', 'rozlucka', 'sraz', 'podekovani', 'smutecni',
    ],

    // Smuteční rozloučení: sběr do data rozloučení + N dní, bez hry.
    'funeral' => [
        'type' => 'smutecni',
        'default_collect_days_after' => 30,
        'disabled_features' => ['game'],
        'theme' => 'ticho',
    ],

];
