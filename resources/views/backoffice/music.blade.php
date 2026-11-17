@extends('backoffice.layout')
@section('title', 'Hudba')
@section('content')
<section class="panel">
  <p class="muted" style="margin:0">Hudební podkresy pro videoprezentace. Organizátor si v administraci události vybere skladbu ze seznamu (s filtrem podle štítků a ukázkou); doporučí se mu štítky podle vzhledu a typu události. Podkres hraje ve smyčce a při videu nebo hlasovém vzkazu se plynule ztiší. Nahrávejte jen hudbu, kterou smíme použít (royalty-free licence povolující užití ve videu a sdílení) – zdroj a licenci si poznamenejte. Vypnutá skladba se v nabídce neukáže a události, které ji mají vybranou, hrají bez podkresu.</p>
</section>

<section class="panel">
  <h2 style="margin:0">Nahrát skladbu</h2>
  <form method="post" action="{{ route('backoffice.music.store') }}" enctype="multipart/form-data" data-upload>@csrf
    <div class="row" style="flex-wrap:wrap;gap:12px;align-items:flex-end">
      <label>MP3 (max. 20 MB)<br><input class="inp" type="file" name="file" accept=".mp3,audio/mpeg" required data-file></label>
      <label>Název<br><input class="inp" name="title" maxlength="140" required value="{{ old('title') }}" data-title></label>
      <label>Autor<br><input class="inp" name="artist" maxlength="140" value="{{ old('artist') }}"></label>
      <label style="flex:1;min-width:260px">Zdroj a licence<br><input class="inp" name="license" maxlength="500" value="{{ old('license') }}" placeholder="např. pixabay.com/music/… – Pixabay Content License" style="width:100%"></label>
    </div>
    <label style="display:block;margin-top:10px">Štítky (oddělené čárkou)<br><input class="inp" name="tags" maxlength="300" value="{{ old('tags') }}" style="width:100%" data-tags></label>
    <p class="muted" style="margin:.4rem 0">Kliknutím přidáte: @foreach ($tags as $t)<button type="button" class="chip" data-add-tag="{{ $t }}">{{ $t }}</button> @endforeach</p>
    <input type="hidden" name="duration" data-duration>
    <div class="row"><button class="b" type="submit">Nahrát</button><span class="muted" data-dur-label></span></div>
  </form>
</section>

<section class="panel">
  <h2 style="margin:0">Knihovna <span class="muted">{{ $tracks->count() }}</span></h2>
  @if ($tracks->isEmpty())
    <p class="muted">Zatím tu nic není. Dokud knihovna zůstane prázdná, organizátoři výběr podkresu neuvidí.</p>
  @endif
  @foreach ($tracks as $t)
    <form method="post" action="{{ route('backoffice.music.update', $t) }}" class="track @if (! $t->active) is-off @endif">@csrf
      <div class="row" style="flex-wrap:wrap;gap:10px;align-items:flex-end">
        <label>Název<br><input class="inp" name="title" value="{{ $t->title }}" maxlength="140" required></label>
        <label>Autor<br><input class="inp" name="artist" value="{{ $t->artist }}" maxlength="140"></label>
        <label style="flex:1;min-width:220px">Štítky<br><input class="inp" name="tags" value="{{ implode(', ', $t->tags ?? []) }}" maxlength="300" style="width:100%"></label>
        <label class="check"><input type="checkbox" name="active" value="1" @checked($t->active)> V nabídce</label>
      </div>
      <label style="display:block;margin-top:6px">Zdroj a licence<br><input class="inp" name="license" value="{{ $t->license }}" maxlength="500" style="width:100%"></label>
      <div class="row" style="margin-top:8px;flex-wrap:wrap">
        <audio controls preload="none" src="{{ $t->url() }}" style="height:34px"></audio>
        <span class="muted">{{ $t->durationLabel() }} · {{ number_format($t->bytes / 1048576, 1, ',', ' ') }} MB · používá {{ $usage[$t->id] ?? 0 }} událostí</span>
        <button class="b b--sm b--ghost" type="submit">Uložit</button>
        <button class="b b--sm b--ghost" style="color:#b3261e" type="submit" form="del-{{ $t->id }}">Smazat</button>
      </div>
    </form>
    <form id="del-{{ $t->id }}" method="post" action="{{ route('backoffice.music.delete', $t) }}" onsubmit="return confirm('Smazat skladbu „{{ e($t->title) }}“?{{ ($usage[$t->id] ?? 0) ? ' Používá ji '.$usage[$t->id].' událostí – budou bez podkresu.' : '' }}')">@csrf @method('DELETE')</form>
  @endforeach
</section>

<style>
  .track { padding: 14px 0; border-top: 1px solid #e6e1d8; } .track.is-off { opacity: .55; }
  .chip { border: 1px solid #d8d2c6; background: #fff; border-radius: 999px; padding: 2px 10px; margin: 2px; cursor: pointer; font: inherit; font-size: 13px; }
</style>
<script>
  // délka skladby z metadat v prohlížeči; název z názvu souboru
  const f = document.querySelector('[data-file]'), tags = document.querySelector('[data-tags]');
  f.addEventListener('change', () => {
    const file = f.files[0]; if (!file) return;
    const title = document.querySelector('[data-title]');
    if (!title.value) title.value = file.name.replace(/\.mp3$/i, '').replace(/[-_]+/g, ' ').trim();
    const a = new Audio(URL.createObjectURL(file));
    a.addEventListener('loadedmetadata', () => {
      if (isFinite(a.duration)) {
        const s = Math.round(a.duration);
        document.querySelector('[data-duration]').value = s;
        document.querySelector('[data-dur-label]').textContent = `délka ${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
      }
    });
  });
  document.querySelectorAll('[data-add-tag]').forEach(b => b.addEventListener('click', () => {
    const cur = tags.value.split(',').map(t => t.trim()).filter(Boolean);
    if (!cur.includes(b.dataset.addTag)) cur.push(b.dataset.addTag);
    tags.value = cur.join(', ');
  }));
</script>
@endsection
