/* Gratulomat — administrace organizátora (převzato z režisérské kabiny prototypu) */
(() => {
  'use strict';
  const GM = window.GM;
  const $ = (s, r = document) => r.querySelector(s);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const post = async (url, body) => {
    const r = await fetch(`${GM.base}/api/${url}`, { method: 'POST', headers: { 'X-CSRF-TOKEN': GM.csrf, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.error || j.message || `Chyba ${r.status}`);
    return j;
  };
  const fmt = iso => new Date(iso).toLocaleString('cs-CZ', { day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
  const localInput = iso => { const d = new Date(iso); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 16); };
  let S;
  const field = (form, name) => form.querySelector(`[name="${name}"]`);

  async function load() {
    S = await (await fetch(`${GM.base}/api/state`, { headers: { Accept: 'application/json' } })).json();
    const mem = S.memories, hh = S.households, E = S.event;
    const has = (m, k) => m.media.some(x => x.kind === k);
    const photos = mem.reduce((n, m) => n + m.media.filter(x => x.kind === 'photo').length, 0);
    const active = hh.filter(x => x.memories > 0).length, visited = hh.filter(x => x.last_seen).length;
    $('[data-kpis]').innerHTML = [
      [mem.length, 'vzpomínek'], [photos, 'fotek'], [mem.filter(m => has(m, 'audio') || has(m, 'video')).length, 's hlasem / videem'],
      [`${active}/${hh.length}`, 'domácností přispělo'], [`${visited}/${hh.length}`, 'otevřelo odkaz'],
      [mem.filter(m => m.wish_public && (m.wish_text || has(m, 'audio'))).length, E.funeral ? 'veřejných vzkazů' : 'veřejných přání'],
      [mem.filter(m => !m.wish_public && (m.wish_text || has(m, 'audio'))).length, E.funeral ? 'soukromých vzkazů 🔒' : 'soukromých přání 🔒'],
    ].map(([v, l]) => `<div class="kpi"><b>${v}</b><span>${l}</span></div>`).join('');

    // videoprezentace
    const sh = S.show;
    $('[data-show-status]').textContent = sh.is_public ? (sh.public_from ? `Hosté videoprezentaci vidí (od ${fmt(sh.public_from)}).` : `Hosté videoprezentaci vidí – automaticky po uzávěrce ${fmt(sh.auto_share_at)}.`)
      : sh.public_from ? `Naplánováno: hosté ji uvidí od ${fmt(sh.public_from)}.`
      : sh.auto_share ? `Hosté ji uvidí automaticky po uzávěrce sběru (${fmt(sh.auto_share_at)}).` : 'Hosté videoprezentaci zatím nevidí. Vy ji máte v náhledu kdykoli.';
    $('[data-timeline]').checked = sh.timeline; $('[data-auto-share]').checked = sh.auto_share; $('[data-auto-share-at]').textContent = fmt(sh.auto_share_at);
    $('[data-show-at]').value = localInput(sh.public_from || E.event_date);
    $('[data-game-wrap]').hidden = !E.game_available; $('[data-game]').checked = sh.game_enabled;
    renderMusic(); renderHandover();

    // hosté
    $('[data-hh-count]').textContent = `(${hh.length}${E.guest_limit ? ' z ' + E.guest_limit : ''})`;
    $('[data-households] tbody').innerHTML = hh.map(x => `
      <tr class="${x.memories ? '' : 'is-silent'}">
        <td><strong>${esc(x.label)}</strong>${x.source === 'anonymous' ? ' <span class="badge" title="Přihlásil se sám přes anonymní pozvánku">sám</span>' : x.source === 'import' ? ' <span class="badge">import</span>' : ''}<br><span class="muted">${esc(x.names.join(' · '))}${x.email ? ' · ' + esc(x.email) : ''}${x.mail_status ? ` · <em>${esc(x.mail_status)}</em>` : ''}</span></td>
        <td>${esc(x.relation || '')}</td>
        <td>${x.memories}</td>
        <td class="muted">${x.last_seen ? fmt(x.last_seen) : '—'}</td>
        <td><code>${esc(x.link.replace(/^https?:\/\//, ''))}</code></td>
        <td class="row-actions"><div>
          <button class="btn btn--ghost btn--sm" data-qr-id="${x.id}">QR + pozvánka</button>
          <button class="btn btn--ghost btn--sm" data-edit="${x.id}">Upravit</button>
          <button class="btn btn--ghost btn--sm" data-token="${x.id}" title="Vygenerovat nový odkaz (starý přestane platit)">↻</button>
          <button class="btn btn--ghost btn--sm btn--danger" data-del="${x.id}">×</button>
        </div></td>
      </tr>`).join('');

    // pozvánky e-mailem
    const withMail = hh.filter(x => x.email && x.mail_status !== 'odhlásil'), notInvited = withMail.filter(x => !x.mail_status);
    $('[data-mail-info]').textContent = withMail.length
      ? `E-mail má ${withMail.length} z ${hh.length} hostů, zatím nepozváno: ${notInvited.length}. Použije se text pozvánky z Nastavení, odpovědi chodí na váš e-mail.`
      : 'Zatím žádný host nemá e-mail. Doplňte ho přes „Upravit“ nebo importem seznamu.';
    $('[data-action="send-invites"]').disabled = !notInvited.length;
    $('[data-action="send-invites"]').textContent = notInvited.length ? `Poslat pozvánky e-mailem (${notInvited.length})` : 'Všichni s e-mailem jsou pozvaní';
    $('[data-reminders-wrap]').hidden = !E.reminders_available; $('[data-reminders-off]').hidden = E.reminders_available;
    $('[data-reminders]').checked = E.reminders;

    // import a anonymní pozvánka (od Premiéry)
    $('[data-file-import-on]').hidden = !E.import_available; $('[data-file-import-off]').hidden = E.import_available;
    $('[data-anon-on]').hidden = !E.anon_available; $('[data-anon-off]').hidden = E.anon_available;
    $('[data-anon-on]').innerHTML = E.anon_link
      ? `<p><code data-anon-url>${esc(E.anon_link)}</code></p><div class="row">
          <button class="btn btn--gold btn--sm" data-anon="copy">Kopírovat odkaz</button>
          <button class="btn btn--ghost btn--sm" data-anon="regenerate" title="Starý odkaz přestane platit">↻ Nový odkaz</button>
          <button class="btn btn--ghost btn--sm btn--danger" data-anon="disable">Vypnout</button></div>`
      : `<button class="btn btn--gold btn--sm" data-anon="enable">Vytvořit anonymní odkaz</button>`;

    // vzpomínky (organizátor vidí i soukromá přání)
    $('[data-mem-count]').textContent = `(${mem.length})`;
    const url = (m, f) => `${S.media_base}/${m.id}/${f}`;
    $('[data-memories]').innerHTML = mem.map(m => {
      const ph = m.media.filter(x => x.kind === 'photo'), audio = m.media.find(x => x.kind === 'audio'), video = m.media.find(x => x.kind === 'video');
      const wish = m.wish_text || audio;
      return `<article class="mem ${wish ? 'mem--wish' : ''} ${m.status === 'hidden' ? 'mem--hidden' : ''}">
        ${ph.length ? `<div class="mem__media" style="--cols:${Math.min(2, ph.length)}">${ph.slice(0, 4).map(p => `<img loading="lazy" src="${url(m, p.thumb || p.file)}" alt="">`).join('')}${ph.length > 4 ? `<span class="more">+${ph.length - 4}</span>` : ''}</div>`
          : video ? `<div class="mem__media"><video src="${url(m, video.file)}" controls preload="metadata" playsinline></video></div>`
          : `<div class="mem__quote">${esc(m.title || (m.body || '').slice(0, 80) || '🎙️')}</div>`}
        <div class="mem__body">
          <div class="mem__meta">${m.year ? `<span class="mem__year">${m.year}</span>` : ''}${m.tags.map(t => `<span class="mem__tag">${esc(t)}</span>`).join('')}${audio ? '<span class="mem__tag">🎙️</span>' : ''}${video ? '<span class="mem__tag">🎬</span>' : ''}</div>
          ${m.title ? `<h3 class="mem__title">${esc(m.title)}</h3>` : ''}
          ${m.body ? `<p class="mem__text">${esc(m.body)}</p>` : ''}
          ${m.wish_text ? `<p class="mem__wish">🥂 ${esc(m.wish_text)}<small>${m.wish_public ? 'veřejné' : '🔒 soukromé'}</small></p>` : ''}
          ${audio ? `<audio controls preload="none" src="${url(m, audio.file)}"></audio>${m.wish_public ? '' : '<small class="hint">🔒 soukromé hlasové přání</small>'}` : ''}
          <div class="mem__by">— ${esc(m.authors.join(' a '))} <span class="muted">(${esc(m.household.label)})</span></div>
        </div>
        <div class="mem__actions">
          <button class="btn btn--ghost btn--sm" data-hide="${m.id}" data-status="${m.status}">${m.status === 'hidden' ? 'Zobrazit' : 'Skrýt z videoprezentace'}</button>
          <button class="btn btn--ghost btn--sm btn--danger" data-mdel="${m.id}">Smazat</button>
        </div>
      </article>`;
    }).join('') || '<p class="empty">Zatím žádné vzpomínky.</p>';

    // tarif a doplňky
    renderPlan();

    // nastavení
    const bn = $('.brand__name'); if (bn) bn.textContent = `${E.title} · administrace`;
    const f = $('[data-settings]');
    // vzhled: šablona + světlý / tmavý režim
    if (!f.dataset.loaded) { f.dataset.theme = E.theme; f.dataset.mode = E.mode; }
    renderThemes();
    if (!f.dataset.loaded) { field(f, 'title').value = E.title; field(f, 'recipient_name').value = E.celebrant; if (E.funeral) $('[data-rec-label]').firstChild.textContent = 'Jméno zesnulé / zesnulého'; field(f, 'invite_text').value = E.invite_text; field(f, 'finale_title').value = E.finale_title || ''; field(f, 'finale_title').placeholder = E.finale_default; if (E.collect_until) field(f, 'collect_until').value = localInput(E.collect_until); if (S.show.wall_open_at) field(f, 'wall_open_at').value = localInput(S.show.wall_open_at); if (E.funeral) field(f, 'wall_open_at').closest('label').hidden = true; f.dataset.loaded = '1'; }
  }

  function renderPlan() {
    const E = S.event, f = $('[data-upgrade]');
    $('[data-plan-now]').textContent = `Teď: ${E.plan_name} · hosté až ${E.guest_limit ?? '∞'} · archiv do ${E.archive_until ? new Date(E.archive_until).toLocaleDateString('cs-CZ') : '—'}`;
    const ar = $('[data-archived]');
    ar.hidden = !E.is_archived && !E.purged;
    ar.textContent = E.purged ? 'Archiv této události vypršel a fotky, videa a vzkazy byly smazány.'
      : 'Viditelnost (archiv) této události skončila – hosté ji už nevidí. Prodlužte archiv níže, jinak budou fotky a vzkazy po 30 dnech smazány.';
    $('[data-plan-card]').hidden = E.purged;
    if (!f.dataset.ready) {
      $('[data-up-plan]').innerHTML = E.upgrade_plans.map(p => `<option value="${p.key}">${esc(p.name)}${p.key === E.plan ? ' (současný)' : ` – až ${p.guests} hostů`}</option>`).join('');
      f.dataset.ready = '1';
    }
  }
  let upTimer;
  async function upgradeQuote() {
    const f = $('[data-upgrade]'), body = { plan: field(f, 'plan').value, guest_packs: +field(f, 'guest_packs').value || 0, archive_years: +field(f, 'archive_years').value || 0, photobook: field(f, 'photobook').checked };
    const q = await post('upgrade-quote', body);
    const kc = h => (h / 100).toLocaleString('cs-CZ') + ' Kč';
    $('[data-up-sum]').innerHTML = q.errors.length ? `<p class="alert">${esc(q.errors[0])}</p>`
      : q.lines.length ? `${q.lines.map(l => `<div><span>${esc(l.name)}${l.qty > 1 ? ' × ' + l.qty : ''}</span><b>${kc(l.total)}</b></div>`).join('')}
        <div class="upgrade__total"><span>Doplatek celkem (s DPH)</span><b>${kc(q.total)}</b></div>
        <p class="muted" style="font-size:.85rem">Po zaplacení: hosté až ${q.guests} · archiv do ${new Date(q.archive_until).toLocaleDateString('cs-CZ')}</p>` : '';
    const btn = $('[data-up-pay]');
    btn.disabled = !!q.errors.length || q.total <= 0;
    btn.textContent = q.total > 0 && !q.errors.length ? `Zaplatit ${kc(q.total)} přes Stripe` : 'Vyberte změnu';
  }

  function renderThemes() {
    const f = $('[data-settings]'), E = S.event, mode = f.dataset.mode, darkOk = E.themes[0]?.dark_available;
    $('[data-mode-pick]').innerHTML = [['light', '☀️ Světlý'], ['dark', '🌙 Tmavý']].map(([m, l]) =>
      `<button type="button" class="btn btn--sm ${mode === m ? 'btn--gold' : 'btn--ghost'}" data-pick-mode="${m}" ${m === 'dark' && !darkOk ? 'disabled title="Tmavý režim je od tarifu Párty"' : ''}>${l}${m === 'dark' && !darkOk ? ' · od Párty' : ''}</button>`).join('');
    $('[data-theme-pick]').innerHTML = E.themes.map(t => {
      const [bg, fg, ac] = mode === 'dark' ? t.dark : t.light, on = f.dataset.theme === t.key;
      return `<button type="button" class="theme-card ${on ? 'is-on' : ''}" data-pick-theme="${t.key}" ${t.available ? '' : 'disabled'} aria-pressed="${on}">
        <span class="theme-card__sw" style="background:${bg};color:${fg}"><b style="font-family:${{ vesela: '\'Bricolage Grotesque\'', neutralni: 'Inter' }[t.key] || 'Fraunces'}, serif;font-weight:${t.key === 'vesela' ? 800 : t.key === 'neutralni' ? 700 : 400}">Aa</b><i style="background:${ac}"></i></span>
        <strong>${esc(t.name)}</strong><small>${esc(t.desc)}</small><small class="muted">${esc(t.fonts)}${t.available ? '' : ' · od tarifu Párty'}</small></button>`;
    }).join('');
  }

  const run = async (fn, okMsg) => { try { await fn(); if (okMsg) toast(okMsg); await load(); } catch (e) { toast(e.message); } };

  document.addEventListener('submit', e => {
    if (e.target.matches('[data-import]')) {
      e.preventDefault();
      run(async () => {
        const r = await post('households', { lines: field(e.target, 'lines').value });
        e.target.reset();
        toast(`Přidáno: ${r.added}${r.skipped ? ` · ${r.skipped} nepřidáno (limit tarifu ${r.limit})` : ''}`);
      });
    }
    if (e.target.matches('[data-file-import]')) {
      e.preventDefault();
      run(async () => {
        const fd = new FormData(e.target);
        const r = await fetch(`${GM.base}/api/import`, { method: 'POST', headers: { 'X-CSRF-TOKEN': GM.csrf, Accept: 'application/json' }, body: fd });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(j.error || j.message || `Chyba ${r.status}`);
        e.target.reset();
        toast(`Importováno: ${j.added}${j.duplicates ? ` · ${j.duplicates} už v seznamu` : ''}${j.skipped ? ` · ${j.skipped} nad limit tarifu (${j.limit})` : ''}`);
      });
    }
    if (e.target.matches('[data-upgrade]')) {
      e.preventDefault();
      const f = e.target, btn = $('[data-up-pay]');
      btn.disabled = true; btn.textContent = 'Přesměrovávám na platbu…';
      post('upgrade', { plan: field(f, 'plan').value, guest_packs: +field(f, 'guest_packs').value || 0, archive_years: +field(f, 'archive_years').value || 0, photobook: field(f, 'photobook').checked })
        .then(r => { location.href = r.url; })
        .catch(err => { toast(err.message); upgradeQuote(); });
      return;
    }
    if (e.target.matches('[data-settings]')) {
      e.preventDefault();
      run(() => post('settings', { finale_title: field(e.target, 'finale_title').value, wall_open_at: field(e.target, 'wall_open_at').value || null, theme: e.target.dataset.theme, mode: e.target.dataset.mode, title: field(e.target, 'title').value, recipient_name: field(e.target, 'recipient_name').value, invite_text: field(e.target, 'invite_text').value, collect_until: field(e.target, 'collect_until').value || null }), 'Uloženo ✓');
    }
  });

  document.addEventListener('click', e => {
    const t = e.target.closest('button'); if (!t) return;
    const a = t.dataset.action;
    if (t.dataset.qrId) openQr(S.households.find(x => x.id == t.dataset.qrId));
    if (t.dataset.edit) {
      const x = S.households.find(y => y.id == t.dataset.edit);
      const label = prompt('Jména (např. „Petra a Honza“):', x.label); if (label == null) return;
      const relation = prompt('Vztah (nepovinné):', x.relation || ''); if (relation == null) return;
      const email = prompt('E-mail pro pozvánku (nepovinné):', x.email || ''); if (email == null) return;
      run(() => post(`households/${x.id}`, { label, relation, email }));
    }
    if (t.dataset.action === 'send-invites' && confirm('Poslat pozvánky e-mailem všem dosud nepozvaným hostům s e-mailem?'))
      run(async () => { const r = await post('send-invites', {}); toast(`Ve frontě: ${r.queued} – odesíláme postupně během pár minut`); }, null);
    if (t.dataset.pickTheme) { $('[data-settings]').dataset.theme = t.dataset.pickTheme; renderThemes(); return; }
    if (t.dataset.pickMode) { $('[data-settings]').dataset.mode = t.dataset.pickMode; renderThemes(); return; }
    if (t.dataset.anon === 'copy') { navigator.clipboard?.writeText(S.event.anon_link).then(() => toast('Odkaz zkopírován ✓')); }
    if (t.dataset.anon === 'enable') run(() => post('anon-link', { action: 'enable' }), 'Anonymní odkaz je připravený');
    if (t.dataset.anon === 'regenerate' && confirm('Vytvořit nový odkaz? Ten starý přestane fungovat.')) run(() => post('anon-link', { action: 'regenerate' }), 'Nový odkaz vytvořen');
    if (t.dataset.anon === 'disable' && confirm('Vypnout anonymní pozvánku? Odkaz přestane fungovat, už přihlášení hosté zůstanou.')) run(() => post('anon-link', { action: 'disable' }), 'Anonymní pozvánka vypnutá');
    if (t.dataset.token && confirm('Vygenerovat nový odkaz? Ten starý přestane fungovat.')) run(() => post(`households/${t.dataset.token}`, { new_token: true }), 'Nový odkaz vytvořen');
    if (t.dataset.del && confirm('Smazat domácnost i všechny její příspěvky?')) run(() => post(`households/${t.dataset.del}`, { delete: true }));
    if (t.dataset.hide) run(() => post(`memories/${t.dataset.hide}`, { status: t.dataset.status === 'hidden' ? 'visible' : 'hidden' }));
    if (t.dataset.mdel && confirm('Natrvalo smazat vzpomínku?')) run(() => post(`memories/${t.dataset.mdel}`, { delete: true }));
    if (a === 'show-now' && confirm('Zpřístupnit videoprezentaci všem hostům hned teď?')) run(() => post('show', { public_from: 'now' }), 'Videoprezentace je otevřená 🎬');
    if (a === 'show-at') run(() => post('show', { public_from: $('[data-show-at]').value }), 'Naplánováno');
    if (a === 'show-close') run(() => post('show', { public_from: null }), 'Zavřeno pro hosty');
    if (a === 'close-qr') $('[data-qr]').close();
    if (a === 'copy-msg') navigator.clipboard.writeText($('[data-qr-msg]').value).then(() => toast('Zkopírováno – vložte do WhatsAppu nebo e-mailu.'));
  });
  $('[data-upgrade]').addEventListener('input', () => { clearTimeout(upTimer); upTimer = setTimeout(upgradeQuote, 250); });
  if (new URLSearchParams(location.search).get('navyseni') === 'ok') {
    // čekání na potvrzení platby webhookem
    const plan0 = () => S.event && `${S.event.plan}|${S.event.guest_limit}|${S.event.archive_until}`; let first = null, n = 0;
    const iv = setInterval(async () => { await load(); first ??= plan0(); if (plan0() !== first || ++n > 20) { clearInterval(iv); $('[data-upgrade-banner]')?.remove(); history.replaceState(null, '', location.pathname); if (n <= 20) toast('Navýšení je aktivní ✓'); } }, 3000);
  }
  $('[data-reminders]').addEventListener('change', e => run(() => post('settings', { reminders: e.target.checked }), e.target.checked ? 'Připomínky zapnuty' : 'Připomínky vypnuty'));
  $('[data-auto-share]').addEventListener('change', e => run(() => post('settings', { auto_share: e.target.checked }), e.target.checked ? 'Po uzávěrce se prezentace hostům nasdílí sama' : 'Automatické nasdílení vypnuto'));
  $('[data-timeline]').addEventListener('change', e => run(() => post('settings', { timeline: e.target.checked }), e.target.checked ? 'Časová osa zapnuta – vzpomínky podle let' : 'Časová osa vypnuta – jen vzpomínky a přání'));
  // hudební podkres: štítky filtrují nabídku, doporučené podle vzhledu a typu události jsou předvybrané
  let musicTag = null;
  function renderMusic() {
    const M = S.music, cur = S.show.music_track_id;
    $('[data-music-wrap]').hidden = !M.tracks.length;
    if (!M.tracks.length) return;
    const all = [...new Set(M.tracks.flatMap(t => t.tags))].sort((a, b) => (M.recommended.includes(b) - M.recommended.includes(a)) || a.localeCompare(b, 'cs'));
    $('[data-music-tags]').innerHTML = [`<button type="button" class="tag ${musicTag ? '' : 'is-on'}" data-mtag="">vše</button>`]
      .concat(all.map(t => `<button type="button" class="tag ${musicTag === t ? 'is-on' : ''}" data-mtag="${esc(t)}">${esc(t)}${M.recommended.includes(t) ? ' ★' : ''}</button>`)).join('');
    const rec = t => t.tags.some(x => M.recommended.includes(x));
    const list = M.tracks.filter(t => !musicTag || t.tags.includes(musicTag) || t.id === cur).sort((a, b) => rec(b) - rec(a) || a.title.localeCompare(b.title, 'cs'));
    $('[data-music-select]').innerHTML = `<option value="">Bez hudby</option>` + list.map(t =>
      `<option value="${t.id}" ${t.id === cur ? 'selected' : ''}>${rec(t) ? '★ ' : ''}${esc(t.title)}${t.artist ? ' – ' + esc(t.artist) : ''} (${t.duration}${t.tags.length ? ' · ' + esc(t.tags.join(', ')) : ''})</option>`).join('');
  }
  $('[data-music-tags]').addEventListener('click', e => { const b = e.target.closest('[data-mtag]'); if (!b) return; musicTag = b.dataset.mtag || null; renderMusic(); });
  $('[data-music-select]').addEventListener('change', e => {
    const a = $('[data-music-audio]'); a.pause(); $('[data-action=music-preview]').textContent = '▶ Ukázka';
    run(() => post('show', { music_track_id: e.target.value || null }), e.target.value ? 'Podkres vybrán' : 'Videoprezentace bude bez hudby');
  });
  $('[data-action=music-preview]').addEventListener('click', e => {
    const a = $('[data-music-audio]'), t = S.music.tracks.find(x => String(x.id) === $('[data-music-select]').value);
    if (!t) return toast('Nejdřív vyberte skladbu');
    if (!a.paused && a.dataset.id === String(t.id)) { a.pause(); e.target.textContent = '▶ Ukázka'; return; }
    a.src = t.url; a.dataset.id = t.id; a.currentTime = 0; a.play(); e.target.textContent = '❚❚ Zastavit';
    a.onended = () => { e.target.textContent = '▶ Ukázka'; };
  });

  // předání oslavenci (u smutečního rozloučení rodině)
  function renderHandover() {
    const H = S.show.handover, fu = S.event.funeral;
    $('[data-ho-title]').textContent = fu ? 'Předání rodině' : 'Předání oslavenci';
    $('[data-ho-intro]').textContent = fu
      ? 'Pošlete rodině tajný odkaz: uvidí vzpomínkovou prezentaci, všechny vzpomínky a veřejné vzkazy a může si stáhnout offline balíček. Soukromé vzkazy zůstávají jen u vás, jak bylo hostům slíbeno. Nic nemůže měnit. Funguje hned, i když prezentace pro hosty není zpřístupněná.'
      : 'Pošlete oslavenci tajný odkaz: uvidí videoprezentaci, všechny vzpomínky včetně soukromých přání určených jemu a může si stáhnout offline balíček. Nic nemůže měnit. Funguje hned, i když prezentace pro hosty ještě není zpřístupněná – pošlete ho třeba den po oslavě.';
    $('[data-ho-email]').placeholder = fu ? 'E-mail někoho z rodiny' : 'E-mail oslavence';
    const em = $('[data-ho-email]'), msg = $('[data-ho-message]');
    if (document.activeElement !== em) em.value = H?.email || '';
    if (document.activeElement !== msg) msg.value = H?.message || '';
    $('[data-ho-state]').hidden = !H;
    $('[data-action=ho-send]').textContent = H?.sent_at ? 'Odeslat znovu' : 'Odeslat e-mailem';
    $('[data-action=ho-link]').textContent = H ? 'Uložit věnování' : 'Jen vytvořit odkaz';
    if (!H) return;
    $('[data-ho-url]').value = H.url; $('[data-ho-open]').href = H.url;
    $('[data-ho-status]').textContent = [`Odkaz vytvořen ${fmt(H.created_at)}`, H.sent_at ? `odeslán na ${H.email} ${fmt(H.sent_at)}` : 'zatím neodeslán',
      H.opened_at ? `✓ otevřen ${fmt(H.opened_at)}` : 'zatím neotevřen'].join(' · ');
  }
  const hoBody = extra => ({ email: $('[data-ho-email]').value.trim() || null, message: $('[data-ho-message]').value, ...extra });
  $('[data-action=ho-send]').addEventListener('click', () => {
    if (!$('[data-ho-email]').value.trim()) return toast('Vyplňte e-mail');
    run(() => post('handover', hoBody({ send: true })), 'Odkaz je odeslaný e-mailem');
  });
  $('[data-action=ho-link]').addEventListener('click', () => run(() => post('handover', hoBody({})), S.show.handover ? 'Uloženo' : 'Odkaz je připravený – zkopírujte ho a pošlete'));
  $('[data-action=ho-copy]').addEventListener('click', async () => { await navigator.clipboard.writeText($('[data-ho-url]').value).catch(() => $('[data-ho-url]').select()); toast('Odkaz zkopírován'); });
  $('[data-action=ho-regenerate]').addEventListener('click', () => {
    if (confirm('Vyměnit odkaz? Starý okamžitě přestane fungovat a nový musíte poslat znovu.')) run(() => post('handover', hoBody({ regenerate: true })), 'Nový odkaz je připravený');
  });
  $('[data-action=ho-revoke]').addEventListener('click', () => {
    if (!confirm('Zrušit předání? Odkaz okamžitě přestane fungovat.')) return;
    run(async () => {
      const r = await fetch(`${GM.base}/api/handover`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': GM.csrf, Accept: 'application/json' } });
      if (!r.ok) throw new Error(`Chyba ${r.status}`);
    }, 'Předání je zrušené');
  });

  $('[data-game]').addEventListener('change', e => run(() => post('show', { game_enabled: e.target.checked })));

  function openQr(x) {
    const qr = qrcode(0, 'M'); qr.addData(x.link); qr.make();
    $('[data-qr-title]').textContent = x.label;
    $('[data-qr-svg]').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 2, scalable: true });
    const date = new Date(S.event.event_date).toLocaleDateString('cs-CZ', { day: 'numeric', month: 'long' });
    $('[data-qr-msg]').value = (field($('[data-settings]'), 'invite_text').value || S.event.invite_text)
      .replaceAll('{jmena}', x.names.join(' a ')).replaceAll('{odkaz}', x.link).replaceAll('{datum}', date);
    $('[data-qr]').showModal();
  }

  let tt; function toast(m) { const t = $('[data-toast]'); t.textContent = m; t.classList.add('is-on'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('is-on'), 2800); }
  load();
})();
