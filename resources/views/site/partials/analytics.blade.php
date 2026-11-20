@if (config('gratulomat.ga_id') && ! request()->attributes->get('is_tester') && ! (auth()->user()?->isOperator()))
{{-- Google Analytics 4 se souhlasem (Consent Mode v2): gtag.js se stáhne až po „Povolit“. Události se do té doby jen řadí do dataLayer. --}}
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  gtag('consent', 'default', { ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied' });
  gtag('js', new Date());
  gtag('config', @json(config('gratulomat.ga_id')));
  window.gmTrack = (name, params) => gtag('event', name, params || {});

  (() => {
    const KEY = 'gm-consent';
    const get = () => { try { return localStorage.getItem(KEY); } catch { return null; } };
    const set = v => { try { localStorage.setItem(KEY, v); } catch {} };
    let loaded = false;
    const apply = v => {
      if (v !== 'granted') return;
      gtag('consent', 'update', { analytics_storage: 'granted' });
      if (loaded) return;
      loaded = true;
      const s = document.createElement('script');
      s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(@json(config('gratulomat.ga_id')));
      document.head.appendChild(s);
    };
    const banner = () => {
      if (document.getElementById('gm-consent')) return;
      const el = document.createElement('div');
      el.id = 'gm-consent'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-label', 'Souhlas s měřením návštěvnosti');
      el.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;max-width:560px;margin:0 auto;z-index:9999;background:#1C1633;color:#FFF6E9;border:1px solid rgba(255,246,233,.25);border-radius:14px;padding:16px 18px;font:14px/1.45 system-ui,sans-serif;box-shadow:0 8px 30px rgba(0,0,0,.35)';
      el.innerHTML = '<p style="margin:0 0 12px">Měříme návštěvnost přes Google Analytics, ať víme, co na webu funguje. Jen když to povolíte. <a href="{{ route('privacy') }}" style="color:inherit">Více v zásadách</a>.</p>'
        + '<div style="display:flex;gap:10px;flex-wrap:wrap"><button type="button" data-v="granted" style="cursor:pointer;border:0;border-radius:10px;padding:10px 16px;font:inherit;font-weight:600;background:#FFF6E9;color:#1C1633">Povolit</button>'
        + '<button type="button" data-v="denied" style="cursor:pointer;border:1px solid rgba(255,246,233,.4);border-radius:10px;padding:10px 16px;font:inherit;background:transparent;color:inherit">Nepovolit</button></div>';
      el.addEventListener('click', e => {
        const v = e.target.closest('button')?.dataset.v;
        if (!v) return;
        set(v); apply(v); el.remove();
      });
      document.body.appendChild(el);
    };
    window.gmCookieSettings = () => { try { localStorage.removeItem(KEY); } catch {} banner(); };
    const init = () => { const v = get(); v ? apply(v) : banner(); };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
  })();
</script>
@else
<script>window.gmTrack = () => {}; window.gmCookieSettings = () => {};</script>
@endif
