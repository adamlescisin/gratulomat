<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Models\Setting;
use App\Models\TesterCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Režim webu (specifikace „Veřejný web, domény a režimy“):
 * přihlášený admin → platné cookie testera → pozvánka k TEST / zaplacené události → jinak coming soon.
 * Režim z back-office (tabulka settings), jinak z .env. Provozovatel (back-office) vidí web vždy.
 */
class SiteMode
{
    /** Cesty, které fungují v každém režimu. */
    private const ALWAYS = ['pristup', 'pristup/*', 'prihlaseni', 'prihlaseni/*', 'odhlaseni', 'moje', 'backoffice', 'backoffice/*',
        'cekaci-listina', 'ochrana-soukromi', 'obchodni-podminky', 'pravidla-pro-hosty', 'jazyk/*', 'up', 'api/stripe/webhook', 'api/worker/*', 'znacka/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $mode = Setting::siteMode();

        $event = $request->route('event');
        $eventOk = $event instanceof Event && $event->reachableBeforeLaunch();

        $tester = $this->isTester($request);
        $request->attributes->set('is_tester', $tester);
        $full = $mode === 'live' || $tester || $request->user()?->isOperator();
        $request->attributes->set('site_full_access', $full);

        if ($full || $request->is(...self::ALWAYS) || $eventOk) {
            return $next($request);
        }

        if ($mode === 'maintenance' && ! $eventOk) {
            return response()->view('site.maintenance', status: 503)->header('Retry-After', '600');
        }

        return response()->view('site.coming-soon');
    }

    private function isTester(Request $request): bool
    {
        $cookie = $request->cookie(config('gratulomat.tester_cookie'));

        return is_string($cookie) && TesterCode::isValidCode($cookie);
    }
}
