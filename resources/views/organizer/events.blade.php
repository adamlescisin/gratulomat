@extends('organizer.layout')
@section('title', 'Moje události · Gratulomat')
@section('content')
<main class="landing">
  <h1 class="display">Moje události</h1>
  @forelse ($events as $e)
    <p class="lead"><a href="{{ route('organizer.admin', $e) }}">{{ $e->title }}</a> · {{ $e->event_date->format('j. n. Y') }}</p>
  @empty
    <p class="lead">Zatím tu nic není.</p>
  @endforelse
  <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn--ghost btn--sm">Odhlásit</button></form>
</main>
@endsection
