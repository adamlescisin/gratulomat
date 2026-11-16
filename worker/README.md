# Pomocný worker (Mac Mini)

Běží doma na Mac Mini bez veřejné adresy. Každých 30 s se ptá API Gratulomatu
(`GET /api/worker/jobs`, token workeru) a zpracuje úlohy:

| Úloha | Co dělá |
| --- | --- |
| `video` | převod videa / hlasu na H.264/AAC (ffmpeg) a náhled |
| `mp4` | nahrání videoprezentace v headless Chromiu do 1080p MP4 |
| `invoice` | daňový doklad v lokálním MyÚčtu (koncept → vystavit → uhrazeno → PDF) |
| `credit_note` | dobropis k dokladu při vrácení peněz |

Výsledky (soubory, číslo a PDF dokladu) nahrává zpět přes API.

## MyÚčto

- Token: MyÚčto → Firma → API tokeny → nový token, rozsah `read_write`, vázaný na firmu Gratulomat, expirace max. 730 dní.
- Token je jen v `worker/config.php` na Mac Mini, nikdy na Hostingeru ani v repozitáři.
- Staging používá samostatnou testovací firmu (vlastní token).
- Před ostrým provozem ověřit na testovací firmě, že `prices_include_vat: true` + cena včetně DPH dává 990 Kč = 818,18 + 171,82.

## Instalace na Mac Mini

1. **PHP:** `brew install php` (stačí 8.1+, s curl), ověřit `php -v`.
2. **Kód:** stačí složka `worker/` z repozitáře, např. `git clone https://github.com/adamlescisin/gratulomat.git ~/gratulomat`.
3. **Konfigurace:** `cp worker/config.example.php worker/config.php` a doplnit:
   - `gratulomat_token` = stejná hodnota jako `WORKER_TOKEN` v `shared/.env` na serveru (např. `openssl rand -hex 32`, na serveru pak `php artisan config:cache`),
   - `myucto_url` = adresa lokálního MyÚčta (např. `http://127.0.0.1:8080`),
   - `myucto_token` = `mi_pat_…` (rozsah `read_write`, vázaný na firmu),
   - `consumer_client_address` = adresa provozovatele pro souhrnného klienta „Koncoví zákazníci“.
   Soubor `config.php` je v `.gitignore`; tokeny nikam neposílejte.
4. **Kontrola:** `php worker/bin/check.php` (prostředí, MyÚčto, Gratulomat; nic nezpracuje).
   `php worker/bin/check.php --test-invoice` navíc založí v MyÚčtu **koncept** dokladu 990 Kč se slevou −198 Kč
   (ověří výpočet DPH při cenách s DPH i záporný řádek slevy); koncept pak v MyÚčtu smažte.
5. **Zkušební běh:** `php worker/bin/worker.php --once` (jeden průchod frontou).
6. **Služba (launchd):** zkopírovat `worker/launchd/cz.gratulomat.worker.plist` do `~/Library/LaunchAgents/`,
   nahradit `CESTA_K_REPO` (a cestu k php podle `which php`), pak
   `launchctl load -w ~/Library/LaunchAgents/cz.gratulomat.worker.plist`.
   Log: `tail -f /tmp/gratulomat-worker.log /tmp/gratulomat-worker.err`. Po aktualizaci kódu:
   `launchctl kickstart -k gui/$(id -u)/cz.gratulomat.worker`.
   Mac Mini nesmí usínat (Nastavení → Úspora energie → zabránit automatickému uspání).

Back-office (Přehled) ukazuje, kdy se worker naposledy ozval.

## Spuštění

```bash
cp worker/config.example.php worker/config.php   # doplnit tokeny
php worker/bin/worker.php                         # nebo launchd (viz níže)
```

Stav úloh je přímo stavem dokladu v Gratulomatu; když worker neběží, úlohy čekají
a back-office po 24 h upozorní.
