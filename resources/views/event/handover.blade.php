@extends('event.layout')
@section('body_class', 'page-handover')
@section('content')
@php
    $funeral = $event->isFuneral();
    $media = fn ($m, $f) => url($event->slug.'/m/'.$m['id'].'/'.$f);
    $vars = ['name' => $event->recipient_name, 'voc' => $event->form('voc') ?: $event->recipient_name, 'people' => $people, 'count' => count($memories)];
@endphp
<main class="handover">
  <header class="handover__hero">
    <p class="eyebrow">{{ $event->title }}</p>
    <h1 class="display">{!! $funeral ? __('guest.handover.title_funeral', $vars) : __('guest.handover.title', ['voc' => '<em>'.e($vars['voc']).'</em>']) !!}</h1>
    @if ($people)<p class="lead">{{ trans_choice($funeral ? 'guest.handover.lead_funeral' : 'guest.handover.lead', $people, $vars) }}</p>@endif
    @if (! empty($h['message']))
      <blockquote class="handover__note">{{ $h['message'] }}<cite>— {{ $owner?->name ?: __('guest.handover.organizer') }}</cite></blockquote>
    @endif
    <div class="handover__cta">
      <a class="btn btn--gold btn--lg" href="{{ route('event.kino', $event) }}">▶ {{ __('guest.handover.play') }}</a>
      @if ($canDownload)
        <a class="btn btn--ghost" href="{{ route('event.handover.offline', [$event, $token]) }}">⬇ {{ __('guest.handover.download') }}</a>
      @endif
    </div>
    <p class="handover__small">
      @if ($privateCount && ! $funeral){{ trans_choice('guest.handover.private', $privateCount, ['count' => $privateCount]) }} @endif
      @if ($event->archive_until){{ __('guest.handover.until', ['date' => $event->archive_until->locale(app()->getLocale())->isoFormat('LL')]) }}@if ($canDownload) {{ __($funeral ? 'guest.handover.until_download_funeral' : 'guest.handover.until_download') }}@endif @endif
    </p>
  </header>

  <section aria-labelledby="mems">
    <h2 class="h2" id="mems">{{ __('guest.handover.memories') }} <span class="muted">{{ count($memories) }}</span></h2>
    <div class="masonry">
      @foreach ($memories as $m)
        @php
            $photos = array_values(array_filter($m['media'], fn ($x) => $x['kind'] === 'photo'));
            $videos = array_values(array_filter($m['media'], fn ($x) => $x['kind'] === 'video'));
            $audio = array_values(array_filter($m['media'], fn ($x) => $x['kind'] === 'audio'));
        @endphp
        <article class="mem">
          @if ($photos)
            <div class="mem__media" style="--cols: {{ min(2, count($photos)) }}">
              @foreach (array_slice($photos, 0, 4) as $p)
                <a href="{{ $media($m, $p['file']) }}" target="_blank" rel="noopener"><img src="{{ $media($m, $p['thumb'] ?: $p['file']) }}" alt="" loading="lazy"></a>
              @endforeach
              @if (count($photos) > 4)<span class="more">+{{ count($photos) - 4 }}</span>@endif
            </div>
          @endif
          @foreach ($videos as $vid)
            <video class="handover__video" src="{{ $media($m, $vid['file']) }}" controls preload="metadata" playsinline></video>
          @endforeach
          <div class="mem__body">
            @if ($m['year'] && $event->timeline())<div class="mem__meta"><span class="mem__year">{{ $m['year'] }}</span></div>@endif
            @if ($m['title'])<h3 class="mem__title">{{ $m['title'] }}</h3>@endif
            @if ($m['body'])<p class="mem__text handover__text">{{ $m['body'] }}</p>@endif
            @if ($m['wish_text'] || $audio)
              <div class="mem__wish">
                @if ($m['wish_text']){{ $m['wish_text'] }}@endif
                @foreach ($audio as $a)<audio src="{{ $media($m, $a['file']) }}" controls preload="none" style="width:100%;margin-top:.4rem"></audio>@endforeach
                @unless ($m['wish_public'])<small>🔒 {{ __('guest.handover.private_badge') }}</small>@endunless
              </div>
            @endif
            <div class="mem__by">{{ implode(' a ', $m['authors']) }}</div>
          </div>
        </article>
      @endforeach
    </div>
    @if (! $memories)<p class="muted">{{ __('guest.handover.empty') }}</p>@endif
  </section>
  <footer class="handover__foot"><a href="{{ url('/') }}">{{ __('guest.made_with') }}</a></footer>
</main>
<style>
  .handover { max-width: 1180px; margin: 0 auto; padding: clamp(2rem, 6vw, 5rem) clamp(1rem, 4vw, 2.5rem) 3rem; position: relative; z-index: 1; }
  .handover__hero { max-width: 760px; margin-bottom: 3rem; }
  .handover__hero .display { font-size: clamp(2.2rem, 5vw, 3.8rem); }
  .handover__note { margin: 0 0 1.8rem; padding: 1.1rem 1.3rem; border-left: 3px solid var(--gold); background: rgba(var(--tint), .04); border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font: italic 400 1.1rem/1.5 var(--serif); color: var(--paper); white-space: pre-line; }
  .handover__note cite { display: block; margin-top: .6rem; font: 500 .85rem var(--sans); color: var(--paper-mute); font-style: normal; }
  .handover__cta { display: flex; flex-wrap: wrap; gap: .8rem; align-items: center; }
  .handover__small { color: var(--paper-mute); font-size: .88rem; margin-top: 1.2rem; }
  .handover__video { width: 100%; display: block; background: #000; }
  .handover__text { -webkit-line-clamp: unset; display: block; }
  .handover__foot { margin-top: 3rem; text-align: center; font-size: .82rem; } .handover__foot a { color: var(--paper-mute); }
</style>
@endsection
