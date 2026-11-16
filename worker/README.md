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

## Spuštění

```bash
cp worker/config.example.php worker/config.php   # doplnit tokeny
php worker/bin/worker.php                         # nebo launchd (viz níže)
```

Stav úloh je přímo stavem dokladu v Gratulomatu; když worker neběží, úlohy čekají
a back-office po 24 h upozorní.
