<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Household;
use App\Models\Media;
use App\Models\Memory;
use App\Models\Setting;
use App\Models\TesterCode;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
