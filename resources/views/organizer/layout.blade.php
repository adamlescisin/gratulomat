<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Gratulomat')</title>
<link rel="stylesheet" href="{{ asset('assets/event/css/app.css') }}?v={{ @filemtime(public_path('assets/event/css/app.css')) }}">
</head>
<body class="@yield('body_class', 'page-landing')">
<div class="grain" aria-hidden="true"></div>
@yield('content')
</body>
</html>
