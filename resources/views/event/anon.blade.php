@extends('event.layout')
@section('body_class', 'page-landing')
@section('content')
@include('event.partials.locale')
<main class="landing">
  @if ($event->age())<div class="landing__fifty" aria-hidden="true">{{ $event->age() }}</div>@endif
  <h1 class="display">{{ $event->title }}</h1>
  @if ($full)
    <p class="lead">{{ __('guest.anon.full') }}</p>
  @else
    <p class="lead">{{ __($event->isFuneral() ? 'guest.anon.lead_funeral' : 'guest.anon.lead') }}</p>
    @if ($errors->any())<p class="alert">{{ $errors->first() }}</p>@endif
    <form class="anon-form" method="post" action="{{ route('event.anon.join', [$event, $token]) }}">
      @csrf
      <label for="anon-name">{{ __('guest.anon.name') }}</label>
      <input class="input" id="anon-name" name="name" required minlength="2" maxlength="80" autocomplete="given-name" value="{{ old('name') }}" placeholder="{{ __('guest.anon.name_placeholder') }}">
      <span class="hint">{{ __('guest.anon.name_hint') }}</span>
      <label for="anon-rel">{{ __('guest.anon.relation') }}</label>
      <input class="input" id="anon-rel" name="relation" maxlength="120" value="{{ old('relation') }}" placeholder="{{ __('guest.anon.relation_placeholder') }}">
      <label for="anon-email">{{ __('guest.anon.email') }}</label>
      <input class="input" id="anon-email" name="email" type="email" maxlength="191" autocomplete="email" value="{{ old('email') }}">
      <span class="hint">{{ __('guest.anon.email_hint') }}</span>
      <button class="btn btn--gold" type="submit">{{ __('guest.anon.submit') }}</button>
    </form>
  @endif
</main>
<style>
  .anon-form { display: flex; flex-direction: column; gap: .4rem; max-width: 420px; margin: 1.5rem auto 0; text-align: left; }
  .anon-form label { font-weight: 600; margin-top: .6rem; }
  .anon-form .hint { font-size: .85rem; opacity: .75; }
  .anon-form button { margin-top: 1.2rem; }
</style>
@endsection
