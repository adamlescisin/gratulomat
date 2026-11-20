@extends('backoffice.layout')
@section('title', $event->title)
@section('content')
@php($loc = fn ($d) => $d?->timezone('Europe/Prague')->format('Y-m-d\TH:i'))
@php($owner = $event->organizers->firstWhere('pivot.role', 'owner'))
<p><a href="{{ route('backoffice.events') }}">← Události</a></p>
<div class="cols">
<section class="panel" style="flex:2 1 460px">
  <p class="muted" style="margin:0">/{{ $event->slug }} · {{ config("gratulomat.plans.{$event->plan}.name", $event->plan) }} · {{ $event->type }}@if ($event->is_test) · <span class="tag">TEST</span>@endif</p>
  <form method="post" action="{{ route('backoffice.event.save', $event) }}" class="ev-form">@csrf
    <label>Název události<input class="inp" name="title" maxlength="140" value="{{ old('title', $event->title) }}" required></label>
    <label>{{ $event->isFuneral() ? 'Jméno zesnulé / zesnulého' : 'Jméno oslavence (1. pád)' }}<input class="inp" name="recipient_name" maxlength="120" value="{{ old('recipient_name', $event->recipient_name) }}" required>
      <span class="muted">Tvary pro texty se přepočítají samy (např. „{{ $event->form('dat') }}“).</span></label>
    <label>E-mail organizátora (vlastníka)<input class="inp" type="email" name="owner_email" value="{{ old('owner_email', $owner?->email) }}" required>
      <span class="muted">Změna převede přístup do administrace na nový e-mail a upraví i e-mail v objednávce.</span></label>
    <div class="grid2">
      <label>Datum a čas události<input class="inp" type="datetime-local" name="event_date" value="{{ old('event_date', $loc($event->event_date)) }}"> <small class="muted">nepovinné</small></label>
      <label>Uzávěrka sběru vzkazů<input class="inp" type="datetime-local" name="collect_until" value="{{ old('collect_until', $loc($event->collect_until)) }}" required></label>
      <label>Viditelnost (archiv) do<input class="inp" type="date" name="archive_until" value="{{ old('archive_until', $event->archive_until?->toDateString()) }}">
        <span class="muted">Po tomto dni stránka a videoprezentace přestanou být dostupné.</span></label>
      <label>Limit hostů<input class="inp" type="number" name="guest_limit" min="1" value="{{ old('guest_limit', $event->limit('guests')) }}"></label>
    </div>
    <div class="row"><button class="b">Uložit</button><a class="b b--ghost" href="{{ route('organizer.admin', $event) }}">Administrace události</a><a class="b b--ghost" href="{{ route('event.show', $event) }}" target="_blank">Stránka ↗</a></div>
  </form>
</section>
<section class="panel" style="flex:1 1 260px">
  <h3 style="margin:0">Organizátoři</h3>
  <ul class="muted" style="margin:0;padding-left:18px">@forelse ($event->organizers as $u)<li>{{ $u->email }} ({{ $u->pivot->role }})</li>@empty<li>žádný</li>@endforelse</ul>
  <h3 style="margin:0">Objednávky</h3>
  <ul class="muted" style="margin:0;padding-left:18px">@forelse ($orders as $o)<li><a href="{{ route('backoffice.order', $o) }}">{{ $o->number }}</a> · {{ $o->statusLabel() }}</li>@empty<li>bez objednávky (založeno ručně příkazem)</li>@endforelse</ul>
</section>
</div>
<style>
  .ev-form { display: flex; flex-direction: column; gap: 14px; }
  .ev-form label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
  .ev-form label .muted { font-weight: 400; font-size: 13px; }
  .ev-form .grid2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
</style>
@endsection
