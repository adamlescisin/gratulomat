<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="{{ asset('assets/site/wizard.css') }}?v={{ @filemtime(public_path('assets/site/wizard.css')) }}">
<title>{{ $order->config['name'] }} · Gratulomat</title>
</head>
<body class="wz">
<header class="wz-head"><div class="in"><a href="/" aria-label="Gratulomat – úvod" style="display:flex">@include('partials.logo-ink', ['width' => 170])</a></div></header>
<main>
  <div class="done-card" data-done data-status="{{ $order->status }}" data-status-url="{{ route('wizard.status', $order) }}">
    <p class="kick">Objednávka {{ $order->number }}@if ($order->is_test) · test@endif</p>
    @if ($order->status === 'fulfilled')
      <h1 class="display">Hotovo! 🎉</h1>
      <p>Událost <strong>{{ $order->config['name'] }}</strong> je připravená. Na <strong>{{ $order->email }}</strong> jsme poslali odkaz do administrace – tam přidáte gratulanty a rozešlete pozvánky.</p>
      <p>Stránka pro gratulanty: <a href="{{ url($order->event->slug) }}">{{ url($order->event->slug) }}</a></p>
      @auth
        @if (auth()->user()->organizes($order->event))<p><a class="b-go" style="display:inline-flex;align-items:center;text-decoration:none" href="{{ route('organizer.admin', $order->event) }}">Otevřít administraci</a></p>@endif
      @endauth
    @elseif ($order->status === 'inquiry')
      <h1 class="display">Poptávka odeslána</h1>
      <p>Děkujeme. Ozveme se na <strong>{{ $order->email }}</strong> do jednoho pracovního dne s nabídkou a platebním odkazem. Adresu <strong>gratulomat.cz/{{ $order->slug }}</strong> vám zatím podržíme.</p>
    @elseif ($order->status === 'pending' && $order->total_halere === 0)
      <h1 class="display">Potvrďte e-mail</h1>
      <p>Na <strong>{{ $order->email }}</strong> jsme poslali odkaz pro potvrzení. Po kliknutí se událost <strong>{{ $order->config['name'] }}</strong> hned založí. Odkaz platí 24 hodin.</p>
      <p class="hint">Nic nepřišlo? Zkontrolujte složku hromadné pošty, nebo nám napište na {{ config('gratulomat.notify_email') }}.</p>
    @elseif (in_array($order->status, ['pending', 'paid'], true))
      <h1 class="display">Čekáme na potvrzení platby…</h1>
      <p>Jakmile Stripe platbu potvrdí (obvykle během pár vteřin), událost se založí a pošleme vám e-mail. Stránka se obnoví sama.</p>
    @else
      <h1 class="display">Objednávka nebyla dokončena</h1>
      <p>Platba neproběhla nebo vypršela. Můžete <a href="{{ route('wizard') }}">to zkusit znovu</a> – údaje v průvodci zůstaly vyplněné.</p>
    @endif
  </div>
</main>
<script>
  // Úspěch: konfigurace v průvodci už není potřeba. Čekání na platbu: obnovovat stav.
  (() => {
    const box = document.querySelector('[data-done]'), st = box.dataset.status;
    if (['fulfilled', 'inquiry'].includes(st) || (st === 'pending' && {{ $order->total_halere }} === 0)) { try { localStorage.removeItem('gm-wizard-v1'); } catch {} }
    if (['pending', 'paid'].includes(st) && {{ $order->total_halere }} > 0) {
      let n = 0;
      const t = setInterval(async () => {
        if (++n > 60) return clearInterval(t);
        try { const r = await (await fetch(box.dataset.statusUrl, { headers: { Accept: 'application/json' } })).json(); if (r.status !== st) location.reload(); } catch {}
      }, 2000);
    }
  })();
</script>
</body>
</html>
