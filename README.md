# Gratulomat

Česká webová služba na **gratulomat.cz**: organizátor založí událost, hosté přes pozvánku
přidají vzpomínky, fotky, videa a přání a z nich se složí videoprezentace pro společné promítání.
Vychází z prototypu [adam50](https://github.com/adamlescisin/adam50).

- Specifikace: [Gratulomat – technická specifikace](https://claude.ai/artifact/DWyA6mhnxDeeEYS8msoQbV)
- Právní texty: [Gratulomat – právní texty](https://claude.ai/artifact/J5D9xbCgLZGHjLv6ryYv6X)

## Stav

Fáze 0 – základ:

- Laravel 11, PHP 8.3
- produktová konfigurace (`config/gratulomat.php`): tarify, funkce, doplňky, jazyky
- výpočet ceny a tarifu (`app/Domain/Pricing`) s testy hraničních případů
- režim webu Připravujeme / Údržba / Ostrý provoz a přístup testerů (`/pristup`)
- jazyk rozhraní hosta: CS / EN / DE, přepínač, `Accept-Language`, `lang/*.json`
- pomocný worker pro Mac Mini (`worker/`): vystavování dokladů v lokálním MyÚčtu

Další fáze podle specifikace: datový model a události → stránka hosta a Kino z prototypu →
průvodce a Stripe → back-office (Filament) → e-maily (Resend) → spuštění.

## Lokální vývoj

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve           # http://localhost:8000
vendor/bin/phpunit          # testy
vendor/bin/pint             # formátování
```

`SITE_MODE=live` v `.env` vypne coming soon; jinak použijte kód z `TESTER_CODES` na `/pristup`.

## Nasazení

`main` → staging.gratulomat.cz, tag `v*` → produkce (GitHub Actions přes SSH, viz specifikace
„Prostředí a testování“). Tajné klíče jen v `.env` na serveru; token MyÚčta jen na Mac Mini.
