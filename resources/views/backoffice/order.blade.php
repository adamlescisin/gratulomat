@extends('backoffice.layout')
@section('title', 'Objednávka '.$order->number)
@section('content')
@php($czk = fn ($h) => \App\Models\Order::czk((int) $h))
<p><a href="{{ route('backoffice.orders') }}">← Objednávky</a></p>
<div class="cols">
<section class="panel" style="flex:2 1 460px">
  <h2 style="margin:0">{{ $order->config['name'] ?? $order->number }} @if ($order->is_test)<span class="tag">TEST</span>@endif</h2>
  <p class="muted" style="margin:0">{{ $order->number }} · {{ $order->statusLabel() }} · vytvořeno {{ $order->created_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}@if ($order->paid_at) · zaplaceno {{ $order->paid_at->timezone('Europe/Prague')->format('j. n. Y H:i') }}@endif</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Položka</th><th>Množství</th><th style="text-align:right">Celkem</th></tr></thead>
    <tbody>
    @forelse ($order->lines as $l)
      <tr><td>{{ $l['name'] }}</td><td>{{ $l['qty'] }}</td><td style="text-align:right">{{ $czk($l['total']) }}</td></tr>
    @empty
      <tr><td colspan="3" class="muted">{{ $order->status === 'inquiry' ? 'VIP poptávka – cena zatím nestanovená.' : 'Bez položek (ruční založení).' }}</td></tr>
    @endforelse
    </tbody>
    <tfoot>
      <tr><th colspan="2">Celkem s DPH</th><th style="text-align:right">{{ $czk($order->total_halere) }}</th></tr>
      <tr><td colspan="2" class="muted">z toho DPH {{ config('gratulomat.vat_rate') }} %</td><td style="text-align:right" class="muted">{{ $czk($order->vat_halere) }}</td></tr>
      @if ($order->refunded_halere)<tr><td colspan="2">Vráceno</td><td style="text-align:right">−{{ $czk($order->refunded_halere) }}</td></tr>@endif
    </tfoot>
  </table></div>

  <h3 style="margin:8px 0 0">Doklady</h3>
  @forelse ($order->invoices as $inv)
    <div class="row" style="align-items:center">
      <span>{{ $inv->kind === 'credit_note' ? 'Dobropis' : 'Doklad' }} {{ $inv->number ?: '(bez čísla)' }}@if ($inv->amount_halere) ({{ $czk($inv->amount_halere) }})@endif · {{ ['queued' => 've frontě', 'issued' => 'vystaven', 'sent' => 'odeslán zákazníkovi'][$inv->status] ?? $inv->status }}</span>
      @if ($inv->pdf_path)<a class="b b--sm b--ghost" href="{{ route('backoffice.order.pdf', [$order, $inv]) }}">PDF</a>@endif
    </div>
  @empty
    <p class="muted" style="margin:0">{{ $order->is_test ? 'Testovací objednávka – doklad se nevystavuje.' : ($order->kind === 'manual' ? 'Ruční založení bez platby – bez dokladu.' : 'Zatím žádný doklad.') }}</p>
  @endforelse
  @foreach ($jobs as $job)
    @if ($job->status !== 'done')
      <div class="row" style="align-items:center">
        <span class="muted">Worker #{{ $job->id }} ({{ $job->type === 'credit_note' ? 'dobropis' : 'doklad' }}): {{ ['queued' => 've frontě', 'running' => 'zpracovává se', 'failed' => 'chyba'][$job->status] ?? $job->status }}, pokusů {{ $job->attempts }}@if ($job->error) – <span style="color:#B3261E">{{ \Illuminate\Support\Str::limit($job->error, 160) }}</span>@endif</span>
        @if ($job->status === 'failed')<form method="post" action="{{ route('backoffice.order.retry', [$order, $job]) }}">@csrf<button class="b b--sm b--ghost">Zkusit znovu</button></form>@endif
      </div>
    @endif
  @endforeach

  <h3 style="margin:8px 0 0">Historie</h3>
  <ul class="muted" style="margin:0;padding-left:18px">
    <li>{{ $order->created_at->timezone('Europe/Prague')->format('j. n. Y H:i') }} – objednávka vytvořena</li>
    @if ($order->paid_at)<li>{{ $order->paid_at->timezone('Europe/Prague')->format('j. n. Y H:i') }} – zaplaceno přes Stripe</li>@endif
    @foreach ($order->history ?? [] as $h)
      <li>{{ \Illuminate\Support\Carbon::parse($h['at'])->timezone('Europe/Prague')->format('j. n. Y H:i') }} – {{ $h['text'] }}@if ($h['by']) <span>({{ $h['by'] }})</span>@endif</li>
    @endforeach
  </ul>
</section>

<div style="flex:1 1 300px;display:flex;flex-direction:column;gap:22px;min-width:0">
<section class="panel">
  <h3 style="margin:0">Zákazník</h3>
  <p style="margin:0"><a href="mailto:{{ $order->email }}">{{ $order->email }}</a></p>
  @if ($order->company)
    <p class="muted" style="margin:0">{{ $order->company['name'] }}<br>IČO {{ $order->company['ico'] }}@if ($order->company['dic'] ?? null) · DIČ {{ $order->company['dic'] }}@endif<br>{{ $order->company['street'] }}, {{ $order->company['zip'] }} {{ $order->company['city'] }}</p>
  @endif
  @if ($order->discount_code)<p class="muted" style="margin:0">Slevový kód {{ $order->discount_code }} ({{ $order->discount_percent }} %, −{{ $czk($order->discount_halere) }})</p>@endif
  @if ($order->stripeUrl())<p style="margin:0"><a href="{{ $order->stripeUrl() }}" target="_blank" rel="noopener">Platba ve Stripe ↗</a></p>@endif
  @if ($order->event)
    <div class="row"><a class="b b--sm" href="{{ route('organizer.admin', $order->event) }}">Administrace události</a><a class="b b--sm b--ghost" href="{{ route('event.show', $order->event) }}" target="_blank">Stránka ↗</a></div>
    <form method="post" action="{{ route('backoffice.order.resend', $order) }}">@csrf<button class="b b--sm b--ghost">Znovu poslat odkaz do administrace</button></form>
  @endif
</section>

@if ($order->kind === 'vip' && in_array($order->status, ['inquiry', 'pending', 'expired'], true))
<section class="panel">
  <h3 style="margin:0">VIP poptávka</h3>
  <p class="muted" style="margin:0">Pošlete zákazníkovi platební odkaz s dohodnutou cenou (platí 23 hodin), nebo událost založte bez platby.</p>
  @if ($order->payment_url && $order->status === 'pending')<p class="muted" style="margin:0">Poslední odkaz: <a href="{{ $order->payment_url }}" target="_blank" rel="noopener">otevřít</a> · platí do {{ $order->expires_at?->timezone('Europe/Prague')->format('j. n. H:i') }}</p>@endif
  <form class="row" method="post" action="{{ route('backoffice.order.vip', $order) }}">@csrf
    <input class="inp" type="number" name="price" min="100" step="1" placeholder="Cena Kč s DPH" value="{{ old('price', config('gratulomat.plans.vip.price')) }}" style="width:150px" required>
    <button class="b b--sm b--coral">Poslat platební odkaz</button>
  </form>
  @if ($order->status === 'inquiry')
    <form method="post" action="{{ route('backoffice.order.vipManual', $order) }}" onsubmit="return confirm('Založit VIP událost bez platby?')">@csrf<button class="b b--sm b--ghost">Založit bez platby</button></form>
  @endif
</section>
@endif

@if ($order->refundableHalere() > 0)
<section class="panel">
  <h3 style="margin:0">Vrátit peníze</h3>
  <p class="muted" style="margin:0">Vrácení proběhne přes Stripe na kartu zákazníka. Lze vrátit až {{ $czk($order->refundableHalere()) }}.@unless ($order->is_test) K vráceným penězům vystaví worker v MyÚčtu dobropis.@endunless Událost zůstává aktivní.</p>
  <form method="post" action="{{ route('backoffice.order.refund', $order) }}" style="display:flex;flex-direction:column;gap:10px" onsubmit="return confirm('Opravdu vrátit peníze? Vrácení nejde vzít zpět.')">@csrf
    <input class="inp" type="number" name="amount" min="1" step="0.01" max="{{ $order->refundableHalere() / 100 }}" placeholder="Částka Kč (prázdné = vše)">
    <input class="inp" name="reason" maxlength="200" placeholder="Důvod (objeví se na dobropisu)" required>
    <button class="b b--sm">Vrátit přes Stripe</button>
  </form>
</section>
@endif

@if (in_array($order->status, ['pending', 'inquiry'], true))
<section class="panel">
  <form method="post" action="{{ route('backoffice.order.cancel', $order) }}" onsubmit="return confirm('Zrušit objednávku a uvolnit adresu?')">@csrf<button class="b b--sm b--ghost">Zrušit objednávku</button></form>
</section>
@endif

<section class="panel">
  <h3 style="margin:0">Interní poznámka</h3>
  <form method="post" action="{{ route('backoffice.order.note', $order) }}" style="display:flex;flex-direction:column;gap:10px">@csrf
    <textarea class="inp" name="note" rows="3" maxlength="2000" style="padding:10px">{{ $order->note }}</textarea>
    <button class="b b--sm b--ghost" style="align-self:flex-start">Uložit</button>
  </form>
</section>
</div>
</div>
@endsection
