@extends('backoffice.layout')
@section('title', 'Ceník')
@section('content')
@php
    $kc = fn ($n) => number_format($n, 0, ',', ' ').' Kč';
@endphp
<section class="panel">
  <p class="muted" style="margin:0">Základní cena tarifu v celých korunách <strong>včetně DPH 21 %</strong> a volitelná plošná sleva v % s datem konce (platí včetně toho dne, pražský čas). Akční cena se zaokrouhlí na celé koruny a hned se promítne na landing page (přeškrtnutá původní cena), do průvodce, do platby přes Stripe i do dokladu. Slevový kód zákazníka se počítá až z akční ceny. Navýšení tarifu stojí rozdíl aktuálních cen (nikdy méně než 0 Kč). Zaplacené objednávky se nemění. VIP je cena „od“ pro poptávku.</p>
</section>

<section class="panel">
  <form method="post" action="{{ route('backoffice.prices.save') }}">@csrf
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Tarif</th><th>Hosté · sběr · archiv</th><th>Základní cena (Kč)</th><th>Plošná sleva (%)</th><th>Akce platí do</th><th>Teď zákazník platí</th></tr></thead>
      <tbody>
      @foreach ($rows as $code => $r)
        <tr data-price-row>
          <td><strong>{{ $r['name'] }}</strong></td>
          <td class="muted">{{ $plans[$code]['guests'] }} · {{ $plans[$code]['collect_days'] }} dní · {{ $plans[$code]['archive_months'] }} měs.</td>
          <td><input class="inp" type="number" name="plans[{{ $code }}][price]" min="0" step="1" value="{{ old("plans.$code.price", $r['list_price']) }}" required style="width:120px" data-list></td>
          <td><input class="inp" type="number" name="plans[{{ $code }}][sale_percent]" min="0" max="90" step="1" placeholder="—" value="{{ old("plans.$code.sale_percent", $r['sale_percent'] ?: '') }}" style="width:90px" data-pct></td>
          <td><input class="inp" type="date" name="plans[{{ $code }}][sale_until]" value="{{ old("plans.$code.sale_until", $r['sale_until']) }}" data-until></td>
          <td>
            <span data-now>@if ($r['sale_active'])<s class="muted">{{ $kc($r['list_price']) }}</s> <strong>{{ $kc($r['price']) }}</strong>@else{{ $kc($r['price']) }}@endif</span>
            @if ($r['sale_percent'] && ! $r['sale_active'])<br><span class="muted">akce skončila {{ \Carbon\Carbon::parse($r['sale_until'])->format('j. n. Y') }}</span>@endif
          </td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    <div class="row" style="margin-top:14px"><button class="b" type="submit">Uložit ceník</button><span class="muted">Slevu zrušíte smazáním procent.</span></div>
  </form>
</section>

@if ($history)
<section class="panel">
  <h2 style="margin:0">Historie změn</h2>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Kdy</th><th>Kdo</th><th>Změna</th></tr></thead>
    <tbody>
    @foreach ($history as $h)
      <tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($h['at'])->timezone('Europe/Prague')->format('j. n. Y H:i') }}</td><td class="muted">{{ $h['by'] }}</td><td>{!! collect($h['changes'])->map(fn ($c) => e($c))->implode('<br>') !!}</td></tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endif

<script>
  // Náhled akční ceny při psaní (závazný výpočet je na serveru)
  document.querySelectorAll('[data-price-row]').forEach(tr => {
    const list = tr.querySelector('[data-list]'), pct = tr.querySelector('[data-pct]'), until = tr.querySelector('[data-until]'), out = tr.querySelector('[data-now]');
    const kc = n => n.toLocaleString('cs-CZ') + ' Kč';
    const upd = () => {
      const l = parseInt(list.value || '0', 10), p = parseInt(pct.value || '0', 10);
      const today = new Date().toLocaleDateString('sv-SE', { timeZone: 'Europe/Prague' });
      const on = p > 0 && l > 0 && until.value && until.value >= today;
      out.innerHTML = on ? `<s class="muted">${kc(l)}</s> <strong>${kc(Math.round(l * (100 - p) / 100))}</strong>` : kc(l);
      until.required = p > 0;
    };
    [list, pct, until].forEach(i => i.addEventListener('input', upd));
  });
</script>
@endsection
