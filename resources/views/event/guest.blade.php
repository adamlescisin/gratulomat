@extends('event.layout')
@section('body_class', 'page-guest')
@section('content')
@php
    $v = $event->textVars();
    $funeral = $event->isFuneral();
    $wishes = \App\Domain\Guest\EventRules::has($event, 'wishes');
    $nowYear = (int) date('Y');
    $minYear = $event->start_year ?? max(1930, $nowYear - 80);
    $heroKey = 'guest.hero.'.(trans()->has('guest.hero.'.$event->type) ? $event->type : 'default');
@endphp
<header class="topbar">
  <a class="brand" href="{{ route('event.show', $event) }}"><span class="brand__mark">{{ $event->age() ?: '✦' }}</span><span class="brand__name">{{ $event->title }}</span></a>
  <div class="topbar__me">@include('event.partials.locale') <span data-bind="hello"></span> <a class="link-muted" href="{{ route('event.logout', $event) }}">{{ __('guest.logout') }}</a></div>
</header>

<main class="guest">
  <section class="hero">
    @if ($event->start_year)
      <div class="hero__years" aria-hidden="true"><span>{{ $event->start_year }}</span><i></i><span>{{ $event->event_date->year }}</span></div>
    @endif
    <h1 class="display hero__title"><span data-bind="greeting"></span><br>{!! __($heroKey, array_map('e', $v)) !!}</h1>
    <p class="lead">{{ $funeral ? __('guest.lead_funeral') : __('guest.lead') }}</p>
    <div class="hero__meta">
      <div class="countdown" data-countdown aria-live="polite"></div>
      <div class="stats" data-stats></div>
    </div>
    <a class="btn btn--gold btn--lg" href="#composer">{{ __('guest.add_memory') }}</a>
    <p class="hint" data-kino-link hidden><a href="{{ route('event.kino', $event) }}">{{ __('guest.kino_link') }}</a></p>
  </section>

  <section class="card composer" id="composer" aria-labelledby="composer-title">
    <div class="composer__head">
      <h2 class="h2" id="composer-title" data-bind="composerTitle">{{ __('guest.composer_new') }}</h2>
      <button type="button" class="btn btn--ghost btn--sm" data-action="cancel-edit" hidden>{{ __('guest.cancel_edit') }}</button>
    </div>
    <div class="closed-note" data-closed hidden>{{ __('guest.closed') }}</div>

    <form data-composer novalidate>
      <fieldset class="step">
        <legend><span class="step__n">1</span> {{ __('guest.step_who') }}</legend>
        <div class="chips" data-authors></div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">2</span> {{ __('guest.step_when') }}</legend>
        <div class="year">
          <output class="year__out" data-year-out></output>
          <span class="year__era" data-year-era></span>
          <input type="range" name="year" min="{{ $minYear }}" max="{{ $nowYear }}" value="{{ (int) round(($minYear + $nowYear) / 2) }}" data-year aria-label="{{ __('guest.year_aria') }}">
          <div class="year__ticks" aria-hidden="true">@for ($y = (int) ceil($minYear / 10) * 10; $y <= $nowYear; $y += 10)<span style="--p:{{ round(($y - $minYear) / max(1, $nowYear - $minYear) * 100, 2) }}%">{{ $y }}</span>@endfor</div>
          <label class="check"><input type="checkbox" data-year-unknown> {{ __('guest.year_unknown') }}</label>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">3</span> {{ __('guest.step_story') }}</legend>
        <input class="input input--title" name="title" maxlength="140" placeholder="{{ __('guest.title_placeholder') }}">
        <div class="textarea-wrap">
          <textarea class="input" name="body" rows="6" maxlength="6000" placeholder="{{ __('guest.body_placeholder') }}"></textarea>
          <div class="prompt">
            <button type="button" class="prompt__btn" data-action="prompt">{{ __('guest.prompt_btn') }}</button>
            <span class="prompt__help">{{ __('guest.prompt_help') }}</span>
            <span class="prompt__text" data-prompt></span>
          </div>
        </div>
      </fieldset>

      <fieldset class="step">
        <legend><span class="step__n">4</span> {{ $videoMb ? __('guest.step_media') : __('guest.step_media_photos') }}</legend>
        <label class="drop" data-drop>
          <input type="file" accept="{{ $videoMb ? 'image/*,video/*' : 'image/*' }}" multiple data-files hidden>
          <span class="drop__icon" aria-hidden="true">📷</span>
          <span><strong>{{ __('guest.drop_strong') }}</strong> {{ __('guest.drop_rest') }}</span>
          <small>{{ $videoMb ? __('guest.drop_small', ['mb' => $videoMb]) : __('guest.drop_small_photos') }}</small>
        </label>
        <div class="thumbs" data-thumbs></div>
      </fieldset>

      @if ($wishes)
      <fieldset class="step">
        <legend><span class="step__n">5</span> {{ $funeral ? __('guest.step_wish_funeral') : __('guest.step_wish', $v) }} <span class="optional">{{ __('guest.optional') }}</span></legend>
        <div class="seg" role="tablist" aria-label="{{ __('guest.wish_form') }}">
          <button type="button" role="tab" class="seg__btn is-on" data-wish-mode="voice" aria-selected="true">{{ __('guest.wish_voice') }}</button>
          <button type="button" role="tab" class="seg__btn" data-wish-mode="text" aria-selected="false">{{ __('guest.wish_write') }}</button>
        </div>
        <div data-wish-panel="voice">
          <div class="rec" data-rec>
            <button type="button" class="rec__btn" data-action="rec" aria-label="{{ __('guest.rec_aria') }}"><span></span></button>
            <div class="rec__body">
              <div class="rec__status" data-rec-status>{{ __('guest.rec_idle') }}</div>
              <canvas class="rec__wave" data-rec-wave width="600" height="60" aria-hidden="true"></canvas>
              <audio controls data-rec-audio hidden></audio>
            </div>
            <button type="button" class="btn btn--ghost btn--sm" data-action="rec-clear" hidden>{{ __('guest.rec_clear') }}</button>
          </div>
        </div>
        <div data-wish-panel="text" hidden>
          <textarea class="input input--wish" name="wish_text" rows="4" maxlength="3000" placeholder="{{ $funeral ? __('guest.wish_placeholder_funeral') : __('guest.wish_placeholder', $v) }}"></textarea>
        </div>
        <label class="wish">
          <input type="checkbox" name="wish_public" data-wish checked>
          <span class="wish__ui"><strong>🥂 {{ $funeral ? __('guest.wish_public_funeral') : __('guest.wish_public') }}</strong><small>{{ $funeral ? __('guest.wish_public_small_funeral') : __('guest.wish_public_small', $v) }}</small></span>
        </label>
      </fieldset>
      @endif

      <fieldset class="step">
        <legend><span class="step__n">{{ $wishes ? 6 : 5 }}</span> {{ __('guest.step_tags') }}</legend>
        <div class="chips chips--tags" data-tags></div>
      </fieldset>

      <div class="submit">
        <div class="progress" data-progress hidden><i></i></div>
        <button class="btn btn--gold btn--lg" type="submit" data-submit>{{ __('guest.save') }}</button>
        <p class="error" data-error role="alert"></p>
      </div>
    </form>
  </section>

  <section class="mine" aria-labelledby="mine-title">
    <h2 class="h2" id="mine-title">{{ __('guest.mine_title') }} <span class="count" data-mine-count></span></h2>
    <div class="grid" data-mine></div>
    <p class="empty" data-mine-empty>{{ __('guest.mine_empty') }}</p>
  </section>

  <section class="wall" data-wall-section hidden aria-labelledby="wall-title">
    <h2 class="h2" id="wall-title">{{ __('guest.wall_title') }}</h2>
    <div class="masonry" data-wall></div>
  </section>
  <section class="teaser" data-teaser hidden>
    <div class="teaser__envelope" aria-hidden="true">✉️</div>
    <p>{{ __('guest.teaser', ['deadline' => $event->wallOpensAt()->timezone('Europe/Prague')->locale(app()->getLocale())->isoFormat($event->setting('wall_open_at') ? 'LLL' : 'LL')]) }}@unless ($funeral) {{ __('guest.teaser_surprise') }}@endunless</p>
  </section>

  <section class="share-show card" data-share hidden>
    <div class="share-show__text">
      <h2 class="h2">{{ $funeral ? __('guest.share_title_funeral') : __('guest.share_title') }}</h2>
      <p class="muted">{{ __('guest.share_text') }}</p>
      <a class="btn btn--gold" href="{{ route('event.kino', $event) }}">{{ __('guest.share_button') }}</a>
    </div>
    <div class="share-show__qr" data-share-qr aria-hidden="true"></div>
  </section>

  <footer class="foot">
    <p>{{ __('guest.foot') }}</p>
  </footer>
</main>

<div class="toast" data-toast role="status" aria-live="polite"></div>
<dialog class="lightbox" data-lightbox><img alt=""><button class="lightbox__close" data-action="lb-close" aria-label="{{ __('guest.close') }}">×</button></dialog>

@php
    $gm = [
    'base' => url($event->slug),
    'locale' => app()->getLocale(),
    'csrf' => csrf_token(),
    'vars' => $v,
    'labels' => [
        'save' => __('guest.save'), 'saveChanges' => __('guest.save_changes'),
        'composerNew' => __('guest.composer_new'), 'composerEdit' => __('guest.composer_edit'), 'recIdle' => __('guest.rec_idle'),
    ],
    'i18n' => ['js' => trans('guest.js'), 'prompts' => trans('guest.prompts.'.(trans()->has('guest.prompts.'.$event->type) ? $event->type : 'default'))],
];
@endphp
<script>window.GM = {!! json_encode($gm, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script src="{{ asset('assets/event/vendor/qrcode.js') }}" defer></script>
<script src="{{ asset('assets/event/js/app.js') }}?v={{ @filemtime(public_path('assets/event/js/app.js')) }}" defer></script>
@endsection
