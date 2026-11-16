/* Gratulomat — stránka pro hosty (rozhraní v jazyce hosta, texty přes window.GM.i18n) */
(() => {
  'use strict';
  const GM = window.GM || {};
  const I = GM.i18n || { js: {}, prompts: [] };
  const LOCALE = GM.locale || 'cs';
  /** Překlad: t('key', {n: 1}) – zástupné symboly :name, :dat, :acc… z GM.vars plus vlastní. */
  const t = (key, vars = {}) => {
    const all = { ...(GM.vars || {}), ...vars };
    return String(key.split('.').reduce((o, k) => o?.[k], I.js) ?? key)
      .replace(/:([a-z_]+)/g, (m, k) => (all[k] ?? m));
  };
  // Množné číslo: tvary oddělené „|“ v pořadí kategorií Intl.PluralRules pro daný jazyk.
  const PLURAL_ORDER = { cs: ['one', 'few', 'other'], en: ['one', 'other'], de: ['one', 'other'] };
  const pl = (n, key) => {
    const forms = t(key).split('|'), order = PLURAL_ORDER[LOCALE] || ['one', 'other'];
    const cat = new Intl.PluralRules(LOCALE).select(n);
    return forms[Math.max(0, order.indexOf(cat))] ?? forms.at(-1);
  };
  const csrf = GM.csrf || document.querySelector('meta[name=csrf-token]')?.content;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const h = (tag, attrs = {}, ...kids) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') el.className = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'html') el.innerHTML = v;
      else el.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat()) if (kid != null && kid !== false) el.append(kid.nodeType ? kid : document.createTextNode(kid));
    return el;
  };

  const PROMPTS = (I.prompts || []).map(p => p.replace(/:([a-z_]+)/g, (m, k) => (GM.vars?.[k] ?? m)));

  /* ---------------- API (skutečné nebo demo) ---------------- */
  const api = GM.demo ? GM.demo : {
    async me() { const r = await fetch(`${GM.base}/api/me`, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); if (r.status === 401) location.reload(); return r.json(); },
    save(fd, id, onProgress) {
      return new Promise((resolve, reject) => {
        const x = new XMLHttpRequest();
        x.open('POST', `${GM.base}/api/memories${id ? '/' + id : ''}`);
        x.setRequestHeader('X-CSRF-TOKEN', csrf);
        x.setRequestHeader('Accept', 'application/json');
        x.upload.onprogress = e => e.lengthComputable && onProgress(e.loaded / e.total);
        x.onload = () => { let j = {}; try { j = JSON.parse(x.responseText); } catch {} x.status < 300 ? resolve(j) : reject(new Error(j.error || j.message || t('error', { code: x.status }))); };
        x.onerror = () => reject(new Error(t('network')));
        x.send(fd);
      });
    },
    async del(id) { const r = await fetch(`${GM.base}/api/memories/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } }); if (!r.ok) throw new Error(t('delete_failed')); },
  };

  const S = { me: null, files: [], removeMedia: new Set(), audio: null, editing: null, wishMode: 'voice', promptIdx: Math.floor(Math.random() * PROMPTS.length) };
  const mediaUrl = (m, f) => (GM.demo ? f : `${S.me.media_base}/${m.id}/${f}`);

  /* ---------------- init ---------------- */
  async function init() {
    S.me = await api.me();
    const { household: hh, config: c } = S.me;
    $('[data-bind=greeting]').textContent = t('hello', { names: LOCALE === 'cs' ? vocative(hh.names) : joinNames(hh.names) });
    $('[data-bind=hello]').textContent = hh.label;
    renderAuthors(); renderTags(); renderStats(); renderMine(); renderWall();
    countdown(); setInterval(countdown, 1000);
    if (!c.submissions_open) { $('[data-closed]').hidden = false; $('[data-composer]').hidden = true; }
    if (!PROMPTS.length) $('.prompt')?.remove();
    if (c.kino_open) {
      $('[data-kino-link]').hidden = false;
      // dole: odkaz a QR na výslednou prezentaci (automaticky po uzávěrce, pokud to organizátor povolil)
      const box = $('[data-share]');
      if (box) {
        box.hidden = false;
        if (window.qrcode) { const qr = qrcode(0, 'M'); qr.addData(new URL($('a', box).href, location.href).href); qr.make(); $('[data-share-qr]').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 2, scalable: true }); }
      }
    }
    bindComposer();
  }

  const joinNames = ns => (ns.length > 1 ? ns.slice(0, -1).join(', ') + t('and') + ns.at(-1) : ns[0] || '');

  // České oslovení – jednoduchá heuristika pro nejčastější jména (5. pád)
  function vocative(names) {
    const v = n => {
      if (/a$/i.test(n)) return n.replace(/a$/i, 'o');            // Petra → Petro
      if (/ěk$/i.test(n)) return n.replace(/ěk$/i, 'ňku');        // Zdeněk → Zdeňku
      if (/ek$/i.test(n)) return n.replace(/ek$/i, 'ku');         // Marek → Marku
      if (/[kgh]$/i.test(n)) return n + 'u';                      // Radek… Oleg → Olegu
      if (/(ie|e|i|y|o|u|í|ý)$/i.test(n)) return n;                   // Marie, Jiří… beze změny
      if (/r$/i.test(n) && /[^aeiouy]r$/i.test(n)) return n.replace(/r$/i, 'ře'); // Petr → Petře
      if (/[šžčřcjď]$/i.test(n)) return n + 'i';                  // Tomáš → Tomáši
      return n + 'e';                                             // Martin → Martine
    };
    const fix = { Pavel: 'Pavle', Karel: 'Karle', Michal: 'Michale', Honza: 'Honzo', Adam: 'Adame' };
    const vs = names.map(n => fix[n] || v(n));
    return vs.length > 1 ? vs.slice(0, -1).join(', ') + ' a ' + vs.at(-1) : vs[0] || '';
  }

  function renderAuthors(selected) {
    const box = $('[data-authors]'); box.innerHTML = '';
    const names = S.me.household.names;
    names.forEach(n => box.append(h('label', { class: 'chip' }, h('input', { type: 'checkbox', name: 'authors[]', value: n, checked: !selected || selected.includes(n) }), h('span', {}, n))));
    if (names.length < 2) box.append(h('span', { class: 'hint' }, t('writing_alone')));
  }
  function renderTags(selected = []) {
    const box = $('[data-tags]'); box.innerHTML = '';
    S.me.config.tags.forEach(t => box.append(h('label', { class: 'chip' }, h('input', { type: 'checkbox', name: 'tags[]', value: t, checked: selected.includes(t) }), h('span', {}, t))));
  }
  function renderStats() {
    const s = S.me.stats, pct = Math.round(100 * s.households / Math.max(1, s.households_total));
    $('[data-stats]').innerHTML = '';
    $('[data-stats]').append(
      h('span', { class: 'stat' }, h('b', {}, s.memories), ' ', pl(s.memories, 'memories')),
      h('span', { class: 'stat' }, h('b', {}, s.photos), ' ', pl(s.photos, 'photos')),
      h('span', { class: 'meter' }, h('span', {}, h('i', { style: `width:${pct}%` })), t('contributed', { n: s.households, total: s.households_total })),
    );
  }

  function countdown() {
    const el = $('[data-countdown]'), left = new Date(S.me.config.party_date) - Date.now();
    if (left <= 0) { el.replaceChildren(h('div', {}, h('b', {}, S.me.config.funeral ? '🕯️' : '🎉'), h('small', {}, S.me.config.funeral ? '' : t('celebrating')))); return; }
    const d = Math.floor(left / 864e5), hh = Math.floor(left / 36e5) % 24, m = Math.floor(left / 6e4) % 60, s = Math.floor(left / 1e3) % 60;
    el.replaceChildren(...[[d, 'd'], [hh, 'h'], [m, 'm'], [s, 's']].map(([v, u]) => h('div', {}, h('b', {}, String(v).padStart(2, '0')), h('small', {}, t('units.' + u)))));
  }

  /* ---------------- karty ---------------- */
  function memCard(m, own) {
    const photos = m.media.filter(x => x.kind === 'photo'), audio = m.media.find(x => x.kind === 'audio'), video = m.media.find(x => x.kind === 'video');
    const hasWish = !!(m.wish_text || audio);
    const card = h('article', { class: `mem${hasWish ? ' mem--wish' : ''}${m.status === 'hidden' ? ' mem--hidden' : ''}` });
    if (photos.length) {
      const shown = photos.slice(0, photos.length >= 4 ? 4 : photos.length === 3 ? 2 : photos.length);
      const media = h('div', { class: 'mem__media', style: `--cols:${shown.length >= 2 ? 2 : 1}` },
        shown.map(p => h('img', { src: mediaUrl(m, p.thumb || p.file), alt: '', loading: 'lazy', 'data-full': mediaUrl(m, p.file) })));
      if (photos.length > shown.length) media.append(h('span', { class: 'more' }, `+${photos.length - shown.length}`));
      card.append(media);
    } else if (video) {
      card.append(h('div', { class: 'mem__media' }, h('video', { src: mediaUrl(m, video.file), controls: true, preload: 'metadata', playsinline: true })));
    } else {
      const b = m.body || m.wish_text || '';
      card.append(h('div', { class: 'mem__quote', style: !m.title && b.length > 100 ? 'font-size:1.05rem' : null }, m.title || (b ? `„${b.length > 180 ? b.slice(0, 180).replace(/\s+\S*$/, '') + '…' : b}“` : t('voice_message'))));
    }
    const body = h('div', { class: 'mem__body' },
      h('div', { class: 'mem__meta' }, m.year ? h('span', { class: 'mem__year' }, m.year) : null, m.tags.map(t => h('span', { class: 'mem__tag' }, t))),
      m.title && (photos.length || video) ? h('h3', { class: 'mem__title' }, m.title) : null,
      m.body && (photos.length || video || m.title) ? h('p', { class: 'mem__text' }, m.body) : null,
      m.wish_text && (m.body || m.title || photos.length || video) ? h('p', { class: 'mem__wish' }, '🥂 ' + m.wish_text, own ? h('small', {}, m.wish_public ? t('public_wish') : t('private_wish')) : null) : null,
      audio ? h('audio', { src: mediaUrl(m, audio.file), controls: true, preload: 'none' }) : null,
      audio && own && !m.wish_public ? h('small', { class: 'hint' }, t('private_voice')) : null,
      h('div', { class: 'mem__by' }, '— ' + joinNames(m.authors)));
    card.append(body);
    if (own) card.append(h('div', { class: 'mem__actions' },
      h('button', { class: 'btn btn--ghost btn--sm', onclick: () => startEdit(m) }, t('edit')),
      h('button', { class: 'btn btn--ghost btn--sm btn--danger', onclick: () => removeMem(m) }, t('delete'))));
    return card;
  }
  function renderMine() {
    const box = $('[data-mine]'); box.innerHTML = '';
    S.me.mine.forEach(m => box.append(memCard(m, true)));
    $('[data-mine-empty]').hidden = S.me.mine.length > 0;
    $('[data-mine-count]').textContent = S.me.mine.length ? `(${S.me.mine.length})` : '';
    const loop = $('[data-loop]'); if (loop) loop.hidden = S.me.mine.length === 0; // growth loop: poděkování až po první vzpomínce
  }
  function renderWall() {
    if (!S.me.wall) { $('[data-teaser]').hidden = false; return; }
    $('[data-wall-section]').hidden = false;
    const box = $('[data-wall]'); box.innerHTML = '';
    S.me.wall.forEach(m => box.append(memCard(m, false)));
  }

  /* ---------------- composer ---------------- */
  function bindComposer() {
    const form = $('[data-composer]');
    const year = $('[data-year]'), yUnknown = $('[data-year-unknown]');
    const syncYear = () => {
      $('[data-year-out]').textContent = year.value;
      const age = year.value - S.me.config.birth_year;
      const person = ['narozeniny', 'duchod', 'rozlucka', 'smutecni'].includes(S.me.config.type);
      $('[data-year-era]').textContent = age < 0 ? '' : person ? (age === 0 ? t('age_birth') : t('age', { age })) : t('age_since', { age: age + 1 });
      $('.year').classList.toggle('is-unknown', yUnknown.checked);
    };
    year.addEventListener('input', () => { yUnknown.checked = false; syncYear(); });
    yUnknown.addEventListener('change', syncYear);
    syncYear();

    $('[data-action=prompt]').addEventListener('click', () => {
      if (!PROMPTS.length) return;
      S.promptIdx = (S.promptIdx + 1) % PROMPTS.length;
      const el = $('[data-prompt]'); el.textContent = PROMPTS[S.promptIdx];
      el.style.animation = 'none'; el.offsetHeight; el.style.animation = '';
    });

    const drop = $('[data-drop]'), input = $('[data-files]');
    input.addEventListener('change', () => { addFiles(input.files); input.value = ''; });
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('is-over'); }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('is-over'); }));
    drop.addEventListener('drop', e => addFiles(e.dataTransfer.files));

    bindRecorder();
    $$('[data-wish-mode]').forEach(b => b.addEventListener('click', () => setWishMode(b.dataset.wishMode)));
    form.addEventListener('submit', submit);
    $('[data-action=cancel-edit]').addEventListener('click', resetComposer);

    document.addEventListener('click', e => {
      const img = e.target.closest('.mem__media img');
      if (img) { const lb = $('[data-lightbox]'); $('img', lb).src = img.dataset.full; lb.showModal(); }
      if (e.target.closest('[data-action=lb-close]') || e.target.matches('[data-lightbox]')) $('[data-lightbox]').close();
    });
  }

  function setWishMode(mode) {
    S.wishMode = mode;
    $$('[data-wish-mode]').forEach(b => { const on = b.dataset.wishMode === mode; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); });
    $$('[data-wish-panel]').forEach(p => { p.hidden = p.dataset.wishPanel !== mode; });
    if (mode === 'text') setTimeout(() => $('[name=wish_text]')?.focus(), 50);
  }

  function addFiles(list) {
    const max = S.me.config.max_photos;
    for (const f of list) {
      if (!f) continue;
      if (!/^(image|video)\//.test(f.type) && !/\.(heic|heif)$/i.test(f.name)) { toast(t('not_media', { file: f.name })); continue; }
      if (f.type.startsWith('video/') && !S.me.config.max_video_mb) { toast(t('no_video')); continue; }
      if (f.type.startsWith('video/') && f.size > S.me.config.max_video_mb * 1048576) { toast(t('video_big', { file: f.name, mb: S.me.config.max_video_mb })); continue; }
      if (S.files.length >= max) { toast(t('max_files', { n: max })); break; }
      S.files.push({ file: f, url: URL.createObjectURL(f) });
    }
    renderThumbs();
  }
  function renderThumbs() {
    const box = $('[data-thumbs]'); box.innerHTML = '';
    if (S.editing) S.editing.media.filter(x => x.kind !== 'audio').forEach(x => {
      const removed = S.removeMedia.has(x.id);
      box.append(h('div', { class: `thumb${removed ? ' is-removed' : ''}` },
        x.kind === 'photo' ? h('img', { src: mediaUrl(S.editing, x.thumb || x.file), alt: '' }) : h('video', { src: mediaUrl(S.editing, x.file), muted: true }),
        h('button', { type: 'button', title: removed ? t('restore') : t('remove'), onclick: () => { removed ? S.removeMedia.delete(x.id) : S.removeMedia.add(x.id); renderThumbs(); } }, removed ? '↺' : '×')));
    });
    S.files.forEach((f, i) => box.append(h('div', { class: 'thumb' },
      f.file.type.startsWith('video/') ? h('video', { src: f.url, muted: true, playsinline: true }) : h('img', { src: f.url, alt: '', onerror: e => { e.target.replaceWith(h('span', { class: 'thumb__badge' }, f.file.name)); } }),
      f.file.type.startsWith('video/') ? h('span', { class: 'thumb__badge' }, t('video_badge')) : null,
      h('button', { type: 'button', title: t('remove'), onclick: () => { URL.revokeObjectURL(f.url); S.files.splice(i, 1); renderThumbs(); } }, '×'))));
  }

  /** Zmenší fotku v prohlížeči na max. 2560 px – rychlé nahrávání i na mobilních datech. */
  async function shrink(file) {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const s = Math.min(1, 2560 / Math.max(bmp.width, bmp.height));
      if (s === 1 && file.size < 3.5e6 && file.type === 'image/jpeg') return file;
      const c = document.createElement('canvas');
      c.width = Math.round(bmp.width * s); c.height = Math.round(bmp.height * s);
      c.getContext('2d').drawImage(bmp, 0, 0, c.width, c.height);
      const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.88));
      return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch { return file; } // např. HEIC v Chrome – necháme na serveru
  }

  async function submit(e) {
    e.preventDefault();
    const form = e.currentTarget, btn = $('[data-submit]'), err = $('[data-error]'), bar = $('[data-progress]');
    err.textContent = '';
    const fd = new FormData();
    $$('[name="authors[]"]:checked', form).forEach(i => fd.append('authors[]', i.value));
    $$('[name="tags[]"]:checked', form).forEach(i => fd.append('tags[]', i.value));
    fd.append('title', form.title.value); fd.append('body', form.body.value);
    if (!$('[data-year-unknown]').checked) fd.append('year', $('[data-year]').value);
    if ($('[data-wish]')?.checked) fd.append('wish_public', '1');
    const wishText = S.wishMode === 'text' && form.wish_text ? form.wish_text.value.trim() : '';
    fd.append('wish_text', wishText);
    // přání je buď hlasové, nebo psané – při psaném zahodíme případnou starou hlasovku
    if (S.wishMode === 'text' && S.removeAudioId) S.removeMedia.add(S.removeAudioId);
    S.removeMedia.forEach(id => fd.append('remove_media[]', id));
    const hasExisting = S.editing && S.editing.media.some(x => !S.removeMedia.has(x.id));
    if (!form.title.value.trim() && !form.body.value.trim() && !wishText && !S.files.length && !(S.audio && S.wishMode === 'voice') && !hasExisting) {
      err.textContent = t('empty'); return;
    }
    btn.disabled = true; btn.textContent = t('preparing'); bar.hidden = false; $('i', bar).style.width = '0';
    try {
      for (const f of S.files) fd.append('files[]', await shrink(f.file));
      if (S.audio && S.wishMode === 'voice') fd.append('audio', S.audio.blob, 'voice.' + (S.audio.blob.type.includes('mp4') ? 'm4a' : 'webm'));
      btn.textContent = t('uploading');
      const saved = await api.save(fd, S.editing?.id, p => { $('i', bar).style.width = `${Math.round(p * 100)}%`; });
      const idx = S.me.mine.findIndex(m => m.id === saved.id);
      if (idx >= 0) S.me.mine[idx] = saved; else { S.me.mine.unshift(saved); S.me.stats.memories++; if (S.me.mine.length === 1) S.me.stats.households++; }
      if (idx < 0) S.me.stats.photos += saved.media.filter(x => x.kind === 'photo').length;
      const wasEdit = !!S.editing;
      resetComposer(); renderMine(); renderStats();
      toast(wasEdit ? t('saved_edit') : t(S.me.config.funeral ? 'saved_new_funeral' : 'saved_new'));
      if (!wasEdit && !S.me.config.funeral) confetti();
      $('.mine').scrollIntoView({ behavior: 'smooth' });
    } catch (ex) {
      err.textContent = ex.message;
    } finally {
      btn.disabled = false; btn.textContent = S.editing ? GM.labels.saveChanges : GM.labels.save; bar.hidden = true;
    }
  }

  function startEdit(m) {
    resetComposer(true);
    S.editing = m;
    const form = $('[data-composer]');
    form.title.value = m.title || ''; form.body.value = m.body || '';
    if (m.year) { $('[data-year]').value = m.year; $('[data-year-unknown]').checked = false; } else $('[data-year-unknown]').checked = true;
    $('[data-year]').dispatchEvent(new Event('input')); if (!m.year) { $('[data-year-unknown]').checked = true; $('[data-year-unknown]').dispatchEvent(new Event('change')); }
    if ($('[data-wish]')) $('[data-wish]').checked = m.wish_public;
    if (form.wish_text) form.wish_text.value = m.wish_text || '';
    renderAuthors(m.authors); renderTags(m.tags); renderThumbs();
    const a = m.media.find(x => x.kind === 'audio');
    setWishMode(m.wish_text && !a ? 'text' : 'voice');
    if (a && $('[data-rec-audio]')) { const el = $('[data-rec-audio]'); el.src = mediaUrl(m, a.file); el.hidden = false; $('[data-rec-status]').textContent = t('rec_saved'); S.removeAudioId = a.id; }
    $('[data-bind=composerTitle]').textContent = GM.labels.composerEdit;
    $('[data-action=cancel-edit]').hidden = false; $('[data-submit]').textContent = GM.labels.saveChanges;
    $('#composer').classList.add('is-editing');
    $('#composer').scrollIntoView({ behavior: 'smooth' });
  }

  function resetComposer(keepScroll) {
    const form = $('[data-composer]'); form.reset();
    S.files.forEach(f => URL.revokeObjectURL(f.url));
    S.files = []; S.removeMedia = new Set(); S.editing = null; S.removeAudioId = null; clearAudio();
    const yr = $('[data-year]'); yr.value = Math.round((+yr.min + +yr.max) / 2); yr.dispatchEvent(new Event('input'));
    renderAuthors(); renderTags(); renderThumbs(); setWishMode('voice');
    $('[data-bind=composerTitle]').textContent = GM.labels.composerNew;
    $('[data-action=cancel-edit]').hidden = true; $('[data-submit]').textContent = GM.labels.save;
    $('#composer').classList.remove('is-editing'); $('[data-error]').textContent = '';
  }

  async function removeMem(m) {
    if (!confirm(t('confirm_delete'))) return;
    try { await api.del(m.id); S.me.mine = S.me.mine.filter(x => x.id !== m.id); S.me.stats.memories--; renderMine(); renderStats(); toast(t('deleted')); }
    catch (ex) { toast(ex.message); }
  }

  /* ---------------- hlasový vzkaz ---------------- */
  function bindRecorder() {
    if (!$('[data-rec]')) return;
    const box = $('[data-rec]'), status = $('[data-rec-status]'), wave = $('[data-rec-wave]'), audioEl = $('[data-rec-audio]');
    let rec, chunks, stream, started, tick, raf, analyser;
    if (!navigator.mediaDevices?.getUserMedia || !window.MediaRecorder) { status.textContent = t('rec_unsupported'); $('[data-action=rec]').disabled = true; return; }
    $('[data-action=rec]').addEventListener('click', async () => {
      if (rec?.state === 'recording') { rec.stop(); return; }
      try { stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } }); }
      catch { status.textContent = t('rec_denied'); return; }
      const mime = ['audio/mp4', 'audio/webm;codecs=opus', 'audio/webm'].find(t => MediaRecorder.isTypeSupported(t)) || '';
      rec = new MediaRecorder(stream, mime ? { mimeType: mime } : {}); chunks = [];
      rec.ondataavailable = e => e.data.size && chunks.push(e.data);
      rec.onstop = () => {
        clearInterval(tick); cancelAnimationFrame(raf); stream.getTracks().forEach(t => t.stop());
        const blob = new Blob(chunks, { type: rec.mimeType || 'audio/webm' });
        S.audio = { blob, url: URL.createObjectURL(blob) };
        if (S.removeAudioId) S.removeMedia.add(S.removeAudioId);
        audioEl.src = S.audio.url; audioEl.hidden = false; wave.hidden = true;
        box.classList.remove('is-recording'); status.textContent = t('rec_done', { t: fmt((Date.now() - started) / 1000) });
        $('[data-action=rec-clear]').hidden = false;
      };
      // vizualizace
      const ac = new (window.AudioContext || window.webkitAudioContext)();
      analyser = ac.createAnalyser(); analyser.fftSize = 256; ac.createMediaStreamSource(stream).connect(analyser);
      const data = new Uint8Array(analyser.frequencyBinCount), ctx = wave.getContext('2d');
      const draw = () => {
        analyser.getByteFrequencyData(data); ctx.clearRect(0, 0, wave.width, wave.height);
        const n = 60, w = wave.width / n;
        for (let i = 0; i < n; i++) { const v = data[Math.floor(i * data.length / n / 1.5)] / 255, hh = Math.max(3, v * wave.height);
          ctx.fillStyle = `rgba(229,103,75,${0.4 + v * 0.6})`; ctx.fillRect(i * w + 1, (wave.height - hh) / 2, w - 3, hh); }
        raf = requestAnimationFrame(draw);
      };
      rec.start(250); started = Date.now(); wave.hidden = false; audioEl.hidden = true; box.classList.add('is-recording'); draw();
      tick = setInterval(() => { const s = (Date.now() - started) / 1000; status.textContent = t('rec_recording', { t: fmt(s) }); if (s > 180) rec.stop(); }, 250);
    });
    $('[data-action=rec-clear]').addEventListener('click', clearAudio);
  }
  function clearAudio() {
    if (S.audio) URL.revokeObjectURL(S.audio.url);
    S.audio = null;
    const a = $('[data-rec-audio]'); if (!a) return;
    a.removeAttribute('src'); a.hidden = true;
    $('[data-rec-status]').textContent = GM.labels.recIdle;
    $('[data-action=rec-clear]').hidden = true;
  }
  const fmt = s => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

  /* ---------------- drobnosti ---------------- */
  let toastT;
  function toast(msg) { const t = $('[data-toast]'); t.textContent = msg; t.classList.add('is-on'); clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('is-on'), 3200); }

  function confetti() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = h('canvas', { class: 'confetti' }); document.body.append(c);
    const ctx = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
    const colors = ['#e3a94b', '#f6cf7d', '#e5674b', '#5fb3a4', '#f3ead8'];
    const ps = Array.from({ length: 160 }, () => ({ x: innerWidth / 2, y: innerHeight * .6, vx: (Math.random() - .5) * 16, vy: -Math.random() * 18 - 6,
      r: Math.random() * 6 + 3, c: colors[Math.floor(Math.random() * colors.length)], a: Math.random() * 6, va: (Math.random() - .5) * .3 }));
    let f = 0;
    (function loop() {
      ctx.clearRect(0, 0, c.width, c.height);
      ps.forEach(p => { p.vy += .45; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.a += p.va;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.a); ctx.fillStyle = p.c; ctx.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); ctx.restore(); });
      if (++f < 180) requestAnimationFrame(loop); else c.remove();
    })();
  }

  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
