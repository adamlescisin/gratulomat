<?php

use App\Http\Middleware\RobotsHeaders;
use App\Http\Middleware\SetGuestLocale;
use App\Http\Middleware\SiteMode;
use App\Models\Setting;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->encryptCookies(except: []);
        // Globálně (i pro 404 a odpovědi bez webového middlewaru): noindex všude mimo veřejný marketing.
        $middleware->append(RobotsHeaders::class);
        $middleware->web(append: [
            SetGuestLocale::class,
            SiteMode::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Neexistující stránka v režimu Připravujeme / Údržba = coming soon / údržba (i neznámé adresy událostí).
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*', 'backoffice', 'backoffice/*', '*/admin', '*/admin/*', '*/api/*', '*/m/*')) {
                return null;
            }
            $mode = Setting::siteMode();
            if ($mode === 'maintenance') {
                return response()->view('site.maintenance', status: 503)->header('Retry-After', '600');
            }
            if ($mode === 'coming_soon') {
                // U adres mimo routy neběží webový middleware – chybové hlášky formuláře doplníme prázdné.
                return response()->view('site.coming-soon', ['errors' => new ViewErrorBag])
                    ->header('X-Robots-Tag', RobotsHeaders::NOINDEX);
            }

            return null;
        });
    })->create();
