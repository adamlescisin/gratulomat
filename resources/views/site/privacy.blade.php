<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<title>Ochrana soukromí · Gratulomat</title>
</head>
<body>
<div class="cs">
  <header class="cs-wrap cs-head"><a href="/" aria-label="Gratulomat – úvod">@include('partials.logo', ['width' => 180])</a></header>
  <main class="legal">
    <p class="eyebrow">Ochrana soukromí</p>
    <h1 class="display">Jak nakládáme s vaším e-mailem</h1>
    <p>Gratulomat zatím připravujeme. Do spuštění zpracováváme jediný údaj: e-mailovou adresu, kterou zadáte do formuláře „Dejte mi vědět o spuštění“.</p>
    <h2>Proč a na jakém základě</h2>
    <p>Abychom vám poslali jeden e-mail o spuštění služby (se slevou na první událost). Právním základem je váš souhlas podle čl. 6 odst. 1 písm. a) GDPR.</p>
    <h2>Jak dlouho</h2>
    <p>Do odeslání e-mailu o spuštění, nejdéle 12 měsíců. Pak adresu smažeme. Souhlas můžete kdykoli odvolat e-mailem na <a href="mailto:{{ config('gratulomat.notify_email') }}">{{ config('gratulomat.notify_email') }}</a> – adresu hned odstraníme.</p>
    <h2>Kdo k ní má přístup</h2>
    <p>Jen provozovatel Gratulomatu. Data jsou uložena na serverech společnosti Hostinger v EU; e-maily odesíláme přes službu Resend (datový region EU). Adresu nikomu neprodáváme a nepoužíváme k jiným účelům.</p>
    <h2>Vaše práva</h2>
    <p>Máte právo na přístup k údajům, jejich opravu a výmaz a právo podat stížnost u Úřadu pro ochranu osobních údajů (www.uoou.gov.cz).</p>
    <h2>Cookies</h2>
    <p>Používáme jen nezbytné cookies (relace a ochrana formulářů). Analytické ani reklamní cookies nepoužíváme.</p>
    <p>Úplné zásady ochrany osobních údajů a obchodní podmínky zveřejníme se spuštěním služby.</p>
    <p>Kontakt: <a href="mailto:{{ config('gratulomat.notify_email') }}">{{ config('gratulomat.notify_email') }}</a></p>
  </main>
  @include('site.partials.footer')
</div>
</body>
</html>
