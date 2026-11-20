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
  @if (request('navyseni') === 'ok')<p class="alert alert--ok" data-upgrade-banner>Děkujeme za platbu. Navýšení se projeví během pár vteřin, jakmile Stripe platbu potvrdí – stránka se obnoví sama.</p>@endif
  @if (request('navyseni') === 'zruseno')<p class="alert">Platba byla zrušena, nic se nezměnilo.</p>@endif
  <p class="alert" data-archived hidden></p>
  <section class="kpis" data-kpis></section>

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
        <p class="muted" style="font-size:.8rem;margin:.4rem 0 0">Stejný vzhled má i videoprezentace (Kino) a offline balíček.</p>
      </div>

      <label class="muted">Závěrečný titulek videoprezentace<br><input class="input" name="finale_title" maxlength="120">
        <span style="display:block;font-size:.8rem">Prázdné = výchozí podle typu události.</span></label>
      <div class="date-pair">
        <label class="muted"><span data-date-label>Datum a čas události</span> <span style="font-size:.8rem">(nepovinné)</span><br><input class="input" type="datetime-local" name="event_date" style="width:auto">
          <span style="display:block;font-size:.8rem">Prázdné = bez data, odpočet na stránce hostů se nezobrazí.</span></label>
        <label class="muted">Hosté mohou přispívat do <span style="font-size:.8rem">(povinné)</span><br><input class="input" type="datetime-local" name="collect_until" style="width:auto" required>
          <span style="display:block;font-size:.8rem">Po tomto okamžiku už nikdo nic nepřidá ani neupraví.</span></label>
      </div>
      <label class="muted">Odpečetit příspěvky hostům<br><input class="input" type="datetime-local" name="wall_open_at" style="width:auto">
        <span style="display:block;font-size:.8rem">Do té doby každý host vidí jen své příspěvky. Prázdné = při uzávěrce sběru.</span></label>
      <p class="muted" style="margin-top:1rem">Text pozvánky – <code>{jmena}</code>, <code>{odkaz}</code> a <code>{datum}</code> se doplní u každého hosta.</p>
      <textarea class="input" rows="6" name="invite_text"></textarea>
      <p><button class="btn btn--gold btn--sm" type="submit">Uložit nastavení</button></p>
    </form>
  </section>

  <section class="card">
    <h2 class="h2">Hosté <span class="muted" data-hh-count></span></h2>
    <p class="muted">Jeden řádek = jeden host nebo pár (sdílí jeden odkaz). Volitelně za středník vztah k oslavenci.<br>
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
      <div class="guest-tool">
        <h3 class="h3">Pozvánky e-mailem</h3>
        <p class="muted" data-mail-info></p>
        <button class="btn btn--gold btn--sm" data-action="send-invites">Poslat pozvánky e-mailem</button>
        <label class="check" data-reminders-wrap hidden><input type="checkbox" data-reminders> 3 dny před uzávěrkou připomenout e-mailem těm, kdo ještě nepřispěli</label>
        <p class="muted" data-reminders-off hidden style="font-size:.85rem">Automatické připomínky jsou součástí tarifu Premiéra.</p>
      </div>
      <div class="guest-tool" data-anon-wrap>
        <h3 class="h3">Anonymní pozvánka</h3>
        <p class="muted">Jeden společný odkaz pro kohokoli (sociální sítě, kancelář, nástěnka). Host se při otevření sám představí a dostane vlastní řádek v tabulce.</p>
        <div data-anon-on hidden></div>
        <p class="muted" data-anon-off hidden>Anonymní pozvánka je součástí tarifu Premiéra.</p>
      </div>
    </div>
    <div class="table-wrap"><table class="table" data-households>
      <thead><tr><th>Hosté</th><th>Vztah</th><th>Příspěvky</th><th>Naposledy</th><th>Odkaz</th><th></th></tr></thead>
      <tbody></tbody>
    </table></div>
  </section>

  <section class="card" data-plan-card>
    <h2 class="h2">Tarif a doplňky</h2>
    <p class="muted" data-plan-now></p>
    <form data-upgrade class="upgrade">
      <label class="muted">Tarif<br><select class="input" name="plan" data-up-plan></select></label>
      <label class="muted">Balíčky +{{ config('gratulomat.addons.guest_pack.size') }} hostů ({{ config('gratulomat.addons.guest_pack.price') }} Kč)<br><input class="input" type="number" name="guest_packs" min="0" max="20" value="0"></label>
      <label class="muted">Archiv +1 rok ({{ config('gratulomat.addons.archive_year.price') }} Kč/rok)<br><input class="input" type="number" name="archive_years" min="0" max="5" value="0"></label>
      <label class="check"><input type="checkbox" name="photobook" value="1"> Fotokniha ze vzpomínek (od {{ config('gratulomat.addons.photobook.price') }} Kč) – ozveme se s návrhem</label>
      <div class="upgrade__sum" data-up-sum></div>
      <button class="btn btn--gold" type="submit" data-up-pay disabled>Vyberte změnu</button>
    </form>
    <p class="muted" style="font-size:.85rem">Tarif VIP vám rádi připravíme na míru – napište na <a href="mailto:{{ config('gratulomat.notify_email') }}">{{ config('gratulomat.notify_email') }}</a>.</p>
  </section>

  <section class="card">
    <h2 class="h2">Vzpomínky <span class="muted" data-mem-count></span></h2>
    <div class="admin-grid" data-memories></div>
  </section>

  <section class="card">
    <h2 class="h2">Videoprezentace pro hosty</h2>
    <p class="muted" data-show-status></p>
    <div class="row row--center">
      <button class="btn btn--gold btn--sm" data-action="show-now">Zpřístupnit hned</button>
      <label class="muted">nebo od <input class="input input--sm" type="datetime-local" data-show-at></label>
      <button class="btn btn--ghost btn--sm" data-action="show-at">Naplánovat</button>
      <button class="btn btn--ghost btn--sm btn--danger" data-action="show-close">Zavřít pro hosty</button>
    </div>
    <label class="check"><input type="checkbox" data-auto-share> Po uzávěrce sběru (<span data-auto-share-at></span>) prezentaci hostům automaticky nasdílet – na jejich stránce se dole objeví odkaz a QR kód</label>
    <label class="check" style="align-items:flex-start"><input type="checkbox" data-timeline style="margin-top:.2em"> <span>Časová osa – vzpomínky řadit podle let a desetiletí
      <span class="muted" style="display:block;font-size:.85rem">Vypnuto: ve videoprezentaci jen kapitoly Vzpomínky a Přání a hosté se na rok neptají. Už zadané roky zůstanou uložené pro případ, že časovou osu zase zapnete.</span></span></label>
    <div class="music-pick" data-music-wrap hidden>
      <p class="muted" style="margin:.8rem 0 .4rem">Hudební podkres <span style="font-size:.85rem">– hraje ve smyčce, při videu nebo hlasovém vzkazu se sám ztiší</span></p>
      <div class="music-tags" data-music-tags></div>
      <div class="row">
        <select class="input" data-music-select style="width:auto;max-width:100%"></select>
        <button class="btn btn--ghost btn--sm" type="button" data-action="music-preview">▶ Ukázka</button>
      </div>
      <audio data-music-audio preload="none"></audio>
    </div>
    <label class="check" data-game-wrap hidden><input type="checkbox" data-game> Hra „Hádej, kdo to napsal“ v Kině</label>

    <div class="handover-box">
      <h3 class="h3" data-ho-title>Předání oslavenci</h3>
      <p class="muted" data-ho-intro style="margin-top:0">Pošlete oslavenci tajný odkaz: uvidí videoprezentaci, všechny vzpomínky včetně soukromých přání určených jemu a může si stáhnout offline balíček. Nic nemůže měnit. Funguje hned, i když prezentace pro hosty ještě není zpřístupněná – pošlete ho třeba den po oslavě.</p>
      <div class="ho-form">
        <input class="input" type="email" data-ho-email placeholder="E-mail oslavence" autocomplete="off">
        <textarea class="input" rows="3" data-ho-message maxlength="2000" placeholder="Osobní věnování (nepovinné) – zobrazí se v e-mailu i na stránce"></textarea>
        <div class="row">
          <button class="btn btn--gold btn--sm" type="button" data-action="ho-send">Odeslat e-mailem</button>
          <button class="btn btn--ghost btn--sm" type="button" data-action="ho-link">Jen vytvořit odkaz</button>
        </div>
      </div>
      <div class="ho-state" data-ho-state hidden>
        <div class="row"><input class="input" data-ho-url readonly style="flex:1;min-width:240px"><button class="btn btn--ghost btn--sm" type="button" data-action="ho-copy">Kopírovat</button><a class="btn btn--ghost btn--sm" data-ho-open target="_blank" rel="noopener">Otevřít</a></div>
        <p class="muted" data-ho-status style="margin:.5rem 0"></p>
        <div class="row"><button class="btn btn--ghost btn--sm" type="button" data-action="ho-regenerate">Vyměnit odkaz</button><button class="btn btn--ghost btn--sm btn--danger" type="button" data-action="ho-revoke">Zrušit předání</button></div>
      </div>
    </div>
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
