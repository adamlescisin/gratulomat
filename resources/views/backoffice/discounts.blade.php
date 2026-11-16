@extends('backoffice.layout')
@section('title', 'Slevové kódy')
@section('content')
<section class="panel">
  <p class="muted">Procentní sleva z celé objednávky (tarif i doplňky). Zákazník kód zadá v posledním kroku průvodce, cena se přepočítá na serveru a sleva se ukáže i ve Stripe a na dokladu. Kód se 100 % slevou založí placenou událost zdarma (po potvrzení e-mailu). Na VIP poptávky se kódy nevztahují.</p>
  <form class="row" method="post" action="{{ route('backoffice.discounts.store') }}" style="flex-wrap:wrap">@csrf
    <input class="inp" name="code" placeholder="Kód, např. JARO20" maxlength="40" value="{{ old('code') }}" required style="text-transform:uppercase">
    <input class="inp" type="number" name="percent" min="1" max="100" placeholder="Sleva %" value="{{ old('percent') }}" required style="width:110px">
    <input class="inp" type="number" name="max_uses" min="1" placeholder="Max. použití" value="{{ old('max_uses') }}" style="width:130px">
    <input class="inp" type="date" name="valid_until" title="Platí do (včetně)" value="{{ old('valid_until') }}">
    <input class="inp" name="note" placeholder="Poznámka (pro koho)" maxlength="200" value="{{ old('note') }}">
    <button class="b" type="submit">+ Vytvořit kód</button>
  </form>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Kód</th><th>Sleva</th><th>Použito</th><th>Platí do</th><th>Stav</th><th>Poznámka</th><th></th></tr></thead>
    <tbody>
    @forelse ($codes as $c)
      <tr>
        <td><code>{{ $c->code }}</code></td>
        <td>{{ $c->percent }} %</td>
        <td>{{ $c->used_count }}{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td>
        <td>{{ $c->valid_until?->format('j. n. Y') ?? 'bez omezení' }}</td>
        <td>{{ $c->statusLabel() }}</td>
        <td class="muted">{{ $c->note }}</td>
        <td style="white-space:nowrap">
          <form method="post" action="{{ route('backoffice.discounts.toggle', $c) }}" style="display:inline">@csrf<button class="b b--sm b--ghost">{{ $c->active ? 'Vypnout' : 'Zapnout' }}</button></form>
          @if ($c->used_count === 0)
          <form method="post" action="{{ route('backoffice.discounts.delete', $c) }}" style="display:inline" onsubmit="return confirm('Smazat kód {{ $c->code }}?')">@csrf @method('DELETE')<button class="b b--sm b--ghost">Smazat</button></form>
          @endif
        </td>
      </tr>
    @empty
      <tr><td colspan="7" class="muted">Zatím žádné slevové kódy.</td></tr>
    @endforelse
    </tbody>
  </table></div>
</section>
@endsection
