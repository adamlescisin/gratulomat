/* Gratulomat — průvodce založením události. Cenu vždy počítá server (/api/quote). */
(() => {
  'use strict';
  const W = window.GMW, $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const kc = n => n.toLocaleString('cs-CZ') + ' Kč';
  const STORE = 'gm-wizard-v1';
  const STEPS = [
    ['Událost', 'Jak se událost jmenuje?', 'Z názvu vytvoříme adresu stránky, kterou pošlete hostům.'],
    ['Pro koho', 'Pro koho je událost?', 'Podle toho upravíme texty, oslovení i otázky pro gratulanty.'],
    ['Gratulanti', 'Kdo se zapojí?', 'Počet gratulantů je hlavní věc, která určuje tarif.'],
    ['Vzhled', 'Jak má událost vypadat?', 'Vzhled platí pro stránku gratulantů i pro videoprezentaci.'],
    ['Funkce', 'Co má Gratulomat umět?', 'U každé funkce vidíte, od kterého tarifu je k dispozici.'],
    ['Doba a doplňky', 'Jak dlouho a co navíc?', 'Archiv běží od data události.'],
    ['Shrnutí', 'Shrnutí a platba', 'Zkontrolujte údaje. Tarif můžete později ještě navýšit.'],
  ];
  const OCCASIONS = [['birthday', 'Narozeniny'], ['wedding', 'Svatba'], ['anniversary', 'Výročí svatby'], ['retire', 'Odchod do důchodu'], ['company', 'Firemní jubileum'], ['farewell', 'Rozlučka'], ['reunion', 'Sraz spolužáků'], ['funeral', 'Smuteční rozloučení']];
  const RECIPIENTS = [['person', 'Jeden člověk', 'Narozeniny, důchod, rozlučka'], ['couple', 'Pár', 'Svatba, výročí, společná oslava'], ['team', 'Tým nebo parta', 'Spolužáci, oddíl, kapela'], ['company', 'Firma', 'Výročí firmy nebo pobočky']];
  const THEMES = [['zaklad', 'Základní', '#FFFFFF', '#1C1633', '#FF5A3C', 0], ['archiv', 'Filmový archiv', '#1C1633', '#FFC433', '#FF5A3C', 1], ['zahrada', 'Zahradní slavnost', '#EAF6EF', '#2EC4A0', '#1C1633', 1], ['elegant', 'Elegance', '#14213D', '#E5E5E5', '#C9A227', 1], ['ticho', 'Tichá vzpomínka', '#EEF0EE', '#5E6B73', '#A3B18A', 1]];
  const COLLECT = [[14, '14 dní', 0], [30, '30 dní', 1], [60, '60 dní', 1], [90, '90 dní', 1], [180, '180 dní', 2]];
  const ARCHIVE = [[1, '1 měsíc', 0], [3, '3 měsíce', 1], [12, '12 měsíců', 2]];
  const TIER = ['Ochutnávka', 'Párty', 'Premiéra', 'VIP'];
  const tag = l => (l === 0 ? 'V každém tarifu' : TIER[l]);

  const today = new Date(); today.setDate(today.getDate() + 30);
  let S = {
    step: 0, maxStep: 0, name: '', slug: '', slugTouched: false, occasion: 'birthday', date: today.toISOString().slice(0, 10),
    recipient: 'person', recName: '', startYear: '', guests: 5, importList: false, anonLink: false, langs: false,
    theme: 'zaklad', mode: 'light', voice: false, offline: false, nowm: false, video: false, game: false, mp4: false, reminders: false,
    collect: 14, archive: 1, book: false, archExt: false, vipSetup: false, vipEdit: false,
  };
  try { const saved = JSON.parse(localStorage.getItem(STORE) || 'null'); if (saved) S = { ...S, ...saved }; } catch {}
  if (W.old) S = { ...S, ...W.old, step: 6, maxStep: 6 };
  if (W.preset === 'premiera') Object.assign(S, { voice: true, nowm: true, offline: true, video: true, game: true, guests: Math.max(S.guests, 30), collect: 60, archive: 12 });
  if (W.preset === 'vip') Object.assign(S, { vipSetup: true });
  if (W.preset === 'party') Object.assign(S, { voice: true, nowm: true, offline: true, guests: Math.max(S.guests, 20), collect: 30, archive: 3 });
  let slugState = { status: null, suggestions: [] }, quote = null;

  const funeral = () => S.occasion === 'funeral';
  const save = () => { try { localStorage.setItem(STORE, JSON.stringify(S)); } catch {} };
  const slugify = s => (s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 30);
  const set = (patch, opts = {}) => { S = { ...S, ...patch }; save(); render(); if (opts.slug) checkSlug(); if (opts.quote !== false) requestQuote(); };

  // --- server: adresa a cena ---
  let slugTimer, quoteTimer, slugReq = 0, quoteReq = 0;
  function checkSlug() {
    clearTimeout(slugTimer);
    slugState = { status: 'checking', suggestions: [] }; renderSlug();
    slugTimer = setTimeout(async () => {
      const id = ++slugReq;
      try {
        const u = new URL(W.urls.slug); u.searchParams.set('slug', S.slug); u.searchParams.set('year', (S.date || '').slice(0, 4)); u.searchParams.set('name', S.recName);
        const r = await (await fetch(u, { headers: { Accept: 'application/json' } })).json();
        if (id === slugReq) { slugState = r; renderSlug(); renderNav(); }
      } catch { if (id === slugReq) { slugState = { status: 'error', suggestions: [] }; renderSlug(); } }
    }, 300);
  }
  function requestQuote() {
    clearTimeout(quoteTimer);
    quoteTimer = setTimeout(async () => {
      const id = ++quoteReq;
      try {
        const r = await fetch(W.urls.quote, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ config: S }) });
        const q = await r.json();
        if (id === quoteReq && r.ok) { quote = q; renderAside(); renderNav(); }
      } catch {}
    }, 200);
  }

  // --- vykreslení ---
  function render() {
    const fu = funeral();
    $('[data-steps]').innerHTML = STEPS.map((s, i) => `<button type="button" data-go="${i}" ${i === S.step ? 'aria-current="step"' : ''} class="${i < S.step ? 'done' : ''}" ${i > S.maxStep ? 'disabled' : ''}><span class="n">${i < S.step ? '✓' : i + 1}</span>${s[0]}</button>`).join('');
    $('[data-step-no]').textContent = S.step + 1;
    $('[data-step-title]').textContent = STEPS[S.step][1];
    $('[data-step-lead]').textContent = STEPS[S.step][2];
    $$('[data-step]').forEach(el => { el.hidden = +el.dataset.step !== S.step; });

    $$('[data-k]').forEach(el => { if (document.activeElement !== el) el.value = S[el.dataset.k] ?? ''; });
    $('[data-guests-out]').textContent = S.guests;
    $('[data-guests-hint]').textContent = S.guests <= 5 ? 'vejde se do Ochutnávky' : S.guests <= 25 ? 'vejde se do Párty' : S.guests <= 150 ? 'Párty s balíčky, nebo Premiéra' : 'Premiéra s balíčky nebo VIP';
    $('[data-guests-label]').textContent = fu ? 'Kolik hostů pozvete ke vzpomínání?' : 'Kolik gratulantů pozvete?';
    $('[data-guest-one]').textContent = fu ? 'host' : 'gratulant';
    $('[data-funeral-note]').hidden = !fu;
    $('[data-date-label]').textContent = fu ? 'Datum rozloučení' : 'Datum události';
    $('[data-rec-label]').textContent = fu ? 'Jméno zesnulé / zesnulého' : { person: 'Jméno oslavence (1. pád)', couple: 'Jména páru', team: 'Název týmu nebo party', company: 'Název firmy' }[S.recipient];
    $('#w-rec').placeholder = { person: 'např. Jana', couple: 'např. Jana a Petr', team: 'např. Třída 4.B', company: 'např. Pekárna Novák' }[S.recipient];
    $('[data-year-label]').textContent = fu ? 'Rok narození' : { person: 'Rok narození', couple: 'Rok svatby nebo seznámení', team: 'Rok, kdy jste začali', company: 'Rok založení' }[S.recipient];

    opts('occasion', OCCASIONS.map(([v, l]) => [v, l]), 'opt');
    opts('recipient', (fu ? RECIPIENTS.slice(0, 1) : RECIPIENTS).map(([v, l, d]) => [v, `<strong>${l}</strong><small>${d}</small>`]), 'opt');
    opts('mode', [['light', 'Světlý'], ['dark', 'Tmavý']]);
    opts('collect', COLLECT.map(([v, l, lv]) => [v, `${l} <span class="tag tag-${lv}">${tag(lv)}</span>`]), 'pill');
    opts('archive', ARCHIVE.map(([v, l, lv]) => [v, `${l} <span class="tag tag-${lv}">${tag(lv)}</span>`]), 'pill');
    $('[data-collect-label]').textContent = fu ? 'Jak dlouho sbírat vzpomínky – i po rozloučení' : 'Jak dlouho sbírat gratulace';
    $('[data-collect-note]').textContent = fu ? `Hosté mohou přidávat vzpomínky a kondolence i po rozloučení. Sběr se uzavře ${Math.max(S.collect, 30)} dní po datu rozloučení.` : 'Sběr začne hned po založení a uzavře se nejpozději v den události.';

    $('[data-themes]').innerHTML = THEMES.map(([v, l, bg, c1, c2, lv]) => {
      const level = fu && v === 'ticho' ? 0 : lv;
      return `<button type="button" class="theme" data-theme="${v}" aria-pressed="${S.theme === v}"><span class="sw" style="background:${bg}"><i style="background:${c1}"></i><i style="background:${c2}"></i></span><span class="nm"><strong>${l}</strong><span class="tag tag-${level}">${tag(level)}</span></span></button>`;
    }).join('');

    toggles('guestFeatures', [
      ['importList', 'Import gratulantů', 'Nahrajte seznam (CSV/Excel) nebo kontakty – odkazy se vytvoří samy', 2],
      ['anonLink', 'Anonymní pozvánka', 'Jeden společný odkaz pro kohokoli – host se podepíše sám (třeba na sociálních sítích nebo v kanceláři)', 2],
      ['langs', 'Překlady vlastních textů', 'Název a text pozvánky i v angličtině a němčině (rozhraní pro hosty je vícejazyčné vždy)', 2],
    ]);
    toggles('features', [
      [null, 'Fotky a psané vzpomínky', 'Základ každé události', 0],
      ['voice', fu ? 'Hlasové a psané vzkazy' : 'Hlasová a psaná přání', 'Namluvená v prohlížeči nebo napsaná, veřejná i soukromá', 1],
      ['nowm', 'Videoprezentace bez vodoznaku', 'Čistý film na oslavu', 1],
      ['offline', 'Offline balíček', 'Videoprezentace poběží i bez internetu', 1],
      ['video', fu ? 'Videa od hostů' : 'Videa od gratulantů', 'Krátká videa až do 200 MB', 2],
      fu ? null : ['game', 'Hra „Hádej, kdo to napsal“', 'Autor se odhalí až po stisku tlačítka', 2],
      ['mp4', 'Film MP4 ke stažení', 'Videoprezentace jako video na památku', 2],
      ['reminders', fu ? 'Připomínky hostům e-mailem' : 'Připomínky gratulantům e-mailem', 'Automaticky upozorníme ty, kdo ještě nepřispěli', 2],
    ].filter(Boolean));
    toggles('addons', [
      ['book', 'Fotokniha', 'Tištěná kniha ze vzpomínek, od 1 290 Kč', 1],
      ['archExt', 'Archiv o rok déle', '290 Kč / rok', 1],
      ['vipSetup', 'Nastavení a pozvánky za vás', 'Vše připravíme a obvoláme gratulanty', 3],
      ['vipEdit', 'Sestřih filmu na míru', 'Doladíme pořadí, hudbu a titulky', 3],
    ]);

    const occ = OCCASIONS.find(o => o[0] === S.occasion)[1], th = THEMES.find(t => t[0] === S.theme)[1];
    $('[data-recap]').innerHTML = [
      ['Událost', `${S.name || '—'} · ${occ}`], ['Adresa', `gratulomat.cz/${S.slug || '…'}`],
      ['Pro', S.recName || '—'], ['Datum', S.date ? S.date.split('-').reverse().join('. ') : '—'],
      ['Gratulanti', String(S.guests)], ['Vzhled', `${th} · ${S.mode === 'dark' ? 'tmavý' : 'světlý'}`],
    ].map(([k, v]) => `<div><span>${k}</span><strong>${esc(v)}</strong></div>`).join('');
    $('[data-company-fields]').hidden = !$('[data-company]').checked;
    renderSlug(); renderAside(); renderNav();
  }
  function opts(key, list, cls) {
    const box = $(`[data-options="${key}"]`);
    box.innerHTML = list.map(([v, html]) => `<button type="button" class="${cls || ''}" data-opt="${key}" data-val="${v}" aria-pressed="${String(S[key]) === String(v)}">${html}</button>`).join('');
  }
  function toggles(key, list) {
    $(`[data-toggles="${key}"]`).innerHTML = list.map(([k, l, d, lv]) => `<label class="tg"><input type="checkbox" ${k ? `data-tog="${k}"` : 'disabled'} ${!k || S[k] ? 'checked' : ''}><span class="t"><strong>${l}</strong><small>${d}</small></span><span class="tag tag-${lv}">${tag(lv)}</span></label>`).join('');
  }
  function renderSlug() {
    const m = $('[data-slug-msg]'), box = $('[data-sugg]');
    const st = slugState.status;
    const map = { ok: ['✓ Adresa je volná', 'ok'], taken: ['Tahle adresa už je obsazená. Vyberte jinou:', 'bad'], reserved: ['Tahle adresa je rezervovaná. Vyberte jinou:', 'bad'], invalid: ['Použijte 3–30 znaků: malá písmena bez diakritiky, číslice a pomlčky.', 'bad'], checking: ['Ověřuji…', ''], error: ['Adresu se nepodařilo ověřit.', 'bad'] };
    const [t, c] = S.slug ? (map[st] || ['', '']) : ['', ''];
    m.textContent = t; m.className = 'msg ' + c;
    box.hidden = !slugState.suggestions?.length;
    box.innerHTML = 'Volné: ' + (slugState.suggestions || []).map(s => `<button type="button" data-sug="${s}">${s}</button>`).join('');
  }
  function renderAside() {
    const q = quote;
    $('[data-tier]').textContent = q ? q.plan_name : '…';
    $('[data-total]').textContent = q ? (q.inquiry ? 'od ' + kc(q.total) : kc(q.total)) : '';
    $('[data-lines]').innerHTML = q ? (q.inquiry ? `<div><span>Tarif VIP</span><b>od ${kc(q.total)}</b></div>` : q.lines.map(l => `<div><span>${esc(l.name)}${l.qty > 1 ? ' × ' + l.qty : ''}</span><b>${kc(l.total / 100)}</b></div>`).join('')) : '';
    $('[data-reasons]').innerHTML = q ? q.reasons.map(r => `<div>• ${esc(r)}</div>`).join('') : '';
    $('[data-meta-guests]').textContent = S.guests;
    $('[data-meta-cap]').textContent = q ? q.guest_limit : '';
    $('[data-meta-collect]').textContent = COLLECT.find(c => c[0] === S.collect)[1];
    $('[data-meta-archive]').textContent = ARCHIVE.find(c => c[0] === S.archive)[1] + (S.archExt ? ' + 1 rok' : '');
  }
  function valid(i) {
    if (i === 0) return S.name.trim().length > 1 && slugState.status === 'ok' && !!S.date;
    if (i === 1) return S.recName.trim().length > 0 && (!S.startYear || /^(19|20)\d\d$/.test(S.startYear));
    if (i === 6) return /.+@.+\..+/.test($('#w-email').value) && $('[data-terms]').checked;
    return true;
  }
  function renderNav() {
    const ok = valid(S.step), last = S.step === 6, q = quote;
    $('[data-back]').hidden = S.step === 0;
    const btn = $('[data-next]');
    btn.disabled = !ok;
    btn.textContent = !last ? 'Pokračovat →' : !q ? 'Počítám cenu…' : q.inquiry ? 'Odeslat VIP poptávku' : q.total === 0 ? 'Založit událost zdarma' : `Zaplatit ${kc(q.total)} přes Stripe`;
    $('[data-block]').textContent = ok ? '' : S.step === 0 ? 'Doplňte název, volnou adresu a datum' : S.step === 1 ? 'Doplňte jméno (rok ve tvaru 1967)' : S.step === 6 ? 'Doplňte e-mail a souhlas' : '';
  }
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  // --- události ---
  document.addEventListener('input', e => {
    const k = e.target.dataset.k;
    if (!k) { if (e.target.matches('#w-email')) renderNav(); return; }
    if (k === 'name') { const p = { name: e.target.value }; if (!S.slugTouched) p.slug = slugify(e.target.value); set(p, { slug: !S.slugTouched }); return; }
    if (k === 'slug') { const v = slugify(e.target.value); e.target.value = e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, ''); set({ slug: v, slugTouched: true }, { slug: true, quote: false }); return; }
    if (k === 'guests') { set({ guests: parseInt(e.target.value, 10) || 1 }); return; }
    set({ [k]: e.target.value }, { quote: k !== 'recName' && k !== 'startYear' });
  });
  document.addEventListener('change', e => {
    if (e.target.dataset.tog) set({ [e.target.dataset.tog]: e.target.checked });
    if (e.target.matches('[data-company]')) $('[data-company-fields]').hidden = !e.target.checked;
    if (e.target.matches('[data-terms], [data-company]')) renderNav();
  });
  document.addEventListener('click', e => {
    const b = e.target.closest('button'); if (!b) return;
    if (b.dataset.go) { S.step = +b.dataset.go; save(); render(); window.scrollTo({ top: 0, behavior: 'smooth' }); }
    if (b.dataset.opt) {
      let v = b.dataset.val; if (['collect', 'archive'].includes(b.dataset.opt)) v = +v;
      const p = { [b.dataset.opt]: v };
      if (b.dataset.opt === 'occasion') {
        if (v === 'funeral') Object.assign(p, { theme: 'ticho', mode: 'light', game: false, recipient: 'person' });
        else if (S.theme === 'ticho') p.theme = 'zaklad';
        if (['wedding', 'anniversary'].includes(v)) p.recipient = 'couple';
        if (v === 'company') p.recipient = 'company';
        if (v === 'reunion') p.recipient = 'team';
      }
      set(p);
    }
    if (b.dataset.theme) set({ theme: b.dataset.theme });
    if (b.dataset.sug) set({ slug: b.dataset.sug, slugTouched: true }, { slug: true, quote: false });
    if (b.matches('[data-back]')) { S.step = Math.max(0, S.step - 1); save(); render(); }
    if (b.matches('[data-next]')) {
      if (!valid(S.step)) return;
      if (S.step < 6) { S.step++; S.maxStep = Math.max(S.maxStep, S.step); save(); render(); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
      $('[data-config]').value = JSON.stringify(S);
      b.disabled = true; b.textContent = 'Odesílám…';
      $('[data-wizard]').submit();
    }
  });

  render();
  if (S.slug) checkSlug();
  requestQuote();
})();
