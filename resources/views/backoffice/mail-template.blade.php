@extends('backoffice.layout')
@section('title', 'Šablona: '.$def['label'])
@section('content')
<p><a href="{{ route('backoffice.mail') }}">← E-maily</a></p>
<div class="tpl-edit">
<section class="panel">
  <p class="muted" style="margin-top:0"><b>Kdy se posílá:</b> {{ $def['when'] }}<br>
    @if ($saved) Upravená šablona{{ ! empty($saved['updated_at']) ? ' (uloženo '.\Illuminate\Support\Carbon::parse($saved['updated_at'])->timezone('Europe/Prague')->format('j. n. Y H:i').')' : '' }}. @else Platí výchozí text. @endif</p>
  <form method="post" action="{{ route('backoffice.mail.template.save', $key) }}" data-tpl-form style="display:flex;flex-direction:column;gap:12px">@csrf
    <label>Předmět<br><input class="inp" name="subject" maxlength="200" required value="{{ old('subject', $tpl['subject']) }}" data-tpl-field></label>
    <label>Text e-mailu (Markdown)<br>
      <textarea class="inp tpl-body" name="body" rows="24" required data-tpl-field data-tpl-body>{{ old('body', $tpl['body']) }}</textarea></label>
    <div class="row" style="flex-wrap:wrap;gap:8px">
      <button class="b" type="submit" name="action" value="save">Uložit</button>
      <button class="b b--ghost" type="submit" formaction="{{ route('backoffice.mail.template.preview', $key) }}" formtarget="tpl-preview" data-tpl-preview>Obnovit náhled</button>
      <button class="b b--ghost" type="submit" formaction="{{ route('backoffice.mail.template.test', $key) }}">Poslat zkušební e-mail na {{ auth()->user()->email }}</button>
      @if ($saved)
        <button class="b b--ghost b--sm" type="submit" name="action" value="reset" formnovalidate onclick="return confirm('Vrátit šablonu na výchozí text? Vaše úpravy se smažou.')">Vrátit výchozí text</button>
      @endif
    </div>
    <p class="muted" style="font-size:13px;margin:0">Zkušební e-mail a náhled používají vzorové údaje a aktuální text z editoru – nemusíte ho předem ukládat.</p>
  </form>

  <div class="tpl-help">
    <h3>Zástupci <span class="muted">(kliknutím vložíte na místo kurzoru)</span></h3>
    <ul>
      @foreach ($def['vars'] + \App\Support\MailTemplates::COMMON_VARS as $name => $desc)
        <li><button type="button" class="tpl-chip" data-insert="{{ '{'.$name.'}' }}">{{ '{'.$name.'}' }}</button> {{ $desc }}</li>
      @endforeach
    </ul>
    @if ($def['buttons'])
      <h3>Tlačítka</h3>
      <ul>
        @foreach ($def['buttons'] as $name => $desc)
          <li><button type="button" class="tpl-chip" data-insert="[tlacitko {{ $name }}]Text tlačítka[/tlacitko]">[tlacitko {{ $name }}]…[/tlacitko]</button> {{ $desc }}</li>
        @endforeach
      </ul>
    @endif
    <h3>Podmíněný text</h3>
    <p class="muted"><code>[pokud X]…[/pokud]</code> se zobrazí, jen když X platí; <code>[pokud ne X]…[/pokud]</code> naopak. Jako X lze použít kterýkoli zástupce výše (platí, když není prázdný)@if ($def['flags']) nebo:@else.@endif</p>
    @if ($def['flags'])
      <ul>
        @foreach ($def['flags'] as $name => $desc)
          <li><button type="button" class="tpl-chip" data-insert="[pokud {{ $name }}]…[/pokud]">{{ $name }}</button> {{ $desc }}</li>
        @endforeach
      </ul>
    @endif
    <h3>Formátování</h3>
    <p class="muted"><code># Nadpis</code> · <code>**tučně**</code> · <code>_kurzíva_</code> · <code>[text odkazu](https://…)</code> · odrážky <code>- položka</code> · číslovaný seznam <code>1. položka</code> · prázdný řádek = nový odstavec · <code>&lt;small&gt;drobný text&lt;/small&gt;</code>. Logo, barvu tlačítek a patičku nastavíte společně pro všechny e-maily na stránce E-maily.</p>
  </div>
</section>
<section class="panel" style="padding:0;overflow:hidden">
  <iframe name="tpl-preview" src="{{ route('backoffice.mail.template.preview', $key) }}" title="Náhled e-mailu" style="width:100%;height:820px;border:0;display:block"></iframe>
</section>
</div>
<style>
  .tpl-edit{display:grid;grid-template-columns:minmax(340px,1fr) minmax(360px,1fr);gap:24px;align-items:start}
  @media (max-width:1000px){.tpl-edit{grid-template-columns:1fr}}
  .tpl-body{font:13px/1.55 ui-monospace,SFMono-Regular,Menlo,monospace;width:100%;resize:vertical}
  .tpl-help{margin-top:20px;font-size:14px}
  .tpl-help h3{font-size:14px;margin:16px 0 6px}
  .tpl-help ul{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:4px}
  .tpl-chip{font:12px ui-monospace,Menlo,monospace;border:1px solid #c9c3dc;background:#f4f2fa;color:#1C1633;border-radius:6px;padding:2px 6px;cursor:pointer}
  .tpl-chip:hover{background:#e9e4f7}
</style>
<script>
(() => {
  const form = document.querySelector('[data-tpl-form]'), body = document.querySelector('[data-tpl-body]'), prev = document.querySelector('[data-tpl-preview]');
  let last = body, t;
  form.querySelectorAll('[data-tpl-field]').forEach(f => {
    f.addEventListener('focus', () => last = f);
    f.addEventListener('input', () => { clearTimeout(t); t = setTimeout(() => form.requestSubmit(prev), 700); });
  });
  document.querySelectorAll('[data-insert]').forEach(b => b.addEventListener('click', () => {
    const el = last, txt = b.dataset.insert, s = el.selectionStart ?? el.value.length, e = el.selectionEnd ?? s;
    el.setRangeText(txt, s, e, 'end'); el.focus(); el.dispatchEvent(new Event('input'));
  }));
})();
</script>
@endsection
