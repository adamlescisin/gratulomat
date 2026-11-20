<?php

use App\Domain\Guest\GuestSession;
use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Backoffice\MusicController;
use App\Http\Controllers\Backoffice\OrdersController;
use App\Http\Controllers\Event\EventPageController;
use App\Http\Controllers\Event\GuestApiController;
use App\Http\Controllers\Organizer\AuthController;
use App\Http\Controllers\Organizer\EventAdminController;
use App\Http\Controllers\Site\WaitlistController;
use App\Http\Controllers\Site\WizardController;
use App\Http\Middleware\EnsureOperator;
use App\Http\Middleware\EnsureOrganizer;
use App\Http\Middleware\EventNotArchived;
use App\Models\Household;
use App\Models\MusicTrack;
use App\Models\TesterCode;
use App\Support\MailBrand;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
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
// Sitemap pro vyhledávače: jen veřejný marketing (stejný seznam jako RobotsHeaders::INDEXABLE).
Route::get('/sitemap.xml', function () {
    $base = rtrim((string) config('gratulomat.marketing_url'), '/');
    $urls = collect(['/' => ['weekly', '1.0'], '/obchodni-podminky' => ['yearly', '0.3'], '/ochrana-soukromi' => ['yearly', '0.3']])
        ->map(fn ($m, $path) => "  <url>\n    <loc>{$base}{$path}</loc>\n    <changefreq>{$m[0]}</changefreq>\n    <priority>{$m[1]}</priority>\n  </url>")
        ->implode("\n");

    return response("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$urls}\n</urlset>\n")
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');
Route::get('/ochrana-soukromi', fn () => view('site.legal', ['doc' => 'privacy']))->name('privacy');
Route::get('/obchodni-podminky', fn () => view('site.legal', ['doc' => 'terms']))->name('terms');
Route::get('/pravidla-pro-hosty', fn () => view('site.legal', ['doc' => 'guest_rules']))->name('guest-rules');
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
    Route::get('/udalosti/{event}', 'event')->name('backoffice.event');
    Route::post('/udalosti/{event}', 'eventSave')->name('backoffice.event.save');
    Route::get('/cekaci-listina', 'waitlist')->name('backoffice.waitlist');
    Route::get('/cekaci-listina.csv', 'waitlistCsv')->name('backoffice.waitlist.csv');
    Route::delete('/cekaci-listina/{entry}', 'waitlistDelete')->name('backoffice.waitlist.delete');
    Route::get('/objednavky', [OrdersController::class, 'index'])->name('backoffice.orders');
    Route::get('/objednavky.csv', [OrdersController::class, 'csv'])->name('backoffice.orders.csv');
    Route::get('/objednavky/{order}', [OrdersController::class, 'show'])->name('backoffice.order');
    Route::post('/objednavky/{order}/vratit', [OrdersController::class, 'refund'])->name('backoffice.order.refund');
    Route::post('/objednavky/{order}/znovu-odeslat', [OrdersController::class, 'resend'])->name('backoffice.order.resend');
    Route::post('/objednavky/{order}/zrusit', [OrdersController::class, 'cancel'])->name('backoffice.order.cancel');
    Route::post('/objednavky/{order}/vip-odkaz', [OrdersController::class, 'vipLink'])->name('backoffice.order.vip');
    Route::post('/objednavky/{order}/vip-rucne', [OrdersController::class, 'vipManual'])->name('backoffice.order.vipManual');
    Route::post('/objednavky/{order}/uloha/{job}', [OrdersController::class, 'retryJob'])->name('backoffice.order.retry');
    Route::get('/objednavky/{order}/doklad/{invoice}', [OrdersController::class, 'invoicePdf'])->name('backoffice.order.pdf');
    Route::post('/objednavky/{order}/vyrizeno/{code}', [OrdersController::class, 'todoDone'])->where('code', '[a-z_]+')->name('backoffice.order.todo');
    Route::post('/objednavky/{order}/email', [OrdersController::class, 'email'])->name('backoffice.order.email');
    Route::post('/objednavky/{order}/poznamka', [OrdersController::class, 'note'])->name('backoffice.order.note');
    Route::get('/hudba', [MusicController::class, 'index'])->name('backoffice.music');
    Route::post('/hudba', [MusicController::class, 'store'])->name('backoffice.music.store');
    Route::post('/hudba/{track}', [MusicController::class, 'update'])->name('backoffice.music.update');
    Route::delete('/hudba/{track}', [MusicController::class, 'destroy'])->name('backoffice.music.delete');
    Route::get('/cenik', 'prices')->name('backoffice.prices');
    Route::post('/cenik', 'pricesSave')->name('backoffice.prices.save');
    Route::get('/slevy', 'discounts')->name('backoffice.discounts');
    Route::post('/slevy', 'discountStore')->name('backoffice.discounts.store');
    Route::post('/slevy/{discount}/prepnout', 'discountToggle')->name('backoffice.discounts.toggle');
    Route::delete('/slevy/{discount}', 'discountDelete')->name('backoffice.discounts.delete');
    Route::get('/pravni', 'legal')->name('backoffice.legal');
    Route::post('/pravni', 'legalSave')->name('backoffice.legal.save');
    Route::get('/emaily', 'mailBrand')->name('backoffice.mail');
    Route::post('/emaily', 'mailBrandSave')->name('backoffice.mail.save');
    Route::get('/emaily/nahled', 'mailPreview')->name('backoffice.mail.preview');
    Route::post('/emaily/test', 'mailTest')->middleware('throttle:5,1')->name('backoffice.mail.test');
    Route::get('/rezim', 'mode')->name('backoffice.mode');
    Route::post('/rezim', 'setMode');
    Route::post('/rezim/kontrola', 'setChecklist')->name('backoffice.checklist');
    Route::post('/rezim/kody', 'addCode')->name('backoffice.codes');
    Route::delete('/rezim/kody/{code}', 'deleteCode')->name('backoffice.codes.delete');
});

// Logo v e-mailech (veřejné – e-maily čte kdokoli).
Route::get('/znacka/{file}', function (string $file) {
    $path = MailBrand::logoPath();
    abort_unless($path && basename($path) === $file, 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
})->where('file', 'logo-[a-f0-9]{10}\.(png|jpg|jpeg|gif|webp)')->name('mail.logo');

// Hudební podkresy z knihovny (veřejné – přehrává je Kino u hostů; soubor podporuje Range kvůli přetáčení)
Route::get('/hudba/{file}', function (string $file) {
    $track = MusicTrack::where('file', $file)->firstOrFail();
    abort_unless(is_file($track->path()), 404);

    return response()->file($track->path(), ['Content-Type' => 'audio/mpeg', 'Cache-Control' => 'public, max-age=31536000, immutable']);
})->where('file', '[a-z0-9]{20,40}\.mp3')->name('music.file');

// Průvodce založením události a platba.
Route::get('/zalozit', [WizardController::class, 'page'])->name('wizard');
Route::post('/zalozit', [WizardController::class, 'submit'])->middleware('throttle:10,1')->name('wizard.submit');
Route::get('/zalozit/hotovo', [WizardController::class, 'done'])->name('wizard.done');
Route::get('/zalozit/stav/{order}', [WizardController::class, 'status'])->name('wizard.status');
Route::get('/zalozit/potvrdit/{order}', [WizardController::class, 'confirm'])->name('wizard.confirm');

/*
 * Stránky událostí – vždy až na konci (zachytávají /{slug}).
 */
Route::prefix('{event}')->where(['event' => '[a-z0-9][a-z0-9-]{1,28}[a-z0-9]'])->middleware(EventNotArchived::class)->group(function () {
    Route::get('/', [EventPageController::class, 'show'])->name('event.show');
    Route::get('/g/{token}', [EventPageController::class, 'invite'])->where('token', '[A-Za-z0-9]{6,32}')->name('event.invite');
    Route::get('/p/{token}', [EventPageController::class, 'anonForm'])->where('token', '[a-z0-9]{8,32}')->name('event.anon');
    Route::post('/p/{token}', [EventPageController::class, 'anonJoin'])->where('token', '[a-z0-9]{8,32}')->middleware('throttle:10,1')->name('event.anon.join');
    Route::post('/prihlasit', [EventPageController::class, 'login'])->middleware('throttle:20,1')->name('event.login');
    Route::get('/odhlasit', [EventPageController::class, 'logout'])->name('event.logout');
    Route::match(['get', 'post'], '/odhlasit-emaily/{household}', [EventPageController::class, 'unsubscribe'])->whereNumber('household')->middleware('signed')->name('event.unsubscribe')
        ->withoutMiddleware([ValidateCsrfToken::class]);
    Route::get('/kino', [EventPageController::class, 'kino'])->name('event.kino');
    Route::get('/predani/{token}', [EventPageController::class, 'handover'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:30,1')->name('event.handover');
    Route::get('/predani/{token}/offline.zip', [EventPageController::class, 'handoverOffline'])->where('token', '[A-Za-z0-9]{32}')->middleware('throttle:5,1')->name('event.handover.offline');
    Route::get('/m/{memoryId}/{file}', [EventPageController::class, 'media'])->name('event.media');

    Route::get('/api/me', [GuestApiController::class, 'me']);
    Route::get('/api/show', [GuestApiController::class, 'show']);
    Route::post('/api/memories/{memoryId?}', [GuestApiController::class, 'save'])->middleware('throttle:60,1');
    Route::delete('/api/memories/{memoryId}', [GuestApiController::class, 'destroy']);

    Route::middleware(EnsureOrganizer::class)->prefix('admin')->group(function () {
        Route::get('/', [EventAdminController::class, 'page'])->name('organizer.admin');
        Route::get('/api/state', [EventAdminController::class, 'state']);
        Route::post('/api/households', [EventAdminController::class, 'addHouseholds']);
        Route::post('/api/import', [EventAdminController::class, 'import']);
        Route::post('/api/anon-link', [EventAdminController::class, 'anonLink']);
        Route::post('/api/send-invites', [EventAdminController::class, 'sendInvites'])->middleware('throttle:10,1');
        Route::post('/api/upgrade-quote', [EventAdminController::class, 'upgradeQuote']);
        Route::post('/api/upgrade', [EventAdminController::class, 'upgrade'])->middleware('throttle:10,1');
        Route::post('/api/households/{household}', [EventAdminController::class, 'updateHousehold'])->whereNumber('household');
        Route::post('/api/memories/{memory}', [EventAdminController::class, 'updateMemory']);
        Route::post('/api/show', [EventAdminController::class, 'updateShow']);
        Route::post('/api/settings', [EventAdminController::class, 'updateSettings']);
        Route::post('/api/handover', [EventAdminController::class, 'handover'])->middleware('throttle:10,1');
        Route::delete('/api/handover', [EventAdminController::class, 'handoverRevoke']);
        Route::get('/offline.zip', [EventAdminController::class, 'offline'])->name('organizer.offline');
    });
});
