<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jazyk rozhraní: volba hosta (cookie) → prohlížeč (Accept-Language) → primární jazyk události → výchozí.
 * Obsah události (vlastní texty organizátora, příspěvky) se tímto nepřekládá.
 */
class SetGuestLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    public function resolve(Request $request): string
    {
        $supported = config('gratulomat.locales.guest');

        $chosen = $request->cookie(config('gratulomat.locales.cookie'));
        if (is_string($chosen) && in_array($chosen, $supported, true)) {
            return $chosen;
        }

        $browser = $request->getPreferredLanguage($supported);
        if ($browser && $request->headers->has('Accept-Language')) {
            return $browser;
        }

        // Až bude model Event: primární jazyk události z route parametru {slug}.
        $eventLocale = $request->attributes->get('event_locale');
        if (is_string($eventLocale) && in_array($eventLocale, $supported, true)) {
            return $eventLocale;
        }

        return config('gratulomat.locales.default');
    }
}
