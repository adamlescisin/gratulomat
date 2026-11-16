<?php

namespace App\Http\Middleware;

use App\Domain\Guest\GuestSession;
use App\Models\Event;
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

        $event = $request->route('event');
        if ($event instanceof Event) {
            $preferred = GuestSession::household($event)?->preferred_locale;
            if ($preferred && in_array($preferred, $supported, true)) {
                return $preferred;
            }
        }

        $browser = $request->getPreferredLanguage($supported);
        if ($browser && $request->headers->has('Accept-Language')) {
            return $browser;
        }

        if ($event instanceof Event && in_array($event->locale, $supported, true)) {
            return $event->locale;
        }

        return config('gratulomat.locales.default');
    }
}
