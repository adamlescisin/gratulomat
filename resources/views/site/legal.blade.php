@php
    $title = \App\Support\Legal::DOCS[$doc];
    $variant = \App\Support\Legal::variant();
    $meta = \App\Support\Legal::meta($doc, $variant);
    $identity = \App\Support\Legal::identityLine();
@endphp
<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
@include('site.partials.analytics')
<title>{{ $title }} · Gratulomat</title>
@include('site.partials.seo', ['title' => $title.' · Gratulomat', 'description' => [
    'terms' => __('Obchodní podmínky služby Gratulomat – pravidla objednávky, platby a využívání služby.'),
    'privacy' => __('Jak Gratulomat zpracovává osobní údaje: jaké údaje, proč, jak dlouho a jaká máte práva.'),
    'guest_rules' => __('Pravidla pro hosty, kteří do Gratulomatu přidávají vzkazy, fotky a vzpomínky.'),
][$doc]])
</head>
<body>
<div class="cs">
  <header class="cs-wrap cs-head"><a href="/" aria-label="Gratulomat – úvod">@include('partials.logo', ['width' => 180])</a></header>
  <main class="legal">
    <p class="eyebrow">{{ $variant === 'test' ? 'Testovací provoz' : 'Gratulomat' }}</p>
    <h1 class="display">{{ $title }}</h1>
    @if (! empty($meta['effective_from']))<p><em>Účinné od {{ \Illuminate\Support\Carbon::parse($meta['effective_from'])->locale('cs')->isoFormat('LL') }}@if (! empty($meta['version'])) · verze {{ $meta['version'] }}@endif</em></p>@endif
    <div class="legal__body">{!! \App\Support\Legal::html($doc) !!}</div>
    @if ($identity)<p class="legal__id">Provozovatel: {{ $identity }}</p>@endif
  </main>
  @include('site.partials.footer')
</div>
</body>
</html>
