@extends('backoffice.layout')
@section('title', 'Ukázkový Gratulomat')
@section('content')
<div style="display:grid;grid-template-columns:minmax(300px,460px) 1fr;gap:24px;align-items:start">
<section class="panel">
  <p class="muted" style="margin-top:0">Video, které se přehraje po stisku tlačítka „Ukázkový Gratulomat“ na hlavní stránce. Nahrajte soubor, nebo vložte odkaz na YouTube/Vimeo. Když je nastavené obojí, přednost má nahraný soubor.</p>
  <form method="post" action="{{ route('backoffice.demo.save') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:14px">@csrf
    <div>Video soubor (MP4 H.264 nebo WebM)<br>
      @if (! empty($raw['file']))<span class="muted" style="display:block;margin:6px 0">Nahráno: <code>{{ $raw['file'] }}</code> ({{ number_format(@filesize(\App\Support\DemoVideo::path($raw['file'])) / 1048576, 1, ',', ' ') }} MB) – nový soubor ho nahradí.</span>@endif
      <input type="file" name="video" accept="video/mp4,video/webm">
      <span class="muted" style="display:block;font-size:13px">Server přijme soubor nejvýš {{ $limit }} (formulář celkem {{ $post }}). Doporučení: 1080p, H.264, 4–8 Mb/s, do 2–3 minut. Větší video dejte raději na YouTube/Vimeo.</span>
    </div>
    <label>…nebo odkaz na video<br><input class="inp" name="url" maxlength="500" value="{{ old('url', $raw['url'] ?? '') }}" placeholder="https://www.youtube.com/watch?v=… nebo https://vimeo.com/…">
      <span class="muted" style="font-size:13px">YouTube, Vimeo nebo přímý odkaz na soubor .mp4/.webm. Prázdné = bez odkazu.</span></label>
    <div>Náhledový obrázek (volitelný, jen pro nahrané video)<br>
      @if (! empty($raw['poster']))<img src="{{ route('demo.file', $raw['poster']) }}" alt="" style="max-width:220px;border-radius:8px;margin:6px 0;display:block">
        <label class="muted"><input type="checkbox" name="remove_poster" value="1"> odebrat náhled</label><br>@endif
      <input type="file" name="poster" accept="image/jpeg,image/png,image/webp">
    </div>
    <div class="row"><button class="b" type="submit">Uložit</button></div>
  </form>
  @if (! empty($raw['file']))
    <form method="post" action="{{ route('backoffice.demo.delete') }}" style="margin-top:12px" onsubmit="return confirm('Smazat nahrané video?')">@csrf @method('DELETE')<button class="b b--ghost b--sm">Smazat nahrané video</button></form>
  @endif
</section>
<section class="panel">
  <h3 style="margin-top:0">Náhled</h3>
  @if (! $demo)
    <p class="muted">Ukázka není nastavená – návštěvníci po stisku tlačítka uvidí konfety a informaci, že ukázku připravujeme, s odkazem na založení události.</p>
  @elseif ($demo['type'] === 'embed')
    <iframe src="{{ str_replace('autoplay=1', 'autoplay=0', $demo['src']) }}" title="Náhled" style="width:100%;aspect-ratio:16/9;border:0;border-radius:12px" allowfullscreen></iframe>
  @else
    <video src="{{ $demo['src'] }}" @if ($demo['poster']) poster="{{ $demo['poster'] }}" @endif controls preload="metadata" style="width:100%;border-radius:12px;background:#000"></video>
  @endif
  <p><a href="/#videoprezentace" target="_blank">Vyzkoušet na hlavní stránce ↗</a></p>
</section>
</div>
@endsection
