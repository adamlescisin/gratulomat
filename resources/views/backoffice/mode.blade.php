@extends('backoffice.layout')
@section('title', 'Režim webu')
@section('content')
@php($allDone = count(array_intersect(array_keys($checks), $checked)) === count($checks))
@if ($mode === 'live')<p class="flash" role="status">Web je v ostrém provozu. Coming soon stránka je vypnutá.</p>@endif

<section class="panel">
  <h2>Co uvidí veřejnost</h2>
  <div class="modes">
    @foreach (['coming_soon' => ['Připravujeme', 'Veřejnost vidí coming soon stránku s čekací listinou. Testovací události a odkazy jejich hostů fungují.'], 'maintenance' => ['Údržba', 'Dočasná stránka „Jsme hned zpátky“. Běžící události a odkazy hostů dál fungují.'], 'live' => ['Ostrý provoz', 'Celý web je veřejný. Přepíná se tlačítkem dole po dokončení kontroly.']] as $key => [$label, $desc])
      <form method="post" action="{{ route('backoffice.mode') }}">@csrf
        <input type="hidden" name="mode" value="{{ $key }}">
        <button class="mode" type="submit" aria-pressed="{{ $mode === $key ? 'true' : 'false' }}" @disabled($key === 'live')><strong>{{ $label }}</strong><span>{{ $desc }}</span></button>
      </form>
    @endforeach
  </div>
  <span class="muted">Provozovatelé a testeři s platným kódem vidí vždy celý web. Hodnota z back-office přebíjí <code>SITE_MODE</code> v .env.</span>
</section>

<div class="cols">
  <section class="panel">
    <h2>Přístup testerů</h2>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Kód</th><th>Pro koho</th><th>Platí do</th><th>Použito</th><th></th></tr></thead>
      <tbody>
      @foreach ($envCodes as $c)
        <tr><td><strong>{{ strtoupper(trim($c)) }}</strong></td><td class="muted">z .env (TESTER_CODES)</td><td>—</td><td>—</td><td></td></tr>
      @endforeach
      @foreach ($codes as $c)
        <tr>
          <td><strong>{{ $c->code }}</strong> @unless ($c->isValid())<span class="tag">neplatný</span>@endunless</td>
          <td>{{ $c->label }}</td>
          <td>{{ $c->valid_until?->format('j. n. Y') ?? '—' }}</td>
          <td>{{ $c->uses }}×{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td>
          <td><form method="post" action="{{ route('backoffice.codes.delete', $c) }}">@csrf @method('DELETE')<button class="b b--sm b--ghost">Zrušit</button></form></td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <form class="row" method="post" action="{{ route('backoffice.codes') }}">@csrf
      <input class="inp" name="label" placeholder="Pro koho (např. Rodina)" maxlength="120">
      <input class="inp" type="date" name="valid_until" title="Platí do">
      <input class="inp" type="number" name="max_uses" min="1" placeholder="Max. použití" style="width:130px">
      <button class="b" type="submit">+ Nový kód</button>
    </form>
    <span class="muted">Tester zadá kód na úvodní stránce (Mám přístupový kód) nebo na <code>{{ url('/pristup') }}</code>.</span>
  </section>

  <section class="panel">
    <h2>Kontrola před spuštěním</h2>
    <form method="post" action="{{ route('backoffice.checklist') }}" style="display:flex;flex-direction:column;gap:6px">@csrf
      @foreach ($checks as $key => $label)
        <label class="check @if (in_array($key, $checked, true)) is-on @endif"><input type="checkbox" name="checks[]" value="{{ $key }}" @checked(in_array($key, $checked, true))> <span>{{ $label }}</span></label>
      @endforeach
      <p><button class="b b--ghost b--sm" type="submit">Uložit kontrolu</button></p>
    </form>
    <span class="muted">Hotovo {{ count(array_intersect(array_keys($checks), $checked)) }} z {{ count($checks) }}. Automatické ověření (Stripe, MyÚčto, e-mail) přibude s platbami.</span>
    <form method="post" action="{{ route('backoffice.mode') }}" style="display:flex;flex-direction:column;gap:10px">@csrf
      <input type="hidden" name="mode" value="live">
      @if ($allDone && $mode !== 'live')
        <label class="check"><input type="checkbox" name="confirm" value="1" required> <span>Rozumím: web uvidí všichni a coming soon stránka zmizí.</span></label>
      @endif
      <button class="b b--coral" type="submit" @disabled(! $allDone || $mode === 'live')>{{ $mode === 'live' ? 'Web běží naostro' : ($allDone ? 'Spustit ostrý provoz' : 'Spustit ostrý provoz (nejdřív dokončete kontrolu)') }}</button>
    </form>
  </section>
</div>
@endsection
