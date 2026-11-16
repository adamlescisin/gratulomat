<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<meta name="robots" content="noindex">
<link rel="stylesheet" href="{{ asset('assets/site/wizard.css') }}?v={{ @filemtime(public_path('assets/site/wizard.css')) }}">
<title>Založit událost · Gratulomat</title>
</head>
<body class="wz">
@if ($testMode)
  <div class="testbar" role="status">Testovací režim – platba proběhne přes Stripe v testovacím režimu (karta 4242 4242 4242 4242, libovolné datum a CVC). Nic se neúčtuje.</div>
@endif
<header class="wz-head">
  <div class="in">
    <a href="/" aria-label="Gratulomat – zpět na úvod" style="display:flex">@include('partials.logo-ink', ['width' => 170])</a>
    <nav class="steps" aria-label="Kroky průvodce" data-steps></nav>
  </div>
</header>

<main class="wz-main">
  <form class="wz-card" method="post" action="{{ route('wizard.submit') }}" data-wizard novalidate>
    @csrf
    <input type="hidden" name="config" data-config>
    <div style="display:flex;flex-direction:column;gap:8px">
      <p class="kick">Krok <span data-step-no>1</span> ze 7</p>
      <h1 class="display" data-step-title></h1>
      <p class="lead" data-step-lead></p>
    </div>

    @if ($cancelled)<p class="err" role="alert">Platba byla přerušena. Údaje zůstaly vyplněné – můžete to zkusit znovu.</p>@endif
    @if ($errors->any())<p class="err" role="alert">{{ $errors->first() }}</p>@endif

    {{-- 1 Událost --}}
    <section class="step" data-step="0">
      <div class="fld"><label for="w-name">Název události</label><input class="inp" id="w-name" data-k="name" placeholder="např. Janiných 60" maxlength="140"></div>
      <div class="fld">
        <label for="w-slug">Adresa stránky</label>
        <div class="slugbox"><span>gratulomat.cz/</span><input id="w-slug" data-k="slug" maxlength="30" aria-describedby="w-slug-msg" autocomplete="off"></div>
        <p class="msg" id="w-slug-msg" role="status" data-slug-msg></p>
        <div class="sugg" data-sugg hidden></div>
      </div>
      <div class="fld"><span class="lbl">Příležitost</span><div class="grid" data-options="occasion"></div></div>
      <p class="note" data-funeral-note hidden>U smutečního rozloučení mohou hosté posílat vzpomínky a kondolence i <strong>po datu rozloučení</strong>. Hravé prvky vypneme a nabídneme klidný vzhled „Tichá vzpomínka“.</p>
      <div class="fld" style="max-width:280px"><label for="w-date" data-date-label>Datum události</label><input class="inp" id="w-date" type="date" data-k="date"></div>
    </section>

    {{-- 2 Pro koho --}}
    <section class="step" data-step="1" hidden>
      <div class="grid grid--wide" data-options="recipient"></div>
      <div class="fld"><label for="w-rec" data-rec-label>Jméno oslavence</label><input class="inp" id="w-rec" data-k="recName" maxlength="120"></div>
      <div class="fld" style="max-width:280px">
        <label for="w-year" data-year-label>Rok narození</label>
        <input class="inp" id="w-year" inputmode="numeric" data-k="startYear" placeholder="např. 1967" maxlength="4">
        <span class="hint">Podle něj rozdělíme videoprezentaci do kapitol po desetiletích.</span>
      </div>
    </section>

    {{-- 3 Gratulanti --}}
    <section class="step" data-step="2" hidden>
      <div class="fld">
        <label for="w-guests" data-guests-label>Kolik gratulantů pozvete?</label>
        <div style="display:flex;align-items:baseline;gap:12px"><span class="big" data-guests-out>20</span><span class="hint" data-guests-hint></span></div>
        <input id="w-guests" type="range" min="1" max="300" data-k="guests">
        <div class="scale"><span>1</span><span>5 · Ochutnávka</span><span>25 · Párty</span><span>150 · Premiéra</span><span>300</span></div>
        <span class="hint">Pár sdílí jeden odkaz – počítá se jako jeden <span data-guest-one>gratulant</span>.</span>
      </div>
      <div class="fld" data-toggles="guestFeatures"></div>
    </section>

    {{-- 4 Vzhled --}}
    <section class="step" data-step="3" hidden>
      <div class="themes" data-themes></div>
      <div class="fld"><span class="lbl">Režim</span><div class="seg" data-options="mode"></div></div>
    </section>

    {{-- 5 Funkce --}}
    <section class="step" data-step="4" hidden>
      <div class="fld" data-toggles="features"></div>
    </section>

    {{-- 6 Doba a doplňky --}}
    <section class="step" data-step="5" hidden>
      <div class="fld"><span class="lbl" data-collect-label>Jak dlouho sbírat gratulace</span><div class="pills" data-options="collect"></div><span class="hint" data-collect-note></span></div>
      <div class="fld"><span class="lbl">Jak dlouho mít videoprezentaci a archiv po události</span><div class="pills" data-options="archive"></div></div>
      <div class="fld"><span class="lbl">Doplňky</span><div data-toggles="addons" style="display:flex;flex-direction:column;gap:10px"></div></div>
    </section>

    {{-- 7 Shrnutí --}}
    <section class="step" data-step="6" hidden>
      <div class="recap" data-recap></div>
      <div class="fld">
        <label for="w-email">Váš e-mail</label>
        <input class="inp" id="w-email" type="email" name="email" autocomplete="email" placeholder="jana@example.cz" value="{{ old('email') }}" required>
        <span class="hint">Pošleme sem odkaz do administrace a daňový doklad. Žádné heslo – přihlásíte se odkazem z e-mailu.</span>
      </div>
      <label class="chk"><input type="checkbox" name="company" value="1" data-company @checked(old('company'))> <span>Nakupuji na firmu (doklad s IČO a DIČ)</span></label>
      <div class="company" data-company-fields @unless (old('company')) hidden @endunless>
        <input class="inp" name="company_name" placeholder="Název firmy" value="{{ old('company_name') }}">
        <input class="inp" name="company_ico" placeholder="IČO" inputmode="numeric" maxlength="8" value="{{ old('company_ico') }}">
        <input class="inp" name="company_dic" placeholder="DIČ (nepovinné)" value="{{ old('company_dic') }}">
        <input class="inp" name="company_street" placeholder="Ulice a číslo" value="{{ old('company_street') }}">
        <input class="inp" name="company_city" placeholder="Obec" value="{{ old('company_city') }}">
        <input class="inp" name="company_zip" placeholder="PSČ" inputmode="numeric" maxlength="6" value="{{ old('company_zip') }}">
      </div>
      <div class="fld">
        <label for="w-discount">Slevový kód <span class="hint" style="font-weight:400">(nepovinné)</span></label>
        <div class="discount"><input class="inp" id="w-discount" data-k="discount" autocomplete="off" placeholder="např. JARO20"><button type="button" class="b-line" data-discount-apply>Použít</button></div>
        <span class="msg" data-discount-msg>@error('discount'){{ $message }}@enderror</span>
      </div>
      @if ($operator)
      <label class="chk manual"><input type="checkbox" name="manual" value="1" data-manual @checked(old('manual'))> <span><strong>Založit ručně bez platby</strong> (provozovatel) – událost se založí hned, organizátorovi přijde odkaz do administrace. Bez platby a bez dokladu.</span></label>
      @endif
      <label class="chk" data-terms-row><input type="checkbox" name="terms" value="1" data-terms @checked(old('terms'))> <span>Souhlasím s <a href="{{ route('terms') }}" target="_blank">obchodními podmínkami</a> a beru na vědomí <a href="{{ route('privacy') }}" target="_blank">zásady ochrany osobních údajů</a></span></label>
    </section>

    <div class="nav">
      <button type="button" class="b-line" data-back hidden>← Zpět</button>
      <span class="sp"></span>
      <span class="block" data-block></span>
      <button type="button" class="b-go" data-next>Pokračovat →</button>
    </div>
  </form>

  <aside class="aside" aria-label="Souhrn ceny" aria-live="polite">
    <span class="k">Váš tarif</span>
    <div class="tier"><b data-tier>Ochutnávka</b><span data-total>0 Kč</span></div>
    <div class="lines" data-lines></div>
    <div class="why"><span>Proč tento tarif</span><div data-reasons></div></div>
    <div class="meta">
      <span>Gratulanti: <span data-meta-guests></span> / <span data-meta-cap></span></span>
      <span>Sběr: <span data-meta-collect></span> · Archiv: <span data-meta-archive></span></span>
      <span>Ceny včetně DPH. Platba kartou, Apple Pay nebo Google Pay přes Stripe.</span>
    </div>
  </aside>
</main>
<script>
window.GMW = {!! json_encode([
    'preset' => $preset,
    'operator' => $operator,
    'old' => old('config') ? json_decode(old('config'), true) : null,
    'plans' => collect(config('gratulomat.plans'))->map(fn ($p) => ['name' => $p['name'], 'guests' => $p['guests']]),
    'urls' => ['slug' => url('/api/slug-check'), 'quote' => url('/api/quote')],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!};
</script>
<script src="{{ asset('assets/site/wizard.js') }}?v={{ @filemtime(public_path('assets/site/wizard.js')) }}" defer></script>
</body>
</html>
