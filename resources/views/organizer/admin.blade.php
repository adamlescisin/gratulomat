@extends('organizer.layout')
@section('title', 'Administrace · '.$event->title)
@section('body_class', 'page-admin')
@section('content')
<header class="topbar">
  <a class="brand" href="{{ route('organizer.admin', $event) }}"><span class="brand__mark">{{ $event->age() ?: '✦' }}</span><span class="brand__name">{{ $event->title }} · administrace</span></a>
  <nav class="topbar__me">
    <a class="btn btn--ghost btn--sm" href="{{ route('event.kino', $event) }}" target="_blank">🎬 Náhled videoprezentace</a>
    @if (\App\Domain\Guest\EventRules::has($event, 'offline'))
      <a class="btn btn--gold btn--sm" href="{{ route('organizer.offline', $event) }}">⬇ Offline balíček</a>
    @endif
    <form method="post" action="{{ route('logout') }}" style="display:inline">@csrf<button class="btn btn--ghost btn--sm">Odhlásit</button></form>
  </nav>
</header>
<main class="admin">
  <section class="kpis" data-kpis></section>

  <section class="card">
    <h2 class="h2">Videoprezentace pro hosty</h2>
    <p class="muted" data-show-status></p>
    <div class="row">
      <button class="btn btn--gold btn--sm" data-action="show-now">Zpřístupnit hned</button>
      <label class="muted">nebo od <input class="input" type="datetime-local" data-show-at style="width:auto;display:inline-block"></label>
      <button class="btn btn--ghost btn--sm" data-action="show-at">Naplánovat</button>
      <button class="btn btn--ghost btn--sm btn--danger" data-action="show-close">Zavřít pro hosty</button>
    </div>
    <label class="check"><input type="checkbox" data-auto-share> Po uzávěrce sběru (<span data-auto-share-at></span>) prezentaci hostům automaticky nasdílet – na jejich stránce se dole objeví odkaz a QR kód</label>
    <label class="check" data-game-wrap hidden><input type="checkbox" data-game> Hra „Hádej, kdo to napsal“ v Kině</label>
  </section>

  <section class="card">
    <h2 class="h2">Hosté <span class="muted" data-hh-count></span></h2>
    <p class="muted">Jeden řádek = jedna domácnost (pár sdílí jeden odkaz). Volitelně za středník vztah k oslavenci.<br>
      <code>Petra a Honza; spolužáci z gymplu</code> – jména pište jen křestní (použijí se k oslovení a podpisu).</p>
    <form data-import class="import">
      <textarea class="input" rows="4" name="lines" placeholder="Jana a Pavel&#10;Martin; kolega z práce"></textarea>
      <button class="btn btn--gold" type="submit">Přidat hosty</button>
    </form>
    <div class="guest-tools">
      <div class="guest-tool" data-file-import-wrap>
        <h3 class="h3">Import ze souboru</h3>
        <div data-file-import-on hidden>
          <p class="muted">CSV nebo Excel (.xlsx). Sloupce <code>Jméno</code>, volitelně <code>E-mail</code> a <code>Vztah</code> – stačí export kontaktů nebo tabulka. Duplicitní jména přeskočíme.</p>
          <form data-file-import class="row"><input type="file" name="file" accept=".csv,.txt,.xlsx" required> <button class="btn btn--ghost btn--sm" type="submit">Importovat</button></form>
        </div>
        <p class="muted" data-file-import-off hidden>Import seznamu (CSV, Excel) je součástí tarifu Premiéra.</p>
      </div>
      <div class="guest-tool" data-anon-wrap>
        <h3 class="h3">Anonymní pozvánka</h3>
        <p class="muted">Jeden společný odkaz pro kohokoli (sociální sítě, kancelář, nástěnka). Host se při otevření sám představí a dostane vlastní řádek v tabulce.</p>
        <div data-anon-on hidden></div>
        <p class="muted" data-anon-off hidden>Anonymní pozvánka je součástí tarifu Premiéra.</p>
      </div>
    </div>
    <div class="table-wrap"><table class="table" data-households>
      <thead><tr><th>Domácnost</th><th>Vztah</th><th>Příspěvky</th><th>Naposledy</th><th>Odkaz</th><th></th></tr></thead>
      <tbody></tbody>
    </table></div>
  </section>

  <section class="card">
    <h2 class="h2">Vzpomínky <span class="muted" data-mem-count></span></h2>
    <div class="admin-grid" data-memories></div>
  </section>

  <section class="card">
    <h2 class="h2">Nastavení</h2>
    <form data-settings>
      <div class="settings-grid">
        <label class="muted">Název události<br><input class="input" name="title" maxlength="140" required></label>
        <label class="muted" data-rec-label>Jméno oslavence / oslavenců<br><input class="input" name="recipient_name" maxlength="120" required></label>
      </div>
      <div class="theme-pick">
        <p class="muted" style="margin:.4rem 0 .5rem">Vzhled stránky pro hosty <a href="{{ route('event.show', $event) }}" target="_blank" style="font-size:.85rem">náhled ↗</a></p>
        <div class="theme-modes" data-mode-pick></div>
        <div class="theme-grid" data-theme-pick></div>
        <p class="muted" style="font-size:.8rem;margin:.4rem 0 0">Videoprezentace (Kino) má vždy filmový tmavý vzhled.</p>
      </div>
      <p class="muted" style="margin:.2rem 0 1rem;font-size:.85rem">Datum události nebo délku archivu vám změníme na <a href="mailto:{{ config('gratulomat.notify_email') }}">{{ config('gratulomat.notify_email') }}</a>.</p>
      <label class="muted">Uzávěrka sběru<br><input class="input" type="datetime-local" name="collect_until" style="width:auto"></label>
      <label class="muted">Odpečetit příspěvky hostům<br><input class="input" type="datetime-local" name="wall_open_at" style="width:auto">
        <span style="display:block;font-size:.8rem">Do té doby každý host vidí jen své příspěvky. Prázdné = při uzávěrce sběru.</span></label>
      <p class="muted" style="margin-top:1rem">Text pozvánky – <code>{jmena}</code>, <code>{odkaz}</code> a <code>{datum}</code> se doplní u každého hosta.</p>
      <textarea class="input" rows="6" name="invite_text"></textarea>
      <p><button class="btn btn--gold btn--sm" type="submit">Uložit nastavení</button></p>
    </form>
  </section>
</main>

<dialog class="qr-dialog" data-qr>
  <h3 class="h3" data-qr-title></h3>
  <div data-qr-svg></div>
  <textarea class="input" rows="7" data-qr-msg readonly></textarea>
  <div class="row"><button class="btn btn--gold btn--sm" data-action="copy-msg">Zkopírovat pozvánku</button><button class="btn btn--ghost btn--sm" data-action="close-qr">Zavřít</button></div>
</dialog>
<div class="toast" data-toast role="status"></div>
<script>window.GM = {!! json_encode(['base' => url($event->slug.'/admin'), 'csrf' => csrf_token()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!};</script>
<script src="{{ asset('assets/event/vendor/qrcode.js') }}"></script>
<script src="{{ asset('assets/event/js/admin.js') }}?v={{ @filemtime(public_path('assets/event/js/admin.js')) }}" defer></script>
@endsection
