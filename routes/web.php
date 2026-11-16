<?php

use App\Domain\Guest\GuestSession;
use App\Http\Controllers\Event\EventPageController;
use App\Http\Controllers\Event\GuestApiController;
use App\Http\Middleware\SiteMode;
use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Přepínač jazyka rozhraní (stránka hosta). Obsah události se nepřekládá.
Route::post('/jazyk/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, config('gratulomat.locales.guest'), true), 404);
    // Volba se uloží i k domácnostem přihlášeným v této relaci (platí pak i na jiných zařízeních).
    if ($ids = GuestSession::householdIds()) {
        Household::whereIn('id', $ids)->update(['preferred_locale' => $locale]);
    }

    return back(fallback: '/')->withCookie(cookie()->forever(config('gratulomat.locales.cookie'), $locale));
})->name('locale.switch');

// Přístup testerů v režimu Připravujeme.
Route::get('/pristup', fn () => view('site.access'))->name('access');
Route::post('/pristup', function (Request $request) {
    $code = strtoupper(trim((string) $request->input('code')));
    if (! in_array($code, SiteMode::testerCodes(), true)) {
        return back()->withErrors(['code' => __('Kód neplatí.')]);
    }

    return redirect('/')->withCookie(cookie(
        config('gratulomat.tester_cookie'), $code, 60 * 24 * config('gratulomat.tester_cookie_days')
    ));
})->middleware('throttle:10,1');

/*
 * Stránky událostí – vždy až na konci (zachytávají /{slug}).
 */
Route::prefix('{event}')->where(['event' => '[a-z0-9][a-z0-9-]{1,28}[a-z0-9]'])->group(function () {
    Route::get('/', [EventPageController::class, 'show'])->name('event.show');
    Route::get('/g/{token}', [EventPageController::class, 'invite'])->where('token', '[A-Za-z0-9]{6,32}')->name('event.invite');
    Route::post('/prihlasit', [EventPageController::class, 'login'])->middleware('throttle:20,1')->name('event.login');
    Route::get('/odhlasit', [EventPageController::class, 'logout'])->name('event.logout');
    Route::get('/kino', [EventPageController::class, 'kino'])->name('event.kino');
    Route::get('/m/{memoryId}/{file}', [EventPageController::class, 'media'])->name('event.media');

    Route::get('/api/me', [GuestApiController::class, 'me']);
    Route::get('/api/show', [GuestApiController::class, 'show']);
    Route::post('/api/memories/{memoryId?}', [GuestApiController::class, 'save'])->middleware('throttle:60,1');
    Route::delete('/api/memories/{memoryId}', [GuestApiController::class, 'destroy']);
});
