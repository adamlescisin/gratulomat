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
<section class="panel">
  <h2>Poslední události</h2>
  @include('backoffice.partials.events-table', ['events' => $events])
  <p><a href="{{ route('backoffice.events') }}">Všechny události →</a></p>
</section>
@endsection
