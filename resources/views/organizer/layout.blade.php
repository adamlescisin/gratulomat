<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<title>@yield('title', 'Gratulomat')</title>
<meta name="theme-color" content="#FFF8EE">
<link rel="stylesheet" href="{{ asset('assets/event/css/app.css') }}?v={{ @filemtime(public_path('assets/event/css/app.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/event/css/themes.css') }}?v={{ @filemtime(public_path('assets/event/css/themes.css')) }}">
</head>
{{-- přihlášení a administrace organizátora vždy v brandu Gratulomatu --}}
<body class="@yield('body_class', 'page-landing')" data-theme="brand" data-mode="light">
@yield('content')
</body>
</html>
