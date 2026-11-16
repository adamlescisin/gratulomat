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

Fáze 1 – události a stránka hosta (z prototypu adam50):

- datový model: `events`, `households`, `memories`, `media`, `show_settings`
- stránka hosta `/{slug}`: osobní pozvánka `/{slug}/g/{token}`, formulář v 6 krocích, vlastní vzpomínky, zeď ostatních (zapečetěná do data události, u smutečního rozloučení hned)
- rozhraní hosta v CS / EN / DE (`lang/*/guest.php`), otázky „Došla slova?“ podle typu události, tvary jména adresáta
- média jen přes aplikaci s kontrolou oprávnění a HTTP Range; fotky přes GD (bez EXIF/GPS, 2560 + 640 px); `memory.json` u každé vzpomínky
- limity tarifu: přání od Párty, videa od Premiéry, počet fotek Ochutnávky
- Kino `/{slug}/kino` – otevře se jen po zpřístupnění organizátorem
- příkazy: `gratulomat:event`, `gratulomat:households`, `gratulomat:import-adam50`

Fáze 2 – administrace organizátora a nasazení:

- přihlášení organizátora odkazem z e-mailu (`/prihlaseni`, bez hesla, jednorázový odkaz), e-maily přes Resend
- `/{slug}/admin`: přehled, hosté (hromadné přidání v limitu tarifu, QR + text pozvánky, nový odkaz),
  moderace vzpomínek včetně soukromých přání, zpřístupnění videoprezentace (hned / k datu / zavřít),
  hra v Kině (Premiéra+), uzávěrka sběru, text pozvánky
- offline balíček ZIP (Párty+): Kino s vloženými daty – stačí otevřít `index.html`, i bez serveru
- příkaz `gratulomat:organizer` (přidá organizátora a vypíše přihlašovací odkaz)
- nasazení GitHub Actions → Hostinger přes SSH, viz [docs/DEPLOY.md](docs/DEPLOY.md)

Fáze 3 – veřejná část a back-office:

- coming soon stránka podle návrhu (čekací listina → tabulka `waitlist` + upozornění na `NOTIFY_EMAIL`), `/ochrana-soukromi`
- favicon a ikony z tlačítka značky (`public/favicon.*`, `apple-touch-icon.png`, `site.webmanifest`), fonty hostované lokálně
- back-office `/backoffice` pro `OPERATOR_EMAILS`: přehled, události (vstup do administrace kterékoli události),
  čekací listina (CSV), režim webu (přebíjí `SITE_MODE`), kódy testerů, kontrolní seznam a spuštění ostrého provozu

Další: průvodce a Stripe → back-office (Filament) → e-maily (Resend) → spuštění.

## Beta: Adamových 50

```bash
php artisan gratulomat:event adamovych-50 --title="Adamových 50" --name=Adam \
  --forms='{"dat":"Adamovi","acc":"Adama","voc":"Adame","gen":"Adama","loc":"Adamovi","ins":"Adamem","poss":"Adamových"}' \
  --year=1977 --date="2027-01-23 18:00" --test
php artisan gratulomat:import-adam50 adamovych-50 ~/domains/adam50.lescisin.cz/adam50-private/data
```

Tokeny pozvánek zůstávají; staré odkazy `adam50.lescisin.cz/g/<token>` stačí přesměrovat
na `gratulomat.cz/adamovych-50/g/<token>`.

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
