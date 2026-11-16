@extends('event.layout')
@section('title', 'Kino · '.$event->title)
@section('body_class', 'page-kino')
@section('content')
{{-- Kino běží v primárním jazyce události (texty a kapitoly), ne v jazyce rozhraní hosta. --}}
<div class="kino" data-kino tabindex="-1">
  <div class="kino__stage" data-stage></div>

  <div class="kino__start" data-start>
    <div class="kino__start-inner">
      <p class="eyebrow">Promítání</p>
      <h1 class="display">{{ $event->title }}</h1>
      <p class="lead" data-start-meta>Načítám vzpomínky…</p>
      <button class="btn btn--gold btn--lg" data-action="start" disabled>▶ Spustit film</button>
      @if ($event->show?->game_enabled && ! $event->isFuneral())
      <label class="check check--light"><input type="checkbox" data-guess> Hra „Hádej, kdo to napsal“ – autor se odhalí až po stisku Enter</label>
      @else
      <input type="checkbox" data-guess hidden>
      @endif
      <p class="keys"><kbd>mezerník</kbd> pauza · <kbd>←</kbd><kbd>→</kbd> zpět/dál · <kbd>F</kbd> celá obrazovka · <kbd>G</kbd> přehled · <kbd>R</kbd> náhoda · <kbd>M</kbd> hudba</p>
    </div>
  </div>

  <div class="kino__hud" data-hud>
    <div class="kino__progress" data-progress><i></i></div>
    <div class="kino__controls">
      <button data-action="prev" aria-label="Předchozí">⏮</button>
      <button data-action="toggle" aria-label="Pauza">⏯</button>
      <button data-action="next" aria-label="Další">⏭</button>
      <span class="kino__chapter" data-chapter></span>
      <span class="spacer"></span>
      <button data-action="overview" aria-label="Přehled">▦</button>
      <button data-action="music" aria-label="Hudba">♫</button>
      <button data-action="fullscreen" aria-label="Celá obrazovka">⛶</button>
    </div>
  </div>

  <div class="kino__overview" data-overview hidden>
    <div class="kino__overview-head"><h2 class="h2">Přehled</h2><button class="btn btn--ghost btn--sm" data-action="overview">Zavřít (G)</button></div>
    <div class="kino__overview-grid" data-overview-grid></div>
  </div>
</div>
@if ($event->show?->music)<audio data-music src="{{ $offline ? $event->show->music : asset($event->show->music) }}" loop preload="auto"></audio>@endif
@php
    $gm = ['showUrl' => $showUrl];
    if ($loopUrl = $event->loopUrl('kino_finale')) { $gm['loop'] = ['url' => $loopUrl, 'label' => __('guest.loop.kino')]; }
    if (!empty($embeddedShow)) { $gm['demoShow'] = $embeddedShow; }
@endphp
<script>window.GM = {!! json_encode($gm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!};</script>
<script src="{{ $offline ? 'assets/js/kino.js' : asset('assets/event/js/kino.js').'?v='.@filemtime(public_path('assets/event/js/kino.js')) }}" defer></script>
@endsection
