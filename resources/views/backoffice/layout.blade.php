<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="{{ asset('assets/site/backoffice.css') }}?v={{ @filemtime(public_path('assets/site/backoffice.css')) }}">
<title>@yield('title') · Gratulomat back-office</title>
</head>
<body>
@php($modeLabels = ['coming_soon' => ['Připravujeme', 'soon'], 'maintenance' => ['Údržba', 'maint'], 'live' => ['Ostrý provoz', 'live']])
<div class="bo">
  <nav class="bo-nav" aria-label="Back-office">
    <div class="brand">@include('partials.logo', ['width' => 150])<small>Back-office</small></div>
    <a href="{{ route('backoffice') }}" @if (request()->routeIs('backoffice')) aria-current="page" @endif>Přehled</a>
    <a href="{{ route('backoffice.events') }}" @if (request()->routeIs('backoffice.events')) aria-current="page" @endif>Události</a>
    <a href="{{ route('backoffice.orders') }}" @if (request()->routeIs('backoffice.order*')) aria-current="page" @endif>Objednávky</a>
    <a href="{{ route('backoffice.waitlist') }}" @if (request()->routeIs('backoffice.waitlist')) aria-current="page" @endif>Čekací listina</a>
    <a href="{{ route('backoffice.prices') }}" @if (request()->routeIs('backoffice.prices*')) aria-current="page" @endif>Ceník</a>
    <a href="{{ route('backoffice.discounts') }}" @if (request()->routeIs('backoffice.discounts')) aria-current="page" @endif>Slevové kódy</a>
    <a href="{{ route('backoffice.legal') }}" @if (request()->routeIs('backoffice.legal*')) aria-current="page" @endif>Právní náležitosti</a>
    <a href="{{ route('backoffice.mail') }}" @if (request()->routeIs('backoffice.mail*')) aria-current="page" @endif>E-maily</a>
    <a href="{{ route('backoffice.mode') }}" @if (request()->routeIs('backoffice.mode')) aria-current="page" @endif>Režim webu</a>
    <span class="spacer"></span>
    <a href="/" target="_blank">Otevřít web ↗</a>
    <form method="post" action="{{ route('logout') }}">@csrf<button>Odhlásit ({{ auth()->user()->email }})</button></form>
  </nav>
  <main class="bo-main">
    <div class="bo-top">
      <h1 class="display">@yield('title')</h1>
      @php([$ml, $mc] = $modeLabels[$mode ?? 'coming_soon'] ?? ['?', 'soon'])
      <span class="badge badge--{{ $mc }}">Režim webu: {{ $ml }}</span>
    </div>
    @if (session('ok'))<p class="flash" role="status">{{ session('ok') }}</p>@endif
    @if ($errors->any())<p class="flash flash--err" role="alert">{{ $errors->first() }}</p>@endif
    @yield('content')
  </main>
</div>
</body>
</html>
