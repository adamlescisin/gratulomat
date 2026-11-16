<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Režim webu (specifikace „Veřejný web, domény a režimy“):
 * přihlášený admin → platné cookie testera → pozvánka k TEST / zaplacené události → jinak coming soon.
 * Zatím: režim z konfigurace, testeři přes cookie. Admin a pozvánky přibudou s back-office a modelem Event.
 */
class SiteMode
{
    /** Cesty, které fungují v každém režimu. */
    private const ALWAYS = ['pristup', 'pristup/*', 'jazyk/*', 'up', 'api/stripe/webhook', 'api/worker/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('gratulomat.site_mode');

        if ($mode === 'live' || $request->is(...self::ALWAYS) || $this->isTester($request)) {
            return $next($request);
        }

        if ($mode === 'maintenance') {
            return response()->view('site.maintenance', status: 503)->header('Retry-After', '600');
        }

        return response()->view('site.coming-soon');
    }

    private function isTester(Request $request): bool
    {
        $cookie = $request->cookie(config('gratulomat.tester_cookie'));

        return is_string($cookie) && in_array($cookie, self::testerCodes(), true);
    }

    /** @return list<string> */
    public static function testerCodes(): array
    {
        return array_values(array_filter(array_map(fn ($c) => strtoupper(trim($c)), (array) config('gratulomat.tester_codes'))));
    }
}
