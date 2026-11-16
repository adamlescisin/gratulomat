<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Gratulomat')</title>
    <style>
        :root { --ink:#1C1633; --paper:#FFF6E9; --coral:#FF5A3C; --sun:#FFC433; --mint:#2EC4A0; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--paper); color:var(--ink);
               font: 17px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { max-width: 34rem; padding: 2rem; text-align:center; }
        h1 { font-size: 2.2rem; margin: 0 0 .5rem; }
        a, button.link { color: var(--coral); }
        input { font: inherit; padding:.6rem .8rem; border:2px solid var(--ink); border-radius:.6rem; }
        .btn { font: inherit; font-weight:600; padding:.6rem 1.2rem; border:0; border-radius:.6rem; background:var(--coral); color:#fff; cursor:pointer; }
        .locale-switcher { position:fixed; top:1rem; right:1rem; display:flex; gap:.25rem; }
        .locale-switcher button { font:inherit; font-size:.85rem; background:none; border:1px solid transparent; border-radius:.4rem; padding:.2rem .5rem; cursor:pointer; color:var(--ink); }
        .locale-switcher button[aria-current] { border-color: var(--ink); }
        .error { color: var(--coral); }
    </style>
</head>
<body>
    @include('partials.locale-switcher')
    <main>@yield('content')</main>
</body>
</html>
