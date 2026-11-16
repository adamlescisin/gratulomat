<?php

use App\Domain\Guest\GuestSession;
use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Event\EventPageController;
use App\Http\Controllers\Event\GuestApiController;
use App\Http\Controllers\Organizer\AuthController;
use App\Http\Controllers\Organizer\EventAdminController;
use App\Http\Controllers\Site\WaitlistController;
use App\Http\Controllers\Site\WizardController;
use App\Http\Middleware\EnsureOperator;
use App\Http\Middleware\EnsureOrganizer;
use App\Models\Household;
use App\Models\TesterCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Úvod: veřejnost v režimu Připravujeme vidí coming soon; testeři, provozovatelé a ostrý provoz landing page.
Route::get('/', fn () => request()->attributes->get('site_full_access') ? view('site.landing') : view('site.coming-soon'))->name('home');

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
Route::get('/pristup', fn () => view('site.coming-soon', ['openCode' => true]))->name('access');
Route::post('/cekaci-listina', [WaitlistController::class, 'store'])->middleware('throttle:6,1')->name('waitlist');
Route::get('/ochrana-soukromi', fn () => view('site.privacy'))->name('privacy');
Route::get('/obchodni-podminky', fn () => view('site.terms'))->name('terms');
Route::post('/pristup', function (Request $request) {
    $code = strtoupper(trim((string) $request->input('code')));
    if (! TesterCode::isValidCode($code)) {
        return back()->withErrors(['code' => __('Kód neplatí.')]);
    }
    TesterCode::where('code', $code)->increment('uses');

    return redirect('/')->withCookie(cookie(
        config('gratulomat.tester_cookie'), $code, 60 * 24 * config('gratulomat.tester_cookie_days')
    ));
})->middleware('throttle:10,1');

// Organizátor: přihlášení odkazem z e-mailu.
Route::get('/prihlaseni', [AuthController::class, 'form'])->name('login');
Route::post('/prihlaseni', [AuthController::class, 'send'])->middleware('throttle:5,1');
Route::get('/prihlaseni/odkaz/{user}', [AuthController::class, 'link'])->name('login.link');
Route::post('/odhlaseni', [AuthController::class, 'logout'])->name('logout');
Route::get('/moje', [AuthController::class, 'events'])->middleware('auth')->name('organizer.events');

// Back-office provozovatele (OPERATOR_EMAILS).
Route::middleware(EnsureOperator::class)->prefix('backoffice')->controller(BackofficeController::class)->group(function () {
    Route::get('/', 'dashboard')->name('backoffice');
    Route::get('/udalosti', 'events')->name('backoffice.events');
    Route::get('/cekaci-listina', 'waitlist')->name('backoffice.waitlist');
    Route::get('/cekaci-listina.csv', 'waitlistCsv')->name('backoffice.waitlist.csv');
    Route::delete('/cekaci-listina/{entry}', 'waitlistDelete')->name('backoffice.waitlist.delete');
    Route::get('/slevy', 'discounts')->name('backoffice.discounts');
    Route::post('/slevy', 'discountStore')->name('backoffice.discounts.store');
    Route::post('/slevy/{discount}/prepnout', 'discountToggle')->name('backoffice.discounts.toggle');
    Route::delete('/slevy/{discount}', 'discountDelete')->name('backoffice.discounts.delete');
    Route::get('/rezim', 'mode')->name('backoffice.mode');
    Route::post('/rezim', 'setMode');
    Route::post('/rezim/kontrola', 'setChecklist')->name('backoffice.checklist');
    Route::post('/rezim/kody', 'addCode')->name('backoffice.codes');
    Route::delete('/rezim/kody/{code}', 'deleteCode')->name('backoffice.codes.delete');
});

// Průvodce založením události a platba.
Route::get('/zalozit', [WizardController::class, 'page'])->name('wizard');
Route::post('/zalozit', [WizardController::class, 'submit'])->middleware('throttle:10,1')->name('wizard.submit');
Route::get('/zalozit/hotovo', [WizardController::class, 'done'])->name('wizard.done');
Route::get('/zalozit/stav/{order}', [WizardController::class, 'status'])->name('wizard.status');
Route::get('/zalozit/potvrdit/{order}', [WizardController::class, 'confirm'])->name('wizard.confirm');

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

    Route::middleware(EnsureOrganizer::class)->prefix('admin')->group(function () {
        Route::get('/', [EventAdminController::class, 'page'])->name('organizer.admin');
        Route::get('/api/state', [EventAdminController::class, 'state']);
        Route::post('/api/households', [EventAdminController::class, 'addHouseholds']);
        Route::post('/api/households/{household}', [EventAdminController::class, 'updateHousehold'])->whereNumber('household');
        Route::post('/api/memories/{memory}', [EventAdminController::class, 'updateMemory']);
        Route::post('/api/show', [EventAdminController::class, 'updateShow']);
        Route::post('/api/settings', [EventAdminController::class, 'updateSettings']);
        Route::get('/offline.zip', [EventAdminController::class, 'offline'])->name('organizer.offline');
    });
});
