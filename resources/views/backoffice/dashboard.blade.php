@extends('backoffice.layout')
@section('title', 'Přehled')
@section('content')
<div class="kpis">
  @foreach ($kpis as [$label, $value])<div class="kpi"><b>{{ $value }}</b><span>{{ $label }}</span></div>@endforeach
</div>
@php
  $hb = \App\Models\Setting::get('worker_heartbeat');
  $hbAt = $hb ? \Illuminate\Support\Carbon::parse($hb['at']) : null;
  $queued = \App\Models\WorkerJob::whereIn('status', ['queued', 'running'])->count();
  $failed = \App\Models\WorkerJob::where('status', 'failed')->count();
@endphp
<section class="panel">
  <h2 style="margin:0">Worker na Mac Mini (doklady v MyÚčtu)</h2>
  <p style="margin:0">
    @if (! $hbAt) <strong>Zatím se neozval.</strong> Návod k instalaci je v <code>worker/README.md</code>.
    @elseif ($hbAt->lt(now()->subMinutes(10))) <strong style="color:#B3261E">Neozval se od {{ $hbAt->timezone('Europe/Prague')->format('j. n. Y H:i') }}</strong> – zkontrolujte, že Mac Mini běží a worker je spuštěný.
    @else <strong style="color:#11806A">V pořádku</strong> – naposledy {{ $hbAt->timezone('Europe/Prague')->format('j. n. H:i') }}{{ $hb['host'] ? ' ('.$hb['host'].', verze '.$hb['version'].')' : '' }}.
    @endif
    Ve frontě {{ $queued }} úloh.
    @if ($failed)<a href="{{ route('backoffice.orders') }}">{{ $failed }} s chybou</a>@endif
  </p>
</section>
@php
  $sch = \App\Models\Setting::get('scheduler_heartbeat');
  $schAt = $sch ? \Illuminate\Support\Carbon::parse($sch) : null;
  $mailQueued = \App\Models\Household::where(fn ($q) => $q->whereNotNull('invite_queued_at')->whereNull('invited_at'))->orWhere(fn ($q) => $q->whereNotNull('reminder_queued_at')->whereNull('reminded_at'))->count();
  $mailFailed = \App\Models\Household::whereNotNull('mail_error')->whereNull('invite_queued_at')->whereNull('invited_at')->count();
  $mailErr = \App\Models\Setting::get('guest_mail_last_error');
@endphp
<section class="panel">
  <h2 style="margin:0">Plánovač a e-maily hostům</h2>
  <p style="margin:0">
    @if (! $schAt) <strong style="color:#B3261E">Plánovač se ještě nespustil.</strong> Pozvánky a připomínky se neodesílají a archivace neběží. V hPanelu → Pokročilé → Cron Jobs přidejte úlohu typu <em>Custom</em>, <em>každou minutu</em>, s příkazem (hPanel nepovoluje znaky jako <code>&gt;</code> nebo <code>&amp;&amp;</code>, proto skript):
      <code style="display:block;margin:.4rem 0;user-select:all">/bin/sh {{ preg_replace('~/releases/[^/]+$~', '/current', base_path()) }}/bin/cron.sh</code>
      Chyby najdete v <code>storage/logs/cron-error.log</code>.
    @elseif ($schAt->lt(now()->subMinutes(5))) <strong style="color:#B3261E">Neběží od {{ $schAt->timezone('Europe/Prague')->format('j. n. Y H:i') }}</strong> – zkontrolujte cron v hPanelu: každou minutu, příkaz <code style="user-select:all">/bin/sh {{ preg_replace('~/releases/[^/]+$~', '/current', base_path()) }}/bin/cron.sh</code>; chyby v <code>storage/logs/cron-error.log</code>.
    @else <strong style="color:#11806A">V pořádku</strong> – naposledy {{ $schAt->timezone('Europe/Prague')->format('H:i') }}.
    @endif
    Ve frontě {{ $mailQueued }} e-mailů.
    @if ($mailFailed)<strong style="color:#B3261E">{{ $mailFailed }} se nepodařilo odeslat.</strong>@endif
  </p>
  @if ($mailErr && \Illuminate\Support\Carbon::parse($mailErr['at'])->gt(now()->subDays(2)))
    <p class="muted" style="margin:.4rem 0 0">Poslední chyba odesílání ({{ \Illuminate\Support\Carbon::parse($mailErr['at'])->timezone('Europe/Prague')->format('j. n. H:i') }}): <code>{{ $mailErr['message'] }}</code></p>
  @endif
</section>
<section class="panel">
  <h2>Poslední události</h2>
  @include('backoffice.partials.events-table', ['events' => $events])
  <p><a href="{{ route('backoffice.events') }}">Všechny události →</a></p>
</section>
@endsection
