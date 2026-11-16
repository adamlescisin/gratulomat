@extends('backoffice.layout')
@section('title', 'Právní náležitosti')
@section('content')
@php
    $L = \App\Support\Legal::class;
    $identity = $L::identity();
    $active = $L::variant();
    $history = (array) \App\Models\Setting::get('legal_history', []);
@endphp
<section class="panel">
  <h2 style="margin:0">Které texty platí na webu</h2>
  <p class="muted" style="margin:0">Teď se zobrazují <strong>{{ $L::VARIANTS[$active] }}</strong> texty (režim webu: {{ ['coming_soon' => 'Připravujeme', 'maintenance' => 'Údržba', 'live' => 'Spuštěno'][$mode] ?? $mode }}). Platí pro <a href="{{ route('terms') }}" target="_blank">obchodní podmínky</a>, <a href="{{ route('privacy') }}" target="_blank">zásady zpracování osobních údajů</a> i <a href="{{ route('guest-rules') }}" target="_blank">pravidla pro hosty</a> (odkaz pod formulářem příspěvku na stránce hosta).</p>
  <form method="post" action="{{ route('backoffice.legal.save') }}" class="row" style="flex-wrap:wrap">@csrf
    @foreach (['auto' => 'Automaticky podle režimu webu (Připravujeme → testovací, spuštění a údržba → ostré)', 'test' => 'Vždy testovací', 'live' => 'Vždy ostré'] as $k => $label)
      <label style="display:flex;gap:8px;align-items:center"><input type="radio" name="legal_variant" value="{{ $k }}" @checked($L::mode() === $k)> {{ $label }}</label>
    @endforeach
    <button class="b b--sm">Uložit přepínač</button>
  </form>
</section>

<section class="panel">
  <h2 style="margin:0">Identifikace provozovatele</h2>
  <p class="muted" style="margin:0">Zobrazí se v patičce webu a e-mailů a dosadí se do textů za zástupné znaky.</p>
  <form method="post" action="{{ route('backoffice.legal.save') }}" class="legal-form legal-form--grid">@csrf
    <input type="hidden" name="identity[_]" value="1">
    @foreach ($L::IDENTITY_FIELDS as $k => $label)
      @if ($k === 'vat_payer')
        <label style="flex-direction:row;align-items:center;gap:8px"><input type="checkbox" name="identity[vat_payer]" value="1" @checked($identity['vat_payer'] === '1')> {{ $label }}</label>
      @else
        <label>{{ $label }}<input class="inp" name="identity[{{ $k }}]" value="{{ old("identity.$k", $identity[$k]) }}" @if ($k === 'email') type="email" @endif></label>
      @endif
    @endforeach
    <div><button class="b">Uložit identifikaci</button></div>
  </form>
</section>

<section class="panel">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h2 style="margin:0">Texty</h2>
    <nav class="row">
      @foreach ($L::VARIANTS as $k => $label)
        <a class="b b--sm {{ $edit === $k ? '' : 'b--ghost' }}" href="{{ route('backoffice.legal', ['varianta' => $k]) }}">{{ $label }}@if ($active === $k) · platí teď @endif</a>
      @endforeach
    </nav>
  </div>
  <p class="muted" style="margin:0">Formát Markdown (<code>## Nadpis</code>, <code>**tučně**</code>, <code>- odrážka</code>, <code>[odkaz](https://…)</code>). Zástupné znaky: <code>{provozovatel}</code> <code>{ico}</code> <code>{dic}</code> <code>{sidlo}</code> <code>{rejstrik}</code> <code>{email}</code> <code>{telefon}</code> <code>{web}</code>. @if ($edit === 'live') Prázdný ostrý text = na webu se zobrazí testovací. @endif</p>
  <form method="post" action="{{ route('backoffice.legal.save') }}" class="legal-form">@csrf
    <input type="hidden" name="variant" value="{{ $edit }}">
    @foreach ($L::DOCS as $doc => $label)
      @php($meta = $L::meta($doc, $edit))
      <label>{{ $label }} <span class="muted" style="font-weight:400">@if (! empty($meta['version'])) verze {{ $meta['version'] }}, uloženo {{ \Illuminate\Support\Carbon::parse($meta['updated_at'])->timezone('Europe/Prague')->format('j. n. Y H:i') }} ({{ $meta['by'] }}) @else výchozí text @endif</span>
        <textarea class="inp" name="{{ $doc }}" rows="16" style="padding:12px;font:14px/1.5 ui-monospace,Menlo,monospace">{{ old($doc, \App\Models\Setting::get("legal_{$doc}_{$edit}") ? $L::raw($doc, $edit) : (is_file(resource_path("legal/{$doc}_{$edit}.md")) ? $L::raw($doc, $edit) : '')) }}</textarea>
      </label>
      <label style="max-width:240px">Účinné od<input class="inp" type="date" name="{{ $doc }}_from" value="{{ old("{$doc}_from", $meta['effective_from'] ?? '') }}"></label>
    @endforeach
    <div><button class="b">Uložit texty ({{ $L::VARIANTS[$edit] }})</button></div>
  </form>
</section>

<section class="panel">
  <h2 style="margin:0">Archiv verzí</h2>
  <p class="muted" style="margin:0">Každá uložená změna se archivuje (posledních 50), aby šlo doložit, jaké znění platilo v době objednávky.</p>
  @forelse ($history as $h)
    <details><summary>{{ \Illuminate\Support\Carbon::parse($h['at'])->timezone('Europe/Prague')->format('j. n. Y H:i') }} · {{ $L::DOCS[$h['doc']] }} · {{ $L::VARIANTS[$h['variant']] }} · verze {{ $h['version'] }}@if ($h['effective_from']) · účinné od {{ \Illuminate\Support\Carbon::parse($h['effective_from'])->format('j. n. Y') }}@endif · {{ $h['by'] }}</summary>
      <pre style="white-space:pre-wrap;font-size:13px;background:#F4EDE2;padding:12px;border-radius:12px">{{ $h['text'] }}</pre></details>
  @empty
    <p class="muted">Zatím žádné uložené verze – web zobrazuje výchozí testovací texty.</p>
  @endforelse
</section>
<style>
  .legal-form { display: flex; flex-direction: column; gap: 14px; }
  .legal-form label { display: flex; flex-direction: column; gap: 6px; font-weight: 600; }
  .legal-form--grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
  .panel details summary { cursor: pointer; padding: 6px 0; }
</style>
@endsection
