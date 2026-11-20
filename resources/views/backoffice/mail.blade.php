@extends('backoffice.layout')
@section('title', 'E-maily')
@section('content')
@php($brand = \App\Support\MailBrand::class)
<div style="display:grid;grid-template-columns:minmax(300px,420px) 1fr;gap:24px;align-items:start">
<section class="panel">
  <p class="muted">Vzhled všech e-mailů, které Gratulomat posílá (přihlášení, objednávky, doklady, upozornění). Adresa odesílatele zůstává z nastavení serveru (<code>{{ config('mail.from.address') }}</code>).</p>
  <form method="post" action="{{ route('backoffice.mail.save') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">@csrf
    <label>Jméno odesílatele<br><input class="inp" name="from_name" maxlength="80" value="{{ old('from_name', $brand::get('from_name')) }}" placeholder="{{ config('mail.from.name') ?: 'Gratulomat' }}"></label>
    <label>Barva tlačítek<br><input type="color" name="color" value="{{ old('color', $brand::color()) }}" style="width:64px;height:40px;border:0;background:none"></label>
    <div>Logo v hlavičce<br>
      @if ($brand::logoUrl())
        <img src="{{ $brand::logoUrl() }}" alt="" style="max-width:220px;max-height:80px;margin:8px 0;display:block;background:#fff;padding:6px;border-radius:8px">
        <label class="muted"><input type="checkbox" name="remove_logo" value="1"> odebrat logo (zobrazí se jméno odesílatele)</label><br>
      @endif
      <input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
      <span class="muted" style="display:block;font-size:13px">PNG/JPG, ideálně průhledné PNG ve dvojnásobném rozlišení, max. 1 MB.</span>
    </div>
    <label>Šířka loga (px)<br><input class="inp" type="number" name="logo_width" min="60" max="320" value="{{ old('logo_width', $brand::logoWidth()) }}" style="width:120px"></label>
    <label>Patička<br><textarea class="inp" name="footer" rows="4" maxlength="600" placeholder="Gratulomat · [gratulomat.cz](https://gratulomat.cz)">{{ old('footer', $brand::get('footer')) }}</textarea>
      <span class="muted" style="font-size:13px">Každý řádek zvlášť. Odkaz: <code>[text](https://…)</code>. Prázdné = výchozí patička.</span></label>
    <div class="row"><button class="b" type="submit">Uložit</button></div>
  </form>
  <form method="post" action="{{ route('backoffice.mail.test') }}" style="margin-top:12px">@csrf<button class="b b--ghost b--sm">Poslat zkušební e-mail na {{ auth()->user()->email }}</button></form>
</section>
<section class="panel" style="padding:0;overflow:hidden">
  <iframe src="{{ route('backoffice.mail.preview') }}" title="Náhled e-mailu" style="width:100%;height:720px;border:0;display:block"></iframe>
</section>
</div>

<section class="panel" style="margin-top:24px">
  <h2 style="margin-top:0">Šablony e-mailů zákazníkům</h2>
  <p class="muted">Texty e-mailů, které dostávají organizátoři. Upravit můžete předmět i text a použít zástupce pro údaje o události a tlačítka. Pozvánky hostům upravuje každý organizátor sám v administraci události.</p>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>E-mail</th><th>Kdy se posílá</th><th>Stav</th><th></th></tr></thead>
    <tbody>
    @foreach (\App\Support\MailTemplates::all() as $key => $def)
      @php($saved = \App\Support\MailTemplates::saved($key))
      <tr>
        <td><a href="{{ route('backoffice.mail.template', $key) }}">{{ $def['label'] }}</a></td>
        <td class="muted">{{ $def['when'] }}</td>
        <td>{{ $saved ? 'upraveno' : 'výchozí' }}</td>
        <td><a class="b b--ghost b--sm" href="{{ route('backoffice.mail.template', $key) }}">Upravit</a></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</section>
@endsection
