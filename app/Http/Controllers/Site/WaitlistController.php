<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\WaitlistSignup;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Čekací listina z coming soon stránky; upozornění chodí na ahoj@gratulomat.cz. */
class WaitlistController extends Controller
{
    public function store(Request $request)
    {
        if (filled($request->input('web'))) {
            return back()->with('waitlist_done', true); // robot – tváříme se, že je hotovo
        }
        $data = $request->validate([
            'email' => 'required|email:rfc|max:191',
            'consent' => 'accepted',
        ], [
            'email.required' => 'Zkontrolujte prosím e-mail.',
            'email.email' => 'Zkontrolujte prosím e-mail.',
            'consent.accepted' => 'Potřebujeme váš souhlas.',
        ]);

        $email = mb_strtolower(trim($data['email']));
        $entry = WaitlistEntry::firstOrCreate(['email' => $email], [
            'consent_at' => now(), 'source' => 'coming_soon', 'ip' => $request->ip(),
        ]);

        if ($entry->wasRecentlyCreated) {
            try {
                Mail::to(config('gratulomat.notify_email'))->send(new WaitlistSignup($entry, WaitlistEntry::count()));
            } catch (Throwable $e) {
                Log::warning('Upozornění na čekací listinu se nepodařilo odeslat: '.$e->getMessage());
            }
        }

        return redirect()->to('/#cekaci-listina')->with('waitlist_done', true);
    }
}
