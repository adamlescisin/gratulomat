<!doctype html>
<html lang="cs">
<head>
@include('site.partials.head')
<link rel="stylesheet" href="{{ asset('assets/site/landing.css') }}?v={{ @filemtime(public_path('assets/site/landing.css')) }}">
<title>Gratulomat – vzpomínky, které se promítají</title>
<meta name="description" content="Gratulanti pošlou historky, staré fotky a hlasová přání. Gratulomat z nich složí videoprezentaci, kterou na oslavě pustíte všichni spolu.">
</head>
<body class="lp">
@php
    $tick = fn ($c = '#D9432A') => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="'.$c.'" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    $plans = config('gratulomat.plans');
    $kc = fn ($n) => number_format($n, 0, ',', ' ').' Kč';
    $addons = config('gratulomat.addons');
    // Cena tarifu z back-office ceníku; při plošné akci s přeškrtnutou původní cenou
    $price = function (string $code, string $prefix = '') use ($plans, $kc) {
        $p = $plans[$code];
        if (empty($p['sale'])) {
            return '<div class="price">'.e($prefix.$kc($p['price'])).'</div>';
        }

        return '<div class="price-was"><s>'.e($kc($p['list_price'])).'</s> <span class="sale-tag">−'.$p['sale']['percent'].' % do '.\Carbon\Carbon::parse($p['sale']['until'])->format('j. n.').'</span></div>'
            .'<div class="price price--sale">'.e($prefix.$kc($p['price'])).'</div>';
    };
@endphp
@if (\App\Models\Setting::siteMode() !== 'live')
  <div class="beta" role="status">Testovací verze – platby probíhají v testovacím režimu Stripe (karta 4242 4242 4242 4242), nic se neúčtuje.</div>
@endif
<header class="lp-head">
  <nav class="wrap lp-nav" aria-label="Hlavní navigace">
    <a href="/" aria-label="Gratulomat – úvod" style="display:flex">@include('partials.logo-ink', ['width' => 190])</a>
    <div class="links">
      <a href="#jak">Jak to funguje</a>
      <a href="#videoprezentace">Videoprezentace</a>
      <a href="#cenik">Ceník</a>
      <a href="#otazky">Otázky</a>
      @auth<a href="{{ route('organizer.events') }}">Moje události</a>@else<a href="{{ route('login') }}">Přihlásit</a>@endauth
      <a class="btn btn--sm" href="{{ route('wizard') }}">Vyzkoušet zdarma</a>
    </div>
  </nav>
</header>

<main>
  <section class="wrap hero">
    <div class="hero-copy">
      <p class="kicker">Pro narozeniny, svatby, výročí a rozlučky</p>
      <h1 class="display">Ze vzpomínek vašich blízkých film na&nbsp;oslavu.</h1>
      <p class="lead2">Gratulanti pošlou historky, staré fotky a hlasová přání. Gratulomat z nich složí příběh po desetiletích – a na oslavě ho pustíte všichni spolu.</p>
      <div class="row">
        <a class="btn" href="{{ route('wizard') }}">Založit událost zdarma</a>
        <a class="btn btn--line" href="#videoprezentace">Jak vypadá videoprezentace</a>
      </div>
      <p class="small">Pro gratulanty bez aplikace a registrace · Česky, anglicky i německy</p>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="screen"><div class="screen-in">
        <span class="yr">1989</span>
        <span class="cap">Osmdesátá léta · Janě 12</span>
        <span class="quote">„Na táboře jsme spolu vyhrály stezku odvahy – jen proto, že jsme se ztratily.“</span>
        <span class="by">— Petra a Honza</span>
      </div></div>
      <div class="phone">
        <b>Přidat vzpomínku</b>
        <span class="f" style="height:34px;align-items:center">Rok: 1989</span>
        <span class="f" style="height:58px">Na táboře jsme spolu…</span>
        <span class="tiles"><i style="background:#FFC433"></i><i style="background:#2EC4A0"></i><i style="background:#C9C3D9"></i></span>
        <span class="save">Uložit</span>
      </div>
    </div>
  </section>

  <section id="jak" class="wrap sec">
    <div class="sec-head"><p class="kicker">Jak to funguje</p><h2 class="h2">Tři kroky. Žádné stříhání videa.</h2></div>
    <div class="cards3">
      <div class="card"><span class="num">1</span><h3>Založte událost</h3><p>Jméno oslavence, datum a vzhled. Za dvě minuty máte vlastní stránku, třeba gratulomat.cz/jana60.</p></div>
      <div class="card"><span class="num">2</span><h3>Pozvěte gratulanty</h3><p>Každý dostane osobní odkaz nebo QR kód. Bez aplikace a bez registrace – fotky, historky i hlasová přání nahrají z mobilu.</p></div>
      <div class="card"><span class="num">3</span><h3>Zmáčkněte tlačítko</h3><p>Na oslavě spustíte videoprezentaci: film ze všech vzpomínek seřazený podle let. Funguje i bez internetu.</p></div>
    </div>
  </section>

  <section id="videoprezentace" class="dark">
    <div class="wrap in">
      <div class="copy">
        <p class="kicker kicker--sun">Videoprezentace</p>
        <h2 class="h2">Na oslavě to pustíte všichni spolu.</h2>
        <p>Žádné papírové přání s hracím strojkem nebo nahánění podpisů. Originální videoprezentace poběží na televizi nebo plátně přímo na oslavě a vy ji ovládáte jedním tlačítkem.</p>
        <ul class="ticks">
          @foreach (['Kapitoly po desetiletích – od dětství až po dnešek', 'Fotky se pomalu přibližují, historky se rozsvěcují slovo po slově', 'Hlasová přání zazní nahlas, hudba se sama ztlumí', 'Hra „Hádej, kdo to napsal“ – autor se odhalí až po stisku tlačítka', 'Offline balíček pro místa bez internetu'] as $t)
            <li>{!! $tick('#FFC433') !!}<span>{{ $t }}</span></li>
          @endforeach
        </ul>
      </div>
      <div class="bigbtn"><div>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" aria-hidden="true" style="display:block;width:200px;height:auto"><circle cx="60" cy="61" r="50" fill="#FFF6E9"/><circle cx="60" cy="65" r="36" fill="#B93A22"/><circle cx="60" cy="58" r="36" fill="#FF5A3C"/><ellipse cx="47" cy="43" rx="12" ry="6" fill="#fff" opacity=".3" transform="rotate(-22 47 43)"/><path d="M60 73C41 60 43 43 53 43C56.5 43 59 45 60 47.5C61 45 63.5 43 67 43C77 43 79 60 60 73Z" fill="#FFF6E9"/></svg>
        <span>Spustit videoprezentaci</span>
      </div></div>
    </div>
  </section>

  <section class="wrap sec" style="padding-top:104px;gap:28px">
    <div class="sec-head"><p class="kicker">Pro jaké příležitosti</p><h2 class="h2">Pro každou životní událost.</h2></div>
    <div class="occ">@foreach (['Narozeniny', 'Svatba', 'Výročí svatby', 'Odchod do důchodu', 'Firemní jubileum', 'Rozlučka s kolegou', 'Sraz spolužáků', 'Smuteční rozloučení'] as $o)<span>{{ $o }}</span>@endforeach</div>
  </section>

  <section class="wrap sec">
    <div class="sec-head"><p class="kicker">Co Gratulomat umí</p><h2 class="h2">Myšleno na gratulanty každého věku.</h2></div>
    <div class="feats">
      @foreach ([
        ['Osobní odkazy pro každého', 'Páry sdílejí jeden odkaz, u každé vzpomínky je vidět, kdo ji napsal.'],
        ['Fotky, videa i hlas', 'Staré fotky stačí vyfotit mobilem. Vzkaz se dá namluvit přímo v prohlížeči.'],
        ['Inspirace „Došla slova?“', 'Když gratulant neví, co napsat, nabídneme mu otázky, které rozvážou jazyk.'],
        ['Veřejná i soukromá přání', 'Některá přání patří na plátno, jiná jen oslavenci. Rozhoduje gratulant.'],
        ['Překvapení zůstane překvapením', 'Příspěvky ostatních jsou zapečetěné až do oslavy.'],
        ['Soukromí a data v EU', 'Stránka je jen pro pozvané, z fotek mažeme polohu, po oslavě data na přání smažeme.'],
      ] as [$h, $p])
        <div class="feat"><h3>{{ $h }}</h3><p>{{ $p }}</p></div>
      @endforeach
    </div>
  </section>

  <section id="cenik" class="pricing">
    <div class="wrap in">
      <div class="sec-head">
        <p class="kicker">Ceník</p>
        <h2 class="h2">Platíte jednou, za událost. Žádné předplatné.</h2>
        <p class="lead2" style="font-size:18px;max-width:none">Událost založíte a vyzkoušíte zdarma. Zaplatíte, až budete chtít pozvat víc gratulantů. Ceny jsou včetně DPH.</p>
      </div>
      <div class="plans">
        <div class="plan">
          <h3>{{ $plans['ochutnavka']['name'] }}</h3>
          <div>{!! $price('ochutnavka') !!}<div class="per">na vyzkoušení</div></div>
          <ul class="ticks">@foreach (['Až '.$plans['ochutnavka']['guests'].' gratulantů', 'Až '.$plans['ochutnavka']['photos'].' fotek', 'Videoprezentace s vodoznakem', 'Sběr gratulací '.$plans['ochutnavka']['collect_days'].' dní', 'Kdykoliv volitelný upgrade na vyšší tarif – nic se neztratí', 'Administrace pozvánek a obsahu'] as $t)<li>{!! $tick() !!}<span>{{ $t }}</span></li>@endforeach</ul>
          <a class="btn btn--line" href="{{ route('wizard') }}">Začít zdarma</a>
        </div>
        <div class="plan">
          <h3>{{ $plans['party']['name'] }}</h3>
          <div>{!! $price('party') !!}<div class="per">jednorázově za událost</div></div>
          <ul class="ticks">@foreach (['Až '.$plans['party']['guests'].' gratulantů', 'Neomezeně fotek', 'Hlasová i psaná přání', 'Videoprezentace bez vodoznaku + offline balíček', 'Světlý i tmavý vzhled', 'Sběr gratulací až '.$plans['party']['collect_days'].' dní', 'Administrace pozvánek a obsahu'] as $t)<li>{!! $tick() !!}<span>{{ $t }}</span></li>@endforeach</ul>
          <a class="btn btn--line" href="{{ route('wizard', ['tarif' => 'party']) }}">Vybrat Párty</a>
        </div>
        <div class="plan plan--hi">
          <span class="badge">Doporučujeme</span>
          <h3>{{ $plans['premiera']['name'] }}</h3>
          <div>{!! $price('premiera') !!}<div class="per">jednorázově za událost</div></div>
          <ul class="ticks">@foreach (['Až '.$plans['premiera']['guests'].' gratulantů', 'Anonymní pozvánka', 'Import gratulantů ze seznamu nebo kontaktů', 'Videa od gratulantů', 'Film MP4 ke stažení (připravujeme)', 'Hra „Hádej, kdo to napsal“', 'Připomínky gratulantům e-mailem', 'Archiv '.$plans['premiera']['archive_months'].' měsíců'] as $t)<li>{!! $tick('#FFC433') !!}<span>{{ $t }}</span></li>@endforeach</ul>
          <a class="btn" href="{{ route('wizard', ['tarif' => 'premiera']) }}">Vybrat Premiéru</a>
        </div>
        <div class="plan">
          <h3>{{ $plans['vip']['name'] }}</h3>
          <div>{!! $price('vip', 'od ') !!}<div class="per">uděláme to za vás</div></div>
          <ul class="ticks">@foreach (['Vše z Premiéry', 'Nastavení a pozvánky za vás', 'Obvoláme, kdo ještě nepřispěl', 'Sestříháme a doladíme film', 'Podpora v den oslavy'] as $t)<li>{!! $tick() !!}<span>{{ $t }}</span></li>@endforeach</ul>
          <a class="btn btn--line" href="{{ route('wizard', ['tarif' => 'vip']) }}">Domluvit se</a>
        </div>
      </div>
      <div class="extras">
        <span><strong>Fotokniha</strong> od {{ $kc($addons['photobook']['price']) }}</span>
        <span><strong>+{{ $addons['guest_pack']['size'] }} gratulantů</strong> {{ $kc($addons['guest_pack']['price']) }}</span>
        <span><strong>Prodloužení archivu</strong> {{ $kc($addons['archive_year']['price']) }} / rok</span>
        <span><strong>Firmy</strong> 10 událostí ročně za 7 990 Kč</span>
      </div>
    </div>
  </section>

  <section class="wrap story">
    <div style="flex:0 0 auto"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 120" aria-hidden="true" style="display:block;width:160px;height:auto"><rect width="120" height="120" rx="26" fill="#FF5A3C"/><circle cx="60" cy="61" r="40" fill="#1C1633"/><circle cx="60" cy="64.2" r="28.8" fill="#B93A22"/><circle cx="60" cy="58.6" r="28.8" fill="#FFC433"/><ellipse cx="49.6" cy="46.6" rx="9.6" ry="4.8" fill="#fff" opacity=".3" transform="rotate(-22 49.6 46.6)"/><path d="M60 70.6C44.8 60.2 46.4 46.6 54.4 46.6C57.2 46.6 59.2 48.2 60 50.2C60.8 48.2 62.8 46.6 65.6 46.6C73.6 46.6 75.2 60.2 60 70.6Z" fill="#1C1633"/></svg></div>
    <div style="flex:1 1 420px;min-width:0;display:flex;flex-direction:column;gap:14px">
      <p class="kicker">Jak vznikl</p>
      <h2 class="h2">Narodil se na jedněch padesátinách.</h2>
      <p>Gratulomat vznikl, když jsme pro jednu oslavu chtěli sesbírat vzpomínky od třiceti lidí a promítnout je tak, aby se u toho všichni smáli i dojímali. Fungovalo to – tak jsme z toho udělali službu pro každého.</p>
    </div>
  </section>

  <section id="otazky" class="wrap sec" style="gap:32px">
    <h2 class="h2">Časté otázky</h2>
    <div class="faq">
      @foreach ([
        ['Musí si organizátor nebo hosté/gratulanti něco instalovat?', 'Ne. Stačí otevřít osobní odkaz v mobilu nebo počítači. Žádná aplikace, žádné heslo.'],
        ['Co když na místě události není internet?', 'Den předem si stáhnete offline balíček a videoprezentaci pustíte z notebooku – bez připojení.'],
        ['Kdo uvidí fotky a vzpomínky?', 'Jen pozvaní hosté a oslavenec. Soukromá přání vidí pouze oslavenec. O sdílení výsledné prezentace rozhoduje organizátor. Stránka se nezobrazuje ve vyhledávačích.'],
        ['Co když někteří hosté nehovoří česky?', 'Vůbec nevadí – každý organizátor nebo host si může přepnout svůj Gratulomat do jazyka dle libosti. Zatím máme češtinu, angličtinu a němčinu, další jazyky budeme přidávat průběžně.'],
        ['Jak dlouho data uchováváte?', 'Podle tarifu 1 až 12 měsíců po oslavě. Pak je smažeme, pokud si archiv neprodloužíte.'],
        ['Můžu Gratulomat použít i pro firmu?', 'Ano – Gratulomat můžete využít i pro výročí či oslavu firmy, zaměstnanců, projektu nebo rozlučky. Nabízíme i zvýhodněné balíčky na míru – napište nám.'],
        ['Hodí se Gratulomat i pro smuteční rozloučení?', 'Ano. U smutečního rozloučení mohou hosté posílat vzpomínky, fotky a kondolence i po datu rozloučení. Hravé prvky vypneme a použijeme klidnou šablonu „Smuteční“.'],
      ] as [$q, $a])
        <details><summary>{{ $q }}<span aria-hidden="true">+</span></summary><p>{{ $a }}</p></details>
      @endforeach
    </div>
  </section>

  <section id="start" class="wrap sec">
    <div class="cta">
      <div>
        <h2 class="h2">Zmáčkni a gratuluj.</h2>
        <p>Založení trvá dvě minuty a Ochutnávka je zdarma. Tarif můžete kdykoli navýšit – nic se neztratí.</p>
      </div>
      <div style="align-items:flex-start"><a class="btn btn--ink" href="{{ route('wizard') }}">Založit událost</a></div>
    </div>
  </section>
</main>

<footer class="lp-foot">
  <div class="wrap in">
    <div style="display:flex;flex-direction:column;gap:12px">@include('partials.logo', ['width' => 180])<span style="font-size:15px">Vzpomínky, které se promítají.</span></div>
    <nav>
      <a href="#cenik">Ceník</a><a href="#otazky">Otázky</a>
      <a href="{{ route('privacy') }}">Ochrana soukromí</a><a href="{{ route('terms') }}">Obchodní podmínky</a>
      <a href="mailto:{{ config('gratulomat.notify_email') }}">Kontakt</a>
      <span style="padding:8px 0">© {{ date('Y') }} Gratulomat · gratulomat.cz @if ($legalId = \App\Support\Legal::identityLine())<br><small>Provozovatel: {{ $legalId }}</small>@endif</span>
    </nav>
  </div>
</footer>
</body>
</html>
