<?php

use App\Http\Middleware\SiteMode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

// Přepínač jazyka rozhraní (stránka hosta). Obsah události se nepřekládá.
Route::post('/jazyk/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, config('gratulomat.locales.guest'), true), 404);

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
