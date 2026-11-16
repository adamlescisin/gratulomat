<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<title>Gratulomat – brzy spouštíme</title>
<meta name="description" content="Gratulomat sesbírá historky, fotky a přání od vašich blízkých a složí z nich videoprezentaci, kterou pustíte všem najednou.">
<meta property="og:title" content="Gratulomat – vzpomínky, které se promítají">
<meta property="og:description" content="Historky, fotky a přání od blízkých složené do jedné videoprezentace. Brzy spouštíme.">
<meta property="og:image" content="{{ url('/icon-512.png') }}">
</head>
<body>
<div class="cs">
  <header class="cs-wrap cs-head">
    @include('partials.logo', ['width' => 200])
    @php($isTester = \App\Models\TesterCode::isValidCode((string) request()->cookie(config('gratulomat.tester_cookie'))))
    @if ($isTester)
    <span class="btn-outline" role="status">Přístup testera aktivní</span>
    @else
    <a class="btn-outline" href="#pristup" data-toggle-code aria-controls="pristup" aria-expanded="{{ ($errors->has('code') || !empty($openCode)) ? 'true' : 'false' }}">Mám přístupový kód</a>
    @endif
  </header>

  <main class="cs-wrap cs-main">
    <div class="cs-copy">
      <p class="eyebrow">Brzy spouštíme</p>
      <h1 class="display">Vzpomínky, které se promítají.</h1>
      <p class="lead">Gratulomat sesbírá historky, fotky a přání od vašich blízkých a složí z nich videoprezentaci, kterou pustíte všem najednou – na narozeninách, svatbě i při tichém rozloučení.</p>

      @if (session('waitlist_done'))
        <p class="done" id="cekaci-listina" role="status">Děkujeme! Až spustíme, ozveme se jako první – a první událost v tarifu Párty od nás dostanete zdarma.</p>
      @else
        <form class="wl" id="cekaci-listina" method="post" action="{{ route('waitlist') }}" novalidate>
          @csrf
          <label for="cs-email" class="wl-label">Dejte mi vědět o spuštění</label>
          <div class="wl-row">
            <input id="cs-email" name="email" type="email" autocomplete="email" required placeholder="jana@example.cz" value="{{ old('email') }}">
            <button class="btn-coral" type="submit">Chci vědět první</button>
          </div>
          {{-- past na roboty: skryté pole, které člověk nevyplní --}}
          <input class="sr-only" type="text" name="web" tabindex="-1" autocomplete="off" aria-hidden="true">
          <label class="consent">
            <input type="checkbox" name="consent" value="1" @checked(old('consent'))>
            <span>Souhlasím se zasláním jednoho e-mailu o spuštění a se <a href="{{ route('privacy') }}">zpracováním e-mailové adresy</a>. Odhlásit se můžete kdykoliv.</span>
          </label>
          @if ($errors->hasAny(['email', 'consent']))
            <span class="msg" role="status">{{ $errors->first('email') ?: $errors->first('consent') }}</span>
          @endif
        </form>
      @endif

      <div class="chips" aria-label="Pro jaké příležitosti">
        @foreach (['Narozeniny', 'Svatba', 'Výročí svatby', 'Odchod do důchodu', 'Firemní jubileum', 'Rozlučka', 'Sraz spolužáků', 'Smuteční rozloučení'] as $t)<span>{{ $t }}</span>@endforeach
      </div>
    </div>

    <div class="cs-art">
      <div class="cs-card">
        <button type="button" class="press" data-press aria-label="Zmáčkni a gratuluj">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" aria-hidden="true" style="display:block;width:220px;height:auto"><circle cx="60" cy="61" r="50" fill="#FFF6E9"/><circle cx="60" cy="65" r="36" fill="#B93A22"/><circle cx="60" cy="58" r="36" fill="#FF5A3C"/><ellipse cx="47" cy="43" rx="12" ry="6" fill="#fff" opacity=".3" transform="rotate(-22 47 43)"/><path d="M60 73C41 60 43 43 53 43C56.5 43 59 45 60 47.5C61 45 63.5 43 67 43C77 43 79 60 60 73Z" fill="#FFF6E9"/></svg>
        </button>
        <span>Zmáčkni a gratuluj.</span>
      </div>
    </div>
  </main>

  <section id="pristup" class="cs-wrap code-box" aria-label="Přístup pro testery" @unless ($errors->has('code') || !empty($openCode)) hidden @endunless>
    <form method="post" action="/pristup">
      @csrf
      <div class="field">
        <label for="cs-code">Přístupový kód pro testování</label>
        <input id="cs-code" name="code" placeholder="např. BETA-7K2Q" autocomplete="off" value="{{ old('code') }}">
      </div>
      <button class="btn-ink" type="submit">Vstoupit</button>
      @error('code')<span class="err" role="status">Tento kód neznáme nebo už vypršel.</span>@enderror
    </form>
  </section>

  @include('site.partials.footer')
</div>
<script>
  // Přístupový kód: rozbalit / sbalit. Tlačítko „gratuluj“: malá konfeta.
  document.querySelector('[data-toggle-code]')?.addEventListener('click', e => {
    e.preventDefault();
    const box = document.getElementById('pristup'), open = box.hidden;
    box.hidden = !open; e.currentTarget.setAttribute('aria-expanded', open);
    if (open) document.getElementById('cs-code').focus();
  });
  document.querySelector('[data-press]').addEventListener('click', e => {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const r = e.currentTarget.getBoundingClientRect(), colors = ['#FF5A3C', '#FFC433', '#2EC4A0', '#FFF6E9'];
    for (let i = 0; i < 28; i++) {
      const p = document.createElement('i'), a = Math.random() * Math.PI * 2, d = 80 + Math.random() * 120;
      p.style.cssText = `position:fixed;left:${r.left + r.width / 2}px;top:${r.top + r.height / 2}px;width:8px;height:12px;border-radius:2px;background:${colors[i % 4]};pointer-events:none;transition:transform .9s cubic-bezier(.2,.7,.3,1),opacity .9s;z-index:9`;
      document.body.append(p);
      requestAnimationFrame(() => { p.style.transform = `translate(${Math.cos(a) * d}px,${Math.sin(a) * d - 60}px) rotate(${Math.random() * 540}deg)`; p.style.opacity = 0; });
      setTimeout(() => p.remove(), 1000);
    }
  });
</script>
</body>
</html>
