@extends('event.layout')
@section('body_class', 'page-landing')
@section('content')
@include('event.partials.locale')
<main class="landing">
  @if ($event->age())<div class="landing__fifty" aria-hidden="true">{{ $event->age() }}</div>@endif
  @if ($event->start_year)<p class="eyebrow">{{ $event->start_year }} → {{ $event->refDate()->year }}</p>@endif
  <h1 class="display">{{ $event->title }}</h1>
  @if (!empty($notice))
    <p class="lead">{{ $notice }}</p>
  @else
    <p class="lead">{{ __('guest.landing_lead') }}</p>
  @endif
  @if (!empty($invalid))<p class="alert">{{ __('guest.landing_invalid') }}</p>@endif
  @unless ($event->isArchived())
  <form class="code-form" method="post" action="{{ route('event.login', $event) }}">
    @csrf
    <label class="sr-only" for="kod">{{ __('guest.code_label') }}</label>
    <input id="kod" name="kod" autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="{{ __('guest.code_placeholder') }}" required>
    <button class="btn btn--gold" type="submit">{{ __('guest.enter') }}</button>
  </form>
  @endunless
</main>
@endsection
