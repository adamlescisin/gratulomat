{{-- Canonical, meta description a Open Graph pro veřejné stránky. Parametry: $title, $description, volitelně $ogTitle, $ogDescription. --}}
@php
    $seoPath = trim(request()->path(), '/');
    $seoUrl = rtrim((string) (config('gratulomat.marketing_url') ?: url('/')), '/').'/'.$seoPath;
@endphp
<link rel="canonical" href="{{ $seoUrl }}">
<meta name="description" content="{{ $description }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Gratulomat">
<meta property="og:locale" content="cs_CZ">
<meta property="og:url" content="{{ $seoUrl }}">
<meta property="og:title" content="{{ $ogTitle ?? $title }}">
<meta property="og:description" content="{{ $ogDescription ?? $description }}">
<meta property="og:image" content="{{ rtrim((string) (config('gratulomat.marketing_url') ?: url('/')), '/') }}/icon-512.png">
