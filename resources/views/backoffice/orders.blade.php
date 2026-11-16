@extends('backoffice.layout')
@section('title', 'Objednávky')
@section('content')
<div class="kpis">
  @foreach ($kpis as [$label, $value])<div class="kpi"><b>{{ $value }}</b><span>{{ $label }}</span></div>@endforeach
</div>
<section class="panel">
  <form class="row" method="get" action="{{ route('backoffice.orders') }}">
    <select class="inp" name="stav">@foreach ($filters as $k => $l)<option value="{{ $k }}" @selected(request('stav', '') === $k)>{{ $l }}</option>@endforeach</select>
    <select class="inp" name="test">
      <option value="">Ostré i testovací</option>
      <option value="ostre" @selected(request('test') === 'ostre')>Jen ostré</option>
      <option value="test" @selected(request('test') === 'test')>Jen testovací</option>
    </select>
    <input class="inp" name="q" value="{{ request('q') }}" placeholder="Číslo, e-mail nebo adresa">
    <button class="b b--sm">Filtrovat</button>
    <a class="b b--sm b--ghost" href="{{ route('backoffice.orders.csv', request()->query()) }}">⬇ Export CSV</a>
  </form>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Objednávka</th><th>Zákazník</th><th>Událost</th><th>Částka</th><th>Stav</th><th>Doklad</th></tr></thead>
    <tbody>
    @forelse ($orders as $o)
      <tr>
        <td><a href="{{ route('backoffice.order', $o) }}"><strong>{{ $o->number }}</strong></a> @if ($o->is_test)<span class="tag">TEST</span>@endif<br><span class="muted">{{ $o->created_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}</span></td>
        <td>{{ $o->email }}@if ($o->company)<br><span class="muted">{{ $o->company['name'] }}</span>@endif</td>
        <td>{{ $o->config['name'] ?? '' }}<br><span class="muted">/{{ $o->slug }} · {{ config("gratulomat.plans.{$o->plan}.name", $o->plan) }}</span></td>
        <td style="white-space:nowrap">{{ $o->status === 'inquiry' ? '—' : $o->totalCzk() }}@if ($o->discount_code)<br><span class="muted">sleva {{ $o->discount_code }}</span>@endif @if ($o->refunded_halere)<br><span class="muted">vráceno {{ \App\Models\Order::czk($o->refunded_halere) }}</span>@endif</td>
        <td>{{ $o->statusLabel() }}</td>
        <td>@php($inv = $o->invoices->firstWhere('kind', 'invoice')){{ $inv ? ($inv->number ?: (['queued' => 've frontě', 'issued' => 'vystaven', 'sent' => 'odeslán'][$inv->status] ?? $inv->status)) : '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="6" class="muted">Žádné objednávky.</td></tr>
    @endforelse
    </tbody>
  </table></div>
  {{ $orders->links('pagination::simple-default') }}
</section>
@endsection
