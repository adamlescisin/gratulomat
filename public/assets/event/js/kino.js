/* Gratulomat — Kino: automaticky sestavený film ze vzpomínek hostů (texty v primárním jazyce události) */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const h = (tag, attrs = {}, ...kids) => {
    const el = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
      if (v == null || v === false) continue;
      if (k === 'class') el.className = v; else if (k === 'style') el.style.cssText = v;
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v); else el.setAttribute(k, v === true ? '' : v);
    }
    for (const kid of kids.flat()) if (kid != null && kid !== false) el.append(kid.nodeType ? kid : document.createTextNode(kid));
    return el;
  };
  const rnd = (a, b) => a + Math.random() * (b - a);
  const plural = (n, a, b, c) => (n === 1 ? a : n >= 2 && n <= 4 ? b : c);

  const PHOTO_SEC = 5.5;
  const DECADES = { 1970: 'Sedmdesátá léta', 1980: 'Osmdesátá léta', 1990: 'Devadesátá léta', 2000: 'Nultá léta', 2010: 'Desátá léta', 2020: 'Dvacátá léta' };

  const kino = $('[data-kino]'), stage = $('[data-stage]'), music = $('[data-music]');
  const P = { data: null, slides: [], idx: -1, paused: true, t: 0, dur: 8, cur: null, guess: false, last: 0, musicOn: true, ac: null };

  /* ---------------- data → scénář ---------------- */
  async function load() {
    const d = window.GM.demoShow || await (await fetch(window.GM.showUrl, { credentials: 'same-origin' })).json();
    if (d.error) throw new Error(d.error);
    P.data = d;
    // vodoznak pro tarify bez „Videoprezentace bez vodoznaku“ (Ochutnávka)
    if (d.watermark && !$('.kino__wm')) kino.append(h('a', { class: 'kino__wm', href: 'https://gratulomat.cz', target: '_blank', rel: 'noopener' }, 'Vytvořeno v Gratulomatu · gratulomat.cz'));
    const url = (m, f) => `${d.media_base}/${m.id}/${f}`;
    d.memories.forEach(m => m.media.forEach(x => { x.url = x.url || url(m, x.file); x.thumbUrl = x.thumbUrl || (x.thumb ? url(m, x.thumb) : x.url); }));

    const by = d.birth_year, mem = d.memories;
    // vzpomínka = příběh/fotky/video; přání (text nebo hlas, jen veřejné) má vlastní slajd ve finále
    const hasStory = m => !!(m.title || m.body || m.media.some(x => x.kind === 'photo' || x.kind === 'video'));
    const hasWish = m => (m.wish_public || d.recipient) && !!(m.wish_text || m.media.some(x => x.kind === 'audio'));
    const stories = mem.filter(hasStory);
    const dated = stories.filter(m => m.year).sort((a, b) => a.year - b.year || a.created_at.localeCompare(b.created_at));
    const undated = shuffle(stories.filter(m => !m.year));
    const wishes = shuffle(mem.filter(hasWish));
    const S = [{ type: 'title' }];
    const tl = d.timeline !== false; // časová osa vypnutá → jen kapitoly Vzpomínky a Přání
    if (!tl) {
      const ms = stories.slice().sort((a, b) => a.created_at.localeCompare(b.created_at));
      if (ms.length) {
        S.push({ type: 'chapter', big: String(ms.length), label: 'Vzpomínky', meta: `${ms.length} ${plural(ms.length, 'vzpomínka', 'vzpomínky', 'vzpomínek')}` });
        ms.forEach(m => S.push({ type: 'memory', m, chapter: 'Vzpomínky' }));
      }
    }
    const groups = new Map();
    if (tl) dated.forEach(m => { const dec = Math.floor(m.year / 10) * 10; groups.has(dec) || groups.set(dec, []); groups.get(dec).push(m); });
    for (const [dec, ms] of groups) {
      const from = Math.max(dec, by), to = Math.min(dec + 9, new Date().getFullYear());
      S.push({ type: 'chapter', big: String(dec), label: DECADES[dec] || `${dec}s`, meta: `${d.celebrant_dative} ${from - by}–${to - by} · ${ms.length} ${plural(ms.length, 'vzpomínka', 'vzpomínky', 'vzpomínek')}` });
      ms.forEach(m => S.push({ type: 'memory', m, chapter: DECADES[dec] }));
    }
    if (tl && undated.length) {
      S.push({ type: 'chapter', big: '∞', label: 'Kdykoliv a pořád', meta: `${undated.length} ${plural(undated.length, 'vzpomínka', 'vzpomínky', 'vzpomínek')} bez letopočtu` });
      undated.forEach(m => S.push({ type: 'memory', m, chapter: 'Kdykoliv a pořád' }));
    }
    if (wishes.length) {
      S.push({ type: 'chapter', big: tl && d.age ? `${d.age}+` : String(wishes.length), label: tl ? 'Do dalších let' : 'Přání', meta: `${wishes.length} ${plural(wishes.length, 'přání', 'přání', 'přání')}` });
      wishes.forEach(m => S.push({ type: 'wish', m, chapter: 'Přání' }));
    }
    S.push({ type: 'finale' });
    P.slides = S;

    const authors = new Set(mem.flatMap(m => m.authors)), photos = mem.reduce((n, m) => n + m.media.filter(x => x.kind === 'photo').length, 0);
    P.stats = { people: authors.size, photos, memories: stories.length, wishes: wishes.length };
    $('[data-start-meta]').textContent = mem.length
      ? `${stories.length} ${plural(stories.length, 'vzpomínka', 'vzpomínky', 'vzpomínek')} · ${wishes.length} přání · ${photos} ${plural(photos, 'fotka', 'fotky', 'fotek')} · od ${authors.size} ${plural(authors.size, 'člověka', 'lidí', 'lidí')}`
      : 'Zatím tu nejsou žádné vzpomínky.';
    $('[data-action=start]').disabled = !mem.length;
    buildProgressMarks(); buildOverview();
  }
  function shuffle(a) { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; }

  /* ---------------- přehrávač ---------------- */
  function start() {
    P.guess = $('[data-guess]').checked;
    $('[data-start]').classList.add('is-gone');
    try { P.ac = new (window.AudioContext || window.webkitAudioContext)(); } catch {}
    if (music) {
      // Hlasitost přes Web Audio (GainNode): iOS ignoruje audio.volume, takže by se podkres neztišoval.
      // Offline balíček (file://) zůstává u audio.volume – Web Audio by tam ze souboru hrál ticho.
      if (P.ac && location.protocol.startsWith('http')) {
        try {
          P.gain = P.ac.createGain(); P.gain.gain.value = 0; P.gain.connect(P.ac.destination);
          // dvě kopie stopy pro plynulé prolnutí konce smyčky do jejího začátku
          const twin = music.cloneNode(); twin.removeAttribute('data-music'); music.after(twin);
          M.els = [music, twin].map(el => {
            el.loop = false; const g = P.ac.createGain(); P.ac.createMediaElementSource(el).connect(g); g.connect(P.gain);
            el.addEventListener('ended', () => { if (M.els[M.cur]?.el === el && !P.paused) { el.currentTime = 0; el.play().catch(() => {}); } }); // pojistka (uspaná karta)
            return { el, g };
          });
          M.els[1].g.gain.value = 0;
        } catch { P.gain = null; M.els = []; }
      }
      if (!P.gain) music.volume = 0; // offline: klasický loop přes atribut, bez prolnutí
      musicPlay(); fadeMusic(.55, 2500);
    }
    P.paused = false; go(0); kino.focus();
    requestAnimationFrame(loop);
  }

  function loop(now) {
    const dt = P.last ? Math.min(.1, (now - P.last) / 1000) : 0; P.last = now;
    if (!P.paused && P.cur) {
      P.t += dt;
      P.cur.tick?.(P.t);
      // konec prezentace: podkres během posledních sekund plynule dozní
      if (P.idx === P.slides.length - 1 && !P.musicEnding && P.dur - P.t <= MUSIC_OUT) { P.musicEnding = true; fadeMusic(0, (P.dur - P.t) * 1000); }
      if (P.t >= P.dur) {
        if (P.cur.waitGuess && !P.cur.revealed) { /* čeká se na Enter */ }
        else if (P.idx < P.slides.length - 1) go(P.idx + 1);
        else { P.paused = true; P.ended = true; setTimeout(musicPause, 300); }
      }
    }
    musicTick();
    const total = P.slides.length, frac = P.dur ? Math.min(1, P.t / P.dur) : 0;
    $('[data-progress] i').style.width = `${((Math.max(0, P.idx) + frac) / total) * 100}%`;
    requestAnimationFrame(loop);
  }

  function go(i) {
    i = Math.max(0, Math.min(P.slides.length - 1, i));
    P.cur?.leave?.();
    const old = stage.querySelector('.slide.is-on');
    if (old) { old.classList.remove('is-on'); setTimeout(() => old.remove(), 1200); }
    P.idx = i; P.t = 0;
    if (P.musicEnding || P.ended) { // návrat z konce – hudba zase naběhne
      const wasEnded = P.ended; P.musicEnding = P.ended = false;
      if (wasEnded) { P.paused = false; kino.classList.remove('is-paused'); }
      if (P.musicOn) musicPlay(); fadeMusic(.55, 1500);
    }
    const s = P.slides[i];
    const r = RENDER[s.type](s);
    P.cur = r; P.dur = r.dur;
    stage.append(r.el);
    requestAnimationFrame(() => requestAnimationFrame(() => r.el.classList.add('is-on')));
    r.enter?.();
    $('[data-chapter]').textContent = s.chapter || s.label || '';
    preload(i + 1);
    markOverview();
  }

  function preload(i) {
    const s = P.slides[i];
    if (s?.type === 'memory') s.m.media.filter(x => x.kind === 'photo').forEach(x => { new Image().src = x.url; });
  }

  /* ---------------- slidy ---------------- */
  const RENDER = {
    title() {
      const by = P.data.birth_year;
      const tl = P.data.timeline !== false;
      const count = h('div', { class: 's-title__count', style: tl ? null : 'display:none' }, by);
      const el = h('section', { class: 'slide s-title' }, h('div', {}, count,
        h('div', { class: 's-title__name' }, P.data.title),
        h('div', { class: 's-title__sub' }, [
          `${P.stats.memories} ${plural(P.stats.memories, 'vzpomínka', 'vzpomínky', 'vzpomínek')}`,
          `${P.stats.photos} ${plural(P.stats.photos, 'fotka', 'fotky', 'fotek')}`,
          P.stats.people === 1 ? 'od 1 jedinečného člověka, bez kterého by tento příběh nebyl úplný…'
            : `od ${P.stats.people} jedinečných lidí, bez kterých by tento příběh nebyl úplný…`,
        ].join(' · '))));
      return { el, dur: 9, tick(t) {
        const p = Math.min(1, Math.max(0, (t - .6) / 3.6)), e = 1 - Math.pow(1 - p, 3);
        if (tl) count.textContent = Math.round(by + P.data.age * e);
        if (p >= 1) el.classList.add('is-done');
      } };
    },

    chapter(s) {
      const el = h('section', { class: 'slide s-chapter' }, h('div', {},
        h('div', { class: 's-chapter__num' }, s.big), h('div', { class: 's-chapter__label' }, s.label), h('div', { class: 's-chapter__meta' }, s.meta)));
      return { el, dur: 4.5 };
    },

    memory({ m }) {
      const photos = m.media.filter(x => x.kind === 'photo');
      const video = m.media.find(x => x.kind === 'video');
      const audio = null; // hlasovka patří k přání → slajd 'wish'
      const text = (m.body || '').trim();
      const words = text.split(/\s+/).filter(Boolean).length;
      const readSec = Math.min(26, Math.max(6, 3 + words / 2.6)); // čte se nahlas a společně → pomaleji
      const by = m.authors.join(' a ');
      const captionParts = (excerpt) => [
        m.year && d().timeline !== false ? h('div', { class: 'caption__year' }, `${m.year} · ${d().celebrant_dative} ${m.year - d().birth_year}`) : null,
        m.title ? h('h2', { class: 'caption__title' }, m.title) : null,
        excerpt ? h('p', { class: 'caption__text' }, excerpt) : null,
        h('div', { class: 'caption__by' }, by),
      ];
      let r;

      if (video) {
        const v = h('video', { src: video.url, playsinline: true, preload: 'auto' });
        const el = h('section', { class: 'slide s-video' }, v, h('div', { class: 'caption' }, captionParts(null)));
        r = { el, dur: 60,
          enter() { duck(true); v.play().catch(() => {}); v.addEventListener('loadedmetadata', () => { if (isFinite(v.duration)) r.dur = P.dur = Math.min(v.duration + 1, 180); }, { once: true }); },
          pause() { v.pause(); }, resume() { v.play(); }, leave() { v.pause(); duck(false); } };
        if (v.readyState >= 1 && isFinite(v.duration)) r.dur = Math.min(v.duration + 1, 180);
      } else if (photos.length) {
        const long = text.length > 240;
        const imgs = photos.map((p, i) => {
          const img = h('img', { class: `s-photo__img layer${i === 0 ? ' is-on' : ''}`, src: p.url, alt: '' });
          const ar = (p.width && p.height) ? p.width / p.height : 1.5;
          if (!long && Math.abs(ar - innerWidth / innerHeight) < .35) img.classList.add('cover');
          const s0 = rnd(1, 1.06), s1 = s0 + rnd(.06, .14);
          img.style.cssText = `--s0:${s0};--s1:${s1};--x0:${rnd(-1.5, 1.5)}%;--y0:${rnd(-1.5, 1.5)}%;--x1:${rnd(-2.5, 2.5)}%;--y1:${rnd(-2.5, 2.5)}%;--dur:${PHOTO_SEC + 1.5}s`;
          return img;
        });
        const bg = h('div', { class: 's-photo__bg', style: `background-image:url("${photos[0].thumbUrl}")` });
        const frame = h('div', { class: 's-photo__frame' }, imgs);
        const excerpt = long ? text : (text.length > 0 ? text : null);
        const el = long
          ? h('section', { class: 'slide s-photo s-split' }, h('div', { style: 'position:relative;overflow:hidden' }, bg, frame), h('div', { class: 's-split__text' }, h('div', { class: 'caption' }, captionParts(excerpt))))
          : h('section', { class: 'slide s-photo' }, bg, frame, h('div', { class: 'caption' }, captionParts(excerpt)));
        const per = Math.max(PHOTO_SEC, readSec / photos.length);
        let shown = 0;
        r = { el, dur: Math.max(photos.length * per, readSec),
          tick(t) {
            const k = Math.min(photos.length - 1, Math.floor(t / per));
            if (k !== shown) {
              imgs[shown].classList.remove('is-on');
              imgs[k].classList.add('is-on');
              imgs[k].style.animation = 'none'; imgs[k].offsetHeight; imgs[k].style.animation = '';
              bg.style.backgroundImage = `url("${photos[k].thumbUrl}")`;
              shown = k;
            }
          } };
      } else {
        // čistě textová vzpomínka – slova se postupně rozsvěcují
        const body = h('div', { class: `s-text__body${text.length > 420 ? ' is-long' : ''}` });
        const ws = text.split(/(\s+)/).map(w => (/\s+/.test(w) ? document.createTextNode(w) : h('span', { class: 'w' }, w)));
        body.append(...ws);
        const spans = ws.filter(w => w.nodeType === 1);
        const el = h('section', { class: 'slide s-text' }, h('div', { class: 's-text__inner' },
          h('span', { class: 's-text__mark', 'aria-hidden': 'true' }, '“'), body,
          h('div', { class: 'caption' }, m.year && d().timeline !== false ? h('div', { class: 'caption__year' }, m.year) : null, m.title ? h('h2', { class: 'caption__title' }, m.title) : null, h('div', { class: 'caption__by' }, by))));
        if (!text && !audio) body.textContent = m.title || '';
        r = { el, dur: readSec + 2, tick(t) { const n = Math.ceil(spans.length * Math.min(1, t / (readSec * .7))); for (let i = 0; i < n; i++) spans[i].classList.add('is-on'); } };
        if (!text && audio) { body.textContent = '🎙️'; body.style.fontSize = '6rem'; }
      }

      if (audio) attachAudio(r, audio);
      addGuess(r);
      return r;
    },

    wish({ m }) {
      const audio = m.media.find(x => x.kind === 'audio');
      const text = (m.wish_text || '').trim();
      const by = m.authors.join(' a ');
      const body = h('div', { class: `s-text__body${text.length > 420 ? ' is-long' : ''}` });
      const ws = text.split(/(\s+)/).map(w => (/\s+/.test(w) ? document.createTextNode(w) : h('span', { class: 'w' }, w)));
      body.append(...ws);
      const spans = ws.filter(w => w.nodeType === 1);
      if (!text && audio) { body.textContent = '🎙️'; body.style.fontSize = '6rem'; }
      const words = text.split(/\s+/).filter(Boolean).length;
      const readSec = Math.min(24, Math.max(6, 3 + words / 2.6));
      const el = h('section', { class: 'slide s-text s-wish' }, h('div', { class: 's-text__inner' },
        h('span', { class: 's-text__mark', 'aria-hidden': 'true' }, '🥂'), body,
        h('div', { class: 'caption' }, h('div', { class: 'caption__year' }, d().timeline !== false ? 'Přání do dalších let' : 'Přání'), h('div', { class: 'caption__by' }, by))));
      const r = { el, dur: readSec + 2, tick(t) { const n = Math.ceil(spans.length * Math.min(1, t / (readSec * .7))); for (let i = 0; i < n; i++) spans[i].classList.add('is-on'); } };
      if (audio) attachAudio(r, audio);
      addGuess(r);
      return r;
    },

    finale() {
      const loop = window.GM && window.GM.loop; // growth loop; u smutečních událostí server nic nepošle
      const all = P.data.memories.flatMap(m => m.media.filter(x => x.kind === 'photo').map(x => x.thumbUrl));
      const cols = Math.ceil(Math.sqrt(Math.max(all.length, 1) * (innerWidth / innerHeight)));
      const rows = Math.ceil(Math.max(all.length, 1) / cols);
      const tiles = shuffle(all.slice()).map((u, i) => h('span', { style: `background-image:url("${u}");animation-delay:${(i * 0.06).toFixed(2)}s` }));
      const el = h('section', { class: 'slide s-finale' },
        h('div', { class: 's-finale__mosaic', style: `--cols:${cols};grid-template-rows:repeat(${rows},1fr)` }, tiles),
        h('div', { class: 's-finale__inner' },
          h('div', { class: 's-finale__title' }, (d().finale_title || `Všechno nejlepší, ${d().celebrant_vocative}!`)),
          h('div', { class: 's-finale__sub' }, [`${P.stats.people} lidí`, `${P.stats.memories} vzpomínek`, `${P.stats.wishes} přání`, `${P.stats.photos} fotek`, d().timeline !== false && d().age ? `${d().age} let` : null].filter(Boolean).join(' · ')),
          loop && h('a', { class: 's-finale__loop', href: loop.url, target: '_blank', rel: 'noopener' }, loop.label)));
      return { el, dur: 30, enter() { fadeMusic(.8, 2000); confetti(); }, leave() { fadeMusic(.55, 1000); } };
    },
  };
  const d = () => P.data;

  function addGuess(r) {
    if (!P.guess) return;
    r.waitGuess = true;
    r.el.classList.add('guess-hidden');
    const badge = h('div', { class: 'guess-badge' }, 'Kdo to napsal? 🤔 (Enter)');
    r.el.append(badge);
    r.reveal = () => { if (r.revealed) return; r.revealed = true; r.el.classList.remove('guess-hidden'); badge.remove(); P.dur = Math.max(P.t + 4, P.dur); };
  }

  function attachAudio(r, a) {
    const au = new Audio(a.url); au.preload = 'auto';
    const viz = h('div', { class: 's-audio__viz' }, Array.from({ length: 14 }, () => h('i')));
    r.el.append(viz, h('div', { class: 's-audio__label' }, '🎙️ hlasový vzkaz'));
    let analyser, data, raf;
    const bars = [...viz.children];
    const draw = () => {
      if (analyser) { analyser.getByteFrequencyData(data); bars.forEach((b, i) => { b.style.height = `${12 + (data[i * 3] / 255) * 88}%`; }); }
      else bars.forEach(b => { b.style.height = `${au.paused ? 12 : rnd(15, 95)}%`; });
      raf = requestAnimationFrame(draw);
    };
    const prev = { enter: r.enter, leave: r.leave, pause: r.pause, resume: r.resume };
    r.enter = () => {
      prev.enter?.(); duck(true);
      try { if (P.ac) { const src = P.ac.createMediaElementSource(au); analyser = P.ac.createAnalyser(); analyser.fftSize = 128; data = new Uint8Array(analyser.frequencyBinCount); src.connect(analyser); analyser.connect(P.ac.destination); } } catch { analyser = null; }
      setTimeout(() => au.play().catch(() => {}), 900);
      au.addEventListener('loadedmetadata', () => { if (isFinite(au.duration)) { r.dur = Math.max(r.dur, au.duration + 2.5); if (P.cur === r) P.dur = r.dur; } }, { once: true });
      draw();
    };
    r.leave = () => { prev.leave?.(); au.pause(); cancelAnimationFrame(raf); duck(false); };
    r.pause = () => { prev.pause?.(); au.pause(); };
    r.resume = () => { prev.resume?.(); au.play().catch(() => {}); };
  }

  /* ---------------- hudba ---------------- */
  let fadeRaf;
  function fadeMusic(to, ms) {
    if (!music) return;
    cancelAnimationFrame(fadeRaf);
    const target = P.musicOn ? to : 0;
    if (P.gain) { // plynulá křivka přímo v audio vlákně
      const g = P.gain.gain, now = P.ac.currentTime;
      g.cancelScheduledValues(now); g.setValueAtTime(g.value, now); g.linearRampToValueAtTime(target, now + ms / 1000);
      return;
    }
    const from = music.volume, t0 = performance.now();
    const step = now => { const p = Math.min(1, (now - t0) / ms); music.volume = from + (target - from) * p; if (p < 1) fadeRaf = requestAnimationFrame(step); };
    fadeRaf = requestAnimationFrame(step);
  }
  const duck = on => fadeMusic(on ? .07 : .55, on ? 600 : 1800);

  // Smyčka podkresu: kratší skladba se opakuje a její konec se prolne do začátku (equal-power crossfade),
  // delší skladba na konci prezentace dozní (MUSIC_OUT). Bez Web Audio (offline) platí atribut loop.
  const MUSIC_OUT = 6, M = { els: [], cur: 0, xf: false };
  const musicEl = () => M.els.length ? M.els[M.cur].el : music;
  function musicPlay() { if (music) musicEl().play().catch(() => {}); }
  function musicPause() { if (!music) return; M.els.length ? M.els.forEach(x => x.el.pause()) : music.pause(); }
  const XF_IN = Float32Array.from({ length: 32 }, (_, i) => Math.sin((i / 31) * Math.PI / 2));
  const XF_OUT = Float32Array.from({ length: 32 }, (_, i) => Math.cos((i / 31) * Math.PI / 2));
  function musicTick() {
    if (M.els.length < 2 || M.xf || P.paused) return;
    const a = M.els[M.cur], d = a.el.duration;
    if (!isFinite(d) || a.el.paused) return;
    if (d < 10) { a.el.loop = true; return; } // velmi krátká stopa (jingle) – klasická smyčka
    const xf = Math.min(4, d / 4);
    if (a.el.currentTime < d - xf) return;
    M.xf = true;
    const b = M.els[1 - M.cur], now = P.ac.currentTime;
    b.el.currentTime = 0; b.el.play().catch(() => {});
    [[b.g, XF_IN], [a.g, XF_OUT]].forEach(([g, curve]) => { g.gain.cancelScheduledValues(now); g.gain.setValueCurveAtTime(curve, now, xf); });
    M.cur = 1 - M.cur;
    setTimeout(() => { a.el.pause(); M.xf = false; }, xf * 1000 + 250);
  }

  /* ---------------- ovládání ---------------- */
  function toggle(force) {
    P.paused = force ?? !P.paused;
    P.paused ? P.cur?.pause?.() : P.cur?.resume?.();
    P.paused ? musicPause() : (P.musicOn && !P.ended && musicPlay());
    kino.classList.toggle('is-paused', P.paused);
    showHud();
  }
  function fullscreen() { document.fullscreenElement ? document.exitFullscreen() : (kino.requestFullscreen?.() || kino.webkitRequestFullscreen?.()); }
  function overview(force) {
    const o = $('[data-overview]'), show = force ?? o.hidden;
    o.hidden = !show;
    if (show) { toggle(true); o.querySelector('.is-current')?.scrollIntoView({ block: 'center' }); }
  }
  function randomMemory() {
    const ids = P.slides.map((s, i) => (s.type === 'memory' || s.type === 'wish' ? i : -1)).filter(i => i >= 0 && i !== P.idx);
    if (ids.length) go(ids[Math.floor(Math.random() * ids.length)]);
  }

  function buildProgressMarks() {
    const bar = $('[data-progress]');
    P.slides.forEach((s, i) => { if (s.type === 'chapter') bar.append(h('b', { style: `left:${(i / P.slides.length) * 100}%` })); });
    bar.addEventListener('click', e => { const r = bar.getBoundingClientRect(); go(Math.floor(((e.clientX - r.left) / r.width) * P.slides.length)); });
  }
  function buildOverview() {
    const g = $('[data-overview-grid]'); g.innerHTML = '';
    P.slides.forEach((s, i) => {
      let btn;
      if (s.type === 'wish') {
        btn = h('button', { class: 'ov ov--chapter', style: 'font-size:1rem;padding:.8rem;text-align:center' }, `🥂 ${(s.m.wish_text || 'hlasové přání').slice(0, 60)}`, h('span', {}, s.m.authors.join(' a ')));
      } else if (s.type === 'memory') {
        const ph = s.m.media.find(x => x.kind === 'photo');
        btn = h('button', { class: 'ov', style: ph ? `background-image:url("${ph.thumbUrl}")` : '' },
          h('span', {}, h('b', {}, s.m.title || (s.m.body || '').slice(0, 40) || '🎙️'), s.m.authors.join(' a ') + (s.m.year && P.data.timeline !== false ? ` · ${s.m.year}` : '')));
      } else btn = h('button', { class: 'ov ov--chapter' }, s.type === 'chapter' ? s.label : s.type === 'title' ? 'Úvod' : 'Finále');
      btn.dataset.i = i;
      btn.addEventListener('click', () => { overview(false); go(i); toggle(false); });
      g.append(btn);
    });
  }
  function markOverview() { document.querySelectorAll('.ov').forEach(b => b.classList.toggle('is-current', +b.dataset.i === P.idx)); }

  let hudT;
  function showHud() {
    kino.classList.remove('hud-off', 'hide-cursor'); clearTimeout(hudT);
    hudT = setTimeout(() => { if (!P.paused) kino.classList.add('hud-off', 'hide-cursor'); }, 2500);
  }

  document.addEventListener('keydown', e => {
    if ($('[data-start]').classList.contains('is-gone') === false) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('[data-action=start]').disabled || start(); } return; }
    const k = e.key.toLowerCase();
    if (k === ' ') { e.preventDefault(); toggle(); }
    else if (k === 'arrowright' || k === 'pagedown') go(P.idx + 1);
    else if (k === 'arrowleft' || k === 'pageup') go(P.idx - 1);
    else if (k === 'enter') { if (P.cur?.waitGuess && !P.cur.revealed) { P.cur.reveal(); confetti(40); } else go(P.idx + 1); }
    else if (k === 'f') fullscreen();
    else if (k === 'g') overview();
    else if (k === 'escape') overview(false);
    else if (k === 'r') randomMemory();
    else if (k === 'm') { P.musicOn = !P.musicOn; fadeMusic(P.musicOn ? .55 : 0, 500); if (P.musicOn && !P.paused) musicPlay(); }
    else if (k === 'home') go(0);
    else if (k === 'end') go(P.slides.length - 1);
    showHud();
  });
  document.addEventListener('mousemove', showHud);
  document.addEventListener('click', e => {
    const a = e.target.closest('[data-action]')?.dataset.action;
    if (!a) { if (e.target.closest('.kino__stage') && P.cur?.waitGuess && !P.cur.revealed) P.cur.reveal(); return; }
    ({ start, toggle: () => toggle(), next: () => go(P.idx + 1), prev: () => go(P.idx - 1), overview: () => overview(), fullscreen,
      music: () => document.dispatchEvent(new KeyboardEvent('keydown', { key: 'm' })) })[a]?.();
  });
  // swipe na tabletu
  let tx = null;
  stage.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
  stage.addEventListener('touchend', e => { if (tx == null) return; const dx = e.changedTouches[0].clientX - tx; if (Math.abs(dx) > 60) go(P.idx + (dx < 0 ? 1 : -1)); tx = null; });

  function confetti(n = 220) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = h('canvas', { class: 'confetti' }); document.body.append(c);
    const ctx = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
    // barvy konfet podle šablony (proměnné z themes.css)
    const cs = getComputedStyle(document.body), v = n => cs.getPropertyValue(n).trim();
    const colors = [v('--gold') || '#e3a94b', v('--gold-2') || '#f6cf7d', v('--coral') || '#e5674b', v('--teal') || '#5fb3a4', v('--paper') || '#f3ead8'];
    const ps = Array.from({ length: n }, () => ({ x: Math.random() * innerWidth, y: -20 - Math.random() * innerHeight * .5, vx: rnd(-1.5, 1.5), vy: rnd(2, 6),
      r: rnd(4, 9), c: colors[Math.floor(Math.random() * colors.length)], a: rnd(0, 6), va: rnd(-.15, .15) }));
    let f = 0;
    (function loop() {
      ctx.clearRect(0, 0, c.width, c.height);
      ps.forEach(p => { p.x += p.vx + Math.sin((f + p.r) / 20); p.y += p.vy; p.a += p.va;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.a); ctx.fillStyle = p.c; ctx.fillRect(-p.r, -p.r / 2, p.r * 2, p.r); ctx.restore(); });
      if (++f < 360) requestAnimationFrame(loop); else c.remove();
    })();
  }

  load().catch(err => { $('[data-start-meta]').textContent = 'Nepodařilo se načíst vzpomínky: ' + err.message; });
})();
