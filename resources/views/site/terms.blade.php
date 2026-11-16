<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<title>Obchodní podmínky · Gratulomat</title>
</head>
<body>
<div class="cs">
  <header class="cs-wrap cs-head"><a href="/" aria-label="Gratulomat – úvod">@include('partials.logo', ['width' => 180])</a></header>
  <main class="legal">
    <p class="eyebrow">Obchodní podmínky</p>
    <h1 class="display">Obchodní podmínky</h1>
    <p>Úplné obchodní podmínky, reklamační řád a podmínky zpracování osobních údajů hostů zveřejníme se spuštěním služby.</p>
    <p>Gratulomat je zatím v testovacím provozu: platby probíhají v testovacím režimu Stripe a nic se neúčtuje. Testovací události mažeme nejpozději 30 dní po skončení testu.</p>
    <p>Dotazy: <a href="mailto:{{ config('gratulomat.notify_email') }}">{{ config('gratulomat.notify_email') }}</a></p>
  </main>
  @include('site.partials.footer')
</div>
</body>
</html>
