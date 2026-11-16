<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Do vyhledávačů smí jen veřejný marketing. Stránky událostí (/{slug}, pozvánky, Kino, média, API),
 * organizátor, back-office, průvodce i neznámé adresy dostávají hlavičku noindex.
 * Hlavička platí i pro odpovědi bez HTML (média, JSON), kde <meta name="robots"> chybí.
 */
class RobotsHeaders
{
    /** Názvy rout, které se smějí indexovat. Cokoli jiného je noindex. */
    public const INDEXABLE = ['home', 'privacy', 'terms'];

    public const NOINDEX = 'noindex, nofollow, noarchive, noimageindex';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! self::isIndexable($request) && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', self::NOINDEX);
        }

        return $response;
    }

    public static function isIndexable(Request $request): bool
    {
        return in_array($request->route()?->getName(), self::INDEXABLE, true) && $request->isMethod('GET');
    }
}
