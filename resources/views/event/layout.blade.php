@php
    $offline = $offline ?? false;
    $assets = $offline ? 'assets' : asset('assets/event');
    // stránka pro hosty i Kino (online i offline balíček) podle zvolené šablony události
    $themed = true;
    $theme = $themed ? $event->themeKey() : null;
    $mode = $themed ? $event->themeMode() : 'dark';
    $bg = $themed ? \App\Domain\Guest\Themes::ALL[$theme][$mode === 'dark' ? 4 : 3][0] : '#14110e';
@endphp
<!doctype html>
<html lang="{{ $offline ? $event->locale : app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="{{ $bg }}">
@unless ($offline)<meta name="csrf-token" content="{{ csrf_token() }}">@endunless
<title>@yield('title', $event->title)</title>
@if ($offline)
<link rel="icon" href="data:image/svg+xml,{{ rawurlencode(file_get_contents(public_path('favicon.svg'))) }}">
@else
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
<link rel="preload" href="{{ $assets }}/fonts/fraunces-latin-ext-standard-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ $assets }}/css/app.css{{ $offline ? '' : '?v='.@filemtime(public_path('assets/event/css/app.css')) }}">
@if ($themed)<link rel="stylesheet" href="{{ $assets }}/css/themes.css{{ $offline ? '' : '?v='.@filemtime(public_path('assets/event/css/themes.css')) }}">@endif
</head>
<body class="@yield('body_class')"@if ($themed) data-theme="{{ $theme }}" data-mode="{{ $mode }}"@endif>
<div class="grain" aria-hidden="true"></div>
@yield('content')
</body>
</html>
