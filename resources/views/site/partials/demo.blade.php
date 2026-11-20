{{-- Ukázkový Gratulomat: po stisku tlačítka [data-demo] konfety a okno s videem z back-office (landing i coming soon). --}}
@php($demo = \App\Support\DemoVideo::get())
<dialog class="demo" data-demo-dialog aria-label="Ukázkový Gratulomat">
  <button type="button" class="demo__close" data-demo-close aria-label="Zavřít">×</button>
  @if ($demo && $demo['type'] === 'embed')
    <iframe data-demo-frame data-src="{{ $demo['src'] }}" title="Ukázkový Gratulomat" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
  @elseif ($demo)
    <video data-demo-video src="{{ $demo['src'] }}" @if ($demo['poster']) poster="{{ $demo['poster'] }}" @endif controls playsinline preload="none"></video>
  @else
    <p class="demo__empty">Ukázku právě připravujeme. Zatím si můžete <a href="{{ route('wizard') }}">založit vlastní Gratulomat</a> – Ochutnávka je zdarma.</p>
  @endif
</dialog>
<script>
(() => {
  const btn = document.querySelector('[data-demo]'), dlg = document.querySelector('[data-demo-dialog]');
  if (!btn || !dlg) return;
  const video = dlg.querySelector('[data-demo-video]'), frame = dlg.querySelector('[data-demo-frame]');
  function confetti(n = 180) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const c = document.createElement('canvas'); c.className = 'demo-confetti'; document.body.append(c);
    const ctx = c.getContext('2d'); c.width = innerWidth; c.height = innerHeight;
    const colors = ['#FF5A3C', '#FFC433', '#FFF6E9', '#2EC4A0', '#8B7CF6'], r = (a, b) => a + Math.random() * (b - a);
    const ps = Array.from({ length: n }, () => ({ x: innerWidth / 2 + r(-80, 80), y: innerHeight * .6, vx: r(-9, 9), vy: r(-17, -7), s: r(5, 10), c: colors[n++ % colors.length], a: r(0, 6), va: r(-.2, .2) }));
    let f = 0;
    (function tick() {
      ctx.clearRect(0, 0, c.width, c.height);
      ps.forEach(p => { p.vy += .35; p.vx *= .99; p.x += p.vx; p.y += p.vy; p.a += p.va;
        ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.a); ctx.fillStyle = p.c; ctx.fillRect(-p.s, -p.s / 2, p.s * 2, p.s); ctx.restore(); });
      if (++f < 150) requestAnimationFrame(tick); else c.remove();
    })();
  }
  btn.addEventListener('click', () => {
    confetti();
    setTimeout(() => {
      dlg.showModal();
      if (frame && !frame.src) frame.src = frame.dataset.src;
      video?.play().catch(() => {});
    }, 650);
  });
  const close = () => { video?.pause(); if (frame) frame.removeAttribute('src'); dlg.close(); };
  dlg.querySelector('[data-demo-close]').addEventListener('click', close);
  dlg.addEventListener('click', e => { if (e.target === dlg) close(); });
  dlg.addEventListener('cancel', () => { video?.pause(); frame?.removeAttribute('src'); });
})();
</script>
