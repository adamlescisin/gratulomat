<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Mail\LoginLink;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Household;
use App\Models\Media;
use App\Models\Memory;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TesterCode;
use App\Models\WaitlistEntry;
use App\Support\Legal;
use App\Support\MailBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Back-office provozovatele (minimální verze před Filamentem): přehled, události, čekací listina, režim webu. */
class BackofficeController extends Controller
{
    /** Kontrolní seznam před ostrým provozem (zatím vše potvrzuje provozovatel ručně). */
    public const CHECKS = [
        'stripe' => 'Ostré API klíče Stripe uložené a ověřené',
        'webhook' => 'Stripe webhook odpovídá (ostrý režim)',
        'invoice' => 'MyÚčto propojené – worker vystavil testovací doklad',
        'mail' => 'Doména e-mailů ověřená v Resend (SPF, DKIM, DMARC)',
        'legal' => 'Obchodní podmínky, ochrana osobních údajů a reklamační řád zveřejněné',
        'e2e' => 'Celý průchod otestovaný: průvodce → platba → pozvánky → videoprezentace',
        'clean' => 'Testovací události a objednávky vyčištěné',
        'backup' => 'Záloha databáze před spuštěním',
    ];

    public function dashboard()
    {
        return view('backoffice.dashboard', [
            'kpis' => [
                ['Události', Event::count()],
                ['z toho TEST', Event::where('is_test', true)->count()],
                ['Pozvánky (domácnosti)', Household::count()],
                ['Vzpomínky', Memory::count()],
                ['Fotky', Media::where('kind', 'photo')->count()],
                ['Čekací listina', WaitlistEntry::count()],
            ],
            'events' => Event::withCount(['households', 'memories'])->latest()->limit(8)->get(),
            'mode' => Setting::siteMode(),
        ]);
    }

    public function events()
    {
        return view('backoffice.events', [
            'events' => Event::withCount(['households', 'memories'])->with('organizers')->orderBy('event_date')->get(),
            'mode' => Setting::siteMode(),
        ]);
    }

    public function discounts()
    {
        return view('backoffice.discounts', ['codes' => DiscountCode::latest()->get(), 'mode' => Setting::siteMode()]);
    }

    public function discountStore(Request $request)
    {
        $request->merge(['code' => DiscountCode::normalize($request->input('code'))]);
        $v = $request->validate([
            'code' => ['required', 'regex:/^[A-Z0-9_-]{3,40}$/', 'unique:discount_codes,code'],
            'percent' => 'required|integer|min:1|max:100',
            'max_uses' => 'nullable|integer|min:1',
            'valid_until' => 'nullable|date|after_or_equal:today',
            'note' => 'nullable|string|max:200',
        ], [
            'code.regex' => 'Kód: 3–40 znaků, písmena bez diakritiky, číslice, - a _.',
            'code.unique' => 'Tento kód už existuje.',
            'percent.*' => 'Sleva musí být 1–100 %.',
            'valid_until.after_or_equal' => 'Platnost nemůže končit v minulosti.',
        ]);
        DiscountCode::create($v);
        Log::info('Slevový kód vytvořen', ['code' => $v['code'], 'percent' => $v['percent'], 'by' => $request->user()->email]);

        return redirect()->route('backoffice.discounts')->with('ok', "Kód {$v['code']} (−{$v['percent']} %) je připravený.");
    }

    public function discountToggle(DiscountCode $discount)
    {
        $discount->update(['active' => ! $discount->active]);

        return back()->with('ok', "Kód {$discount->code} je ".($discount->active ? 'zapnutý.' : 'vypnutý.'));
    }

    public function discountDelete(DiscountCode $discount)
    {
        if ($discount->used_count > 0) {
            return back()->with('ok', "Kód {$discount->code} už byl použit – můžete ho jen vypnout.");
        }
        $discount->delete();

        return back()->with('ok', "Kód {$discount->code} je smazaný.");
    }

    public function mailBrand()
    {
        return view('backoffice.mail', ['mode' => Setting::siteMode()]);
    }

    public function mailBrandSave(Request $request)
    {
        $v = $request->validate([
            'from_name' => 'nullable|string|max:80',
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer' => 'nullable|string|max:600',
            'logo_width' => 'nullable|integer|min:60|max:320',
            'logo' => 'nullable|file|mimes:png,jpg,jpeg,gif,webp|max:1024',
            'remove_logo' => 'nullable|boolean',
        ], [
            'logo.mimes' => 'Logo nahrajte jako PNG, JPG, GIF nebo WebP (SVG e-mailové klienty neukazují).',
            'logo.max' => 'Logo může mít nejvýš 1 MB.',
            'color.regex' => 'Barva ve tvaru #FF5A3C.',
        ]);
        foreach (['from_name', 'color', 'footer', 'logo_width'] as $k) {
            Setting::put("mail_$k", isset($v[$k]) && $v[$k] !== '' ? (string) $v[$k] : null);
        }
        if ($request->boolean('remove_logo')) {
            MailBrand::removeLogo();
        }
        if ($request->hasFile('logo')) {
            MailBrand::storeLogo($request->file('logo'));
        }

        return redirect()->route('backoffice.mail')->with('ok', 'Vzhled e-mailů je uložený.');
    }

    /** Náhled na vzorovém e-mailu (přihlašovací odkaz). */
    public function mailPreview(Request $request)
    {
        return response((new LoginLink($request->user(), route('login')))->render());
    }

    public function mailTest(Request $request)
    {
        Mail::to($request->user())->send(new LoginLink($request->user(), route('login')));

        return back()->with('ok', 'Zkušební e-mail odešel na '.$request->user()->email.'.');
    }

    public function event(Event $event)
    {
        return view('backoffice.event', [
            'event' => $event->load('organizers'), 'mode' => Setting::siteMode(),
            'orders' => Order::where('event_id', $event->id)->latest()->get(),
        ]);
    }

    public function eventSave(Request $request, Event $event)
    {
        $v = $request->validate([
            'title' => 'required|string|max:140',
            'recipient_name' => 'required|string|max:120',
            'event_date' => 'required|date',
            'collect_until' => 'nullable|date',
            'archive_until' => 'nullable|date',
            'guest_limit' => 'nullable|integer|min:1|max:5000',
            'owner_email' => 'required|email:rfc|max:191',
        ], ['owner_email.*' => 'Zkontrolujte e-mail organizátora.', 'title.required' => 'Název nesmí být prázdný.']);

        $tz = 'Europe/Prague';
        $nameChanged = $event->recipient_name !== $v['recipient_name'];
        $event->fill([
            'title' => $v['title'], 'recipient_name' => $v['recipient_name'],
            'event_date' => Carbon::parse($v['event_date'], $tz)->utc(),
            'collect_until' => $v['collect_until'] ? Carbon::parse($v['collect_until'], $tz)->utc() : null,
            'archive_until' => $v['archive_until'] ? Carbon::parse($v['archive_until'], $tz)->toDateString() : null,
        ]);
        if ($nameChanged) {
            $event->refreshRecipientForms();
        }
        if (! empty($v['guest_limit'])) {
            $event->limits = [...($event->limits ?? []), 'guests' => (int) $v['guest_limit']];
        }
        $changes = array_keys($event->getDirty());
        $event->save();

        $owner = $event->organizers()->wherePivot('role', 'owner')->first();
        if (mb_strtolower($v['owner_email']) !== $owner?->email) {
            $event->setOwner($v['owner_email']);
            $changes[] = 'organizátor';
            Order::where('event_id', $event->id)->update(['email' => mb_strtolower($v['owner_email'])]);
        }
        Log::info('Událost upravena provozovatelem', ['event' => $event->slug, 'changes' => $changes, 'by' => $request->user()->email]);

        return redirect()->route('backoffice.event', $event)->with('ok', $changes ? 'Událost je uložená.' : 'Beze změn.');
    }

    public function legal(Request $request)
    {
        return view('backoffice.legal', ['mode' => Setting::siteMode(), 'edit' => in_array($request->query('varianta'), ['test', 'live'], true) ? $request->query('varianta') : Legal::variant()]);
    }

    public function legalSave(Request $request)
    {
        $by = $request->user()->email;
        if ($request->has('identity')) {
            $v = $request->validate([
                'identity.name' => 'nullable|string|max:160', 'identity.ico' => ['nullable', 'regex:/^\d{8}$/'], 'identity.dic' => ['nullable', 'regex:/^[A-Z]{2}\d{8,10}$/i'],
                'identity.address' => 'nullable|string|max:255', 'identity.register' => 'nullable|string|max:255', 'identity.email' => 'nullable|email:rfc|max:191',
                'identity.phone' => 'nullable|string|max:40', 'identity.vat_payer' => 'nullable|boolean',
            ], ['identity.ico.regex' => 'IČO má 8 číslic.', 'identity.dic.regex' => 'DIČ ve tvaru CZ12345678.']);
            $identity = array_map(fn ($x) => is_string($x) ? trim($x) : $x, $v['identity'] ?? []);
            $identity['dic'] = isset($identity['dic']) ? strtoupper($identity['dic']) : null;
            $identity['vat_payer'] = $request->boolean('identity.vat_payer') ? '1' : '0';
            Setting::put('legal_identity', $identity);
            Log::info('Identifikace provozovatele upravena', ['by' => $by]);

            return back()->with('ok', 'Identifikace provozovatele je uložená.');
        }
        if ($request->has('legal_variant')) {
            $request->validate(['legal_variant' => 'required|in:auto,test,live']);
            Setting::put('legal_variant', $request->input('legal_variant'));
            Log::info('Přepínač právních textů změněn', ['to' => $request->input('legal_variant'), 'by' => $by]);

            return back()->with('ok', 'Na webu teď platí: '.Legal::VARIANTS[Legal::variant()].'.');
        }
        $v = $request->validate([
            'variant' => 'required|in:test,live',
            ...collect(array_keys(Legal::DOCS))->flatMap(fn ($d) => [$d => 'nullable|string|max:100000', "{$d}_from" => 'nullable|date'])->all(),
        ]);
        $changed = [];
        foreach (array_keys(Legal::DOCS) as $doc) {
            if ($request->has($doc) && Legal::save($doc, $v['variant'], (string) ($v[$doc] ?? ''), $v["{$doc}_from"] ?? null, $by)) {
                $changed[] = Legal::DOCS[$doc];
            }
        }

        return redirect()->route('backoffice.legal', ['varianta' => $v['variant']])
            ->with('ok', $changed ? 'Uloženo ('.Legal::VARIANTS[$v['variant']].'): '.implode(', ', $changed).'. Předchozí verze zůstává v archivu.' : 'Beze změn.');
    }

    public function waitlist()
    {
        return view('backoffice.waitlist', ['entries' => WaitlistEntry::latest()->get(), 'mode' => Setting::siteMode()]);
    }

    public function waitlistCsv()
    {
        $rows = WaitlistEntry::orderBy('created_at')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM kvůli Excelu
            fputcsv($out, ['email', 'prihlasen', 'souhlas', 'pozvan'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [$r->email, $r->created_at, $r->consent_at, $r->invited_at], ';');
            }
            fclose($out);
        }, 'cekaci-listina-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function waitlistDelete(WaitlistEntry $entry)
    {
        Log::info('Back-office: smazán záznam čekací listiny', ['email' => $entry->email, 'by' => auth()->user()->email]);
        $entry->delete();

        return back()->with('ok', 'Smazáno.');
    }

    public function mode()
    {
        return view('backoffice.mode', [
            'mode' => Setting::siteMode(),
            'codes' => TesterCode::latest()->get(),
            'envCodes' => (array) config('gratulomat.tester_codes'),
            'checks' => self::CHECKS,
            'checked' => (array) Setting::get('launch_checklist', []),
        ]);
    }

    public function setMode(Request $request)
    {
        $mode = $request->validate(['mode' => 'required|in:coming_soon,maintenance,live'])['mode'];
        if ($mode === 'live') {
            $done = count(array_intersect(array_keys(self::CHECKS), (array) Setting::get('launch_checklist', [])));
            abort_if($done < count(self::CHECKS) || ! $request->boolean('confirm'), 422, 'Nejdřív dokončete kontrolu a potvrďte spuštění.');
        }
        Setting::put('site_mode', $mode);
        Log::warning('Back-office: změna režimu webu', ['mode' => $mode, 'by' => auth()->user()->email]);

        return back()->with('ok', 'Režim webu změněn.');
    }

    public function setChecklist(Request $request)
    {
        Setting::put('launch_checklist', array_values(array_intersect(array_keys(self::CHECKS), (array) $request->input('checks', []))));

        return back()->with('ok', 'Kontrolní seznam uložen.');
    }

    public function addCode(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:120',
            'valid_until' => 'nullable|date|after_or_equal:today',
            'max_uses' => 'nullable|integer|min:1|max:10000',
        ]);
        $code = TesterCode::create([...$data, 'code' => TesterCode::generate()]);

        return back()->with('ok', "Nový kód: {$code->code}");
    }

    public function deleteCode(TesterCode $code)
    {
        $code->delete();

        return back()->with('ok', 'Kód zrušen.');
    }
}
