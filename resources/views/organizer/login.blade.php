@extends('organizer.layout')
@section('title', 'Přihlášení · Gratulomat')
@section('content')
<main class="landing">
  <a href="/" aria-label="Gratulomat" style="display:inline-block;margin-bottom:1.5rem">@include('partials.logo-ink', ['width' => 190])</a>
  <h1 class="display">Administrace události</h1>
  @if ($sent)
    <p class="lead">Pokud je {{ $sent }} e-mail organizátora, poslali jsme na něj přihlašovací odkaz. Platí 30 minut.</p>
  @else
    <p class="lead">Zadejte e-mail, který jste použili při založení události. Pošleme vám přihlašovací odkaz – bez hesla.</p>
  @endif
  @if ($expired)<p class="alert">Odkaz už neplatí. Nechte si poslat nový.</p>@endif
  <form class="code-form" method="post" action="{{ route('login') }}">
    @csrf
    <label class="sr-only" for="email">E-mail</label>
    <input id="email" name="email" type="email" autocomplete="email" required placeholder="vas@email.cz" value="{{ old('email') }}">
    <button class="btn btn--gold" type="submit">Poslat odkaz</button>
  </form>
  @error('email')<p class="alert">{{ $message }}</p>@enderror
</main>
@endsection
