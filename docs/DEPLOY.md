# Nasazení na Hostinger (Cloud Startup)

Zatím `main` → **gratulomat.cz** (v režimu Připravujeme). Po založení stagingu a nastavení proměnné
repozitáře `STAGING=1`: `main` → **staging.gratulomat.cz**, tag `v*` → **gratulomat.cz**. Nasazuje GitHub Actions
(`.github/workflows/ci.yml`) až po úspěšných testech: sestaví `vendor/`, nahraje archiv přes SSH
a spustí `scripts/deploy-remote.sh` (vydání do `releases/`, sdílené `.env` a `storage/`, migrace,
přepnutí symlinku `current`). Hostinger Git deploy se nepoužívá.

## Jednorázová příprava (pro každé prostředí)

1. **hPanel → Weby → Přidat web** `staging.gratulomat.cz` (u produkce `gratulomat.cz`), PHP **8.3**
   (rozšíření gd, exif, intl, zip, fileinfo). SSL zapnout.
2. **Databáze → MySQL**: nová databáze + uživatel (zvlášť pro staging a produkci).
3. **SSH přístup** zapnout (port 65002) a přidat veřejný klíč pro nasazování
   (`ssh-keygen -t ed25519 -f gratulomat-deploy`). Soukromý klíč patří do GitHub secrets.
4. Přes SSH:
   ```bash
   cd ~/domains/staging.gratulomat.cz
   mkdir -p shared releases
   nano shared/.env            # podle .env.example, viz níže
   mv public_html public_html.puvodni && ln -s current/public public_html
   ```
   (Než proběhne první nasazení, `public_html` ukazuje do prázdna – to je v pořádku.)
5. **GitHub → Settings → Environments** `staging` a `production`, v každém secrets:
   `SSH_HOST`, `SSH_PORT` (65002), `SSH_USER`, `SSH_KEY`, `DEPLOY_PATH`
   (např. `/home/u123456789/domains/staging.gratulomat.cz`). U `production` zapnout
   „Required reviewers“, ať tag nejde ven bez potvrzení. Volitelná proměnná `PHP_BIN`,
   pokud `php` v SSH není 8.3 (např. `/opt/alt/php83/usr/bin/php`).
6. **hPanel → Pokročilé → Cron Jobs**: každou minutu (`* * * * *`)
   `cd ~/domains/gratulomat.cz/current && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1`
   Plánovač spouští: `gratulomat:guest-mail` (každou minutu – pozvánky a připomínky po dávkách)
   a `gratulomat:archive` (denně 3:15 – upozornění 7 dní před koncem archivu, úklid médií 30 dní po něm).
   Ruční kontrola: `php artisan gratulomat:archive --dry-run`.
7. První nasazení: push do `main`. Po něm `php artisan key:generate` jen pokud `.env` nemá `APP_KEY`.

## shared/.env (staging)

```
APP_NAME=Gratulomat
APP_ENV=staging
APP_KEY=base64:…            # php artisan key:generate --show (lokálně)
APP_DEBUG=false
APP_URL=https://staging.gratulomat.cz
APP_LOCALE=cs
APP_TIMEZONE=Europe/Prague

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=u123456789_gm_staging
DB_USERNAME=u123456789_gm_staging
DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_LIFETIME=172800
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=resend
RESEND_KEY=re_…
MAIL_FROM_ADDRESS=ahoj@gratulomat.cz
MAIL_FROM_NAME=Gratulomat

SITE_MODE=coming_soon
TESTER_CODES=BETA-…
```

Staging je navíc vhodné zamknout HTTP Basic Auth (hPanel → Zabezpečení → Ochrana adresářů
na `public_html`) – pozvánky testovacích událostí pak fungují jen pro ty, kdo heslo znají.
Pro betu Adamových 50 se proto použije **produkce v režimu Připravujeme** s událostí TEST.

## Beta Adamových 50 na produkci

```bash
cd ~/domains/gratulomat.cz/current
php artisan gratulomat:event adamovych-50 --title="Adamových 50" --name=Adam \
  --forms='{"dat":"Adamovi","acc":"Adama","voc":"Adame","gen":"Adama","loc":"Adamovi","ins":"Adamem","poss":"Adamových"}' \
  --year=1977 --date="2027-01-23 18:00" --test
php artisan gratulomat:import-adam50 adamovych-50 ~/domains/adam50.lescisin.cz/adam50-private/data
php artisan gratulomat:organizer adamovych-50 <váš e-mail>   # vypíše přihlašovací odkaz
```

Pak na adam50.lescisin.cz přesměrovat staré odkazy (`.htaccess` v kořeni webu):

```
RewriteEngine On
RewriteRule ^g/([A-Za-z0-9]+)$ https://gratulomat.cz/adamovych-50/g/$1 [R=301,L]
RewriteRule ^(.*)$ https://gratulomat.cz/adamovych-50 [R=302,L]
```

Datum oslavy (`--date`) upravte podle skutečnosti; jde změnit i později v databázi.

## Platby (Stripe)

1. Stripe Dashboard → Developers → API keys: zkopírujte **Secret key** (test: `sk_test_…`).
2. Developers → Webhooks → Add endpoint: `https://gratulomat.cz/api/stripe/webhook`, události
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.expired`,
   `charge.refunded`, `charge.dispute.created`. Zkopírujte **Signing secret** (`whsec_…`).
3. Na serveru do `shared/.env` doplňte `STRIPE_TEST_SECRET`, `STRIPE_TEST_WEBHOOK_SECRET` (a po spuštění
   `STRIPE_LIVE_*` z live režimu Stripe) a `WORKER_TOKEN` (např. `openssl rand -hex 32`, stejná hodnota
   ve `worker/config.php` na Mac Mini). Pak `php artisan config:cache`.

Dokud je web v režimu coming soon, objednávky jsou testovací (číslo `T-…`, příznak test u události)
a nevystavuje se doklad. Testovací karta: `4242 4242 4242 4242`, libovolné datum a CVC.
