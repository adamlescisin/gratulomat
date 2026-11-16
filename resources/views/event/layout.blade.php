@php
    $offline = $offline ?? false;
    $assets = $offline ? 'assets' : asset('assets/event');
@endphp
<!doctype html>
<html lang="{{ $offline ? $event->locale : app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#14110e">
@unless ($offline)<meta name="csrf-token" content="{{ csrf_token() }}">@endunless
<title>@yield('title', $event->title)</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%2314110e'/><text x='32' y='44' font-family='Georgia' font-style='italic' font-size='30' text-anchor='middle' fill='%23e3a94b'>{{ $event->age() ?: 'G' }}</text></svg>">
<link rel="preload" href="{{ $assets }}/fonts/fraunces-latin-ext-standard-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $assets }}/css/app.css{{ $offline ? '' : '?v='.@filemtime(public_path('assets/event/css/app.css')) }}">
</head>
<body class="@yield('body_class')">
<div class="grain" aria-hidden="true"></div>
@yield('content')
</body>
</html>
